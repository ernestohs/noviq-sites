<?php
/**
 * Noviq child theme.
 *
 * Everything here is presentation. The data model, pricing rules and compliance
 * gates live in the noviq-core plugin so they survive a parent-theme swap — see
 * "Swapping the parent theme" in README.md.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

namespace Noviq\Child;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.2.0';

require_once __DIR__ . '/inc/vial-image.php';
require_once __DIR__ . '/inc/pdp-parts.php';

/**
 * Segmented variant selector on the product page.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( function_exists( 'is_product' ) && is_product() ) {
			wp_enqueue_script(
				'noviq-pdp',
				get_stylesheet_directory_uri() . '/assets/js/pdp.js',
				array( 'jquery' ),
				VERSION,
				true
			);
		}
	},
	30
);

/**
 * The description and specification sit side by side below the fold, so the
 * tab strip is removed rather than left duplicating them.
 */
add_filter( 'woocommerce_product_tabs', '__return_empty_array', 99 );


/**
 * The chrome carries the full RUO disclaimer in the footer, so the plugin's
 * own site-wide copy is stood down to avoid printing it twice. The plugin
 * keeps ownership of the contextual notices on product, cart and checkout —
 * deactivating this theme restores its footer notice.
 */
add_action(
	'wp_loaded',
	static function (): void {
		if ( class_exists( \Noviq\Core\Compliance\Ruo::class ) ) {
			remove_action( 'wp_footer', array( \Noviq\Core\Compliance\Ruo::class, 'render_footer' ), 5 );
		}
	}
);

/**
 * Mobile navigation toggle. Nine lines of vanilla JS rather than a dependency.
 */
add_action(
	'wp_footer',
	static function (): void {
		?>
		<script>
		( function () {
			var burger = document.querySelector( '.nq-burger' );
			var panel = document.getElementById( 'nq-mobile-nav' );
			if ( ! burger || ! panel ) { return; }
			burger.addEventListener( 'click', function () {
				var open = burger.getAttribute( 'aria-expanded' ) === 'true';
				burger.setAttribute( 'aria-expanded', String( ! open ) );
				panel.hidden = open;
			} );
		}() );
		</script>
		<?php
	},
	99
);

/**
 * Theme styles. The design system is one stylesheet plus the self-hosted faces.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$child = get_stylesheet_directory_uri();

		wp_enqueue_style(
			'noviq-fonts',
			$child . '/assets/css/fonts.css',
			array(),
			VERSION
		);

		wp_enqueue_style(
			'noviq-brand',
			$child . '/assets/css/noviq.css',
			array( 'noviq-fonts' ),
			VERSION
		);
	},
	20
);

/**
 * Preload the two faces used above the fold. The mono face is not preloaded —
 * it is used for figures, which are almost never the first paint.
 */
add_action(
	'wp_head',
	static function (): void {
		$dir = get_stylesheet_directory_uri() . '/assets/fonts/';

		foreach ( array( 'inter-var.woff2', 'space-grotesk-var.woff2' ) as $file ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin />' . "\n",
				esc_url( $dir . $file )
			);
		}
	},
	1
);

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/css/noviq.css' );

		// WooCommerce.
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );

		register_nav_menus(
			array(
				'primary' => __( 'Primary', 'noviq-child' ),
				'footer'  => __( 'Footer', 'noviq-child' ),
			)
		);
	}
);

/**
 * The brand signature: every number on the site is monospace and tabular.
 *
 * WooCommerce renders prices inside .woocommerce-Price-amount, so the rule is
 * applied there globally rather than being sprinkled through templates. A
 * parent theme's own price styling would otherwise win on specificity.
 */
add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'noviq';

		return $classes;
	}
);

/**
 * Woo's breadcrumb defaults are noisy for a catalog this shallow.
 */
add_filter(
	'woocommerce_breadcrumb_defaults',
	static function ( array $defaults ): array {
		$defaults['delimiter']   = ' <span aria-hidden="true">/</span> ';
		$defaults['wrap_before'] = '<nav class="woocommerce-breadcrumb noviq-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'noviq-child' ) . '">';

		return $defaults;
	}
);

/**
 * Four products per row on the shop archive, matching the reference grid.
 * The grid itself is CSS; this keeps Woo's own column class in step.
 */
add_filter( 'loop_shop_columns', static fn(): int => 4 );

/**
 * Live cart count.
 *
 * WooCommerce only enqueues `wc-cart-fragments` from its own cart *widget*,
 * which this theme does not use — so adding to cart worked but nothing on the
 * page ever changed, which reads as "the cart is broken". Enqueueing the script
 * and registering the header count as a refreshable fragment is what makes the
 * AJAX add visibly do something.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_front_page() ) ) {
			wp_enqueue_script( 'wc-cart-fragments' );
		}
	},
	25
);

/**
 * Markup for the header cart control. Shared by the header template and the
 * fragment refresh so the two can never drift apart.
 */
function cart_pill_html(): string {
	$count = ( function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart )
		? WC()->cart->get_cart_contents_count()
		: 0;

	return sprintf(
		'<a class="nq-cart" href="%1$s"><span class="nq-cart__label">%2$s</span><span class="noviq-num nq-cart__count">%3$d</span></a>',
		esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ),
		esc_html__( 'Cart', 'noviq-child' ),
		(int) $count
	);
}

/**
 * @param array<string, string> $fragments Fragments keyed by selector.
 * @return array<string, string>
 */
add_filter(
	'woocommerce_add_to_cart_fragments',
	static function ( array $fragments ): array {
		$fragments['a.nq-cart'] = cart_pill_html();

		return $fragments;
	}
);

/**
 * Use the generated vial in the cart, checkout review and mini-cart, instead of
 * WooCommerce's grey placeholder.
 *
 * @param string               $html Thumbnail markup.
 * @param array<string, mixed> $item Cart item.
 */
add_filter(
	'woocommerce_cart_item_thumbnail',
	static function ( string $html, array $item ): string {
		$product = $item['data'] ?? null;

		if ( ! $product instanceof \WC_Product || $product->get_image_id() ) {
			return $html;
		}

		// Parent carries the artwork; a variation rarely has its own image.
		$parent = $product->get_parent_id() > 0 ? wc_get_product( $product->get_parent_id() ) : $product;

		return Vial\render( $parent instanceof \WC_Product ? $parent : $product, 'woocommerce_thumbnail' );
	},
	10,
	2
);

/**
 * Drop WooCommerce's layout stylesheets.
 *
 * `woocommerce-layout` is a float-and-percentage grid from an older era: it
 * floats the gallery and summary at 48% and floats loop items, which fights
 * every modern grid this theme defines — and it ties on specificity, so it
 * wins purely by load order.
 *
 * `woocommerce-general` is kept: it styles functional furniture (form rows,
 * notices, the variation form) that is not worth reimplementing.
 *
 * @param array<string, array<string, mixed>> $styles Registered Woo styles.
 * @return array<string, array<string, mixed>>
 */
add_filter(
	'woocommerce_enqueue_styles',
	static function ( array $styles ): array {
		unset( $styles['woocommerce-layout'], $styles['woocommerce-smallscreen'] );

		return $styles;
	}
);

/**
 * Content width for the block editor and embeds.
 */
add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'align-wide' );
	}
);

/**
 * Product gallery on the single product page.
 *
 * Woo's own gallery template assumes an attachment and renders an empty column
 * when a product has no photography. Rather than override the template — which
 * puts the outcome at the mercy of template resolution — the action is swapped
 * for one that renders the generated vial plus the analytical facts strip.
 *
 * A product with a real featured image falls straight back to Woo's gallery,
 * so uploading photography restores zoom, lightbox and thumbnails untouched.
 */
add_action(
	'init',
	static function (): void {
		remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
		add_action( 'woocommerce_before_single_product_summary', __NAMESPACE__ . '\\render_product_gallery', 20 );
	},
	20
);

/**
 * Render the product gallery, or the generated stand-in.
 */
function render_product_gallery(): void {
	global $product;

	if ( ! $product instanceof \WC_Product ) {
		return;
	}

	if ( $product->get_image_id() ) {
		woocommerce_show_product_images();

		return;
	}

	$compound_ids = class_exists( \Noviq\Core\Meta::class )
		? \Noviq\Core\Meta::compound_ids( $product->get_id() )
		: array();

	$compound = ( array() !== $compound_ids && class_exists( \Noviq\Core\Content\Compound::class ) )
		? \Noviq\Core\Content\Compound::from_id( $compound_ids[0] )
		: null;

	echo '<div class="nq-gallery">';

	// Escaped inside the renderer.
	echo Vial\render( $product, 'woocommerce_single' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	if ( null !== $compound ) {
		$dash  = \Noviq\Core\Content\Compound::EM_DASH;
		$facts = array(
			array( __( 'CAS', 'noviq-child' ), $compound->display( 'noviq_cas' ) ),
			array( __( 'Formula', 'noviq-child' ), $compound->display( 'noviq_formula' ) ),
			array(
				__( 'MW', 'noviq-child' ),
				null !== $compound->field( 'noviq_mol_weight' )
					? number_format( (float) $compound->field( 'noviq_mol_weight' ), 1 ) . ' g/mol'
					: $dash,
			),
			array( __( 'Residues', 'noviq-child' ), $compound->display( 'noviq_aa_count' ) ),
		);

		echo '<dl class="nq-facts">';
		foreach ( $facts as $fact ) {
			printf(
				'<div class="nq-facts__cell"><dt class="nq-eyebrow">%1$s</dt><dd class="noviq-num">%2$s</dd></div>',
				esc_html( $fact[0] ),
				esc_html( $fact[1] )
			);
		}
		echo '</dl>';
	}

	echo '</div>';
}

add_filter(
	'loop_shop_per_page',
	static fn(): int => 24,
	20
);

/**
 * The product kicker ("Incretin analogue") under the title, from noviq-core's
 * product meta. Guarded so the theme still renders if the plugin is disabled.
 */
add_action(
	'woocommerce_single_product_summary',
	static function (): void {
		global $product;

		if ( ! $product instanceof \WC_Product || ! class_exists( \Noviq\Core\Meta::class ) ) {
			return;
		}

		$kicker = get_post_meta( $product->get_id(), \Noviq\Core\Meta::PRODUCT_KICKER, true );

		if ( is_string( $kicker ) && '' !== $kicker ) {
			printf( '<p class="noviq-kicker noviq-product__kicker">%s</p>', esc_html( $kicker ) );
		}
	},
	4
);

/**
 * Remove Woo's rating markup from loops.
 *
 * Reviews are disabled store-wide: with no real reviews, an empty star row
 * reads as a zero rating, and inventing testimonials on a research-use-only
 * listing is the most common enforcement trigger in this category.
 */
add_action(
	'init',
	static function (): void {
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
	}
);
