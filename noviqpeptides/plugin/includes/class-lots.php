<?php
/**
 * Lot registry empty states and verify lookup.
 *
 * @package NoviqPeptides
 */

declare(strict_types=1);

namespace NoviqPeptides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Lots {

	public static function init(): void {
		add_action( 'save_post_noviq_lot', array( self::class, 'save_lot' ), 10, 2 );
	}

	public static function save_lot( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['noviq_lot_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noviq_lot_nonce'] ) ), 'noviq_lot_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = isset( $_POST['_noviq_lot_number'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_noviq_lot_number'] ) ) : '';
		if ( '' === $raw ) {
			delete_post_meta( $post_id, '_noviq_lot_number' );
		} else {
			update_post_meta( $post_id, '_noviq_lot_number', $raw );
		}
	}

	/**
	 * @return list<\WP_Post>
	 */
	public static function published_lots(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'noviq_lot',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		return is_array( $posts ) ? $posts : array();
	}

	public static function find_by_lot_number( string $lot_number ): ?\WP_Post {
		$lot_number = trim( $lot_number );
		if ( '' === $lot_number ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'      => 'noviq_lot',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => '_noviq_lot_number',
				'meta_value'     => $lot_number,
			)
		);
		return isset( $posts[0] ) ? $posts[0] : null;
	}

	public static function empty_message(): string {
		return 'No certificates are published yet. The lot registry is empty until real laboratory documents are supplied.';
	}
}
