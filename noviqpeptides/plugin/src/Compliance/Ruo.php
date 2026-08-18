<?php
/**
 * Research-use-only notices.
 *
 * The wording comes from Claims::ruo_short() / Claims::ruo_full(), which are
 * verbatim copies of the approved text in noviq/src/data/site.ts. Do not
 * paraphrase either string — the copy is the compliance control, and a
 * "clearer" rewrite is a regression.
 *
 * Placement required by the brief: every product page, the cart, and checkout.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Compliance;

use Noviq\Core\Claims;

defined( 'ABSPATH' ) || exit;

final class Ruo {

	public static function init(): void {
		// Product page — under the add-to-cart form.
		add_action( 'woocommerce_single_product_summary', array( self::class, 'render_short' ), 35 );

		// Cart and checkout. The empty cart renders cart-empty.php, which never
		// fires woocommerce_before_cart — so the notice is hooked on both paths
		// rather than disappearing exactly when the cart is empty.
		add_action( 'woocommerce_before_cart', array( self::class, 'render_short' ), 5 );
		add_action( 'woocommerce_cart_is_empty', array( self::class, 'render_short' ), 5 );
		add_action( 'woocommerce_before_checkout_form', array( self::class, 'render_short' ), 5 );

		// Full disclaimer in the footer of the commerce surfaces.
		add_action( 'woocommerce_after_cart', array( self::class, 'render_full' ), 20 );
		add_action( 'woocommerce_after_checkout_form', array( self::class, 'render_full' ), 20 );
		add_action( 'woocommerce_after_single_product', array( self::class, 'render_full' ), 20 );

		// Site-wide footer disclaimer, theme-agnostic.
		add_action( 'wp_footer', array( self::class, 'render_footer' ), 5 );
	}

	/**
	 * Short notice. Rendered as a callout with the warning token, not the
	 * brand blue, so it cannot be mistaken for marketing furniture.
	 */
	public static function render_short(): void {
		printf(
			'<div class="noviq-ruo noviq-ruo--short" role="note"><strong>%1$s</strong> <span>%2$s</span></div>',
			esc_html__( 'Research use only', 'noviq-core' ),
			esc_html( Claims::ruo_short() )
		);
	}

	/** Guards against the full disclaimer printing twice on one page. */
	private static bool $full_rendered = false;

	public static function render_full(): void {
		// A product page fires both woocommerce_after_single_product and
		// wp_footer; the disclaimer belongs on the page once, not twice.
		if ( self::$full_rendered ) {
			return;
		}
		self::$full_rendered = true;

		printf(
			'<div class="noviq-ruo noviq-ruo--full" role="note"><h2 class="noviq-ruo__heading">%1$s</h2><p>%2$s</p></div>',
			esc_html__( 'Research use only', 'noviq-core' ),
			esc_html( Claims::ruo_full() )
		);
	}

	/**
	 * Footer notice on every page, so the disclaimer is not confined to the
	 * commerce funnel.
	 */
	public static function render_footer(): void {
		if ( is_admin() || self::$full_rendered ) {
			return;
		}
		self::$full_rendered = true;

		printf(
			'<div class="noviq-ruo noviq-ruo--footer" role="contentinfo" aria-label="%1$s"><p>%2$s</p></div>',
			esc_attr__( 'Research use disclaimer', 'noviq-core' ),
			esc_html( Claims::ruo_full() )
		);
	}
}
