<?php
/**
 * Require a registered account to place an order.
 *
 * Guest checkout is off as policy, not preference: filters force registration
 * required and enabled so an admin cannot reopen anonymous purchases from
 * WooCommerce → Settings → Accounts. Guests may browse and add to cart; the
 * cart shows a notice and checkout creates or requires an account.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Compliance;

defined( 'ABSPATH' ) || exit;

final class BuyerAccounts {

	/**
	 * Registration is deferred to woocommerce_init: this plugin loads before
	 * WooCommerce, so checkout helpers do not exist yet at boot.
	 */
	public static function init(): void {
		add_action( 'woocommerce_init', array( self::class, 'register' ) );
	}

	public static function register(): void {
		add_filter( 'woocommerce_checkout_registration_required', '__return_true' );
		add_filter( 'woocommerce_checkout_registration_enabled', '__return_true' );
		add_action( 'woocommerce_before_cart', array( self::class, 'cart_notice' ) );
		add_filter( 'woocommerce_account_settings', array( self::class, 'lock_guest_checkout_setting' ) );
	}

	/**
	 * Point logged-out shoppers at My Account before they reach checkout.
	 */
	public static function cart_notice(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		$account_url = function_exists( 'wc_get_page_permalink' )
			? (string) wc_get_page_permalink( 'myaccount' )
			: '';

		$message = __( 'An account is required to place an order. Log in or create an account at checkout.', 'noviq-core' );

		if ( '' !== $account_url ) {
			$message = sprintf(
				/* translators: %s: My Account page URL */
				__( 'An account is required to place an order. <a href="%s">Log in or create an account</a>, or continue to checkout to register.', 'noviq-core' ),
				esc_url( $account_url )
			);
		}

		wc_print_notice( $message, 'notice' );
	}

	/**
	 * Keep the Accounts settings screen honest: guest checkout stays off and
	 * the control is disabled so it cannot be flipped back on.
	 *
	 * @param array<int, array<string, mixed>> $settings Account settings fields.
	 * @return array<int, array<string, mixed>>
	 */
	public static function lock_guest_checkout_setting( array $settings ): array {
		foreach ( $settings as $index => $setting ) {
			if ( ! isset( $setting['id'] ) || 'woocommerce_enable_guest_checkout' !== $setting['id'] ) {
				continue;
			}

			$description = isset( $setting['desc'] ) ? (string) $setting['desc'] : '';
			$enforced    = __( 'Enforced by Noviq Core: guest checkout stays off.', 'noviq-core' );
			$settings[ $index ]['desc'] = '' !== $description
				? $description . ' ' . $enforced
				: $enforced;

			$custom = isset( $setting['custom_attributes'] ) && is_array( $setting['custom_attributes'] )
				? $setting['custom_attributes']
				: array();
			$custom['disabled']                   = 'disabled';
			$settings[ $index ]['custom_attributes'] = $custom;
			break;
		}

		return $settings;
	}
}
