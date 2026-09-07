<?php
/**
 * Service / shipping assurance row.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$nq_has_core = class_exists( \Noviq\Core\Claims::class );
$nq_cutoff   = ( $nq_has_core && \Noviq\Core\Claims::has( 'dispatch_cutoff' ) )
	? (string) \Noviq\Core\Claims::get( 'dispatch_cutoff' )
	: '';

	$nq_items = array(
	array(
		'icon'  => 'truck',
		'title' => __( 'Same-Day Shipping', 'noviq-child' ),
		'sub'   => '' !== $nq_cutoff
			? sprintf(
				/* translators: %s: dispatch cutoff time from profile. */
				__( 'Orders placed before %s', 'noviq-child' ),
				$nq_cutoff
			)
			: __( 'Business-day dispatch', 'noviq-child' ),
	),
	array(
		'icon'  => 'package',
		'title' => __( 'Discreet Packaging', 'noviq-child' ),
		'sub'   => __( 'Privacy is our priority', 'noviq-child' ),
	),
	array(
		'icon'  => 'damage-shield',
		'title' => __( 'Damage Protection', 'noviq-child' ),
		'sub'   => __( "We've got you covered", 'noviq-child' ),
	),
	array(
		'icon'  => 'support',
		'title' => __( 'Expert Support', 'noviq-child' ),
		'sub'   => __( 'Researcher support team', 'noviq-child' ),
	),
);
?>
<section class="nq-service" aria-label="<?php esc_attr_e( 'Shipping and support', 'noviq-child' ); ?>">
	<div class="nq-wrap nq-service__grid">
		<?php foreach ( $nq_items as $nq_item ) : ?>
			<div class="nq-service__item">
				<span class="nq-service__icon">
					<?php \Noviq\Child\the_icon( $nq_item['icon'], array( 'size' => 40 ) ); ?>
				</span>
				<div>
					<p class="nq-service__title"><?php echo esc_html( $nq_item['title'] ); ?></p>
					<p class="nq-service__sub"><?php echo esc_html( $nq_item['sub'] ); ?></p>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
