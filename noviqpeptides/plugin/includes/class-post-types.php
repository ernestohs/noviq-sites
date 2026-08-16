<?php
/**
 * Custom post types and taxonomies.
 *
 * @package NoviqPeptides
 */

declare(strict_types=1);

namespace NoviqPeptides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Post_Types {

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	public static function register(): void {
		register_post_type(
			'noviq_compound',
			array(
				'labels'              => array(
					'name'          => 'Compounds',
					'singular_name' => 'Compound',
					'add_new_item'  => 'Add Compound',
					'edit_item'     => 'Edit Compound',
				),
				'public'              => true,
				'has_archive'         => false,
				'rewrite'             => array( 'slug' => 'learn', 'with_front' => false ),
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-science',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			)
		);

		register_post_type(
			'noviq_lot',
			array(
				'labels'              => array(
					'name'          => 'Lots',
					'singular_name' => 'Lot',
					'add_new_item'  => 'Add Lot',
					'edit_item'     => 'Edit Lot',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-media-document',
				'supports'            => array( 'title', 'custom-fields' ),
			)
		);

		register_post_type(
			'noviq_compare',
			array(
				'labels'              => array(
					'name'          => 'Comparisons',
					'singular_name' => 'Comparison',
					'add_new_item'  => 'Add Comparison',
					'edit_item'     => 'Edit Comparison',
				),
				'public'              => true,
				'has_archive'         => false,
				'rewrite'             => array( 'slug' => 'compare', 'with_front' => false ),
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-image-flip-horizontal',
				'supports'            => array( 'title', 'editor', 'custom-fields' ),
			)
		);

		register_taxonomy(
			'noviq_research',
			array( 'noviq_compound', 'product' ),
			array(
				'labels'            => array(
					'name'          => 'Research Areas',
					'singular_name' => 'Research Area',
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'research-area' ),
			)
		);
	}
}
