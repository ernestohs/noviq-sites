<?php
/**
 * Front page / blog index. Homepage content when set as front page uses front-page.php.
 *
 * @package Noviq_Peptides
 */

get_header();
?>
<div class="noviq-container noviq-section">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article <?php post_class(); ?>>
				<h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
				<?php the_excerpt(); ?>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p>No posts yet.</p>
	<?php endif; ?>
</div>
<?php
get_footer();
