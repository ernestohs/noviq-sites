<?php
/**
 * Single product content.
 *
 * @package Noviq_Peptides
 */

defined( 'ABSPATH' ) || exit;

global $product;
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( '', $product ); ?>>
	<?php
	do_action( 'woocommerce_before_single_product_summary' );
	?>
	<div class="summary entry-summary">
		<?php do_action( 'woocommerce_single_product_summary' ); ?>
	</div>
	<?php do_action( 'woocommerce_after_single_product_summary' ); ?>
</div>
<?php
do_action( 'woocommerce_after_single_product' );
