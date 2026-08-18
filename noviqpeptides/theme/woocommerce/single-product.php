<?php
/**
 * Single product wrapper.
 *
 * Exists so the product page gets the same `main#main` landmark and container
 * as every other route. Without it WooCommerce falls through to its own
 * wrapper, the skip-to-content anchor points at nothing, and the page gutter
 * disagrees with the rest of the site.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>

<main id="main" class="nq-main nq-main--product">
	<div class="nq-wrap">
		<?php woocommerce_output_all_notices(); ?>

		<?php
		while ( have_posts() ) :
			the_post();
			wc_get_template_part( 'content', 'single-product' );
		endwhile;
		?>
	</div>
</main>

<?php
get_footer( 'shop' );
