/**
 * Registration gate — progressive enhancement.
 *
 * Forms already work as POST/redirect. This only swaps panels without a round
 * trip when the visitor clicks Sign in / Exit / Back links.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-noviq-registration-gate]' );
	if ( ! root ) {
		return;
	}

	function show( name ) {
		var panels = root.querySelectorAll( '[data-noviq-gate-panel]' );
		var target = null;

		Array.prototype.forEach.call( panels, function ( panel ) {
			var match = panel.getAttribute( 'data-noviq-gate-panel' ) === name;
			panel.hidden = ! match;
			if ( match ) {
				target = panel;
			}
		} );

		if ( ! target ) {
			return;
		}

		var focus = target.querySelector( 'input, button, a' );
		if ( focus ) {
			focus.focus();
		}
	}

	root.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '[data-noviq-gate-show]' );
		if ( ! link || ! root.contains( link ) ) {
			return;
		}

		event.preventDefault();
		show( link.getAttribute( 'data-noviq-gate-show' ) );
	} );

	function stops() {
		return Array.prototype.filter.call(
			root.querySelectorAll( 'a[href], button, input, select, textarea' ),
			function ( el ) {
				return null === el.closest( '[hidden]' ) && ! el.disabled;
			}
		);
	}

	root.addEventListener( 'keydown', function ( event ) {
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

	var first = stops()[ 0 ];
	if ( first ) {
		first.focus();
	}
}() );
