<?php
/**
 * Quality standard.
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
				echo '<p>Release specifications for every lot. Content is pending client confirmation.</p>';
			} else {
				the_content();
			}
			?>
		<?php endwhile; ?>
	<?php else : ?>
		<h1>Quality standard</h1>
		<p>Release specifications for every lot. Content is pending client confirmation.</p>
	<?php endif; ?>
	<?php noviq_ruo_notice(); ?>
</div>
<?php
get_footer();
