<?php
/**
 * Wholesale.
 *
 * @package Noviq_Peptides
 */

get_header();
?>
<div class="noviq-container noviq-section">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<h1><?php the_title(); ?></h1>
			<?php
			$content = get_the_content();
			if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
				echo '<p>Bulk and institutional purchasing. Terms and thresholds are pending client confirmation.</p>';
			} else {
				the_content();
			}
			?>
		<?php endwhile; ?>
	<?php else : ?>
		<h1>Wholesale</h1>
		<p>Bulk and institutional purchasing. Terms and thresholds are pending client confirmation.</p>
	<?php endif; ?>
	<p><a class="noviq-btn noviq-btn--primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></p>
</div>
<?php
get_footer();
