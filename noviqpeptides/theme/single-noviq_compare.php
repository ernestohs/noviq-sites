<?php
/**
 * Single comparison (/compare/{slug}).
 *
 * @package Noviq_Peptides
 */

get_header();
?>
<div class="noviq-container noviq-section">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<article <?php post_class(); ?>>
			<h1><?php the_title(); ?></h1>
			<?php the_content(); ?>
			<?php noviq_ruo_notice(); ?>
		</article>
	<?php endwhile; ?>
</div>
<?php
get_footer();
