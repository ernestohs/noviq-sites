<?php
/**
 * Seven-step testing process.
 *
 * Steps whose method claim is null are omitted. Sterility uses USP <71> from
 * sterility_incubation_days, not the design's duplicated LAL <85> copy.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( \Noviq\Core\Claims::class ) ) {
	return;
}

/** @var array<int, array{icon: string, title: string, sub: string}> $nq_steps */
$nq_steps = array();

if ( \Noviq\Core\Claims::has( 'identity_method' ) ) {
	$nq_steps[] = array(
		'icon'  => 'step-identity',
		'title' => __( 'Identity', 'noviq-child' ),
		'sub'   => (string) \Noviq\Core\Claims::get( 'identity_method' ),
	);
}
if ( \Noviq\Core\Claims::has( 'purity_method' ) ) {
	$nq_steps[] = array(
		'icon'  => 'step-purity',
		'title' => __( 'Purity', 'noviq-child' ),
		'sub'   => (string) \Noviq\Core\Claims::get( 'purity_method' ),
	);
}
if ( \Noviq\Core\Claims::has( 'content_method' ) ) {
	$nq_steps[] = array(
		'icon'  => 'step-content',
		'title' => __( 'Content', 'noviq-child' ),
		'sub'   => (string) \Noviq\Core\Claims::get( 'content_method' ),
	);
}
if ( \Noviq\Core\Claims::has( 'endotoxin_spec' ) ) {
	$nq_steps[] = array(
		'icon'  => 'step-endotoxin',
		'title' => __( 'Endotoxin', 'noviq-child' ),
		'sub'   => __( 'LAL Test (USP <85>)', 'noviq-child' ),
	);
}
if ( \Noviq\Core\Claims::has( 'sterility_incubation_days' ) ) {
	$nq_steps[] = array(
		'icon'  => 'step-sterility',
		'title' => __( 'Sterility', 'noviq-child' ),
		'sub'   => sprintf(
			/* translators: %s: incubation days. */
			__( 'USP <71> — %s-day incubation', 'noviq-child' ),
			\Noviq\Core\Claims::get( 'sterility_incubation_days' )
		),
	);
}
if ( \Noviq\Core\Claims::has( 'heavy_metals_method' ) ) {
	$nq_steps[] = array(
		'icon'  => 'step-metals',
		'title' => __( 'Heavy Metals', 'noviq-child' ),
		'sub'   => (string) \Noviq\Core\Claims::get( 'heavy_metals_method' ),
	);
}
if ( \Noviq\Core\Claims::has( 'consistency_method' ) ) {
	$nq_steps[] = array(
		'icon'  => 'step-consistency',
		'title' => __( 'Consistency', 'noviq-child' ),
		'sub'   => (string) \Noviq\Core\Claims::get( 'consistency_method' ),
	);
}

if ( array() === $nq_steps ) {
	return;
}

$nq_count = count( $nq_steps );
?>
<section class="nq-section nq-testing">
	<div class="nq-wrap nq-testing__inner">
		<div class="nq-testing__copy">
			<p class="nq-kicker nq-kicker--blue"><?php esc_html_e( 'QUALITY YOU CAN TRUST', 'noviq-child' ); ?></p>
			<h2>
				<?php
				printf(
					/* translators: %d: number of substantiated testing steps. */
					esc_html__( 'Our %d-step testing process for every lot', 'noviq-child' ),
					(int) $nq_count
				);
				?>
			</h2>
			<p><?php esc_html_e( 'We go beyond industry standards to ensure the highest purity and safety for your research.', 'noviq-child' ); ?></p>
			<a class="nq-btn nq-btn--outline" href="<?php echo esc_url( home_url( '/quality-standard/' ) ); ?>">
				<?php esc_html_e( 'LEARN MORE ABOUT TESTING', 'noviq-child' ); ?>
				<span class="nq-btn__chev" aria-hidden="true">›</span>
			</a>
		</div>

		<ol class="nq-testing__steps">
			<?php foreach ( $nq_steps as $i => $nq_step ) : ?>
				<li class="nq-testing__step">
					<span class="nq-testing__num noviq-num"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
					<span class="nq-testing__icon">
						<?php \Noviq\Child\the_icon( $nq_step['icon'], array( 'size' => 40 ) ); ?>
					</span>
					<span class="nq-testing__title"><?php echo esc_html( $nq_step['title'] ); ?></span>
					<span class="nq-testing__sub"><?php echo esc_html( $nq_step['sub'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
