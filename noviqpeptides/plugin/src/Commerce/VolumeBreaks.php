<?php
/**
 * Volume-break pricing.
 *
 * Buy 3+ → 6% off every unit, 5+ → 10%, 10+ → 16%. The deepest tier met wins,
 * the discount applies to every unit (not just the units above the threshold),
 * and it is evaluated per variant per product — ten units spread across five
 * different variants is not a volume break.
 *
 * All arithmetic is in integer cents, mirroring noviq/src/lib/commerce/pricing.ts.
 * Money never becomes a float here: floats are only crossed at the WooCommerce
 * boundary, which stores prices as decimal strings.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

use Noviq\Core\Meta;

defined( 'ABSPATH' ) || exit;

final class VolumeBreaks {

	/**
	 * Default tier table. Products opt in via the noviq_volume_tiers meta the
	 * seeder writes, so the price book can diverge per product later without a
	 * code change.
	 *
	 * @return array<int, array{min_qty: int, discount: float}>
	 */
	public static function default_tiers(): array {
		return array(
			array(
				'min_qty'  => 3,
				'discount' => 0.06,
			),
			array(
				'min_qty'  => 5,
				'discount' => 0.10,
			),
			array(
				'min_qty'  => 10,
				'discount' => 0.16,
			),
		);
	}

	public static function init(): void {
		add_action( 'woocommerce_before_calculate_totals', array( self::class, 'apply_to_cart' ), 20 );
		add_filter( 'woocommerce_cart_item_price', array( self::class, 'cart_item_price_html' ), 10, 3 );
		add_action( 'woocommerce_after_cart_item_name', array( self::class, 'cart_item_nudge' ), 10, 2 );
		add_action( 'woocommerce_single_product_summary', array( self::class, 'product_tier_ladder' ), 25 );
	}

	/**
	 * Tier table for a product, or an empty array when it takes no breaks.
	 *
	 * @return array<int, array{min_qty: int, discount: float}>
	 */
	public static function tiers_for_product( int $product_id ): array {
		$raw = get_post_meta( $product_id, Meta::PRODUCT_TIERS, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$tiers = array();
		foreach ( $decoded as $tier ) {
			if ( ! isset( $tier['min_qty'], $tier['discount'] ) ) {
				continue;
			}
			$tiers[] = array(
				'min_qty'  => (int) $tier['min_qty'],
				'discount' => (float) $tier['discount'],
			);
		}

		usort( $tiers, static fn( array $a, array $b ): int => $a['min_qty'] <=> $b['min_qty'] );

		return $tiers;
	}

	/**
	 * The deepest volume break met at this quantity, or null.
	 *
	 * @param array<int, array{min_qty: int, discount: float}> $tiers Tier table.
	 * @return array{min_qty: int, discount: float}|null
	 */
	public static function active_tier( array $tiers, int $quantity ): ?array {
		$best = null;
		foreach ( $tiers as $tier ) {
			if ( $quantity < $tier['min_qty'] ) {
				continue;
			}
			if ( null === $best || $tier['discount'] > $best['discount'] ) {
				$best = $tier;
			}
		}

		return $best;
	}

	/**
	 * The next unmet break, for the "add N more to save X%" nudge.
	 *
	 * @param array<int, array{min_qty: int, discount: float}> $tiers Tier table.
	 * @return array{min_qty: int, discount: float}|null
	 */
	public static function next_tier( array $tiers, int $quantity ): ?array {
		$upcoming = array_values(
			array_filter( $tiers, static fn( array $t ): bool => $t['min_qty'] > $quantity )
		);

		return $upcoming[0] ?? null;
	}

	/**
	 * Unit price in cents after volume breaks.
	 *
	 * @param array<int, array{min_qty: int, discount: float}> $tiers Tier table.
	 */
	public static function unit_price_cents( int $base_cents, array $tiers, int $quantity ): int {
		$tier = self::active_tier( $tiers, $quantity );
		if ( null === $tier ) {
			return $base_cents;
		}

		return (int) round( $base_cents * ( 1 - $tier['discount'] ) );
	}

	private static function to_cents( float|string $amount ): int {
		return (int) round( ( (float) $amount ) * 100 );
	}

	/**
	 * Quantity of each priced unit in the cart, keyed by variation id (falling
	 * back to product id for simple products). Aggregating here is what makes
	 * the break apply per variant rather than per cart line — two lines of the
	 * same 10 mg vial count as one run of six.
	 *
	 * @return array<int, int>
	 */
	private static function quantities( \WC_Cart $cart ): array {
		$quantities = array();

		foreach ( $cart->get_cart() as $item ) {
			$key = (int) ( $item['variation_id'] ?: $item['product_id'] );
			$quantities[ $key ] = ( $quantities[ $key ] ?? 0 ) + (int) $item['quantity'];
		}

		return $quantities;
	}

	/**
	 * Rewrite cart line prices to the tiered unit price.
	 *
	 * The base price is re-read from a fresh product instance rather than from
	 * the cart's own object, so running this filter twice in one request cannot
	 * compound the discount.
	 */
	public static function apply_to_cart( \WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$quantities = self::quantities( $cart );

		foreach ( $cart->get_cart() as $item ) {
			if ( ! isset( $item['data'] ) || ! $item['data'] instanceof \WC_Product ) {
				continue;
			}

			$product_id = (int) $item['product_id'];
			$tiers      = self::tiers_for_product( $product_id );
			if ( array() === $tiers ) {
				continue;
			}

			$priced_id = (int) ( $item['variation_id'] ?: $product_id );
			$quantity  = $quantities[ $priced_id ] ?? (int) $item['quantity'];

			$base = wc_get_product( $priced_id );
			if ( ! $base instanceof \WC_Product ) {
				continue;
			}

			$base_cents = self::to_cents( (string) $base->get_price( 'edit' ) );
			$unit_cents = self::unit_price_cents( $base_cents, $tiers, $quantity );

			if ( $unit_cents !== $base_cents ) {
				$item['data']->set_price( $unit_cents / 100 );
			}
		}
	}

	/**
	 * Show the struck list price beside the discounted unit price in the cart.
	 *
	 * @param string               $price_html Rendered price.
	 * @param array<string, mixed> $item       Cart item.
	 */
	public static function cart_item_price_html( string $price_html, array $item, string $cart_item_key ): string {
		$product_id = (int) ( $item['product_id'] ?? 0 );
		$tiers      = self::tiers_for_product( $product_id );
		if ( array() === $tiers || ! isset( $item['data'] ) ) {
			return $price_html;
		}

		$priced_id = (int) ( $item['variation_id'] ?: $product_id );
		$base      = wc_get_product( $priced_id );
		if ( ! $base instanceof \WC_Product ) {
			return $price_html;
		}

		$base_cents    = self::to_cents( (string) $base->get_price( 'edit' ) );
		$current_cents = self::to_cents( (string) $item['data']->get_price( 'edit' ) );

		if ( $current_cents >= $base_cents ) {
			return $price_html;
		}

		return sprintf(
			'<span class="noviq-price-was">%s</span> <span class="noviq-price-now">%s</span>',
			wp_kses_post( wc_price( $base_cents / 100 ) ),
			wp_kses_post( wc_price( $current_cents / 100 ) )
		);
	}

	/**
	 * Per-line tier state and the next-break nudge, shown in the cart.
	 *
	 * @param array<string, mixed> $item Cart item.
	 */
	public static function cart_item_nudge( array $item, string $cart_item_key ): void {
		$product_id = (int) ( $item['product_id'] ?? 0 );
		$tiers      = self::tiers_for_product( $product_id );
		if ( array() === $tiers ) {
			return;
		}

		$quantity = (int) ( $item['quantity'] ?? 0 );
		$active   = self::active_tier( $tiers, $quantity );
		$next     = self::next_tier( $tiers, $quantity );

		echo '<div class="noviq-tier-state">';

		if ( null !== $active ) {
			printf(
				'<span class="noviq-tier-active">%s</span>',
				esc_html(
					sprintf(
						/* translators: %d: discount percentage. */
						__( 'Volume break applied — %d%% off every unit', 'noviq-core' ),
						(int) round( $active['discount'] * 100 )
					)
				)
			);
		}

		if ( null !== $next ) {
			$needed = $next['min_qty'] - $quantity;
			printf(
				'<span class="noviq-tier-next">%s</span>',
				esc_html(
					sprintf(
						/* translators: 1: number of additional units, 2: discount percentage. */
						_n(
							'Add %1$d more to save %2$d%%',
							'Add %1$d more to save %2$d%%',
							$needed,
							'noviq-core'
						),
						$needed,
						(int) round( $next['discount'] * 100 )
					)
				)
			);
		}

		echo '</div>';
	}

	/**
	 * The tier ladder on the product page. Numerics are monospace and tabular
	 * via the .noviq-num class — that is the brand signature.
	 */
	public static function product_tier_ladder(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$tiers = self::tiers_for_product( $product->get_id() );
		if ( array() === $tiers ) {
			return;
		}

		$in_cart = self::quantity_in_cart_for_product( $product->get_id() );
		$active  = self::active_tier( $tiers, $in_cart );
		$next    = self::next_tier( $tiers, $in_cart );

		echo '<div class="noviq-tiers" role="table" aria-label="' . esc_attr__( 'Volume pricing', 'noviq-core' ) . '">';
		echo '<div class="noviq-tiers__head">' . esc_html__( 'Volume pricing', 'noviq-core' ) . '</div>';
		echo '<ul class="noviq-tiers__list">';

		foreach ( $tiers as $tier ) {
			$is_active = null !== $active && $tier['min_qty'] === $active['min_qty'];
			printf(
				'<li class="noviq-tiers__row%1$s"><span class="noviq-num">%2$d+</span><span class="noviq-tiers__sep">units</span><span class="noviq-num noviq-tiers__pct">%3$d%%</span>%4$s</li>',
				$is_active ? ' is-active' : '',
				(int) $tier['min_qty'],
				(int) round( $tier['discount'] * 100 ),
				$is_active ? '<span class="noviq-tiers__flag">' . esc_html__( 'applied', 'noviq-core' ) . '</span>' : ''
			);
		}

		echo '</ul>';

		if ( null !== $next ) {
			$needed = $next['min_qty'] - $in_cart;
			printf(
				'<p class="noviq-tiers__nudge">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: number of additional units, 2: discount percentage. */
						__( 'Add %1$d more to save %2$d%% on every unit.', 'noviq-core' ),
						$needed,
						(int) round( $next['discount'] * 100 )
					)
				)
			);
		}

		echo '<p class="noviq-tiers__note">' . esc_html__( 'Breaks apply per variant. Discount is calculated at the cart.', 'noviq-core' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Units of any variant of this product already in the cart, so the ladder
	 * reflects what the researcher has actually selected.
	 */
	private static function quantity_in_cart_for_product( int $product_id ): int {
		if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
			return 0;
		}

		$total = 0;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( (int) $item['product_id'] === $product_id ) {
				$total += (int) $item['quantity'];
			}
		}

		return $total;
	}
}
