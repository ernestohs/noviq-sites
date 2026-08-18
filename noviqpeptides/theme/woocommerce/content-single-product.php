<?php
/**
 * Single product layout.
 *
 * Two columns with a sticky gallery, and the buying panel ordered the way the
 * reference storefront orders it. WooCommerce's own functions still render the
 * price and the add-to-cart form, so variation logic, stock and validation are
 * untouched — only the arrangement is ours.
 *
 * There are deliberately no WooCommerce tabs: the description and the
 * analytical specification sit side by side below the fold instead.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}

$nq_kicker = class_exists( \Noviq\Core\Meta::class )
	? (string) get_post_meta( $product->get_id(), \Noviq\Core\Meta::PRODUCT_KICKER, true )
	: '';

$nq_cats = get_the_terms( $product->get_id(), 'product_cat' );
$nq_cat  = ( is_array( $nq_cats ) && isset( $nq_cats[0] ) ) ? $nq_cats[0] : null;
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'nq-pdp', $product ); ?>>

	<div class="nq-pdp__gallery">
		<?php do_action( 'woocommerce_before_single_product_summary' ); ?>
	</div>

	<div class="nq-pdp__info summary entry-summary">
		<nav class="nq-crumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'noviq-child' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'noviq-child' ); ?></a>
			<span aria-hidden="true"> / </span>
			<?php if ( $nq_cat instanceof WP_Term ) : ?>
				<a href="<?php echo esc_url( (string) get_term_link( $nq_cat ) ); ?>"><?php echo esc_html( $nq_cat->name ); ?></a>
				<span aria-hidden="true"> / </span>
			<?php endif; ?>
			<span><?php the_title(); ?></span>
		</nav>

		<?php if ( '' !== $nq_kicker ) : ?>
			<p class="nq-kicker"><?php echo esc_html( $nq_kicker ); ?></p>
		<?php endif; ?>

		<h1 class="nq-pdp__title"><?php the_title(); ?></h1>

		<?php
		/*
		 * The reference puts a star rating here. Noviq has no reviews, so the
		 * slot carries the documentation badge instead — a claim we can stand
		 * behind — rather than an invented score.
		 */
		?>
		<span class="nq-badge-tested">
			<svg viewBox="0 0 24 24" width="14" height="14" fill="none" aria-hidden="true">
				<path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
			</svg>
			<?php esc_html_e( 'Lot-matched COA · Third-party tested', 'noviq-child' ); ?>
		</span>

		<div class="nq-pdp__highlights">
			<?php the_excerpt(); ?>
			<?php echo \Noviq\Child\highlight_pills(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<?php
		// Price + variation form + add to cart. Woo's own output.
		woocommerce_template_single_price();
		woocommerce_template_single_add_to_cart();

		// Volume tiers, priced from the same engine the cart charges from.
		echo \Noviq\Child\volume_box( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

		<div class="nq-purity">
			<span class="nq-purity__seal" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none">
					<path d="M12 3l7 3v6c0 4-3 7.5-7 9-4-1.5-7-5-7-9V6l7-3z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
					<path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
			</span>
			<div>
				<strong><?php esc_html_e( 'The documentation guarantee', 'noviq-child' ); ?></strong>
				<span><?php echo esc_html( \Noviq\Child\purity_sentence() ); ?></span>
			</div>
		</div>

		<?php echo \Noviq\Child\trust_list(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php do_action( 'woocommerce_product_meta_start' ); ?>
		<p class="nq-pdp__sku noviq-num">
			<?php
			printf(
				/* translators: %s: product SKU. */
				esc_html__( 'SKU %s', 'noviq-child' ),
				esc_html( $product->get_sku() ? $product->get_sku() : '—' )
			);
			?>
		</p>
	</div>
</div>

<?php echo \Noviq\Child\product_detail_split( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

<?php
// Cross-sells ("complete the protocol") and related products.
do_action( 'woocommerce_after_single_product_summary' );
