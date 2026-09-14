<?php
/**
 * Fractional storefront prices under a whole-dollar catalog default.
 *
 * Production seed sets woocommerce_price_num_decimals to 0 so peptide vials
 * read as $55, not $55.00. Supplies such as bacteriostatic water can still
 * carry cent-level prices; this module shows those cents without changing the
 * global store setting.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

defined( 'ABSPATH' ) || exit;

final class PriceDisplay {

	public static function init(): void {
		add_filter( 'formatted_woocommerce_price', array( self::class, 'fractional_formatted_price' ), 10, 6 );
		add_filter( 'woocommerce_get_price_html', array( self::class, 'supplies_price_html' ), 20, 2 );
		add_filter( 'woocommerce_cart_item_price', array( self::class, 'cart_unit_price' ), 20, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( self::class, 'cart_line_subtotal' ), 20, 3 );
	}

	/**
	 * wc_price_args receives only formatting args, not the amount. Re-format here
	 * once WooCommerce has the raw price and the active decimal count.
	 *
	 * @param string               $formatted_price    Price string from number_format().
	 * @param float                $price              Unformatted amount.
	 * @param int                  $decimals           Decimal places used for $formatted_price.
	 * @param string               $decimal_separator  Store decimal separator.
	 * @param string               $thousand_separator Store thousands separator.
	 * @param float|string         $original_price     Original wc_price() input.
	 */
	public static function fractional_formatted_price(
		string $formatted_price,
		float $price,
		int $decimals,
		string $decimal_separator,
		string $thousand_separator,
		$original_price = ''
	): string {
		if ( $decimals >= self::FRACTIONAL_DECIMALS || ! self::has_fractional_cents( $price ) ) {
			return $formatted_price;
		}

		return number_format( $price, self::FRACTIONAL_DECIMALS, $decimal_separator, $thousand_separator );
	}

	/**
	 * @param string               $html    Default price HTML.
	 * @param \WC_Product|null     $product Product being rendered.
	 */
	public static function supplies_price_html( string $html, $product ): string {
		if ( ! $product instanceof \WC_Product || ! self::is_supplies_product( $product ) ) {
			return $html;
		}

		return self::format_product_price_html( $product ) ?? $html;
	}

	/**
	 * @param string               $html      Default unit price HTML.
	 * @param array<string, mixed> $cart_item Cart row.
	 */
	public static function cart_unit_price( string $html, array $cart_item, string $cart_item_key ): string {
		$product = $cart_item['data'] ?? null;
		if ( ! $product instanceof \WC_Product ) {
			return $html;
		}

		return self::format_cart_amount_html( $cart_item, (float) $product->get_price() ) ?? $html;
	}

	/**
	 * @param string               $html      Default line subtotal HTML.
	 * @param array<string, mixed> $cart_item Cart row.
	 */
	public static function cart_line_subtotal( string $html, array $cart_item, string $cart_item_key ): string {
		$product = $cart_item['data'] ?? null;
		if ( ! $product instanceof \WC_Product ) {
			return $html;
		}

		$qty    = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
		$amount = (float) $product->get_price() * max( 1, $qty );

		return self::format_cart_amount_html( $cart_item, $amount ) ?? $html;
	}

	/**
	 * @param array<string, mixed> $cart_item Cart row.
	 */
	private static function format_cart_amount_html( array $cart_item, float $amount ): ?string {
		$product = $cart_item['data'] ?? null;
		if ( ! $product instanceof \WC_Product || ! self::is_supplies_product( $product ) ) {
			return null;
		}

		if ( ! self::has_fractional_cents( $amount ) ) {
			return null;
		}

		return wp_kses_post( wc_price( $amount, array( 'decimals' => self::FRACTIONAL_DECIMALS ) ) );
	}

	private const FRACTIONAL_DECIMALS = 2;

	private static function format_product_price_html( \WC_Product $product ): ?string {
		$price    = (float) $product->get_price();
		$regular  = (float) $product->get_regular_price();
		$on_sale  = $product->is_on_sale() && $regular > $price && $price > 0;
		$fraction = self::has_fractional_cents( $price ) || self::has_fractional_cents( $regular );

		if ( ! $fraction ) {
			return null;
		}

		$args = array( 'decimals' => self::FRACTIONAL_DECIMALS );

		if ( $on_sale ) {
			return wp_kses_post(
				wc_format_sale_price(
					wc_price( $regular, $args ),
					wc_price( $price, $args )
				)
			);
		}

		return wp_kses_post( wc_price( $price, $args ) );
	}

	private static function has_fractional_cents( float $amount ): bool {
		return abs( $amount - round( $amount ) ) > 0.001;
	}

	private static function is_supplies_product( \WC_Product $product ): bool {
		if ( ! function_exists( 'has_term' ) ) {
			return false;
		}

		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

		return has_term( 'supplies', 'product_cat', $product_id );
	}
}
