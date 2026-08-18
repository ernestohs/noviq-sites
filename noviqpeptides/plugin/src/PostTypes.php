<?php
/**
 * Custom post types.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core;

defined( 'ABSPATH' ) || exit;

final class PostTypes {

	public const COMPOUND   = 'noviq_compound';
	public const LOT        = 'noviq_lot';
	public const COMPARISON = 'noviq_comparison';

	public static function register(): void {
		if ( Profile::feature( 'compounds' ) ) {
			self::register_compound();
		}
		if ( Profile::feature( 'lots' ) ) {
			self::register_lot();
		}
		if ( Profile::feature( 'comparisons' ) ) {
			self::register_comparison();
		}
	}

	/**
	 * One record per molecule. This is the leverage point of the site: a single
	 * compound feeds the product spec table, the /learn monograph, /compare
	 * tables, /research-hub and the JSON-LD. Chemistry is never duplicated into
	 * product meta.
	 */
	private static function register_compound(): void {
		register_post_type(
			self::COMPOUND,
			array(
				'labels'              => array(
					'name'          => __( 'Compounds', 'noviq-core' ),
					'singular_name' => __( 'Compound', 'noviq-core' ),
					'menu_name'     => __( 'Compounds', 'noviq-core' ),
					'add_new_item'  => __( 'Add compound', 'noviq-core' ),
					'edit_item'     => __( 'Edit compound', 'noviq-core' ),
					'search_items'  => __( 'Search compounds', 'noviq-core' ),
				),
				'public'              => true,
				'show_in_rest'        => true,
				'rest_base'           => 'compounds',
				'menu_icon'           => 'dashicons-analytics',
				'menu_position'       => 26,
				'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'custom-fields' ),
				'has_archive'         => 'learn',
				'rewrite'             => array(
					'slug'       => 'learn',
					'with_front' => false,
				),
				'capability_type'     => 'post',
				'exclude_from_search' => false,
				'hierarchical'        => false,
			)
		);
	}

	/**
	 * Analytical release records. Backs /coa and /verify.
	 *
	 * Seeded with ZERO rows by design — see the compliance note in README.md.
	 * Inventing a lot number, a purity figure or a COA PDF means publishing a
	 * fake laboratory record, so the surfaces that read this type ship a
	 * deliberate empty state instead.
	 */
	private static function register_lot(): void {
		register_post_type(
			self::LOT,
			array(
				'labels'              => array(
					'name'          => __( 'Lots', 'noviq-core' ),
					'singular_name' => __( 'Lot', 'noviq-core' ),
					'menu_name'     => __( 'Lots & COAs', 'noviq-core' ),
					'add_new_item'  => __( 'Add lot', 'noviq-core' ),
					'edit_item'     => __( 'Edit lot', 'noviq-core' ),
					'search_items'  => __( 'Search lots', 'noviq-core' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_rest'        => true,
				'rest_base'           => 'lots',
				'menu_icon'           => 'dashicons-media-document',
				'menu_position'       => 27,
				'supports'            => array( 'title', 'custom-fields' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'exclude_from_search' => true,
			)
		);
	}

	/**
	 * Head-to-head comparison of two or more compounds.
	 */
	private static function register_comparison(): void {
		register_post_type(
			self::COMPARISON,
			array(
				'labels'          => array(
					'name'          => __( 'Comparisons', 'noviq-core' ),
					'singular_name' => __( 'Comparison', 'noviq-core' ),
					'menu_name'     => __( 'Comparisons', 'noviq-core' ),
					'add_new_item'  => __( 'Add comparison', 'noviq-core' ),
					'edit_item'     => __( 'Edit comparison', 'noviq-core' ),
				),
				'public'          => true,
				'show_in_rest'    => true,
				'rest_base'       => 'comparisons',
				'menu_icon'       => 'dashicons-editor-table',
				'menu_position'   => 28,
				'supports'        => array( 'title', 'editor', 'excerpt', 'revisions', 'custom-fields' ),
				'has_archive'     => 'compare',
				'rewrite'         => array(
					'slug'       => 'compare',
					'with_front' => false,
				),
				'capability_type' => 'post',
				'hierarchical'    => false,
			)
		);
	}
}
