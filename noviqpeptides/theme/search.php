<?php
/**
 * Fallback template.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="noviq-page">
	<?php if ( have_posts() ) : ?>

		<?php if ( is_home() && ! is_front_page() ) : ?>
			<header class="nq-page-head">
				<p class="nq-eyebrow"><?php esc_html_e( 'Journal', 'noviq-child' ); ?></p>
				<h1 class="nq-page-head__title"><?php echo esc_html( get_the_title( (int) get_option( 'page_for_posts' ) ) ); ?></h1>
			</header>
		<?php elseif ( is_archive() || is_search() ) : ?>
			<header class="nq-page-head">
				<p class="nq-eyebrow"><?php esc_html_e( 'Archive', 'noviq-child' ); ?></p>
				<h1 class="nq-page-head__title"><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
			</header>
		<?php endif; ?>

		<ul class="nq-postlist">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<li class="nq-postlist__item">
					<a href="<?php the_permalink(); ?>">
						<span class="nq-postlist__title"><?php the_title(); ?></span>
						<span class="nq-postlist__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26 ) ); ?></span>
						<span class="noviq-num nq-postlist__date"><?php echo esc_html( get_the_date( 'Y-m-d' ) ); ?></span>
					</a>
				</li>
			<?php endwhile; ?>
		</ul>

		<div class="nq-pagination">
			<?php the_posts_pagination( array( 'mid_size' => 2 ) ); ?>
		</div>

	<?php else : ?>
		<header class="nq-page-head">
			<h1 class="nq-page-head__title"><?php esc_html_e( 'Nothing found', 'noviq-child' ); ?></h1>
		</header>
		<p class="noviq-page__lede"><?php esc_html_e( 'No entries matched. Try the catalog or the compound reference.', 'noviq-child' ); ?></p>
	<?php endif; ?>
</main>

<?php
get_footer();
