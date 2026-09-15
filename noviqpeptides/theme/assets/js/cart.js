/**
 * Cart quantity auto-update — no "Update cart" click required.
 *
 * @package Noviq\Child
 */
( function ( $ ) {
	'use strict';

	var debounceTimer = null;
	var activeRequest = null;

	function blockCart( $scope ) {
		if ( ! $.fn.block ) {
			return;
		}
		$scope.block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.55,
			},
		} );
	}

	function unblockCart( $scope ) {
		if ( $.fn.unblock ) {
			$scope.unblock();
		}
	}

	function replaceCartSections( response ) {
		var $html = $( response );
		var $newForm = $html.find( 'form.woocommerce-cart-form' );

		if ( ! $newForm.length ) {
			window.location.reload();
			return;
		}

		$( 'form.woocommerce-cart-form' ).replaceWith( $newForm );

		var $newCollaterals = $html.find( '.cart-collaterals' );
		var $collaterals = $( '.cart-collaterals' );
		if ( $newCollaterals.length && $collaterals.length ) {
			$collaterals.replaceWith( $newCollaterals );
		}

		var $notices = $html.find( '.woocommerce-notices-wrapper' ).first();
		var $existing = $( '.woocommerce-notices-wrapper' ).first();
		if ( $notices.length && $existing.length ) {
			$existing.replaceWith( $notices );
		} else if ( $notices.length && ! $existing.length ) {
			$( '.woocommerce-cart-form' ).before( $notices );
		}

		$( document.body ).trigger( 'updated_cart_totals' );
		$( document.body ).trigger( 'updated_wc_div' );
		$( document.body ).trigger( 'wc_fragment_refresh' );
	}

	function updateCart( $form ) {
		var $scope = $form.closest( '.woocommerce' );
		var data = $form.serialize();

		if ( data.indexOf( 'update_cart' ) === -1 ) {
			data += '&update_cart=Update+cart';
		}

		blockCart( $scope );

		activeRequest = $.ajax( {
			type: $form.attr( 'method' ) || 'POST',
			url: $form.attr( 'action' ),
			data: data,
			dataType: 'html',
		} )
			.done( replaceCartSections )
			.fail( function ( _jqXHR, textStatus ) {
				if ( textStatus !== 'abort' ) {
					window.location.reload();
				}
			} )
			.always( function () {
				activeRequest = null;
				unblockCart( $scope );
			} );

		return activeRequest;
	}

	function scheduleUpdate( $form ) {
		clearTimeout( debounceTimer );
		debounceTimer = setTimeout( function () {
			if ( activeRequest ) {
				activeRequest.abort();
			}
			updateCart( $form );
		}, 350 );
	}

	$( document.body ).on( 'change', 'form.woocommerce-cart-form .qty', function () {
		var $form = $( this ).closest( 'form.woocommerce-cart-form' );
		if ( ! $form.length ) {
			return;
		}
		scheduleUpdate( $form );
	} );
}( window.jQuery ) );
