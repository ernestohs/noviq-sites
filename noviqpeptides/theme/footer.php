<?php
/**
 * Site footer.
 *
 * Assurance badges, navigation columns, then the full RUO disclaimer, all on
 * the deep-navy ground.
 *
 * Every figure comes from Claims; a claim the client cannot substantiate drops
 * its whole badge rather than rendering a blank.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$nq_has_core = class_exists( \Noviq\Core\Claims::class );
$nq_site     = $nq_has_core ? \Noviq\Core\Claims::site() : array();

/** @var array<int, array{title: string, sub: string}> $nq_badges */
$nq_badges = array();

if ( $nq_has_core ) {
	$nq_badges[] = array(
		'title' => __( 'COA per lot', 'noviq-child' ),
		'sub'   => __( 'Every lot, every product', 'noviq-child' ),
	);

	if ( \Noviq\Core\Claims::has( 'purity_spec' ) ) {
		$nq_badges[] = array(
			'title' => sprintf( '≥%s%% purity spec', \Noviq\Core\Claims::get( 'purity_spec' ) ),
			'sub'   => __( 'RP-HPLC + mass spec', 'noviq-child' ),
		);
	}

	if ( \Noviq\Core\Claims::has( 'dispatch_cutoff' ) ) {
		$nq_badges[] = array(
			'title' => __( 'Same-day dispatch', 'noviq-child' ),
			'sub'   => sprintf(
				/* translators: %s: cutoff time. */
				__( 'Orders before %s', 'noviq-child' ),
				\Noviq\Core\Claims::get( 'dispatch_cutoff' )
			),
		);
	}

	$nq_badges[] = array(
		'title' => __( 'Unmarked packaging', 'noviq-child' ),
		'sub'   => __( 'No product names outside', 'noviq-child' ),
	);
}
?>
</div><!-- .nq-shell -->

<footer class="nq-footer">
	<div class="nq-wrap">
		<?php if ( array() !== $nq_badges ) : ?>
			<div class="nq-footer__badges">
				<?php foreach ( $nq_badges as $nq_badge ) : ?>
					<div>
						<p class="nq-footer__badge-title"><?php echo esc_html( $nq_badge['title'] ); ?></p>
						<p class="nq-footer__badge-sub"><?php echo esc_html( $nq_badge['sub'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="nq-footer__grid">
			<div>
				<span class="nq-footer__logo" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="20" height="20" fill="none">
						<path d="M2 13h4l2.5-7 3.5 14 3-9 2 2h5" stroke="currentColor" stroke-width="2"
							stroke-linecap="round" stroke-linejoin="round" />
					</svg>
				</span>

				<p class="nq-footer__tagline">
					<?php echo esc_html( $nq_has_core ? (string) $nq_site['description'] : get_bloginfo( 'description' ) ); ?>
				</p>

				<?php if ( $nq_has_core && ! empty( $nq_site['support_email'] ) ) : ?>
					<a class="nq-footer__email noviq-num" href="mailto:<?php echo esc_attr( (string) $nq_site['support_email'] ); ?>">
						<?php echo esc_html( (string) $nq_site['support_email'] ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $nq_has_core && ! empty( $nq_site['phone'] ) ) : ?>
					<?php
					$nq_phone = (string) $nq_site['phone'];
					$nq_tel   = preg_replace( '/[^\d+]/', '', $nq_phone ) ?? $nq_phone;
					?>
					<a class="nq-footer__email noviq-num" href="tel:<?php echo esc_attr( $nq_tel ); ?>">
						<?php echo esc_html( $nq_phone ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $nq_has_core && ! empty( $nq_site['address'] ) ) : ?>
					<p class="nq-footer__tagline noviq-num">
						<?php echo esc_html( (string) $nq_site['address'] ); ?>
					</p>
				<?php endif; ?>
			</div>

			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'nq-footer__list',
					'depth'          => 2,
					'fallback_cb'    => '__return_empty_string',
				)
			);
			?>
		</div>

		<div class="nq-footer__legal">
			<p><?php echo esc_html( $nq_has_core ? \Noviq\Core\Claims::ruo_full() : '' ); ?></p>
			<p class="noviq-num">
				<?php
				$nq_entity = ( $nq_has_core && ! empty( $nq_site['legal_entity'] ) )
					? (string) $nq_site['legal_entity']
					: null;
				if ( null !== $nq_entity ) {
					printf(
						'© %s %s',
						esc_html( gmdate( 'Y' ) ),
						esc_html( $nq_entity )
					);
				} else {
					printf(
						/* translators: %s: current year. */
						esc_html__( '© %s legal entity TBD', 'noviq-child' ),
						esc_html( gmdate( 'Y' ) )
					);
				}
				?>
			</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
