<?php
/**
 * Journal article.
 *
 * A narrow measure for long-form reading — the catalog is wide, the writing is
 * not.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header();

the_post();

$nq_terms = get_the_terms( get_the_ID(), 'category' );
$nq_first = is_array( $nq_terms ) && isset( $nq_terms[0] ) ? $nq_terms[0] : null;
?>

<main id="main" class="noviq-page nq-article">
	<article>
		<header class="nq-page-head">
			<nav class="nq-crumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'noviq-child' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'noviq-child' ); ?></a>
				<span aria-hidden="true"> / </span>
				<a href="<?php echo esc_url( (string) get_permalink( (int) get_option( 'page_for_posts' ) ) ); ?>"><?php esc_html_e( 'Journal', 'noviq-child' ); ?></a>
				<?php if ( $nq_first instanceof WP_Term ) : ?>
					<span aria-hidden="true"> / </span>
					<span><?php echo esc_html( $nq_first->name ); ?></span>
				<?php endif; ?>
			</nav>

			<h1 class="nq-page-head__title nq-article__title"><?php the_title(); ?></h1>

			<p class="nq-article__meta">
				<span class="noviq-num"><?php echo esc_html( get_the_date( 'Y-m-d' ) ); ?></span>
			</p>

			<?php if ( has_excerpt() ) : ?>
				<p class="nq-article__standfirst"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
		</header>

		<div class="entry-content prose-noviq nq-article__body">
			<?php the_content(); ?>
		</div>
	</article>
</main>

<?php
get_footer();
