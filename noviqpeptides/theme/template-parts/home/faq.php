<?php
/**
 * Homepage FAQ accordion.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$nq_has_core = class_exists( \Noviq\Core\Claims::class );
$nq_bg       = get_stylesheet_directory_uri() . '/assets/img/bg/faq.jpg';

$nq_purity_answer = __( 'Purity is measured by reversed-phase HPLC and reported as a percentage of total peak area at 214 nm; identity is confirmed by mass spectrometry. A certificate that reports a purity figure without the chromatogram behind it is a claim, not a measurement.', 'noviq-child' );
if ( $nq_has_core && \Noviq\Core\Claims::has( 'purity_spec' ) ) {
	$nq_purity_answer = sprintf(
		/* translators: %s: purity spec percent. */
		__( 'Every lot is released against a ≥%s%% chromatographic purity specification, measured by reversed-phase HPLC with identity confirmed by mass spectrometry. The chromatogram ships with the Certificate of Analysis for that lot.', 'noviq-child' ),
		\Noviq\Core\Claims::get( 'purity_spec' )
	);
}

$nq_faqs = array(
	array(
		__( 'What purity level are your peptides and how is it verified?', 'noviq-child' ),
		$nq_purity_answer,
	),
	array(
		__( 'What is a Certificate of Analysis (CoA) and how do I read it?', 'noviq-child' ),
		__( 'A Certificate of Analysis is the primary analytical record for one specific lot: identity, purity, and any safety attributes measured at release. Certificates are published per lot at release and match the lot number printed on the vial. No lots have been released yet, so the library is empty — we do not publish specimen documents.', 'noviq-child' ),
	),
	array(
		__( 'How should I store the lyophilized product?', 'noviq-child' ),
		__( 'Store lyophilized peptides cold, dry, and protected from light, typically 2°C to 8°C for short-term holding or −20°C for longer storage. Follow the handling notes on the product page and compound monograph. Never invent storage conditions that conflict with the COA for that lot.', 'noviq-child' ),
	),
	array(
		__( 'How long is the lyophilized product stable?', 'noviq-child' ),
		__( 'Stability depends on the compound, packaging, and storage conditions. The Certificate of Analysis and product documentation for each lot state the release date and any stated shelf window. Do not rely on a general claim in place of the lot record.', 'noviq-child' ),
	),
	array(
		__( 'Are these peptides for human use?', 'noviq-child' ),
		$nq_has_core ? \Noviq\Core\Claims::ruo_full() : '',
	),
	array(
		__( 'What is Noviq Peptides and why should I trust you?', 'noviq-child' ),
		__( 'Noviq Peptides supplies research-grade peptides with lot-matched analytical documentation. Every quantitative claim on this site is gated behind evidence the client can substantiate; figures we cannot back are omitted rather than invented.', 'noviq-child' ),
	),
);
?>
<section class="nq-section nq-faq-section" style="--nq-faq-bg: url('<?php echo esc_url( $nq_bg ); ?>')">
	<div class="nq-wrap nq-faq-wrap">
		<div class="nq-faq-card">
			<header class="nq-faq-card__head">
				<h2><?php esc_html_e( 'Frequently Asked Questions', 'noviq-child' ); ?></h2>
				<p class="nq-faq-card__lede"><?php esc_html_e( 'Everything you need to know about peptide research', 'noviq-child' ); ?></p>
			</header>

			<div class="nq-faq">
				<?php
				foreach ( $nq_faqs as $nq_faq ) :
					if ( '' === $nq_faq[1] ) {
						continue;
					}
					?>
					<details class="nq-faq__item">
						<summary>
							<span><?php echo esc_html( $nq_faq[0] ); ?></span>
							<span class="nq-faq__chev" aria-hidden="true">
								<?php \Noviq\Child\the_icon( 'chevron-faq', array( 'size' => 16 ) ); ?>
							</span>
						</summary>
						<p><?php echo esc_html( $nq_faq[1] ); ?></p>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
