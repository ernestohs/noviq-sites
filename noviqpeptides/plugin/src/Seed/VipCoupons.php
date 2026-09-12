<?php
/**
 * VIP creator coupons — 10% perpetual, no usage cap.
 *
 * Codes come from data/{profile}/vip-coupons.json. Matching is on coupon code,
 * so re-runs update amount/description in place and never duplicate.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Seed;

defined( 'ABSPATH' ) || exit;

final class VipCoupons {

	public function __construct( private readonly Seeder $seeder ) {}

	public function run(): void {
		if ( ! class_exists( \WC_Coupon::class ) ) {
			$this->seeder->error( 'WooCommerce coupons are required to seed VIP codes.' );
		}

		$this->seeder->section( 'VIP coupons' );

		$rows = $this->seeder->data( 'vip-coupons' );

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$code = isset( $row['code'] ) ? wc_format_coupon_code( (string) $row['code'] ) : '';
			if ( '' === $code ) {
				$this->seeder->warn( 'Skipping VIP coupon with empty code.' );
				continue;
			}

			$amount      = isset( $row['amount'] ) ? (float) $row['amount'] : 10.0;
			$description = isset( $row['description'] ) && is_string( $row['description'] )
				? $row['description']
				: 'VIP creator — 10% perpetual';

			$this->upsert( $code, $amount, $description );
		}
	}

	private function upsert( string $code, float $amount, string $description ): void {
		$existing_id = (int) wc_get_coupon_id_by_code( $code );
		$label       = 'coupon ' . $code;

		if ( $existing_id > 0 ) {
			$coupon = new \WC_Coupon( $existing_id );

			if ( $this->matches_vip_fields( $coupon, $amount, $description ) ) {
				$this->seeder->skipped( $label );

				return;
			}

			if ( ! $this->seeder->is_dry_run() ) {
				$this->apply_vip_fields( $coupon, $amount, $description );
				$coupon->save();
			}

			$this->seeder->updated( $label );

			return;
		}

		if ( $this->seeder->is_dry_run() ) {
			$this->seeder->created( $label );

			return;
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$this->apply_vip_fields( $coupon, $amount, $description );
		$coupon->save();

		if ( 0 === (int) $coupon->get_id() ) {
			$this->seeder->warn( sprintf( 'Could not create coupon %s.', $code ) );

			return;
		}

		$this->seeder->created( $label );
	}

	/**
	 * True when every field apply_vip_fields() would write already matches.
	 */
	private function matches_vip_fields( \WC_Coupon $coupon, float $amount, string $description ): bool {
		return 'percent' === $coupon->get_discount_type()
			&& abs( (float) $coupon->get_amount() - $amount ) < 0.001
			&& $description === (string) $coupon->get_description()
			&& false === $coupon->get_individual_use()
			&& 0 === (int) $coupon->get_usage_limit()
			&& 0 === (int) $coupon->get_usage_limit_per_user()
			&& null === $coupon->get_limit_usage_to_x_items()
			&& null === $coupon->get_date_expires()
			&& false === $coupon->get_free_shipping()
			&& false === $coupon->get_exclude_sale_items();
	}

	private function apply_vip_fields( \WC_Coupon $coupon, float $amount, string $description ): void {
		$coupon->set_description( $description );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( $amount );
		$coupon->set_individual_use( false );
		$coupon->set_usage_limit( 0 );
		$coupon->set_usage_limit_per_user( 0 );
		$coupon->set_limit_usage_to_x_items( null );
		$coupon->set_date_expires( null );
		$coupon->set_free_shipping( false );
		$coupon->set_exclude_sale_items( false );
	}
}
