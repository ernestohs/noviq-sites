<?php
/**
 * VIP creator coupon rows from data/{profile}/vip-coupons.json.
 *
 * Single source for the allowlist, seed fields, and social share metadata.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

use Noviq\Core\Profile;

defined( 'ABSPATH' ) || exit;

final class VipCouponsRegistry {

	private const DEFAULT_IMAGE = 'vip/default.jpg';

	/** @var list<array<string, mixed>>|null */
	private static ?array $rows = null;

	/** @var array<string, array<string, mixed>>|null code => row */
	private static ?array $by_code = null;

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function rows(): array {
		self::load();

		return self::$rows ?? array();
	}

	/**
	 * @return list<string>
	 */
	public static function codes(): array {
		self::load();

		return array_keys( self::$by_code ?? array() );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function row( string $code ): ?array {
		if ( ! function_exists( 'wc_format_coupon_code' ) ) {
			return null;
		}

		$code = wc_format_coupon_code( $code );
		if ( '' === $code ) {
			return null;
		}

		self::load();

		return self::$by_code[ $code ] ?? null;
	}

	/**
	 * Resolved Open Graph / Twitter Card fields for a VIP code.
	 *
	 * @return array{
	 *   code: string,
	 *   title: string,
	 *   description: string,
	 *   image_url: string,
	 *   canonical_url: string,
	 *   amount: float,
	 *   creator_name: string,
	 *   has_custom_title: bool,
	 *   has_custom_description: bool,
	 *   has_custom_image: bool
	 * }
	 */
	public static function share_meta( string $code, bool $via_go = false ): array {
		if ( function_exists( 'wc_format_coupon_code' ) ) {
			$code = wc_format_coupon_code( $code );
		}

		$row    = self::row( $code ) ?? array();
		$amount = isset( $row['amount'] ) ? (float) $row['amount'] : 10.0;
		$share  = ( isset( $row['share'] ) && is_array( $row['share'] ) ) ? $row['share'] : array();

		$display_code = $code;
		if ( isset( $row['code'] ) && is_string( $row['code'] ) && '' !== trim( $row['code'] ) ) {
			$display_code = trim( $row['code'] );
		}

		$creator = '';
		if ( isset( $share['creator_name'] ) && is_string( $share['creator_name'] ) ) {
			$creator = trim( $share['creator_name'] );
		}

		$label = '' !== $creator ? $creator : $display_code;

		$custom_title = '';
		if ( isset( $share['title'] ) && is_string( $share['title'] ) ) {
			$custom_title = trim( $share['title'] );
		}

		$custom_description = '';
		if ( isset( $share['description'] ) && is_string( $share['description'] ) ) {
			$custom_description = trim( $share['description'] );
		}

		$title = '' !== $custom_title
			? $custom_title
			: sprintf(
				/* translators: 1: creator name or coupon code, 2: percent amount */
				__( '%1$s — %2$s%% off at Noviq Peptides', 'noviq-core' ),
				$label,
				(string) (int) $amount
			);

		$description = '' !== $custom_description
			? $custom_description
			: sprintf(
				/* translators: 1: coupon code, 2: percent amount */
				__( 'Use code %1$s at checkout for %2$s%% off research-grade peptide reagents. For laboratory research use only.', 'noviq-core' ),
				$display_code,
				(string) (int) $amount
			);

		$image_rel    = '';
		$custom_image = false;
		if ( isset( $share['image'] ) && is_string( $share['image'] ) && '' !== trim( $share['image'] ) ) {
			$candidate = ltrim( str_replace( '\\', '/', trim( $share['image'] ) ), '/' );
			// Block path traversal; only allow files under plugin/assets/.
			if ( ! str_contains( $candidate, '..' ) && is_readable( NOVIQ_CORE_PATH . 'assets/' . $candidate ) ) {
				$image_rel    = $candidate;
				$custom_image = true;
			}
		}

		if ( '' === $image_rel ) {
			$image_rel = self::DEFAULT_IMAGE;
		}

		$path = $via_go
			? '/go/' . rawurlencode( $code )
			: '/' . rawurlencode( $code );

		return array(
			'code'                  => $code,
			'title'                 => $title,
			'description'           => $description,
			'image_url'             => NOVIQ_CORE_URL . 'assets/' . $image_rel,
			'canonical_url'         => home_url( $path ),
			'amount'                => $amount,
			'creator_name'          => $creator,
			'has_custom_title'      => '' !== $custom_title,
			'has_custom_description'=> '' !== $custom_description,
			'has_custom_image'      => $custom_image,
		);
	}

	/**
	 * Relative path under plugin/assets/ for a share image, or empty if unset.
	 */
	public static function share_image_path( string $code ): string {
		$row = self::row( $code );
		if ( null === $row ) {
			return '';
		}

		$share = ( isset( $row['share'] ) && is_array( $row['share'] ) ) ? $row['share'] : array();
		if ( ! isset( $share['image'] ) || ! is_string( $share['image'] ) ) {
			return '';
		}

		$candidate = ltrim( str_replace( '\\', '/', trim( $share['image'] ) ), '/' );
		if ( '' === $candidate || str_contains( $candidate, '..' ) ) {
			return '';
		}

		return $candidate;
	}

	private static function load(): void {
		if ( null !== self::$rows ) {
			return;
		}

		self::$rows    = array();
		self::$by_code = array();

		if ( ! function_exists( 'wc_format_coupon_code' ) ) {
			return;
		}

		$path = NOVIQ_CORE_PATH . 'data/' . Profile::id() . '/vip-coupons.json';
		if ( ! is_readable( $path ) ) {
			return;
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $decoded ) ) {
			return;
		}

		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['code'] ) ) {
				continue;
			}
			$code = wc_format_coupon_code( (string) $row['code'] );
			if ( '' === $code ) {
				continue;
			}
			self::$rows[]           = $row;
			self::$by_code[ $code ] = $row;
		}
	}
}
