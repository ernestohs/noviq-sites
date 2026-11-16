<?php
/**
 * Integer-cent money for affiliate commissions.
 *
 * Rates are basis points. 2500 bp is 25 percent, 700 bp is 7 percent. Both
 * commissions use the same base independently:
 *
 *   rep       = round_half_up(base_cents * 2500 / 10000)
 *   recruiter = round_half_up(base_cents * 700 / 10000)
 *
 * The commissionable base is the sum of WooCommerce product line totals after
 * discounts, excluding tax, shipping, and fees. Callers pass that base in cents.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates;

defined( 'ABSPATH' ) || exit;

final class Money {

	public const REP_BP       = 2500;
	public const RECRUITER_BP = 700;

	/**
	 * Parse a WooCommerce decimal string into cents, half-up on the third decimal.
	 */
	public static function to_cents( mixed $amount ): int {
		if ( is_int( $amount ) ) {
			$raw = (string) $amount;
		} elseif ( is_float( $amount ) ) {
			$raw = number_format( $amount, 6, '.', '' );
		} else {
			$raw = trim( (string) $amount );
		}

		$raw = str_replace( array( ',', ' ' ), '', $raw );
		if ( '' === $raw ) {
			return 0;
		}

		$negative = str_starts_with( $raw, '-' );
		if ( $negative ) {
			$raw = substr( $raw, 1 );
		}

		if ( ! preg_match( '/^\d+(\.\d+)?$/', $raw ) ) {
			return 0;
		}

		$parts = explode( '.', $raw, 2 );
		$whole = (int) $parts[0];
		$frac  = str_pad( substr( $parts[1] ?? '', 0, 3 ), 3, '0' );
		$cents = (int) substr( $frac, 0, 2 );
		if ( (int) $frac[2] >= 5 ) {
			++$cents;
		}
		if ( $cents >= 100 ) {
			++$whole;
			$cents -= 100;
		}

		$total = ( $whole * 100 ) + $cents;

		return $negative ? -$total : $total;
	}

	/**
	 * Half-up integer percentage. $rate_bp is basis points (10000 = 100%).
	 */
	public static function apply_rate( int $base_cents, int $rate_bp ): int {
		if ( 0 === $base_cents || $rate_bp <= 0 ) {
			return 0;
		}

		$sign = $base_cents < 0 ? -1 : 1;
		$num  = abs( $base_cents ) * $rate_bp;

		return $sign * intdiv( $num + 5000, 10000 );
	}

	/**
	 * Half-up $amount * $numerator / $denominator, capped at $amount.
	 */
	public static function prorate( int $amount, int $numerator, int $denominator ): int {
		if ( 0 === $amount || $numerator <= 0 || $denominator <= 0 ) {
			return 0;
		}
		if ( $numerator >= $denominator ) {
			return $amount;
		}

		$sign   = $amount < 0 ? -1 : 1;
		$amount = abs( $amount );

		return $sign * intdiv( ( $amount * $numerator ) + intdiv( $denominator, 2 ), $denominator );
	}

	/**
	 * Signed ledger delta for one refund or reversal step.
	 *
	 * When the cumulative refunded base reaches the original base, the remainder
	 * of the original commission is reversed so rounding cannot drift. The result
	 * is never a larger reversal than the amount still unreversed.
	 */
	public static function refund_delta( int $original_amount, int $original_base, int $cumulative_refunded_base, int $already_reversed ): int {
		if ( $original_amount <= 0 || $original_base <= 0 || $cumulative_refunded_base <= 0 ) {
			return 0;
		}

		$already_reversed = max( 0, $already_reversed );
		$remaining        = $original_amount - $already_reversed;
		if ( $remaining <= 0 ) {
			return 0;
		}

		$cumulative = min( $cumulative_refunded_base, $original_base );
		$target     = $cumulative >= $original_base
			? $original_amount
			: self::prorate( $original_amount, $cumulative, $original_base );
		$target     = min( $target, $original_amount );
		$delta      = $target - $already_reversed;

		if ( $delta <= 0 ) {
			return 0;
		}

		return -min( $delta, $remaining );
	}

	/**
	 * Plain currency for CSV and logs. Always two decimals, independent of the
	 * storefront price-decimal setting (production displays whole dollars).
	 */
	public static function format_plain( int $cents, string $symbol = '$' ): string {
		$negative = $cents < 0;
		$abs      = abs( $cents );
		$dollars  = number_format( intdiv( $abs, 100 ) );
		$text     = $dollars . '.' . str_pad( (string) ( $abs % 100 ), 2, '0', STR_PAD_LEFT );

		return ( $negative ? '-' : '' ) . $symbol . $text;
	}

	public static function format_html( int $cents ): string {
		$symbol = function_exists( 'get_woocommerce_currency_symbol' )
			? html_entity_decode( (string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
			: '$';
		$plain  = esc_html( self::format_plain( $cents, $symbol ) );
		$class  = $cents < 0 ? 'noviq-aff-neg noviq-num' : 'noviq-num';

		return '<span class="' . esc_attr( $class ) . '">' . $plain . '</span>';
	}
}
