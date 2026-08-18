<?php
/**
 * Single source of truth for company facts and every quantitative claim the
 * site makes.
 *
 * Values come from the active profile (profiles/{NOVIQ_PROFILE}.php). The
 * public API is unchanged: `null` means "we cannot substantiate this yet", and
 * every consumer renders nothing rather than a placeholder number.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core;

defined( 'ABSPATH' ) || exit;

final class Claims {

	/**
	 * Company facts.
	 *
	 * @return array<string, mixed>
	 */
	public static function site(): array {
		return Profile::site();
	}

	/**
	 * Quantitative claims. Every non-null value here must be backed by evidence
	 * the client holds.
	 *
	 * @return array<string, int|float|string|null>
	 */
	public static function all(): array {
		return Profile::claims();
	}

	/**
	 * Raw claim value, or null when unsubstantiated.
	 */
	public static function get( string $key ): int|float|string|null {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * True when a claim may be rendered at all. Templates must branch on this
	 * rather than printing a falsy value.
	 */
	public static function has( string $key ): bool {
		return null !== self::get( $key );
	}

	/**
	 * Escaped claim value for direct output, or an empty string when the claim
	 * is unsubstantiated. Callers that need surrounding copy must still guard
	 * with has() so no orphaned label is printed.
	 */
	public static function render( string $key, string $format = '%s' ): string {
		$value = self::get( $key );
		if ( null === $value ) {
			return '';
		}

		return esc_html( sprintf( $format, $value ) );
	}

	/**
	 * A company fact, or null.
	 */
	public static function fact( string $key ): ?string {
		$value = self::site()[ $key ] ?? null;

		return is_string( $value ) ? $value : null;
	}

	/**
	 * Approved short RUO notice. Verbatim from the profile — do not paraphrase.
	 */
	public static function ruo_short(): string {
		return Profile::ruo_short();
	}

	/**
	 * Approved full RUO disclaimer. Verbatim from the profile.
	 */
	public static function ruo_full(): string {
		return Profile::ruo_full();
	}

	/**
	 * Rotating top-strip items. Claims only, no puffery. Items backed by a null
	 * claim are dropped rather than rendered with a blank.
	 *
	 * A profile may supply an explicit list (e.g. March Analytics banner copy).
	 *
	 * @return string[]
	 */
	public static function ticker_items(): array {
		$explicit = Profile::ticker_items();
		if ( null !== $explicit ) {
			return $explicit;
		}

		$items = array( 'Lot-matched Certificate of Analysis on every vial' );

		if ( self::has( 'purity_spec' ) ) {
			$items[] = sprintf( '≥%s%% purity spec — RP-HPLC + mass spec', self::get( 'purity_spec' ) );
		}
		if ( self::has( 'endotoxin_spec' ) ) {
			$items[] = sprintf( 'Endotoxin screened to ≤%s EU/mg', self::get( 'endotoxin_spec' ) );
		}
		if ( self::has( 'sterility_incubation_days' ) ) {
			$items[] = sprintf( 'USP <71> sterility — full %s-day incubation', self::get( 'sterility_incubation_days' ) );
		}
		if ( self::has( 'dispatch_cutoff' ) ) {
			$items[] = sprintf( 'Same-day dispatch on orders before %s', self::get( 'dispatch_cutoff' ) );
		}
		if ( self::has( 'free_shipping_threshold' ) ) {
			$items[] = sprintf( 'Free shipping over $%s', self::get( 'free_shipping_threshold' ) );
		}

		$items[] = 'For laboratory research use only';

		return $items;
	}
}
