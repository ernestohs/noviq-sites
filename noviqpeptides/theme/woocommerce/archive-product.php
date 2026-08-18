<?php
/**
 * Product archive — shop and category listings.
 *
 * Replaces the parent theme's archive so the header block matches the
 * reference: breadcrumb, oversized display title, lede from the category
 * description, and a right-aligned stat pair, then a hairline rule above the
 * grid.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

$nq_term  = is_tax( 'product_cat' ) ? get_queried_object() : null;
$nq_title = $nq_term instanceof WP_Term ? $nq_term->name : __( 'Full catalog', 'noviq-child' );
$nq_lede  = $nq_term instanceof WP_Term ? $nq_term->description : '';
$nq_count = $nq_term instanceof WP_Term ? (int) $nq_term->count : (int) wp_count_posts( 'product' )->publish;

if ( '' === $nq_lede && ! $nq_term instanceof WP_Term ) {
	$nq_lede = __( 'Every compound and blend Noviq stocks, each with lot-matched analytical documentation.', 'noviq-child' );
}

$nq_coa = get_page_by_path( 'coa' );
?>

<main id="main" class="nq-main nq-archive">
	<div class="nq-wrap">

	<header class="nq-coll-head">
		<nav class="nq-crumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'noviq-child' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'noviq-child' ); ?></a>
			<span aria-hidden="true"> / </span>
			<?php if ( $nq_term instanceof WP_Term ) : ?>
				<a href="<?php echo esc_url( (string) get_permalink( wc_get_page_id( 'shop' ) ) ); ?>"><?php esc_html_e( 'Catalog', 'noviq-child' ); ?></a>
				<span aria-hidden="true"> / </span>
			<?php endif; ?>
			<span><?php echo esc_html( $nq_title ); ?></span>
		</nav>

		<div class="nq-coll-head__row">
			<div>
				<h1 class="nq-coll-head__title"><?php echo esc_html( $nq_title ); ?></h1>
				<?php if ( '' !== $nq_lede ) : ?>
					<p class="nq-coll-head__lede"><?php echo esc_html( wp_strip_all_tags( $nq_lede ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="nq-coll-head__stats">
				<div class="nq-stat">
					<p class="nq-eyebrow"><?php esc_html_e( 'Products', 'noviq-child' ); ?></p>
					<p class="nq-stat__value"><?php echo esc_html( (string) $nq_count ); ?></p>
				</div>

				<?php if ( $nq_coa instanceof WP_Post ) : ?>
					<div class="nq-stat">
						<p class="nq-eyebrow"><?php esc_html_e( 'Documentation', 'noviq-child' ); ?></p>
						<p class="nq-stat__link">
							<a href="<?php echo esc_url( (string) get_permalink( $nq_coa ) ); ?>"><?php esc_html_e( 'COA per lot', 'noviq-child' ); ?></a>
						</p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<hr class="nq-coll-head__rule" />
	</header>

	<div class="nq-archive__body">
		<?php woocommerce_output_all_notices(); ?>

		<?php if ( woocommerce_product_loop() ) : ?>

			<?php
			woocommerce_product_loop_start();

			if ( wc_get_loop_prop( 'total' ) ) {
				while ( have_posts() ) {
					the_post();
					wc_get_template_part( 'content', 'product' );
				}
			}

			woocommerce_product_loop_end();
			?>

			<div class="nq-archive__pagination">
				<?php do_action( 'woocommerce_after_shop_loop' ); ?>
			</div>

		<?php else : ?>
			<p class="noviq-page__lede"><?php esc_html_e( 'No products in this category yet.', 'noviq-child' ); ?></p>
		<?php endif; ?>
	</div>

	</div>
</main>

<?php
get_footer( 'shop' );
