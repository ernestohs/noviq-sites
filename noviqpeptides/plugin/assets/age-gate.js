/**
 * Age gate — progressive enhancement.
 *
 * The gate already works as a form post, so this only removes the round trip
 * and keeps focus inside the dialog while it is open. It also re-checks the
 * cookie on load, so a page served from a cache still dismisses itself for a
 * visitor who already answered.
 */
( function () {
	'use strict';

	var gate = document.querySelector( '[data-noviq-age-gate]' );
	if ( ! gate ) {
		return;
	}

	var config = window.noviqAgeGate || {};
	var ask = gate.querySelector( '[data-noviq-age-ask]' );
	var denied = gate.querySelector( '[data-noviq-age-denied]' );
	var open = true;

	function hasCookie() {
		var pattern = new RegExp( '(?:^|;\\s*)' + config.cookie + '=([^;]*)' );
		var match = document.cookie.match( pattern );

		return !! match && decodeURIComponent( match[ 1 ] ) === config.value;
	}

	function close() {
		open = false;
		gate.parentNode.removeChild( gate );
		document.body.classList.remove( 'noviq-age-gate-open' );
	}

	function accept() {
		document.cookie =
			config.cookie + '=' + encodeURIComponent( config.value ) +
			'; path=' + ( config.path || '/' ) +
			'; max-age=' + config.lifetime + '; samesite=lax' +
			( config.secure ? '; secure' : '' );

		close();
	}

	/** Swap between the question and the refusal panel. */
	function show( panel ) {
		if ( ! ask || ! denied ) {
			return;
		}

		ask.hidden = panel !== ask;
		denied.hidden = panel !== denied;

		var target = panel.querySelector( 'button, a' );
		if ( target ) {
			target.focus();
		}
	}

	if ( hasCookie() ) {
		close();

		return;
	}

	gate.addEventListener( 'click', function ( event ) {
		var yes = event.target.closest( '[data-noviq-age-yes]' );
		var no = event.target.closest( '[data-noviq-age-no]' );
		var back = event.target.closest( '[data-noviq-age-back]' );

		if ( yes ) {
			event.preventDefault();
			accept();
		} else if ( no ) {
			event.preventDefault();
			show( denied );
		} else if ( back ) {
			event.preventDefault();
			show( ask );
		}
	} );

	// Keep Tab inside the dialog. The page behind it is still in the document
	// and still focusable, so without this the visitor can tab straight past
	// the question.
	function stops() {
		return Array.prototype.filter.call(
			gate.querySelectorAll( 'button, a[href]' ),
			function ( el ) {
				return null === el.closest( '[hidden]' );
			}
		);
	}

	gate.addEventListener( 'keydown', function ( event ) {
		if ( 'Tab' !== event.key ) {
			return;
		}

		var items = stops();
		if ( ! items.length ) {
			return;
		}

		var edge = event.shiftKey ? items[ 0 ] : items[ items.length - 1 ];
		if ( document.activeElement === edge ) {
			event.preventDefault();
			( event.shiftKey ? items[ items.length - 1 ] : items[ 0 ] ).focus();
		}
	} );

	document.addEventListener( 'focusin', function ( event ) {
		if ( ! open || gate.contains( event.target ) ) {
			return;
		}

		var items = stops();
		if ( items.length ) {
			items[ 0 ].focus();
		}
	} );

	var first = stops()[ 0 ];
	if ( first ) {
		first.focus();
	}
}() );
