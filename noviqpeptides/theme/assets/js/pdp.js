/**
 * Segmented variant selector.
 *
 * Replaces WooCommerce's <select> with a row of chips, matching the reference
 * storefront. The select is kept in the DOM and driven programmatically rather
 * than replaced, so every piece of Woo's variation machinery — price
 * resolution, stock, SKU, the reset link, validation — keeps working untouched.
 *
 * No dependency on jQuery beyond the change event Woo listens for.
 */
( function () {
	'use strict';

	function buildChips( select ) {
		if ( select.dataset.nqChips === 'done' ) {
			return;
		}
		select.dataset.nqChips = 'done';

		var row = document.createElement( 'div' );
		row.className = 'nq-opt-row';
		row.setAttribute( 'role', 'radiogroup' );

		var label = select.closest( 'tr' ) ? select.closest( 'tr' ).querySelector( 'label' ) : null;
		if ( label ) {
			row.setAttribute( 'aria-label', label.textContent.trim() );
		}

		var chips = [];

		Array.prototype.forEach.call( select.options, function ( option ) {
			if ( ! option.value ) {
				return;
			}

			var chip = document.createElement( 'button' );
			chip.type = 'button';
			chip.className = 'nq-opt-chip';
			chip.setAttribute( 'role', 'radio' );
			chip.setAttribute( 'aria-checked', 'false' );
			chip.dataset.value = option.value;
			chip.textContent = option.textContent;

			chip.addEventListener( 'click', function () {
				select.value = option.value;
				// Woo binds with jQuery, so a native event is not enough.
				if ( window.jQuery ) {
					window.jQuery( select ).trigger( 'change' );
				} else {
					select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			} );

			chips.push( chip );
			row.appendChild( chip );
		} );

		if ( ! chips.length ) {
			return;
		}

		select.parentNode.insertBefore( row, select );
		select.classList.add( 'nq-visually-hidden' );

		function sync() {
			chips.forEach( function ( chip ) {
				var on = chip.dataset.value === select.value;
				chip.classList.toggle( 'is-active', on );
				chip.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			} );
		}

		select.addEventListener( 'change', sync );
		// Woo disables options that are unavailable for the current selection.
		if ( window.MutationObserver ) {
			new MutationObserver( sync ).observe( select, { childList: true, subtree: true } );
		}
		sync();
	}

	function init() {
		document
			.querySelectorAll( '.variations select, table.variations select' )
			.forEach( buildChips );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Woo rebuilds the form after its own AJAX; re-run then too.
	if ( window.jQuery ) {
		window.jQuery( document.body ).on( 'wc_variation_form check_variations', init );
	}
}() );
