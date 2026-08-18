<?php
/**
 * Registered post meta.
 *
 * Every field is registered with show_in_rest, an explicit sanitize_callback
 * and an explicit auth_callback — nothing relies on the permissive defaults.
 *
 * Values the client cannot state with confidence are stored empty and rendered
 * as an em-dash by the templates, never as a guessed figure.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core;

defined( 'ABSPATH' ) || exit;

final class Meta {

	/** Compound chemistry. Prefix is public (no underscore) so it reaches REST. */
	public const COMPOUND_FIELDS = array(
		'noviq_cas'            => 'string',
		'noviq_formula'        => 'string',
		'noviq_mol_weight'     => 'number',
		'noviq_aa_count'       => 'integer',
		'noviq_sequence'       => 'string',
		'noviq_peptide_class'  => 'string',
		'noviq_physical_form'  => 'string',
		'noviq_solubility'     => 'string',
		'noviq_precis'         => 'string',
		'noviq_synonyms'       => 'string',
	);

	public const LOT_FIELDS = array(
		'noviq_lot_number'       => 'string',
		'noviq_lot_product_id'   => 'integer',
		'noviq_lot_variation_id' => 'integer',
		'noviq_lot_release_date' => 'string',
		'noviq_lot_purity'       => 'number',
		'noviq_lot_coa_id'       => 'integer',
		'noviq_lot_sds_id'       => 'integer',
		/** Accession registry fields (March Analytics). Unused on Noviq lots. */
		'noviq_lot_company'      => 'string',
		'noviq_lot_analyte'      => 'string',
		'noviq_lot_lcms'         => 'string',
	);

	/** Product ↔ compound is many-to-many: one meta row per linked compound. */
	public const PRODUCT_COMPOUND = 'noviq_compound_id';

	/** Short type label under the product title, e.g. "Research peptide". */
	public const PRODUCT_KICKER = 'noviq_kicker';

	/** Peptide mass per vial on a variation; drives the calculator hand-off. */
	public const VARIATION_AMOUNT_MG = 'noviq_amount_mg';

	/** Display-only bundle contents, stored as JSON. */
	public const PRODUCT_BUNDLE_OF = 'noviq_bundle_of';

	/**
	 * Volume-break table for this product, stored as JSON. Empty means the
	 * product does not take volume breaks (bundles and apparel do not).
	 */
	public const PRODUCT_TIERS = 'noviq_volume_tiers';

	/** Compound slugs a comparison covers: one meta row per compound. */
	public const COMPARISON_COMPOUND = 'noviq_comparison_compound_id';

	public static function register(): void {
		if ( Profile::feature( 'compounds' ) ) {
			self::register_compound_meta();
		}
		if ( Profile::feature( 'lots' ) ) {
			self::register_lot_meta();
		}
		if ( Profile::feature( 'comparisons' ) ) {
			self::register_comparison_meta();
		}
		self::register_product_meta();
	}

	/**
	 * Only users who can edit the object may write these fields.
	 */
	public static function auth_edit_post( bool $allowed, string $meta_key, int $post_id ): bool {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Numeric meta is sanitized to a normalized *string*, never to a PHP float.
	 *
	 * WooCommerce filters `update_post_metadata` and, when it sees a float,
	 * rewrites it with wc_float_to_string() and calls update_metadata() again.
	 * A sanitize_callback that casts back to float turns that single correction
	 * into unbounded mutual recursion: Woo makes it a string, this callback
	 * makes it a float, Woo makes it a string… The process spins until it is
	 * killed. Returning a string terminates on Woo's first pass.
	 *
	 * The registered REST `type` stays `number` — WordPress coerces the stored
	 * numeric string back to a number against the schema on output.
	 */
	private static function sanitizer( string $type ): callable {
		return match ( $type ) {
			'integer' => static fn( $value ): string => (string) (int) $value,
			'number'  => static fn( $value ): string => self::float_to_string( (float) $value ),
			default   => static fn( $value ): string => sanitize_text_field( (string) $value ),
		};
	}

	/**
	 * Decimal string for a float, without scientific notation or trailing
	 * zeroes. Mirrors wc_float_to_string() so values written by this plugin and
	 * by WooCommerce are byte-identical, but does not depend on Woo being
	 * loaded — this plugin also registers meta on non-Woo post types.
	 */
	private static function float_to_string( float $value ): string {
		$string = wp_strip_all_tags( sprintf( '%.10F', $value ) );

		return rtrim( rtrim( $string, '0' ), '.' ) ?: '0';
	}

	private static function register_compound_meta(): void {
		foreach ( self::COMPOUND_FIELDS as $key => $type ) {
			register_post_meta(
				PostTypes::COMPOUND,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'noviq_precis' === $key
						? static fn( $value ): string => wp_kses_post( (string) $value )
						: self::sanitizer( $type ),
					'auth_callback'     => array( self::class, 'auth_edit_post' ),
				)
			);
		}
	}

	private static function register_lot_meta(): void {
		foreach ( self::LOT_FIELDS as $key => $type ) {
			register_post_meta(
				PostTypes::LOT,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => self::sanitizer( $type ),
					'auth_callback'     => array( self::class, 'auth_edit_post' ),
				)
			);
		}
	}

	private static function register_comparison_meta(): void {
		register_post_meta(
			PostTypes::COMPARISON,
			self::COMPARISON_COMPOUND,
			array(
				'type'              => 'integer',
				'single'            => false,
				'show_in_rest'      => true,
				'sanitize_callback' => self::sanitizer( 'integer' ),
				'auth_callback'     => array( self::class, 'auth_edit_post' ),
			)
		);
	}

	private static function register_product_meta(): void {
		register_post_meta(
			'product',
			self::PRODUCT_COMPOUND,
			array(
				'type'              => 'integer',
				'single'            => false,
				'show_in_rest'      => true,
				'sanitize_callback' => self::sanitizer( 'integer' ),
				'auth_callback'     => array( self::class, 'auth_edit_post' ),
			)
		);

		register_post_meta(
			'product',
			self::PRODUCT_KICKER,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( self::class, 'auth_edit_post' ),
			)
		);

		register_post_meta(
			'product',
			self::PRODUCT_BUNDLE_OF,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => static function ( $value ): string {
					$decoded = json_decode( (string) $value, true );

					return is_array( $decoded ) ? wp_json_encode( $decoded ) : '';
				},
				'auth_callback'     => array( self::class, 'auth_edit_post' ),
			)
		);

		register_post_meta(
			'product',
			self::PRODUCT_TIERS,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => static function ( $value ): string {
					$decoded = json_decode( (string) $value, true );

					return is_array( $decoded ) ? wp_json_encode( $decoded ) : '';
				},
				'auth_callback'     => array( self::class, 'auth_edit_post' ),
			)
		);

		register_post_meta(
			'product_variation',
			self::VARIATION_AMOUNT_MG,
			array(
				'type'              => 'number',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => self::sanitizer( 'number' ),
				'auth_callback'     => array( self::class, 'auth_edit_post' ),
			)
		);
	}

	/**
	 * Compound post IDs linked to a product.
	 *
	 * @return int[]
	 */
	public static function compound_ids( int $product_id ): array {
		$ids = get_post_meta( $product_id, self::PRODUCT_COMPOUND, false );

		return array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
	}

	/**
	 * Replace the product ↔ compound links. Idempotent: re-running the seeder
	 * with the same data produces the same rows.
	 *
	 * @param int[] $compound_ids Compound post IDs.
	 */
	public static function set_compound_ids( int $product_id, array $compound_ids ): void {
		delete_post_meta( $product_id, self::PRODUCT_COMPOUND );
		foreach ( array_unique( array_map( 'intval', $compound_ids ) ) as $id ) {
			if ( $id > 0 ) {
				add_post_meta( $product_id, self::PRODUCT_COMPOUND, $id );
			}
		}
	}

	/**
	 * Products that contain a given compound. Powers the monograph's
	 * "available as" panel without duplicating the catalog.
	 *
	 * @return int[]
	 */
	public static function products_for_compound( int $compound_id ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => 50,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => self::PRODUCT_COMPOUND,
						'value' => $compound_id,
					),
				),
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Compound post IDs a comparison covers, in stored order.
	 *
	 * @return int[]
	 */
	public static function comparison_compound_ids( int $comparison_id ): array {
		$ids = get_post_meta( $comparison_id, self::COMPARISON_COMPOUND, false );

		return array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
	}
}
