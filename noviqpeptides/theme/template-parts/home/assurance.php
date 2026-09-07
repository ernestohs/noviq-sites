<?php
/**
 * Homepage assurance / credibility band.
 *
 * Only substantiated claim cells render. Unsubstantiated keys stay null in the
 * profile and drop their cell rather than inventing a number.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( \Noviq\Core\Claims::class ) ) {
	return;
}

/** @var array<int, array{icon: string, title: string, label: string, sub: string}> $nq_cells */
$nq_cells = array();

if ( \Noviq\Core\Claims::has( 'purity_spec' ) ) {
	$nq_cells[] = array(
		'icon'  => 'purity-tube',
		'title' => '≥ ' . \Noviq\Core\Claims::get( 'purity_spec' ) . '%',
		'label' => __( 'Chromatographic Purity', 'noviq-child' ),
		'sub'   => __( 'Every lot verified', 'noviq-child' ),
	);
}

if ( \Noviq\Core\Claims::has( 'third_party_tested' ) ) {
	$nq_cells[] = array(
		'icon'  => 'shield-check',
		'title' => __( 'Third-Party', 'noviq-child' ),
		'label' => __( 'Independently Tested', 'noviq-child' ),
		'sub'   => __( 'Accredited laboratories', 'noviq-child' ),
	);
}

if ( \Noviq\Core\Claims::has( 'endotoxin_spec' ) ) {
	$nq_cells[] = array(
		'icon'  => 'endotoxin-tube',
		'title' => '≤ ' . \Noviq\Core\Claims::get( 'endotoxin_spec' ) . ' EU/mg',
		'label' => __( 'Endotoxin Level', 'noviq-child' ),
		'sub'   => __( 'Rigorously tested', 'noviq-child' ),
	);
}

if ( \Noviq\Core\Claims::has( 'made_in_usa' ) || \Noviq\Core\Claims::has( 'cgmp_compliant' ) ) {
	$nq_cells[] = array(
		'icon'  => 'usa-flag',
		'title' => \Noviq\Core\Claims::has( 'made_in_usa' ) ? __( 'Made in USA', 'noviq-child' ) : __( 'cGMP Compliant', 'noviq-child' ),
		'label' => \Noviq\Core\Claims::has( 'cgmp_compliant' ) ? __( 'cGMP Compliant', 'noviq-child' ) : __( 'Domestic supply', 'noviq-child' ),
		'sub'   => __( 'Quality you can trust', 'noviq-child' ),
	);
}

if ( array() === $nq_cells ) {
	return;
}
?>
<section class="nq-assurance" aria-label="<?php esc_attr_e( 'Quality assurances', 'noviq-child' ); ?>">
	<div class="nq-wrap nq-assurance__grid">
		<?php foreach ( $nq_cells as $i => $nq_cell ) : ?>
			<?php if ( $i > 0 ) : ?>
				<span class="nq-assurance__rule" aria-hidden="true"></span>
			<?php endif; ?>
			<div class="nq-assurance__cell">
				<span class="nq-assurance__icon">
					<?php \Noviq\Child\the_icon( $nq_cell['icon'], array( 'size' => 72 ) ); ?>
				</span>
				<div class="nq-assurance__text">
					<p class="nq-assurance__title noviq-num"><?php echo esc_html( $nq_cell['title'] ); ?></p>
					<p class="nq-assurance__label"><?php echo esc_html( $nq_cell['label'] ); ?></p>
					<p class="nq-assurance__sub"><?php echo esc_html( $nq_cell['sub'] ); ?></p>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
