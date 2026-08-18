<?php
/**
 * Product card.
 *
 * Anatomy matches the reference storefront: the whole card is one anchor, and
 * the add-to-cart is a *sibling* button rather than nested inside it — nesting
 * a button in an anchor is invalid and swallows the click.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
	return;
}

$nq_kicker = class_exists( \Noviq\Core\Meta::class )
	? (string) get_post_meta( $product->get_id(), \Noviq\Core\Meta::PRODUCT_KICKER, true )
	: '';

$nq_variants = $product->is_type( 'variable' ) ? count( $product->get_children() ) : 0;
$nq_is_range = $product->is_type( 'variable' );
?>
<li <?php wc_product_class( 'nq-card', $product ); ?>>
	<a class="nq-card__link" href="<?php echo esc_url( $product->get_permalink() ); ?>">
		<div class="nq-card__media">
			<?php
			// Escaped inside the renderer.
			echo \Noviq\Child\Vial\render( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>

		<div class="nq-card__body">
			<h3 class="nq-card__title"><?php echo esc_html( $product->get_name() ); ?></h3>

			<?php if ( '' !== $nq_kicker ) : ?>
				<p class="nq-card__sub"><?php echo esc_html( $nq_kicker ); ?></p>
			<?php endif; ?>

			<?php
			/*
			 * Ratings deliberately omitted. The reference shows stars on every
			 * card; Noviq has no reviews, and inventing them on a research-use-
			 * only listing is the compliance trip-wire flagged in the brief.
			 */
			?>

			<div class="nq-card__foot">
				<p class="nq-card__price noviq-num">
					<?php if ( $nq_is_range ) : ?>
						<small><?php esc_html_e( 'from', 'noviq-child' ); ?></small>
					<?php endif; ?>
					<?php echo wp_kses_post( wc_price( (float) $product->get_price() ) ); ?>
				</p>

				<?php if ( $nq_variants > 1 ) : ?>
					<p class="nq-card__variants">
						<?php
						printf(
							/* translators: %d: number of vial sizes. */
							esc_html( _n( '%d size', '%d sizes', $nq_variants, 'noviq-child' ) ),
							(int) $nq_variants
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</a>

	<?php
	/*
	 * A variable product cannot be added from a listing — a vial size has to be
	 * chosen first — so it links through instead of pretending to be one click.
	 */
	if ( $nq_is_range ) :
		?>
		<a class="nq-card__atc" href="<?php echo esc_url( $product->get_permalink() ); ?>">
			<?php esc_html_e( 'Select size', 'noviq-child' ); ?>
		</a>
	<?php else : ?>
		<a class="nq-card__atc ajax_add_to_cart add_to_cart_button"
			href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
			data-product_id="<?php echo esc_attr( (string) $product->get_id() ); ?>"
			data-product_sku="<?php echo esc_attr( (string) $product->get_sku() ); ?>"
			data-quantity="1"
			rel="nofollow">
			<svg viewBox="0 0 24 24" width="17" height="17" fill="none" aria-hidden="true">
				<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6" stroke="currentColor"
					stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
				<circle cx="10" cy="20" r="1.4" fill="currentColor" />
				<circle cx="18" cy="20" r="1.4" fill="currentColor" />
			</svg>
			<span><?php esc_html_e( 'Add to cart', 'noviq-child' ); ?></span>
		</a>
	<?php endif; ?>
</li>
