<?php
/**
 * Admin meta boxes.
 *
 * Plain metaboxes rather than an ACF dependency - the brief requires that ACF
 * Pro is not added without asking, and these fields are a flat list of scalars
 * that does not justify a page-builder-grade field framework.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Admin;

use Noviq\Core\Meta;
use Noviq\Core\PostTypes;
use Noviq\Core\Profile;

defined( 'ABSPATH' ) || exit;

final class MetaBoxes {

	private const NONCE = 'noviq_meta_nonce';

	public static function init(): void {
		add_action( 'add_meta_boxes', array( self::class, 'register' ) );
		add_action( 'save_post', array( self::class, 'save' ), 10, 2 );
	}

	public static function register(): void {
		if ( Profile::feature( 'compounds' ) ) {
			add_meta_box(
				'noviq-compound-spec',
				__( 'Analytical specification', 'noviq-core' ),
				array( self::class, 'render_compound' ),
				PostTypes::COMPOUND,
				'normal',
				'high'
			);

			add_meta_box(
				'noviq-product-compounds',
				__( 'Linked compounds', 'noviq-core' ),
				array( self::class, 'render_product' ),
				'product',
				'side',
				'default'
			);
		}

		if ( Profile::feature( 'lots' ) ) {
			add_meta_box(
				'noviq-lot-record',
				Profile::feature( 'compounds' )
					? __( 'Lot record', 'noviq-core' )
					: __( 'Accession / COA record', 'noviq-core' ),
				array( self::class, 'render_lot' ),
				PostTypes::LOT,
				'normal',
				'high'
			);
		}
	}

	/**
	 * @param array<string, array{label: string, type: string, help?: string}> $fields Field spec.
	 */
	private static function render_fields( \WP_Post $post, array $fields ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		echo '<div class="noviq-fields">';

		foreach ( $fields as $key => $field ) {
			$value = get_post_meta( $post->ID, $key, true );
			$id    = esc_attr( $key );

			printf( '<p class="noviq-field"><label for="%1$s"><strong>%2$s</strong></label><br />', $id, esc_html( $field['label'] ) );

			if ( 'textarea' === $field['type'] ) {
				printf(
					'<textarea class="widefat" rows="4" id="%1$s" name="%1$s">%2$s</textarea>',
					$id,
					esc_textarea( (string) $value )
				);
			} else {
				printf(
					'<input class="widefat" type="%1$s" id="%2$s" name="%2$s" value="%3$s" %4$s />',
					esc_attr( $field['type'] ),
					$id,
					esc_attr( (string) $value ),
					'number' === $field['type'] ? 'step="any"' : ''
				);
			}

			if ( isset( $field['help'] ) ) {
				printf( '<br /><span class="description">%s</span>', esc_html( $field['help'] ) );
			}

			echo '</p>';
		}

		echo '</div>';
	}

	public static function render_compound( \WP_Post $post ): void {
		self::render_fields(
			$post,
			array(
				'noviq_cas'           => array(
					'label' => __( 'CAS number', 'noviq-core' ),
					'type'  => 'text',
					'help'  => __( 'Leave empty if the compound has no assigned registry number. An empty field renders an em-dash, never a guess.', 'noviq-core' ),
				),
				'noviq_formula'       => array(
					'label' => __( 'Molecular formula', 'noviq-core' ),
					'type'  => 'text',
				),
				'noviq_mol_weight'    => array(
					'label' => __( 'Average molecular weight (g/mol)', 'noviq-core' ),
					'type'  => 'number',
				),
				'noviq_aa_count'      => array(
					'label' => __( 'Amino-acid count', 'noviq-core' ),
					'type'  => 'number',
				),
				'noviq_sequence'      => array(
					'label' => __( 'Primary sequence', 'noviq-core' ),
					'type'  => 'textarea',
				),
				'noviq_peptide_class' => array(
					'label' => __( 'Peptide class', 'noviq-core' ),
					'type'  => 'text',
				),
				'noviq_physical_form' => array(
					'label' => __( 'Physical form', 'noviq-core' ),
					'type'  => 'text',
				),
				'noviq_solubility'    => array(
					'label' => __( 'Solubility', 'noviq-core' ),
					'type'  => 'textarea',
				),
				'noviq_synonyms'      => array(
					'label' => __( 'Synonyms', 'noviq-core' ),
					'type'  => 'text',
					'help'  => __( 'Pipe-separated.', 'noviq-core' ),
				),
				'noviq_precis'        => array(
					'label' => __( 'Précis', 'noviq-core' ),
					'type'  => 'textarea',
					'help'  => __( 'Describe what the compound is and what it has been studied in. No therapeutic or outcome claims.', 'noviq-core' ),
				),
			)
		);
	}

	public static function render_lot( \WP_Post $post ): void {
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'A lot record publishes a laboratory result. Only enter a lot number, purity or certificate that came from the analysing lab - never a placeholder.', 'noviq-core' )
		);

		$fields = array(
			'noviq_lot_number'       => array(
				'label' => Profile::feature( 'compounds' )
					? __( 'Lot number', 'noviq-core' )
					: __( 'Accession number', 'noviq-core' ),
				'type'  => 'text',
			),
			'noviq_lot_company'      => array(
				'label' => __( 'Client company name', 'noviq-core' ),
				'type'  => 'text',
				'help'  => __( 'Used for public COA lookup by company. Leave empty on Noviq release lots.', 'noviq-core' ),
			),
			'noviq_lot_analyte'      => array(
				'label' => __( 'Analyte identified', 'noviq-core' ),
				'type'  => 'text',
			),
			'noviq_lot_lcms'         => array(
				'label' => __( 'LC-MS identity confirmed', 'noviq-core' ),
				'type'  => 'text',
				'help'  => __( 'e.g. yes / no / not tested. Empty renders as an em-dash.', 'noviq-core' ),
			),
			'noviq_lot_product_id'   => array(
				'label' => __( 'Product ID', 'noviq-core' ),
				'type'  => 'number',
			),
			'noviq_lot_variation_id' => array(
				'label' => __( 'Variation ID', 'noviq-core' ),
				'type'  => 'number',
			),
			'noviq_lot_release_date' => array(
				'label' => __( 'Release date (YYYY-MM-DD)', 'noviq-core' ),
				'type'  => 'text',
			),
			'noviq_lot_purity'       => array(
				'label' => __( 'Measured purity (%)', 'noviq-core' ),
				'type'  => 'number',
			),
			'noviq_lot_coa_id'       => array(
				'label' => __( 'COA attachment ID', 'noviq-core' ),
				'type'  => 'number',
			),
			'noviq_lot_sds_id'       => array(
				'label' => __( 'SDS attachment ID', 'noviq-core' ),
				'type'  => 'number',
			),
		);

		self::render_fields( $post, $fields );
	}

	/**
	 * Product ↔ compound is many-to-many, so this is a multi-select rather than
	 * a single parent field.
	 */
	public static function render_product( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$selected  = Meta::compound_ids( $post->ID );
		$compounds = get_posts(
			array(
				'post_type'      => PostTypes::COMPOUND,
				'posts_per_page' => 200,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( array() === $compounds ) {
			printf( '<p>%s</p>', esc_html__( 'No compounds yet.', 'noviq-core' ) );

			return;
		}

		echo '<select name="noviq_compound_ids[]" multiple size="10" class="widefat">';
		foreach ( $compounds as $compound ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $compound->ID,
				selected( in_array( $compound->ID, $selected, true ), true, false ),
				esc_html( get_the_title( $compound ) )
			);
		}
		echo '</select>';

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'A blend links several. Chemistry is read from these records, never copied onto the product.', 'noviq-core' )
		);
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$keys = match ( $post->post_type ) {
			PostTypes::COMPOUND => array_keys( Meta::COMPOUND_FIELDS ),
			PostTypes::LOT      => array_keys( Meta::LOT_FIELDS ),
			default             => array(),
		};

		foreach ( $keys as $key ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			$raw = wp_unslash( $_POST[ $key ] );
			if ( '' === trim( (string) $raw ) ) {
				delete_post_meta( $post_id, $key );
				continue;
			}

			// Registered sanitize_callback runs inside update_post_meta.
			update_post_meta( $post_id, $key, $raw );
		}

		if ( 'product' === $post->post_type && isset( $_POST['noviq_compound_ids'] ) ) {
			$ids = array_map( 'intval', (array) wp_unslash( $_POST['noviq_compound_ids'] ) );
			Meta::set_compound_ids( $post_id, $ids );
		}
	}
}
