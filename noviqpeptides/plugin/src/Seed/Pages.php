<?php
/**
 * Idempotent page upsert.
 *
 * Matching is on the page path, so a re-run finds the page the previous run
 * created and updates it rather than creating a second one with a -2 slug.
 *
 * @package Noviq\Core\Seed
 */

declare(strict_types=1);

namespace Noviq\Core\Seed;

defined( 'ABSPATH' ) || exit;

final class Pages {

	/**
	 * Create or update a page, returning its ID.
	 *
	 * @param string $path    Full path, e.g. "policies/terms".
	 * @param string $title   Page title.
	 * @param string $content Post content.
	 * @param int    $parent  Parent page ID.
	 */
	public static function upsert(
		Seeder $seeder,
		string $path,
		string $title,
		string $content,
		int $parent = 0
	): int {
		$existing = get_page_by_path( $path );
		$slug     = basename( $path );

		$postarr = array(
			'post_type'    => 'page',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_parent'  => $parent,
		);

		if ( $existing instanceof \WP_Post ) {
			$unchanged = $existing->post_title === $title
				&& $existing->post_content === $content
				&& (int) $existing->post_parent === $parent;

			if ( $unchanged ) {
				$seeder->skipped( 'page ' . $path );

				return $existing->ID;
			}

			if ( ! $seeder->is_dry_run() ) {
				$postarr['ID'] = $existing->ID;
				wp_update_post( $postarr );
			}

			$seeder->updated( 'page /' . $path );

			return $existing->ID;
		}

		if ( $seeder->is_dry_run() ) {
			$seeder->created( 'page /' . $path );

			return 0;
		}

		$id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $id ) ) {
			$seeder->warn( sprintf( 'Could not create page %s: %s', $path, $id->get_error_message() ) );

			return 0;
		}

		$seeder->created( 'page /' . $path );

		return (int) $id;
	}
}
