<?php
/**
 * Research library rail.
 *
 * Prefer recent journal posts when present; otherwise fall back to fixed
 * documentation destinations with decorative imagery.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$nq_img_base = get_stylesheet_directory_uri() . '/assets/img/bg/';

$nq_cards = array();
$nq_posts = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 3,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

$nq_eyebrows = array( 'GUIDE', 'PROTOCOL', 'ARTICLE' );
$nq_fallback_imgs = array( 'lib-guide.jpg', 'lib-protocol.jpg', 'lib-article.jpg' );

if ( array() !== $nq_posts ) {
	foreach ( $nq_posts as $i => $nq_post ) {
		$nq_cards[] = array(
			'eyebrow' => $nq_eyebrows[ $i % 3 ],
			'title'   => get_the_title( $nq_post ),
			'url'     => (string) get_permalink( $nq_post ),
			'img'     => $nq_fallback_imgs[ $i % 3 ],
		);
	}
} else {
	$nq_cards = array(
		array(
			'eyebrow' => 'GUIDE',
			'title'   => __( 'Understanding Peptide Purity & Testing', 'noviq-child' ),
			'url'     => home_url( '/quality-standard/' ),
			'img'     => 'lib-guide.jpg',
		),
		array(
			'eyebrow' => 'PROTOCOL',
			'title'   => __( 'Best Practices for Peptide Storage & Handling', 'noviq-child' ),
			'url'     => home_url( '/research-hub/' ),
			'img'     => 'lib-protocol.jpg',
		),
		array(
			'eyebrow' => 'ARTICLE',
			'title'   => __( 'The Importance of COA Transparency', 'noviq-child' ),
			'url'     => home_url( '/coa/' ),
			'img'     => 'lib-article.jpg',
		),
	);
}
?>
<section class="nq-section nq-library">
	<div class="nq-wrap nq-library__inner">
		<div class="nq-library__copy">
			<p class="nq-kicker nq-kicker--blue"><?php esc_html_e( 'RESEARCH LIBRARY', 'noviq-child' ); ?></p>
			<h2><?php esc_html_e( 'Knowledge for better research', 'noviq-child' ); ?></h2>
			<p><?php esc_html_e( 'Access in-depth resources, protocols, and scientific articles to support your research journey.', 'noviq-child' ); ?></p>
			<a class="nq-btn nq-btn--outline" href="<?php echo esc_url( home_url( '/research-hub/' ) ); ?>">
				<?php esc_html_e( 'EXPLORE LIBRARY', 'noviq-child' ); ?>
				<span class="nq-btn__chev" aria-hidden="true">›</span>
			</a>
		</div>

		<div class="nq-carousel nq-library__carousel" data-nq-carousel>
			<button class="nq-carousel__btn nq-carousel__btn--prev" type="button" data-nq-carousel-prev aria-label="<?php esc_attr_e( 'Previous articles', 'noviq-child' ); ?>">
				<?php \Noviq\Child\the_icon( 'chevron-right', array( 'size' => 20, 'class' => 'nq-carousel__chev' ) ); ?>
			</button>

			<div class="nq-library__track" data-nq-carousel-track>
				<?php foreach ( $nq_cards as $nq_card ) : ?>
					<a class="nq-lib-card" href="<?php echo esc_url( $nq_card['url'] ); ?>">
						<span class="nq-lib-card__media" style="background-image: url('<?php echo esc_url( $nq_img_base . $nq_card['img'] ); ?>')"></span>
						<span class="nq-lib-card__body">
							<span class="nq-lib-card__eyebrow"><?php echo esc_html( $nq_card['eyebrow'] ); ?></span>
							<span class="nq-lib-card__title"><?php echo esc_html( $nq_card['title'] ); ?></span>
							<span class="nq-lib-card__cta">
								<?php esc_html_e( 'READ GUIDE', 'noviq-child' ); ?>
								<span aria-hidden="true">›</span>
							</span>
						</span>
					</a>
				<?php endforeach; ?>
			</div>

			<button class="nq-carousel__btn nq-carousel__btn--next" type="button" data-nq-carousel-next aria-label="<?php esc_attr_e( 'Next articles', 'noviq-child' ); ?>">
				<?php \Noviq\Child\the_icon( 'chevron-right', array( 'size' => 20, 'class' => 'nq-carousel__chev' ) ); ?>
			</button>
		</div>
	</div>
</section>
