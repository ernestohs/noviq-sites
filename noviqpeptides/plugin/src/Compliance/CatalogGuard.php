<?php
/**
 * Catalog guard: no bac water or injection consumables.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Compliance;

defined( 'ABSPATH' ) || exit;

final class CatalogGuard {

	private const BLOCKED_TERMS = array(
		'bacteriostatic',
		'bac water',
		'bacwater',
		'syringe',
		'syringes',
		'prep pad',
		'prep pads',
		'alcohol pad',
		'diluent',
	);

	public static function init(): void {
		add_filter( 'woocommerce_product_is_visible', array( self::class, 'hide_blocked' ), 10, 2 );
		add_action( 'woocommerce_before_single_product', array( self::class, 'block_single' ) );
		add_filter( 'wp_insert_post_data', array( self::class, 'block_insert' ), 10, 2 );
	}

	public static function is_blocked_text( string $text ): bool {
		$hay = strtolower( $text );
		foreach ( self::BLOCKED_TERMS as $term ) {
			if ( false !== strpos( $hay, $term ) ) {
				return true;
			}
		}
		return false;
	}

	public static function hide_blocked( bool $visible, int $product_id ): bool {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
		if ( ! $product instanceof \WC_Product ) {
			return $visible;
		}
		$blob = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();
		if ( self::is_blocked_text( $blob ) ) {
			return false;
		}
		return $visible;
	}

	public static function block_single(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$blob = $product->get_name() . ' ' . $product->get_description();
		if ( self::is_blocked_text( $blob ) ) {
			wc_add_notice( 'This product is not available in the Noviq Peptides catalog.', 'error' );
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}
	}

	/**
	 * @param array<string, mixed> $data    Sanitized data.
	 * @param array<string, mixed> $postarr Raw.
	 * @return array<string, mixed>
	 */
	public static function block_insert( array $data, array $postarr ): array {
		if ( ( $data['post_type'] ?? '' ) !== 'product' ) {
			return $data;
		}
		$blob = ( $data['post_title'] ?? '' ) . ' ' . ( $data['post_content'] ?? '' );
		if ( self::is_blocked_text( $blob ) ) {
			$data['post_status'] = 'draft';
			add_filter(
				'redirect_post_location',
				static function ( string $location ): string {
					return add_query_arg( 'noviq_catalog_blocked', '1', $location );
				}
			);
		}
		return $data;
	}
}
