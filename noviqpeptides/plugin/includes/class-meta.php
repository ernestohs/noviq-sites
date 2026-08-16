<?php
/**
 * Meta registration and hygiene.
 *
 * @package NoviqPeptides
 */

declare(strict_types=1);

namespace NoviqPeptides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Meta {

	public const COMPOUND_KEYS = array(
		'_noviq_cas'     => 'string',
		'_noviq_formula' => 'string',
		'_noviq_mw'      => 'numeric_string',
		'_noviq_class'   => 'string',
	);

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'add_meta_boxes', array( self::class, 'meta_boxes' ) );
		add_action( 'save_post_noviq_compound', array( self::class, 'save_compound' ), 10, 2 );
		add_action( 'save_post_product', array( self::class, 'save_product_compounds' ), 10, 2 );
		add_action( 'add_meta_boxes_product', array( self::class, 'product_meta_box' ) );
	}

	public static function register(): void {
		foreach ( self::COMPOUND_KEYS as $key => $type ) {
			register_post_meta(
				'noviq_compound',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => static function (): bool {
						return current_user_can( 'edit_posts' );
					},
					'sanitize_callback' => static function ( $value ) use ( $type ) {
						return Meta::sanitize_value( $value, $type );
					},
				)
			);
		}

		register_post_meta(
			'product',
			'_noviq_compound_ids',
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => false,
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_products' );
				},
				'sanitize_callback' => array( self::class, 'sanitize_id_list' ),
			)
		);

		register_post_meta(
			'noviq_lot',
			'_noviq_lot_number',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
	}

	/**
	 * @param mixed  $value Raw.
	 * @param string $type  string|numeric_string.
	 */
	public static function sanitize_value( $value, string $type ): string {
		if ( null === $value || '' === $value ) {
			return '';
		}
		if ( 'numeric_string' === $type ) {
			if ( function_exists( 'wc_format_decimal' ) ) {
				$normalized = wc_format_decimal( (string) $value, false, true );
				return is_string( $normalized ) ? $normalized : '';
			}
			$float = filter_var( $value, FILTER_VALIDATE_FLOAT );
			return false === $float ? '' : (string) $float;
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * @param mixed $value Raw.
	 * @return list<int>
	 */
	public static function sanitize_id_list( $value ): array {
		if ( ! is_array( $value ) ) {
			$value = array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
		}
		$ids = array();
		foreach ( $value as $item ) {
			$id = absint( $item );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	public static function meta_boxes(): void {
		add_meta_box(
			'noviq_compound_chemistry',
			'Chemistry',
			array( self::class, 'render_compound_box' ),
			'noviq_compound',
			'normal',
			'high'
		);
		add_meta_box(
			'noviq_lot_fields',
			'Lot details',
			array( self::class, 'render_lot_box' ),
			'noviq_lot',
			'normal',
			'high'
		);
	}

	public static function product_meta_box(): void {
		add_meta_box(
			'noviq_product_compounds',
			'Linked compounds',
			array( self::class, 'render_product_box' ),
			'product',
			'side',
			'default'
		);
	}

	public static function render_compound_box( \WP_Post $post ): void {
		wp_nonce_field( 'noviq_compound_save', 'noviq_compound_nonce' );
		foreach ( self::COMPOUND_KEYS as $key => $type ) {
			$label = strtoupper( str_replace( array( '_noviq_', '_' ), array( '', ' ' ), $key ) );
			$value = get_post_meta( $post->ID, $key, true );
			printf(
				'<p><label for="%1$s"><strong>%2$s</strong></label><br><input type="text" class="widefat" id="%1$s" name="%1$s" value="%3$s" /></p>',
				esc_attr( $key ),
				esc_html( $label ),
				esc_attr( is_string( $value ) ? $value : '' )
			);
		}
		echo '<p class="description">Leave blank if unsubstantiated. Blank is stored as absent, not zero.</p>';
	}

	public static function render_lot_box( \WP_Post $post ): void {
		wp_nonce_field( 'noviq_lot_save', 'noviq_lot_nonce' );
		$value = get_post_meta( $post->ID, '_noviq_lot_number', true );
		printf(
			'<p><label for="_noviq_lot_number"><strong>Lot number</strong></label><br><input type="text" class="widefat" id="_noviq_lot_number" name="_noviq_lot_number" value="%s" /></p>',
			esc_attr( is_string( $value ) ? $value : '' )
		);
		echo '<p class="description">Do not attach a certificate until a real lab document exists.</p>';
	}

	public static function render_product_box( \WP_Post $post ): void {
		wp_nonce_field( 'noviq_product_save', 'noviq_product_nonce' );
		$selected = get_post_meta( $post->ID, '_noviq_compound_ids', true );
		$selected = is_array( $selected ) ? $selected : array();
		$compounds = get_posts(
			array(
				'post_type'      => 'noviq_compound',
				'posts_per_page' => 200,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		echo '<select name="_noviq_compound_ids[]" multiple style="width:100%;min-height:120px;">';
		foreach ( $compounds as $compound ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $compound->ID,
				selected( in_array( $compound->ID, array_map( 'intval', $selected ), true ), true, false ),
				esc_html( $compound->post_title )
			);
		}
		echo '</select>';
	}

	public static function save_compound( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['noviq_compound_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noviq_compound_nonce'] ) ), 'noviq_compound_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( self::COMPOUND_KEYS as $key => $type ) {
			$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$clean = self::sanitize_value( $raw, $type );
			if ( '' === $clean ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $clean );
			}
		}
	}

	public static function save_product_compounds( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['noviq_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noviq_product_nonce'] ) ), 'noviq_product_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = isset( $_POST['_noviq_compound_ids'] ) ? wp_unslash( $_POST['_noviq_compound_ids'] ) : array();
		$ids = self::sanitize_id_list( $raw );
		if ( array() === $ids ) {
			delete_post_meta( $post_id, '_noviq_compound_ids' );
		} else {
			update_post_meta( $post_id, '_noviq_compound_ids', $ids );
		}
	}

	/**
	 * @return list<int>
	 */
	public static function product_compound_ids( int $product_id ): array {
		$ids = get_post_meta( $product_id, '_noviq_compound_ids', true );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	public static function compound_field( int $compound_id, string $key ): string {
		$value = get_post_meta( $compound_id, $key, true );
		return is_string( $value ) ? $value : '';
	}
}
