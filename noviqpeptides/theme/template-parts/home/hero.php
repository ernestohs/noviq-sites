<?php
/**
 * Homepage hero.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$nq_shop     = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
$nq_shop_url = $nq_shop > 0 ? (string) get_permalink( $nq_shop ) : home_url( '/shop/' );
$nq_bg       = get_stylesheet_directory_uri() . '/assets/img/bg/hero.jpg';
?>
<section class="nq-hero" style="--nq-hero-bg: url('<?php echo esc_url( $nq_bg ); ?>')">
	<div class="nq-wrap nq-hero__inner">
		<div class="nq-hero__copy">
			<p class="nq-hero__kicker"><?php esc_html_e( 'RESEARCH-GRADE PEPTIDES', 'noviq-child' ); ?></p>
			<h1 class="nq-hero__title"><?php esc_html_e( 'Proven purity. Verifiable science.', 'noviq-child' ); ?></h1>
			<p class="nq-hero__lede">
				<?php esc_html_e( 'Every lot is independently tested and released with a complete Certificate of Analysis so you can focus on your research with confidence.', 'noviq-child' ); ?>
			</p>
			<div class="nq-hero__actions">
				<a class="nq-btn nq-btn--cta" href="<?php echo esc_url( $nq_shop_url ); ?>">
					<?php esc_html_e( 'SHOP CATALOG', 'noviq-child' ); ?>
					<span class="nq-btn__chev" aria-hidden="true">›</span>
				</a>
				<a class="nq-btn nq-btn--ghost-light" href="<?php echo esc_url( home_url( '/verify/' ) ); ?>">
					<?php esc_html_e( 'VERIFY A LOT', 'noviq-child' ); ?>
					<?php \Noviq\Child\the_icon( 'shield-verify', array( 'size' => 24, 'class' => 'nq-btn__icon' ) ); ?>
				</a>
			</div>
		</div>
	</div>
</section>
