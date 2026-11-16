<?php
/**
 * Affiliate discounts do not stack with other coupons.
 *
 * Volume breaks are cart line prices, not coupons, and are left alone. When a
 * promotion wins, the affiliate code is still remembered so the order can be
 * attributed. An existing promotion is never replaced by the 10% code.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates;

defined( 'ABSPATH' ) || exit;

final class PromotionGuard {

	private static bool $adjusting = false;

	private static bool $displaced = false;

	public static function init(): void {
		add_filter( 'woocommerce_coupon_is_valid', array( self::class, 'keep_existing_promotion' ), 15, 3 );
		add_filter( 'woocommerce_apply_with_individual_use_coupon', array( self::class, 'allow_promotion_over_affiliate' ), 10, 3 );
		add_action( 'woocommerce_applied_coupon', array( self::class, 'drop_affiliate_for_promotion' ), 5, 1 );
	}

	/**
	 * Reject an affiliate coupon while another promotion is already applied.
	 *
	 * Looks at the cart or order being validated, not at a leftover cart when
	 * the coupon is being attached to an order.
	 *
	 * @param bool|\WP_Error    $valid     Validity so far.
	 * @param \WC_Coupon        $coupon    Coupon being applied.
	 * @param \WC_Discounts|null $discounts Discount context.
	 * @return bool|\WP_Error
	 */
	public static function keep_existing_promotion( $valid, $coupon, $discounts = null ) {
		if ( true !== $valid || ! $coupon instanceof \WC_Coupon ) {
			return $valid;
		}
		if ( ! Codes::is_affiliate_code( $coupon->get_code() ) ) {
			return $valid;
		}

		$incoming = Program::format_code( $coupon->get_code() );
		foreach ( self::codes_in_context( $discounts ) as $applied ) {
			$applied = Program::format_code( (string) $applied );
			if ( '' === $applied || $applied === $incoming || Codes::is_affiliate_code( $applied ) ) {
				continue;
			}

			Attribution::remember_code( $incoming );

			throw new \Exception(
				__( 'A store promotion is already applied, so the representative discount was not added. Your referral code is still recorded.', 'noviq-core' ),
				100
			);
		}

		return $valid;
	}

	/**
	 * @param \WC_Discounts|null $discounts Discount context from the validity filter.
	 * @return list<string>
	 */
	private static function codes_in_context( $discounts ): array {
		$object = ( $discounts instanceof \WC_Discounts ) ? $discounts->get_object() : null;
		if ( $object instanceof \WC_Cart ) {
			return array_map( 'strval', $object->get_applied_coupons() );
		}
		if ( $object instanceof \WC_Order ) {
			$codes = array();
			foreach ( $object->get_items( 'coupon' ) as $item ) {
				if ( $item instanceof \WC_Order_Item_Coupon ) {
					$codes[] = $item->get_code();
				}
			}

			return $codes;
		}
		if ( function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ) {
			return array_map( 'strval', WC()->cart->get_applied_coupons() );
		}

		return array();
	}

	/**
	 * Let a store promotion apply even though the affiliate coupon is individual-use.
	 * The affiliate coupon is removed afterwards by drop_affiliate_for_promotion().
	 *
	 * @param bool       $apply    Whether WooCommerce should allow the new coupon.
	 * @param \WC_Coupon $new_coupon Coupon being added.
	 * @param \WC_Coupon $existing   Existing individual-use coupon.
	 */
	public static function allow_promotion_over_affiliate( bool $apply, $new_coupon, $existing ): bool {
		if ( ! $new_coupon instanceof \WC_Coupon || ! $existing instanceof \WC_Coupon ) {
			return $apply;
		}
		if ( ! Codes::is_affiliate_code( $existing->get_code() ) || Codes::is_affiliate_code( $new_coupon->get_code() ) ) {
			return $apply;
		}

		Attribution::remember_code( $existing->get_code() );
		self::$displaced = true;

		return true;
	}

	public static function drop_affiliate_for_promotion( string $code ): void {
		if ( self::$adjusting || Codes::is_affiliate_code( $code ) ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
			return;
		}

		self::$adjusting = true;
		$removed         = false;
		foreach ( WC()->cart->get_applied_coupons() as $applied ) {
			if ( ! Codes::is_affiliate_code( (string) $applied ) ) {
				continue;
			}
			Attribution::remember_code( (string) $applied );
			WC()->cart->remove_coupon( (string) $applied );
			$removed = true;
		}

		if ( ( $removed || self::$displaced ) && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice(
				__( 'The representative discount was removed so the store promotion can apply. Your referral code is still recorded.', 'noviq-core' ),
				'notice'
			);
		}
		self::$displaced = false;
		self::$adjusting = false;
	}
}
