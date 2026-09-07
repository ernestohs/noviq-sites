<?php
/**
 * Verify-your-lot band.
 *
 * Sample result panel only renders when a real noviq_lot exists. Fabricated
 * lot numbers from the design are never invented here.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$nq_verify_url = home_url( '/verify/' );
$nq_bg         = get_stylesheet_directory_uri() . '/assets/img/bg/verify.jpg';

$nq_sample = null;
if ( class_exists( \Noviq\Core\PostTypes::class ) ) {
	$nq_lots = get_posts(
		array(
			'post_type'      => \Noviq\Core\PostTypes::LOT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	if ( array() !== $nq_lots ) {
		$lot          = $nq_lots[0];
		$product_id   = (int) get_post_meta( $lot->ID, 'noviq_lot_product_id', true );
		$product_name = $product_id > 0 ? get_the_title( $product_id ) : $lot->post_title;
		$purity       = get_post_meta( $lot->ID, 'noviq_lot_purity', true );
		$release      = (string) get_post_meta( $lot->ID, 'noviq_lot_release_date', true );
		$lot_number   = (string) get_post_meta( $lot->ID, 'noviq_lot_number', true );

		$nq_sample = array(
			'name'   => $product_name,
			'lot'    => $lot_number,
			'purity' => is_numeric( $purity ) ? (string) $purity : '',
			'date'   => $release,
		);
	}
}
?>
<section class="nq-section nq-verify-band" style="--nq-verify-bg: url('<?php echo esc_url( $nq_bg ); ?>')">
	<div class="nq-wrap nq-verify-band__panel">
		<div class="nq-verify-band__copy">
			<p class="nq-verify-band__kicker"><?php esc_html_e( 'VERIFY YOUR LOT', 'noviq-child' ); ?></p>
			<h2><?php esc_html_e( 'Verify your product. Trust the evidence.', 'noviq-child' ); ?></h2>
			<p><?php esc_html_e( 'Enter the lot number printed on your vial to view the official Certificate of Analysis.', 'noviq-child' ); ?></p>
		</div>

		<div class="nq-verify-band__form-wrap">
			<p class="nq-verify-band__hint"><?php esc_html_e( 'Secure. Private. Results from our laboratory database.', 'noviq-child' ); ?></p>
			<form class="nq-verify-band__form" method="get" action="<?php echo esc_url( $nq_verify_url ); ?>">
				<label class="screen-reader-text" for="nq-home-lot"><?php esc_html_e( 'Lot number', 'noviq-child' ); ?></label>
				<input class="nq-verify-band__input" id="nq-home-lot" type="search" name="lot" placeholder="<?php esc_attr_e( 'Enter lot number', 'noviq-child' ); ?>" required />
				<button class="nq-btn nq-btn--cta nq-verify-band__submit" type="submit">
					<?php esc_html_e( 'Verify lot', 'noviq-child' ); ?>
					<?php \Noviq\Child\the_icon( 'shield-verify', array( 'size' => 24 ) ); ?>
				</button>
			</form>
		</div>

		<div class="nq-verify-band__result">
			<?php if ( null !== $nq_sample ) : ?>
				<div class="nq-verify-band__result-head">
					<p><?php esc_html_e( 'SAMPLE RESULT', 'noviq-child' ); ?></p>
					<p class="nq-verify-band__verified">
						<?php \Noviq\Child\the_icon( 'verified-check', array( 'size' => 14 ) ); ?>
						<?php esc_html_e( 'Verified', 'noviq-child' ); ?>
					</p>
				</div>
				<div class="nq-verify-band__result-body">
					<p><?php echo esc_html( $nq_sample['name'] ); ?></p>
					<p><?php echo esc_html( sprintf( /* translators: %s: lot number */ __( 'Lot: %s', 'noviq-child' ), $nq_sample['lot'] ) ); ?></p>
					<?php if ( '' !== $nq_sample['purity'] ) : ?>
						<p>
							<?php esc_html_e( 'Purity (HPLC)', 'noviq-child' ); ?>
							<strong class="noviq-num"><?php echo esc_html( $nq_sample['purity'] ); ?>%</strong>
						</p>
					<?php endif; ?>
					<?php if ( class_exists( \Noviq\Core\Claims::class ) && \Noviq\Core\Claims::has( 'endotoxin_spec' ) ) : ?>
						<p>
							<?php esc_html_e( 'Endotoxin', 'noviq-child' ); ?>
							<strong class="noviq-num">&lt;<?php echo esc_html( (string) \Noviq\Core\Claims::get( 'endotoxin_spec' ) ); ?> EU/mg</strong>
						</p>
					<?php endif; ?>
					<?php if ( '' !== $nq_sample['date'] ) : ?>
						<p><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Tested Date %s', 'noviq-child' ), $nq_sample['date'] ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="nq-verify-band__empty">
					<p class="nq-verify-band__result-head"><?php esc_html_e( 'LOT LOOKUP', 'noviq-child' ); ?></p>
					<p><?php esc_html_e( 'No lots have been released yet. Certificates publish here the moment a lot ships — we do not show specimen documents.', 'noviq-child' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
