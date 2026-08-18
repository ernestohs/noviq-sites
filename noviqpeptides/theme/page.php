<?php
/**
 * Page template.
 *
 * The front page drops the heading block — its content supplies its own — while
 * every other page gets the standard eyebrow + display title header.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header();

the_post();

$nq_is_front = is_front_page();

/*
 * Cart, checkout and account are WooCommerce UI, not prose. Applying the
 * long-form measure to them clamps the whole store interface to a 46rem column
 * and leaks the prose list-bullet rule onto shipping and payment method lists.
 */
$nq_is_commerce = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() );
?>

<main id="main" class="<?php echo esc_attr( $nq_is_front ? 'nq-front' : ( $nq_is_commerce ? 'nq-main nq-main--commerce' : 'noviq-page' ) ); ?>">
	<?php if ( ! $nq_is_front ) : ?>
		<header class="nq-page-head">
			<nav class="nq-crumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'noviq-child' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'noviq-child' ); ?></a>
				<?php
				$nq_parent = wp_get_post_parent_id( get_the_ID() );
				if ( $nq_parent > 0 ) :
					?>
					<span aria-hidden="true"> / </span>
					<a href="<?php echo esc_url( (string) get_permalink( $nq_parent ) ); ?>"><?php echo esc_html( get_the_title( $nq_parent ) ); ?></a>
				<?php endif; ?>
				<span aria-hidden="true"> / </span>
				<span><?php the_title(); ?></span>
			</nav>

			<h1 class="nq-page-head__title"><?php the_title(); ?></h1>
		</header>
	<?php endif; ?>

	<div class="entry-content <?php echo esc_attr( ( $nq_is_front || $nq_is_commerce ) ? '' : 'prose-noviq' ); ?>">
		<?php the_content(); ?>
	</div>
</main>

<?php
get_footer();
