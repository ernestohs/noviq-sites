<?php
/**
 * Affiliate codes are WooCommerce coupons plus a rep row.
 *
 * Linking a code never rewrites an existing coupon, so VIP creator coupons keep
 * the fields the seeder owns. Deactivating a rep rejects the coupon in
 * woocommerce_coupon_is_valid without deleting it or its redemption history.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates;

defined( 'ABSPATH' ) || exit;

final class Codes {

	/** @var array<string, array<string, mixed>>|null */
	private static ?array $by_code = null;

	public static function init(): void {
		add_filter( 'woocommerce_coupon_is_valid', array( self::class, 'reject_inactive' ), 9, 2 );
	}

	public static function flush_cache(): void {
		self::$by_code = null;
	}

	/**
	 * @return int|\WP_Error Coupon id.
	 */
	public static function ensure_coupon( string $code ) {
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) || ! class_exists( \WC_Coupon::class ) ) {
			return new \WP_Error( 'noviq_aff_woo', __( 'WooCommerce coupons are required.', 'noviq-core' ) );
		}

		$code = Program::format_code( $code );
		if ( '' === $code ) {
			return new \WP_Error( 'noviq_aff_code', __( 'Enter a code.', 'noviq-core' ) );
		}

		$existing_id = (int) wc_get_coupon_id_by_code( $code );
		if ( $existing_id > 0 ) {
			$coupon = new \WC_Coupon( $existing_id );
			if ( 'percent' !== $coupon->get_discount_type() || abs( (float) $coupon->get_amount() - 10.0 ) > 0.001 ) {
				return new \WP_Error(
					'noviq_aff_coupon',
					__( 'That coupon exists but is not a 10% code. Affiliate codes are not rewritten.', 'noviq-core' )
				);
			}

			return $existing_id;
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_description( __( 'Affiliate representative — 10%', 'noviq-core' ) );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 0 );
		$coupon->set_usage_limit_per_user( 0 );
		$coupon->set_limit_usage_to_x_items( null );
		$coupon->set_date_expires( null );
		$coupon->set_free_shipping( false );
		$coupon->set_exclude_sale_items( false );
		$coupon->save();

		$coupon_id = (int) $coupon->get_id();
		if ( $coupon_id <= 0 ) {
			return new \WP_Error( 'noviq_aff_coupon', __( 'Could not create the coupon.', 'noviq-core' ) );
		}

		return $coupon_id;
	}

	/**
	 * Suggest a unique code from a name when the admin leaves the field blank.
	 */
	public static function suggest_code( string $name ): string {
		$base = strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', $name ) );
		if ( '' === $base ) {
			$base = 'REP';
		}
		$base = substr( $base, 0, 12 );

		for ( $i = 0; $i < 20; $i++ ) {
			$candidate = Program::format_code( $base . (string) wp_rand( 1000, 9999 ) );
			if ( null === Program::rep_by_code( $candidate ) && ( ! function_exists( 'wc_get_coupon_id_by_code' ) || 0 === (int) wc_get_coupon_id_by_code( $candidate ) ) ) {
				return $candidate;
			}
		}

		return Program::format_code( $base . strtoupper( wp_generate_password( 4, false, false ) ) );
	}

	public static function is_affiliate_code( string $code ): bool {
		return null !== self::rep_for_code( $code );
	}

	public static function is_active_code( string $code ): bool {
		$rep = self::rep_for_code( $code );

		return null !== $rep && 'active' === (string) $rep['status'];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function rep_for_code( string $code ): ?array {
		$code = Program::format_code( $code );
		if ( '' === $code ) {
			return null;
		}

		self::warm();

		return self::$by_code[ $code ] ?? null;
	}

	/**
	 * @param bool|\WP_Error $valid  Validity so far.
	 * @param \WC_Coupon     $coupon Coupon being checked.
	 * @return bool|\WP_Error
	 */
	public static function reject_inactive( $valid, $coupon ) {
		if ( true !== $valid || ! $coupon instanceof \WC_Coupon ) {
			return $valid;
		}
		if ( ! self::is_affiliate_code( $coupon->get_code() ) ) {
			return $valid;
		}
		if ( self::is_active_code( $coupon->get_code() ) ) {
			return $valid;
		}

		throw new \Exception(
			__( 'This referral code is not active.', 'noviq-core' ),
			100
		);
	}

	private static function warm(): void {
		if ( null !== self::$by_code ) {
			return;
		}

		global $wpdb;
		self::$by_code = array();
		if ( ! self::tables_ready() ) {
			return;
		}

		$rows = $wpdb->get_results( 'SELECT * FROM ' . Schema::table( 'reps' ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return;
		}
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && isset( $row['code'] ) ) {
				self::$by_code[ (string) $row['code'] ] = $row;
			}
		}
	}

	private static function tables_ready(): bool {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::table( 'reps' ) ) );

		return is_string( $found ) && '' !== $found;
	}
}
