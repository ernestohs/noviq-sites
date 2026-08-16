<?php
/**
 * Product card in loops. Prefer IMAGE TBD when no thumbnail.
 *
 * @package Noviq_Peptides
 */

defined( 'ABSPATH' ) || exit;

global $product;
if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}
?>
<li <?php wc_product_class( 'noviq-card', $product ); ?>>
	<a href="<?php the_permalink(); ?>">
		<?php
		if ( has_post_thumbnail() ) {
			echo woocommerce_get_product_thumbnail(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			noviq_image_tbd();
		}
		?>
		<h2 class="woocommerce-loop-product__title"><?php the_title(); ?></h2>
	</a>
	<?php woocommerce_template_loop_price(); ?>
	<?php woocommerce_template_loop_add_to_cart(); ?>
</li>
