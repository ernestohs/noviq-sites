<?php
/**
 * Single product.
 *
 * @package Noviq_Peptides
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>
<div class="noviq-container noviq-section">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<?php wc_get_template_part( 'content', 'single-product' ); ?>
	<?php endwhile; ?>
</div>
<?php
get_footer( 'shop' );
