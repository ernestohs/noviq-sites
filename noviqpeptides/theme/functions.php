<?php
/**
 * Theme functions.
 *
 * @package Noviq_Peptides
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
		);
		add_theme_support( 'woocommerce' );
		register_nav_menus(
			array(
				'primary' => 'Primary',
				'footer'  => 'Footer',
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style(
			'noviq-peptides',
			get_stylesheet_uri(),
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}
);

/**
 * IMAGE TBD placeholder markup.
 */
function noviq_image_tbd( string $label = 'IMAGE TBD' ): void {
	printf(
		'<div class="noviq-image-tbd" role="img" aria-label="%1$s"><span>%1$s</span></div>',
		esc_html( $label )
	);
}

/**
 * Print RUO from plugin when available.
 */
function noviq_ruo_notice(): void {
	if ( class_exists( '\\NoviqPeptides\\RUO' ) ) {
		\NoviqPeptides\RUO::render();
		return;
	}
	echo '<aside class="noviq-ruo-notice" role="note"><p>Research Use Only. Products are intended for laboratory research purposes.</p></aside>';
}

/**
 * Default primary menu fallback.
 */
function noviq_primary_menu_fallback(): void {
	$items = array(
		'/shop/'             => 'Catalog',
		'/coa/'              => 'Documentation',
		'/research-hub/'     => 'Learn',
		'/quality-standard/' => 'Standard',
		'/wholesale/'        => 'Wholesale',
		'/blog/'             => 'Journal',
	);
	echo '<ul>';
	foreach ( $items as $url => $label ) {
		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( home_url( $url ) ),
			esc_html( $label )
		);
	}
	echo '</ul>';
}

add_filter(
	'woocommerce_enqueue_styles',
	static function ( $styles ) {
		return $styles;
	}
);

// Product documentation / empty lot panel on single product.
add_action(
	'woocommerce_after_single_product_summary',
	static function (): void {
		echo '<section class="noviq-section noviq-container"><h2>Documentation</h2>';
		if ( class_exists( '\\NoviqPeptides\\Lots' ) && array() === \NoviqPeptides\Lots::published_lots() ) {
			echo '<p class="noviq-empty">' . esc_html( \NoviqPeptides\Lots::empty_message() ) . '</p>';
		} else {
			echo '<p class="noviq-empty">Lot documents appear here when published.</p>';
		}
		echo '</section>';
	},
	15
);

// Compound specification on product.
add_action(
	'woocommerce_single_product_summary',
	static function (): void {
		if ( ! class_exists( '\\NoviqPeptides\\Meta' ) || ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		$product = wc_get_product( get_the_ID() );
		if ( ! $product ) {
			return;
		}
		$ids = \NoviqPeptides\Meta::product_compound_ids( $product->get_id() );
		if ( array() === $ids ) {
			return;
		}
		echo '<div class="noviq-compound-spec"><h3>Specification</h3>';
		foreach ( $ids as $compound_id ) {
			$title = get_the_title( $compound_id );
			$cas   = \NoviqPeptides\Meta::compound_field( $compound_id, '_noviq_cas' );
			$formula = \NoviqPeptides\Meta::compound_field( $compound_id, '_noviq_formula' );
			$mw    = \NoviqPeptides\Meta::compound_field( $compound_id, '_noviq_mw' );
			$class = \NoviqPeptides\Meta::compound_field( $compound_id, '_noviq_class' );
			echo '<h4>' . esc_html( $title ) . '</h4><dl>';
			if ( '' !== $cas ) {
				echo '<dt>CAS</dt><dd>' . esc_html( $cas ) . '</dd>';
			}
			if ( '' !== $formula ) {
				echo '<dt>Formula</dt><dd>' . esc_html( $formula ) . '</dd>';
			}
			if ( '' !== $mw ) {
				echo '<dt>MW</dt><dd>' . esc_html( $mw ) . '</dd>';
			}
			if ( '' !== $class ) {
				echo '<dt>Class</dt><dd>' . esc_html( $class ) . '</dd>';
			}
			echo '</dl>';
			printf(
				'<p><a href="%s">Compound reference</a></p>',
				esc_url( get_permalink( $compound_id ) )
			);
		}
		echo '</div>';
	},
	25
);

add_action(
	'woocommerce_before_single_product',
	static function (): void {
		noviq_ruo_notice();
	},
	5
);

add_action(
	'woocommerce_before_cart',
	static function (): void {
		noviq_ruo_notice();
	},
	5
);

add_action(
	'woocommerce_before_checkout_form',
	static function (): void {
		noviq_ruo_notice();
	},
	5
);

// Empty cart still needs RUO (cart hook may not fire content).
add_action(
	'woocommerce_cart_is_empty',
	static function (): void {
		noviq_ruo_notice();
	},
	5
);
