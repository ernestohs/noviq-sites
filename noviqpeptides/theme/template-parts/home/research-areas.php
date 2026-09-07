<?php
/**
 * Shop by research area — three category cards.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$nq_shop     = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
$nq_shop_url = $nq_shop > 0 ? (string) get_permalink( $nq_shop ) : home_url( '/shop/' );
$nq_img_base = get_stylesheet_directory_uri() . '/assets/img/bg/';

$nq_cards = array(
	array(
		'slug'  => 'metabolic',
		'title' => __( 'Metabolic Research', 'noviq-child' ),
		'copy'  => __( 'Peptides that support metabolic and weight management research.', 'noviq-child' ),
		'img'   => 'cat-metabolic.jpg',
	),
	array(
		'slug'  => 'peptides',
		'title' => __( 'Research Peptides', 'noviq-child' ),
		'copy'  => __( 'Explore a wide range of research peptides.', 'noviq-child' ),
		'img'   => 'cat-peptides.jpg',
	),
	array(
		'slug'  => 'blends',
		'title' => __( 'Blends', 'noviq-child' ),
		'copy'  => __( 'Carefully formulated peptide blends for your studies.', 'noviq-child' ),
		'img'   => 'cat-blends.jpg',
	),
);

$nq_ready = array();
foreach ( $nq_cards as $nq_card ) {
	$term = get_term_by( 'slug', $nq_card['slug'], 'product_cat' );
	if ( ! $term instanceof WP_Term ) {
		continue;
	}
	$nq_card['url'] = (string) get_term_link( $term );
	$nq_ready[]     = $nq_card;
}

if ( array() === $nq_ready ) {
	return;
}
?>
<section class="nq-section nq-areas" id="catalog" style="--nq-split-copy: 504px">
	<div class="nq-wrap">
		<div class="nq-section-head nq-section-head--split">
			<div>
				<p class="nq-kicker nq-kicker--blue"><?php esc_html_e( 'SHOP BY RESEARCH AREA', 'noviq-child' ); ?></p>
				<h2><?php esc_html_e( 'Find the compounds you need', 'noviq-child' ); ?></h2>
				<p><?php esc_html_e( 'High purity peptides for metabolic, therapeutic and biological research.', 'noviq-child' ); ?></p>
			</div>
			<a class="nq-btn nq-btn--cta" href="<?php echo esc_url( $nq_shop_url ); ?>">
				<?php esc_html_e( 'VIEW ALL PRODUCTS', 'noviq-child' ); ?>
				<?php \Noviq\Child\the_icon( 'chevron-right', array( 'size' => 18, 'class' => 'nq-btn__chev' ) ); ?>
			</a>
		</div>

		<div class="nq-areas__grid">
			<?php foreach ( $nq_ready as $nq_card ) : ?>
				<a class="nq-area" href="<?php echo esc_url( $nq_card['url'] ); ?>"
					style="--nq-area-bg: url('<?php echo esc_url( $nq_img_base . $nq_card['img'] ); ?>')">
					<span class="nq-area__title"><?php echo esc_html( $nq_card['title'] ); ?></span>
					<span class="nq-area__copy"><?php echo esc_html( $nq_card['copy'] ); ?></span>
					<span class="nq-area__cta">
						<?php \Noviq\Child\the_icon( 'arrow-browse', array( 'size' => 24 ) ); ?>
						<?php esc_html_e( 'BROWSE PRODUCTS', 'noviq-child' ); ?>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
