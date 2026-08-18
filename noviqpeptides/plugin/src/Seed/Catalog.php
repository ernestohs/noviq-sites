<?php
/**
 * Catalog seeding: product categories, global attributes and products.
 *
 * @package Noviq\Core\Seed
 */

declare(strict_types=1);

namespace Noviq\Core\Seed;

use Noviq\Core\Meta;
use Noviq\Core\Profile;
use Noviq\Core\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class Catalog {

	/** @var array<string, int> Product handle → post ID. */
	private array $product_ids = array();

	public function __construct(
		private readonly Seeder $seeder,
		private readonly Library $library,
	) {}

	public function run(): void {
		$this->categories();
		$this->research_areas();
		$this->attributes();
		$this->products();
	}

	// ------------------------------------------------------------- taxonomy

	/**
	 * All nine categories are created even when the review sample leaves one
	 * empty — the category tree is the information architecture, not a
	 * by-product of which products happen to be seeded.
	 */
	private function categories(): void {
		$this->seeder->section( 'Product categories' );

		foreach ( $this->seeder->data( 'taxonomy' )['categories'] as $category ) {
			$this->upsert_term( $category, 'product_cat' );
		}
	}

	private function research_areas(): void {
		if ( ! Profile::feature( 'research_areas' ) ) {
			return;
		}

		$this->seeder->section( 'Research areas' );

		$areas = $this->seeder->data( 'taxonomy' )['research_areas'] ?? array();
		if ( ! is_array( $areas ) ) {
			return;
		}

		foreach ( $areas as $area ) {
			$this->upsert_term( $area, Taxonomies::RESEARCH_AREA );
		}
	}

	/**
	 * @param array{slug: string, name: string, description: string} $term Term data.
	 */
	private function upsert_term( array $term, string $taxonomy ): int {
		$existing = get_term_by( 'slug', $term['slug'], $taxonomy );

		if ( $existing instanceof \WP_Term ) {
			if ( $existing->name === $term['name'] && $existing->description === $term['description'] ) {
				$this->seeder->skipped( $taxonomy . ':' . $term['slug'] );

				return $existing->term_id;
			}

			if ( ! $this->seeder->is_dry_run() ) {
				wp_update_term(
					$existing->term_id,
					$taxonomy,
					array(
						'name'        => $term['name'],
						'description' => $term['description'],
					)
				);
			}

			$this->seeder->updated( $taxonomy . ' ' . $term['slug'] );

			return $existing->term_id;
		}

		if ( $this->seeder->is_dry_run() ) {
			$this->seeder->created( $taxonomy . ' ' . $term['slug'] );

			return 0;
		}

		$result = wp_insert_term(
			$term['name'],
			$taxonomy,
			array(
				'slug'        => $term['slug'],
				'description' => $term['description'],
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->seeder->warn( sprintf( 'Term %s: %s', $term['slug'], $result->get_error_message() ) );

			return 0;
		}

		$this->seeder->created( $taxonomy . ' ' . $term['slug'] );

		return (int) $result['term_id'];
	}

	// ----------------------------------------------------------- attributes

	/**
	 * Global product attributes.
	 *
	 * Vial size is global rather than per-product so "10 mg" is one term across
	 * the catalog and archives can be filtered by it.
	 *
	 * @return array<string, int> Attribute slug → attribute ID.
	 */
	private function attributes(): array {
		$this->seeder->section( 'Global attributes' );

		$wanted = array(
			'vial-size' => 'Vial size',
			'size'      => 'Size',
		);

		$ids = array();

		foreach ( $wanted as $slug => $label ) {
			$id = $this->attribute_id( $slug );

			if ( $id > 0 ) {
				$this->seeder->skipped( 'attribute ' . $slug );
			} elseif ( ! $this->seeder->is_dry_run() ) {
				$created = wc_create_attribute(
					array(
						'name'         => $label,
						'slug'         => $slug,
						'type'         => 'select',
						'order_by'     => 'menu_order',
						'has_archives' => false,
					)
				);

				if ( is_wp_error( $created ) ) {
					$this->seeder->warn( sprintf( 'Attribute %s: %s', $slug, $created->get_error_message() ) );
					continue;
				}

				$id = (int) $created;
				$this->seeder->created( 'attribute pa_' . $slug );
			}

			$ids[ $slug ] = $id;

			// wc_create_attribute registers the taxonomy on the next request;
			// this process needs it now in order to insert terms.
			$taxonomy = wc_attribute_taxonomy_name( $slug );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy(
					$taxonomy,
					array( 'product' ),
					array(
						'hierarchical' => false,
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => false,
					)
				);
			}
		}

		delete_transient( 'wc_attribute_taxonomies' );

		return $ids;
	}

	private function attribute_id( string $slug ): int {
		foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
			if ( $taxonomy->attribute_name === $slug ) {
				return (int) $taxonomy->attribute_id;
			}
		}

		return 0;
	}

	// ------------------------------------------------------------- products

	private function products(): void {
		$this->seeder->section( 'Products' );

		$attribute_ids = $this->attributes();

		foreach ( $this->seeder->data( 'products' ) as $data ) {
			$this->upsert_product( $data, $attribute_ids );
		}
	}

	/**
	 * @param array<string, mixed> $data          Product data.
	 * @param array<string, int>   $attribute_ids Attribute slug → ID.
	 */
	private function upsert_product( array $data, array $attribute_ids ): void {
		$handle = (string) $data['handle'];
		$type   = (string) $data['type'];

		$existing = get_page_by_path( $handle, OBJECT, 'product' );
		$id       = $existing instanceof \WP_Post ? $existing->ID : 0;
		$is_new   = 0 === $id;

		$this->seeder->trace( sprintf( 'product %s (%s, existing id %d)', $handle, $type, $id ) );

		if ( $this->seeder->is_dry_run() ) {
			$is_new ? $this->seeder->created( 'product ' . $handle ) : $this->seeder->skipped( 'product ' . $handle );

			return;
		}

		$product = 'variable' === $type
			? new \WC_Product_Variable( $id )
			: new \WC_Product_Simple( $id );

		$product->set_name( (string) $data['title'] );
		$product->set_slug( $handle );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_short_description( (string) $data['summary'] );
		$product->set_description( $this->long_description( $data ) );
		$product->set_featured( (bool) $data['featured'] );
		$product->set_reviews_allowed( false );
		$product->set_sold_individually( false );

		$category = get_term_by( 'slug', (string) $data['category'], 'product_cat' );
		if ( $category instanceof \WP_Term ) {
			$product->set_category_ids( array( $category->term_id ) );
		}

		if ( 'variable' === $type ) {
			// The family code, e.g. NVQ-RETA. Without it Woo prints "SKU: N/A"
			// on every variable product page.
			$product->set_sku( (string) $data['sku'] );
			$this->set_variation_attribute( $product, $data, $attribute_ids );
		} else {
			$variant = $data['variants'][0];
			$product->set_sku( (string) $variant['sku'] );
			$product->set_regular_price( $this->price( (int) $variant['price_cents'] ) );
			$this->set_display_attribute( $product, $data );
		}

		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );

		if ( ! empty( $data['virtual'] ) ) {
			$product->set_virtual( true );
		}

		$product_id = $product->save();

		if ( ! empty( $data['rank'] ) ) {
			wp_update_post(
				array(
					'ID'         => $product_id,
					'menu_order' => (int) $data['rank'],
				)
			);
		}

		$this->attach_image( $product_id, $data );

		$this->product_ids[ $handle ] = $product_id;

		if ( 'variable' === $type ) {
			$this->upsert_variations( $product_id, $data );
		}

		$this->product_meta( $product_id, $data );

		$is_new
			? $this->seeder->created( sprintf( 'product %s (%s, %d variant(s))', $handle, $type, count( $data['variants'] ) ) )
			: $this->seeder->updated( sprintf( 'product %s', $handle ) );
	}

	/**
	 * Body copy for the product page.
	 *
	 * Deliberately thin: what a compound *is* comes from the linked compound
	 * record and renders in the Specification tab. Duplicating chemistry into
	 * product copy is how the two drift apart.
	 *
	 * @param array<string, mixed> $data Product data.
	 */
	private function long_description( array $data ): string {
		$paragraphs = array( (string) $data['summary'] );

		if ( array() !== $data['bundle_of'] ) {
			$labels = array_map( static fn( array $i ): string => (string) $i['label'], $data['bundle_of'] );
			$paragraphs[] = sprintf(
				/* translators: %s: comma-separated list of bundle contents. */
				__( 'This set contains %s. Components are supplied as individually labelled vials, each with its own lot-matched Certificate of Analysis.', 'noviq-core' ),
				implode( ', ', $labels )
			);
		}

		if ( empty( $data['virtual'] ) ) {
			$paragraphs[] = __( 'Supplied as a laboratory reference material. Store as indicated on the vial label and consult the lot Certificate of Analysis for solvent guidance.', 'noviq-core' );
		}

		return implode( "\n\n", array_map( static fn( string $p ): string => '<p>' . esc_html( $p ) . '</p>', $paragraphs ) );
	}

	/**
	 * Cents to the decimal string WooCommerce stores. This is the only place
	 * money crosses out of integer arithmetic.
	 */
	private function price( int $cents ): string {
		return number_format( $cents / 100, 2, '.', '' );
	}

	/**
	 * @param array<string, mixed> $data          Product data.
	 * @param array<string, int>   $attribute_ids Attribute slug → ID.
	 */
	private function set_variation_attribute( \WC_Product_Variable $product, array $data, array $attribute_ids ): void {
		$slug = (string) $data['attribute'];
		if ( '' === $slug ) {
			return;
		}

		$taxonomy = wc_attribute_taxonomy_name( $slug );
		$term_ids = array();

		foreach ( $data['variants'] as $variant ) {
			$label = (string) $variant['label'];
			$term  = get_term_by( 'name', $label, $taxonomy );

			if ( ! $term instanceof \WP_Term ) {
				$inserted = wp_insert_term( $label, $taxonomy, array( 'slug' => sanitize_title( $label ) ) );
				if ( is_wp_error( $inserted ) ) {
					continue;
				}
				$term_ids[] = (int) $inserted['term_id'];
				continue;
			}

			$term_ids[] = $term->term_id;
		}

		$this->order_attribute_terms( $taxonomy );

		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( $attribute_ids[ $slug ] ?? 0 );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( $term_ids );
		$attribute->set_position( 0 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$product->set_attributes( array( $attribute ) );
	}

	/**
	 * Order attribute terms numerically.
	 *
	 * WooCommerce orders attribute terms by name, which sorts vial sizes as
	 * strings: 10 mg, 20 mg, 30 mg, 5 mg, 60 mg. Sizes have to read smallest to
	 * largest, so the leading number drives an explicit term order instead.
	 * Apparel sizes fall back to a written order.
	 */
	private function order_attribute_terms( string $taxonomy ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( ! is_array( $terms ) || array() === $terms ) {
			return;
		}

		$sizes = array( 'xs' => 1, 's' => 2, 'm' => 3, 'l' => 4, 'xl' => 5, '2xl' => 6, '3xl' => 7 );

		usort(
			$terms,
			static function ( \WP_Term $a, \WP_Term $b ) use ( $sizes ): int {
				$rank = static function ( string $name ) use ( $sizes ): float {
					if ( preg_match( '/[\d.]+/', $name, $m ) ) {
						return (float) $m[0];
					}

					return (float) ( $sizes[ strtolower( trim( $name ) ) ] ?? 999 );
				};

				return $rank( $a->name ) <=> $rank( $b->name );
			}
		);

		/*
		 * The meta key is plain `order`. WooCommerce's menu_order sort resolves
		 * to `meta_value_num` on that key (see wc_change_pre_get_terms), not to
		 * a per-taxonomy key.
		 */
		foreach ( $terms as $index => $term ) {
			update_term_meta( $term->term_id, 'order', $index + 1 );
		}
	}

	/**
	 * A single-variant product is a simple product; its size is a display
	 * attribute rather than a one-option variation picker.
	 *
	 * @param array<string, mixed> $data Product data.
	 */
	private function set_display_attribute( \WC_Product_Simple $product, array $data ): void {
		if ( null === $data['attribute'] || array() === $data['variants'] ) {
			return;
		}

		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'vial-size' === $data['attribute'] ? __( 'Vial size', 'noviq-core' ) : __( 'Size', 'noviq-core' ) );
		$attribute->set_options( array( (string) $data['variants'][0]['label'] ) );
		$attribute->set_position( 0 );
		$attribute->set_visible( true );
		$attribute->set_variation( false );

		$product->set_attributes( array( $attribute ) );
	}

	/**
	 * Variations are matched on SKU, which is the stable natural key — a re-run
	 * updates the price on the existing variation rather than adding a second.
	 *
	 * @param array<string, mixed> $data Product data.
	 */
	private function upsert_variations( int $product_id, array $data ): void {
		$taxonomy = wc_attribute_taxonomy_name( (string) $data['attribute'] );

		foreach ( $data['variants'] as $variant ) {
			$sku = (string) $variant['sku'];
			$existing_id = (int) wc_get_product_id_by_sku( $sku );
			$variation = new \WC_Product_Variation( $existing_id );
			$variation->set_parent_id( $product_id );
			$variation->set_sku( $sku );
			$variation->set_regular_price( $this->price( (int) $variant['price_cents'] ) );
			$variation->set_status( 'publish' );
			$variation->set_manage_stock( false );
			$variation->set_stock_status( 'instock' );
			$variation->set_attributes( array( $taxonomy => sanitize_title( (string) $variant['label'] ) ) );

			$variation_id = $variation->save();

			if ( null !== $variant['amount_mg'] ) {
				update_post_meta( $variation_id, Meta::VARIATION_AMOUNT_MG, $variant['amount_mg'] );
			}
		}

		\WC_Product_Variable::sync( $product_id );
	}

	/**
	 * @param array<string, mixed> $data Product data.
	 */
	private function product_meta( int $product_id, array $data ): void {
		update_post_meta( $product_id, Meta::PRODUCT_KICKER, (string) $data['kicker'] );

		$tiers = array() !== $data['tiers'] ? (string) wp_json_encode( $data['tiers'] ) : '';
		update_post_meta( $product_id, Meta::PRODUCT_TIERS, $tiers );

		$bundle = array() !== $data['bundle_of'] ? (string) wp_json_encode( $data['bundle_of'] ) : '';
		update_post_meta( $product_id, Meta::PRODUCT_BUNDLE_OF, $bundle );

		if ( Profile::feature( 'research_areas' ) && array() !== $data['research_areas'] ) {
			wp_set_object_terms( $product_id, $data['research_areas'], Taxonomies::RESEARCH_AREA );
		}
	}

	// --------------------------------------------------------------- linking

	/**
	 * Second pass: relations that need every product and compound to exist.
	 *
	 * Protocol add-ons use WooCommerce cross-sells rather than a bespoke
	 * module, per the brief.
	 */
	public function link(): void {
		$this->seeder->section( 'Relations' );

		if ( $this->seeder->is_dry_run() ) {
			return;
		}

		foreach ( $this->seeder->data( 'products' ) as $data ) {
			$handle     = (string) $data['handle'];
			$product_id = $this->product_ids[ $handle ] ?? 0;

			if ( 0 === $product_id ) {
				continue;
			}

			$compound_ids = array();
			foreach ( $data['compounds'] as $slug ) {
				$compound_id = $this->library->compound_id( (string) $slug );
				if ( $compound_id > 0 ) {
					$compound_ids[] = $compound_id;
				}
			}
			Meta::set_compound_ids( $product_id, $compound_ids );

			$cross_sells = array();
			foreach ( $data['addons'] ?? array() as $addon_handle ) {
				$addon_id = $this->product_ids[ (string) $addon_handle ] ?? 0;
				if ( $addon_id > 0 && $addon_id !== $product_id ) {
					$cross_sells[] = $addon_id;
				}
			}

			$product = wc_get_product( $product_id );
			if ( $product instanceof \WC_Product ) {
				$product->set_cross_sell_ids( $cross_sells );
				$product->save();
			}

			$image_id = (int) get_post_thumbnail_id( $product_id );
			if ( 1 === count( $compound_ids ) && $image_id > 0 && ! has_post_thumbnail( $compound_ids[0] ) ) {
				set_post_thumbnail( $compound_ids[0], $image_id );
			}
		}

		$this->seeder->log( '  compound links and cross-sells written' );
	}

	/**
	 * Sideload a client product PNG into the media library once, then set it
	 * as the product featured image. Generated vial art is only a fallback.
	 *
	 * @param array<string, mixed> $data Product data.
	 */
	private function attach_image( int $product_id, array $data ): void {
		$filename = isset( $data['image'] ) ? (string) $data['image'] : '';
		if ( '' === $filename ) {
			return;
		}

		$existing = (int) get_post_thumbnail_id( $product_id );
		if ( $existing > 0 && get_post_meta( $existing, '_noviq_seed_file', true ) === $filename ) {
			return;
		}

		$attachment_id = $this->sideload_seed_image( $filename );
		if ( $attachment_id <= 0 ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( $product instanceof \WC_Product ) {
			$product->set_image_id( $attachment_id );
			$product->save();
		}
	}

	private function sideload_seed_image( string $filename ): int {
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_noviq_seed_file',
				'meta_value'     => $filename,
			)
		);
		if ( array() !== $found ) {
			return (int) $found[0];
		}

		$dir = getenv( 'NOVIQ_SEED_IMAGES' );
		$dir = is_string( $dir ) && '' !== $dir ? rtrim( $dir, '/' ) : '/seed-images';
		$path = $dir . '/' . $filename;

		if ( ! is_readable( $path ) ) {
			$this->seeder->warn( sprintf( 'Missing product image: %s', $path ) );
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( $filename );
		if ( ! is_string( $tmp ) || ! copy( $path, $tmp ) ) {
			$this->seeder->warn( sprintf( 'Could not copy product image: %s', $path ) );
			return 0;
		}

		$file_array = array(
			'name'     => sanitize_file_name( strtolower( str_replace( ' ', '-', $filename ) ) ),
			'tmp_name' => $tmp,
		);

		$id = media_handle_sideload( $file_array, 0, pathinfo( $filename, PATHINFO_FILENAME ) );
		if ( is_wp_error( $id ) ) {
			if ( file_exists( $tmp ) ) {
				unlink( $tmp );
			}
			$this->seeder->warn( $id->get_error_message() );
			return 0;
		}

		update_post_meta( (int) $id, '_noviq_seed_file', $filename );
		$this->seeder->created( 'image ' . $filename );

		return (int) $id;
	}
}
