<?php
/**
 * Popular research peptides carousel.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wc_get_products' ) ) {
	return;
}

$nq_shop     = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
$nq_shop_url = $nq_shop > 0 ? (string) get_permalink( $nq_shop ) : home_url( '/shop/' );

$nq_products = wc_get_products(
	array(
		'status'  => 'publish',
		'limit'   => 8,
		'orderby' => 'popularity',
		'order'   => 'DESC',
	)
);

if ( array() === $nq_products ) {
	$nq_products = wc_get_products(
		array(
			'status'  => 'publish',
			'limit'   => 8,
			'orderby' => 'menu_order',
			'order'   => 'ASC',
		)
	);
}

if ( array() === $nq_products ) {
	return;
}
?>
<section class="nq-section nq-popular" aria-labelledby="nq-popular-title" style="--nq-split-copy: 606px">
	<div class="nq-wrap">
		<div class="nq-section-head nq-section-head--split">
			<div>
				<p class="nq-kicker nq-kicker--blue"><?php esc_html_e( 'POPULAR RESEARCH PEPTIDES', 'noviq-child' ); ?></p>
				<h2 id="nq-popular-title"><?php esc_html_e( 'Top compounds trusted by researchers', 'noviq-child' ); ?></h2>
				<p><?php esc_html_e( 'High purity peptides for metabolic, therapeutic and biological research.', 'noviq-child' ); ?></p>
			</div>
			<a class="nq-btn nq-btn--cta" href="<?php echo esc_url( $nq_shop_url ); ?>">
				<?php esc_html_e( 'VIEW ALL PRODUCTS', 'noviq-child' ); ?>
				<?php \Noviq\Child\the_icon( 'chevron-right', array( 'size' => 18, 'class' => 'nq-btn__chev' ) ); ?>
			</a>
		</div>

		<div class="nq-carousel" data-nq-carousel>
			<button class="nq-carousel__btn nq-carousel__btn--prev" type="button" data-nq-carousel-prev aria-label="<?php esc_attr_e( 'Previous products', 'noviq-child' ); ?>">
				<?php \Noviq\Child\the_icon( 'chevron-right', array( 'size' => 20, 'class' => 'nq-carousel__chev' ) ); ?>
			</button>

			<ul class="products nq-carousel__track columns-4" data-nq-carousel-track>
				<?php
				foreach ( $nq_products as $nq_product ) {
					$GLOBALS['product'] = $nq_product;
					wc_get_template_part( 'content', 'product' );
				}
				?>
			</ul>

			<button class="nq-carousel__btn nq-carousel__btn--next" type="button" data-nq-carousel-next aria-label="<?php esc_attr_e( 'Next products', 'noviq-child' ); ?>">
				<?php \Noviq\Child\the_icon( 'chevron-right', array( 'size' => 20, 'class' => 'nq-carousel__chev' ) ); ?>
			</button>

			<div class="nq-carousel__dots" data-nq-carousel-dots role="tablist" aria-label="<?php esc_attr_e( 'Product slides', 'noviq-child' ); ?>"></div>
		</div>
	</div>
</section>
<?php
unset( $GLOBALS['product'] );
