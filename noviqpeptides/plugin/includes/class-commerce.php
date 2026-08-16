<?php
/**
 * Volume pricing and store commerce defaults.
 *
 * @package NoviqPeptides
 */

declare(strict_types=1);

namespace NoviqPeptides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Commerce {

	public static function init(): void {
		add_action( 'init', array( self::class, 'defaults' ) );
		if ( ! Plugin::woo_active() ) {
			add_action( 'woocommerce_loaded', array( self::class, 'woo_hooks' ) );
			return;
		}
		self::woo_hooks();
	}

	public static function defaults(): void {
		if ( false === get_option( 'noviq_free_shipping_threshold_cents', false ) ) {
			update_option( 'noviq_free_shipping_threshold_cents', '' );
		}
		if ( false === get_option( 'noviq_flat_shipping_cents', false ) ) {
			update_option( 'noviq_flat_shipping_cents', '' );
		}
		if ( false === get_option( 'noviq_subscribe_enabled', false ) ) {
			update_option( 'noviq_subscribe_enabled', '0' );
		}
	}

	public static function woo_hooks(): void {
		add_filter( 'woocommerce_product_get_price', array( self::class, 'maybe_volume_price' ), 20, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( self::class, 'maybe_volume_price' ), 20, 2 );
		add_filter( 'woocommerce_product_review_list_args', array( self::class, 'disable_reviews_query' ) );
		add_filter( 'woocommerce_product_get_rating_html', array( self::class, 'strip_rating_html' ), 10, 3 );
		add_filter( 'woocommerce_structured_data_product', array( self::class, 'strip_aggregate_rating' ) );
		add_filter( 'comments_open', array( self::class, 'close_product_reviews' ), 20, 2 );
		add_filter( 'woocommerce_shipping_free_shipping_is_available', array( self::class, 'free_shipping_from_option' ), 20, 2 );
		add_filter( 'woocommerce_package_rates', array( self::class, 'apply_flat_rate_from_option' ), 20, 2 );
	}

	/**
	 * Volume tiers live in product meta `_noviq_volume_tiers` as JSON:
	 * [{"qty":5,"percent":5},{"qty":10,"percent":10}]
	 * Empty until intake C17. Same-variant qty only.
	 *
	 * @param mixed      $price   Price.
	 * @param \WC_Product $product Product.
	 * @return mixed
	 */
	public static function maybe_volume_price( $price, $product ) {
		if ( ! $product instanceof \WC_Product || ! is_numeric( $price ) ) {
			return $price;
		}
		$tiers_raw = $product->get_meta( '_noviq_volume_tiers' );
		if ( ! is_string( $tiers_raw ) || '' === $tiers_raw ) {
			return $price;
		}
		$tiers = json_decode( $tiers_raw, true );
		if ( ! is_array( $tiers ) || array() === $tiers ) {
			return $price;
		}
		$qty = 0;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				$match_id = isset( $item['variation_id'] ) && $item['variation_id'] ? (int) $item['variation_id'] : (int) $item['product_id'];
				if ( $match_id === (int) $product->get_id() ) {
					$qty += (int) $item['quantity'];
				}
			}
		}
		if ( $qty < 1 ) {
			return $price;
		}
		$best = 0;
		foreach ( $tiers as $tier ) {
			if ( ! is_array( $tier ) ) {
				continue;
			}
			$need = isset( $tier['qty'] ) ? (int) $tier['qty'] : 0;
			$pct  = isset( $tier['percent'] ) ? (int) $tier['percent'] : 0;
			if ( $need > 0 && $qty >= $need && $pct > $best ) {
				$best = $pct;
			}
		}
		if ( $best <= 0 ) {
			return $price;
		}
		$cents = (int) round( ( (float) $price ) * 100 );
		$cents = (int) round( $cents * ( 100 - $best ) / 100 );
		return (string) ( $cents / 100 );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	public static function disable_reviews_query( array $args ): array {
		$args['number'] = 0;
		return $args;
	}

	public static function strip_rating_html( string $html, $rating, $count ): string {
		return '';
	}

	/**
	 * @param array $markup Markup.
	 * @return array
	 */
	public static function strip_aggregate_rating( array $markup ): array {
		unset( $markup['aggregateRating'] );
		return $markup;
	}

	public static function close_product_reviews( bool $open, $post_id ): bool {
		if ( 'product' === get_post_type( $post_id ) ) {
			return false;
		}
		return $open;
	}

	public static function subscribe_enabled(): bool {
		return '1' === (string) get_option( 'noviq_subscribe_enabled', '0' );
	}

	public static function free_shipping_threshold_cents(): ?int {
		$raw = get_option( 'noviq_free_shipping_threshold_cents', '' );
		return is_numeric( $raw ) ? (int) $raw : null;
	}

	public static function flat_shipping_cents(): ?int {
		$raw = get_option( 'noviq_flat_shipping_cents', '' );
		return is_numeric( $raw ) ? (int) $raw : null;
	}

	/**
	 * When free-over threshold option is set, gate free shipping on cart subtotal.
	 * Empty option = leave Woo method settings alone.
	 *
	 * @param bool  $available Availability.
	 * @param array $package   Package.
	 */
	public static function free_shipping_from_option( bool $available, $package ): bool {
		$threshold = self::free_shipping_threshold_cents();
		if ( null === $threshold ) {
			return $available;
		}
		$subtotal_cents = 0;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$subtotal_cents = (int) round( (float) WC()->cart->get_subtotal() * 100 );
		}
		return $subtotal_cents >= $threshold;
	}

	/**
	 * When flat shipping option is set, override flat_rate costs. Empty = no override.
	 *
	 * @param array $rates   Rates.
	 * @param array $package Package.
	 * @return array
	 */
	public static function apply_flat_rate_from_option( $rates, $package ) {
		$flat = self::flat_shipping_cents();
		if ( null === $flat || ! is_array( $rates ) ) {
			return $rates;
		}
		$cost = number_format( $flat / 100, 2, '.', '' );
		foreach ( $rates as $rate ) {
			if ( $rate instanceof \WC_Shipping_Rate && 0 === strpos( (string) $rate->get_method_id(), 'flat_rate' ) ) {
				$rate->set_cost( $cost );
			}
		}
		return $rates;
	}
}
