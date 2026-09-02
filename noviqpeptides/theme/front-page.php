<?php
/**
 * Front page.
 *
 * Sections follow the reference storefront's order: hero, credibility band,
 * assurance row, the catalog grouped by category with jump chips, the
 * reference library, and an FAQ.
 *
 * Every figure comes from Claims. A claim the client cannot substantiate drops
 * its cell rather than rendering a placeholder number — which is why there is
 * no "researchers served" or review score here, unlike the reference.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header();

$nq_has_core = class_exists( \Noviq\Core\Claims::class );
$nq_shop     = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
$nq_shop_url = $nq_shop > 0 ? (string) get_permalink( $nq_shop ) : home_url( '/shop/' );
?>

<main id="main" class="nq-main nq-home">

	<section class="nq-hero">
		<div class="nq-wrap nq-hero__inner">
			<p class="nq-kicker"><?php esc_html_e( 'Research-grade peptides', 'noviq-child' ); ?></p>

			<h1 class="nq-hero__title">
				<?php esc_html_e( 'Supplied with the analytical package that proves it', 'noviq-child' ); ?>
			</h1>

			<p class="nq-hero__lede">
				<?php esc_html_e( 'Every lot is released against a written specification and ships with a Certificate of Analysis carrying its own lot number.', 'noviq-child' ); ?>
			</p>

			<div class="nq-hero__actions">
				<a class="nq-btn nq-btn--primary" href="<?php echo esc_url( $nq_shop_url ); ?>">
					<?php esc_html_e( 'Browse the catalog', 'noviq-child' ); ?>
				</a>
				<a class="nq-btn nq-btn--ghost" href="<?php echo esc_url( home_url( '/quality-standard/' ) ); ?>">
					<?php esc_html_e( 'Our testing standard', 'noviq-child' ); ?>
				</a>
			</div>
		</div>
	</section>

	<?php
	// Credibility band. Only substantiated figures appear.
	$nq_stats = array();

	if ( $nq_has_core ) {
		if ( \Noviq\Core\Claims::has( 'purity_spec' ) ) {
			$nq_stats[] = array(
				'value' => '≥' . \Noviq\Core\Claims::get( 'purity_spec' ) . '%',
				'label' => __( 'Chromatographic purity spec', 'noviq-child' ),
			);
		}
		if ( \Noviq\Core\Claims::has( 'endotoxin_spec' ) ) {
			$nq_stats[] = array(
				'value' => '≤' . \Noviq\Core\Claims::get( 'endotoxin_spec' ),
				'label' => __( 'EU/mg endotoxin', 'noviq-child' ),
			);
		}
		if ( \Noviq\Core\Claims::has( 'sterility_incubation_days' ) ) {
			$nq_stats[] = array(
				'value' => (string) \Noviq\Core\Claims::get( 'sterility_incubation_days' ),
				'label' => __( 'Day USP <71> incubation', 'noviq-child' ),
			);
		}
		$nq_stats[] = array(
			'value' => '100%',
			'label' => __( 'Lots released with a COA', 'noviq-child' ),
		);
	}

	if ( array() !== $nq_stats ) :
		?>
		<section class="nq-cred">
			<div class="nq-wrap nq-cred__grid">
				<?php foreach ( $nq_stats as $nq_stat ) : ?>
					<div class="nq-cred__cell">
						<p class="nq-cred__num noviq-num"><?php echo esc_html( $nq_stat['value'] ); ?></p>
						<p class="nq-cred__label"><?php echo esc_html( $nq_stat['label'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	// The catalog, grouped by category, with jump chips — the reference's
	// central section.
	$nq_cat_order = array( 'metabolic', 'peptides', 'blends' );

	$nq_cats = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'slug'       => $nq_cat_order,
		)
	);

	if ( is_array( $nq_cats ) && array() !== $nq_cats ) :
		usort(
			$nq_cats,
			static fn( WP_Term $a, WP_Term $b ): int =>
				array_search( $a->slug, $nq_cat_order, true ) <=> array_search( $b->slug, $nq_cat_order, true )
		);
		?>
		<section class="nq-section" id="catalog">
			<div class="nq-wrap">
				<div class="nq-section-head">
					<p class="nq-kicker"><?php esc_html_e( 'Shop the catalog', 'noviq-child' ); ?></p>
					<h2><?php esc_html_e( 'The research catalog', 'noviq-child' ); ?></h2>
					<p><?php esc_html_e( 'Single compounds and co-lyophilized blends, each released with lot-matched analytical documentation.', 'noviq-child' ); ?></p>
				</div>

				<div class="nq-jump">
					<?php foreach ( $nq_cats as $nq_cat ) : ?>
						<a class="nq-jump__link" href="#cat-<?php echo esc_attr( $nq_cat->slug ); ?>">
							<?php echo esc_html( $nq_cat->name ); ?>
							<span class="noviq-num"><?php echo esc_html( (string) $nq_cat->count ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>

				<?php foreach ( $nq_cats as $nq_cat ) : ?>
					<div class="nq-group" id="cat-<?php echo esc_attr( $nq_cat->slug ); ?>">
						<div class="nq-group__head">
							<h3><?php echo esc_html( $nq_cat->name ); ?></h3>
							<a class="nq-group__all" href="<?php echo esc_url( (string) get_term_link( $nq_cat ) ); ?>">
								<?php esc_html_e( 'View all', 'noviq-child' ); ?>
								<span class="noviq-num"><?php echo esc_html( (string) $nq_cat->count ); ?></span>
							</a>
						</div>

						<?php
						echo do_shortcode(
							sprintf(
								'[products category="%s" limit="8" columns="4" orderby="menu_order title"]',
								esc_attr( $nq_cat->slug )
							)
						);
						?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="nq-section nq-library">
		<div class="nq-wrap">
			<div class="nq-section-head">
				<p class="nq-kicker"><?php esc_html_e( 'Reference library', 'noviq-child' ); ?></p>
				<h2><?php esc_html_e( 'Find your compound', 'noviq-child' ); ?></h2>
				<p><?php esc_html_e( 'Molecular profile, handling and analytics for every compound in the catalog — never biological effect or outcome.', 'noviq-child' ); ?></p>
			</div>

			<div class="nq-tiles">
				<?php
				$nq_tiles = array(
					array( '01', __( 'Compound monographs', 'noviq-child' ), __( 'CAS number, formula, molecular weight, sequence and handling for each molecule.', 'noviq-child' ), '/research-hub/' ),
					array( '02', __( 'Side-by-side comparisons', 'noviq-child' ), __( 'Specification tables generated from the same compound records, so they cannot disagree.', 'noviq-child' ), '/compare/' ),
					array( '03', __( 'Verify a lot', 'noviq-child' ), __( 'Look up the lot number printed on the vial label.', 'noviq-child' ), '/verify/' ),
					array( '04', __( 'COA & SDS library', 'noviq-child' ), __( 'Certificates published per lot on release, and a lot-number lookup.', 'noviq-child' ), '/coa/' ),
					array( '05', __( 'Our testing standard', 'noviq-child' ), __( 'The specifications every lot is released against, and what each method measures.', 'noviq-child' ), '/quality-standard/' ),
					array( '06', __( 'Journal', 'noviq-child' ), __( 'Handling, storage and analytical practice at the bench.', 'noviq-child' ), '/blog/' ),
				);

				foreach ( $nq_tiles as $nq_tile ) :
					?>
					<a class="nq-tile" href="<?php echo esc_url( home_url( $nq_tile[3] ) ); ?>">
						<span class="nq-tile__n noviq-num"><?php echo esc_html( $nq_tile[0] ); ?></span>
						<h3><?php echo esc_html( $nq_tile[1] ); ?></h3>
						<p><?php echo esc_html( $nq_tile[2] ); ?></p>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="nq-section nq-faq-section">
		<div class="nq-wrap nq-faq-wrap">
			<div class="nq-section-head">
				<p class="nq-kicker"><?php esc_html_e( 'Answers', 'noviq-child' ); ?></p>
				<h2><?php esc_html_e( 'Frequently asked', 'noviq-child' ); ?></h2>
			</div>

			<div class="nq-faq">
				<?php
				$nq_faqs = array(
					array(
						__( 'What does "research use only" mean here?', 'noviq-child' ),
						$nq_has_core ? \Noviq\Core\Claims::ruo_full() : '',
					),
					array(
						__( 'Where is the Certificate of Analysis?', 'noviq-child' ),
						__( 'Certificates are published per lot at the moment the lot is released, and every vial ships with the certificate matching its own lot number. No lots have been released yet, so the library is empty — we do not publish specimen documents.', 'noviq-child' ),
					),
					array(
						__( 'How is purity measured?', 'noviq-child' ),
						__( 'Purity is measured by reversed-phase HPLC and reported as a percentage of total peak area at 214 nm; identity is confirmed by mass spectrometry. A certificate that reports a purity figure without the chromatogram behind it is a claim, not a measurement.', 'noviq-child' ),
					),
					array(
						__( 'Do you offer volume or wholesale orders?', 'noviq-child' ),
						__( 'Laboratories placing repeat or bulk orders can request a quote on the wholesale page. Preview prices use the public American Peptides catalog where a direct match exists; unmatched entries remain placeholders.', 'noviq-child' ),
					),
				);

				foreach ( $nq_faqs as $nq_faq ) :
					if ( '' === $nq_faq[1] ) {
						continue;
					}
					?>
					<details class="nq-faq__item">
						<summary><?php echo esc_html( $nq_faq[0] ); ?></summary>
						<p><?php echo esc_html( $nq_faq[1] ); ?></p>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

</main>

<?php
get_footer();
