/**
 * Auto-save cart quantities on change (select dropdown or number input).
 *
 * Delegates the AJAX refresh to WooCommerce's cart.js quantity_update path.
 *
 * @package Noviq\Child
 */
jQuery( function ( $ ) {
	'use strict';

	var debounceTimer = null;

	function queueQuantityUpdate( $form ) {
		clearTimeout( debounceTimer );
		debounceTimer = setTimeout( function () {
			var $btn = $form.find( ':input[name="update_cart"]' );

			// WC keeps this disabled until a cart line input changes.
			$btn.prop( 'disabled', false );

			// cart_submit() routes update_cart clicks to quantity_update().
			$btn.attr( 'clicked', 'true' );
			$form.trigger( 'submit' );
			$btn.removeAttr( 'clicked' );
		}, 350 );
	}

	$( document.body ).on(
		'change',
		'form.woocommerce-cart-form .qty',
		function () {
			var $form = $( this ).closest( 'form.woocommerce-cart-form' );
			if ( $form.length ) {
				queueQuantityUpdate( $form );
			}
		}
	);

	// Stepper mode: save after the shopper stops typing.
	$( document.body ).on(
		'input',
		'form.woocommerce-cart-form input.qty',
		function () {
			var $form = $( this ).closest( 'form.woocommerce-cart-form' );
			if ( $form.length ) {
				queueQuantityUpdate( $form );
			}
		}
	);

	// Header pill count after WC finishes refreshing the cart table.
	$( document.body ).on( 'updated_wc_div', function () {
		$( document.body ).trigger( 'wc_fragment_refresh' );
	} );
} );
