<?php
/**
 * Site footer.
 *
 * Brand column, four seeded nav groups, newsletter, RUO disclaimer, then a
 * bottom bar with copyright, PayPal-invoice billing note, and trust chips.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$nq_has_core = class_exists( \Noviq\Core\Claims::class );
$nq_site     = $nq_has_core ? \Noviq\Core\Claims::site() : array();
$nq_name     = $nq_has_core ? (string) ( $nq_site['short_name'] ?? 'Noviq' ) : get_bloginfo( 'name' );

/** @var array<int, string> $nq_chips */
$nq_chips = array( __( 'COA Per Lot', 'noviq-child' ) );

if ( $nq_has_core && \Noviq\Core\Claims::has( 'purity_spec' ) ) {
	$nq_chips[] = sprintf( '≥%s%% Purity', \Noviq\Core\Claims::get( 'purity_spec' ) );
}
if ( $nq_has_core && \Noviq\Core\Claims::has( 'dispatch_cutoff' ) ) {
	$nq_chips[] = __( 'Same-Day Dispatch', 'noviq-child' );
}
$nq_chips[] = __( 'Unmarked Packaging', 'noviq-child' );
?>
</div><!-- .nq-shell -->

<footer class="nq-footer">
	<div class="nq-wrap nq-footer__top">
		<div class="nq-footer__brand">
			<a class="nq-logo nq-logo--footer" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<span class="nq-logo__mark" aria-hidden="true">
					<?php \Noviq\Child\the_icon( 'pulse', array( 'size' => 28 ) ); ?>
				</span>
				<span class="nq-logo__text">
					<span class="nq-logo__word"><?php echo esc_html( strtoupper( $nq_name ) ); ?></span>
					<span class="nq-logo__sub"><?php esc_html_e( 'PEPTIDES', 'noviq-child' ); ?></span>
				</span>
			</a>

			<p class="nq-footer__tagline">
				<?php echo esc_html( $nq_has_core ? (string) ( $nq_site['description'] ?? '' ) : get_bloginfo( 'description' ) ); ?>
			</p>

			<?php if ( $nq_has_core && ! empty( $nq_site['support_email'] ) ) : ?>
				<a class="nq-footer__contact noviq-num" href="mailto:<?php echo esc_attr( (string) $nq_site['support_email'] ); ?>">
					<?php echo esc_html( (string) $nq_site['support_email'] ); ?>
				</a>
			<?php endif; ?>

			<?php if ( $nq_has_core && ! empty( $nq_site['phone'] ) ) : ?>
				<?php
				$nq_phone = (string) $nq_site['phone'];
				$nq_tel   = preg_replace( '/[^\d+]/', '', $nq_phone ) ?? $nq_phone;
				?>
				<a class="nq-footer__contact noviq-num" href="tel:<?php echo esc_attr( $nq_tel ); ?>">
					<?php echo esc_html( $nq_phone ); ?>
				</a>
			<?php endif; ?>

			<?php if ( $nq_has_core && ! empty( $nq_site['address'] ) ) : ?>
				<p class="nq-footer__address noviq-num">
					<?php echo esc_html( (string) $nq_site['address'] ); ?>
				</p>
			<?php endif; ?>

			<?php
			$nq_socials = array();
			foreach ( array( 'instagram', 'x', 'youtube' ) as $nq_net ) {
				$url = $nq_site[ $nq_net ] ?? null;
				if ( is_string( $url ) && '' !== $url ) {
					$nq_socials[ $nq_net ] = $url;
				}
			}
			if ( array() !== $nq_socials ) :
				?>
				<ul class="nq-footer__social">
					<?php foreach ( $nq_socials as $nq_net => $nq_url ) : ?>
						<li>
							<a href="<?php echo esc_url( $nq_url ); ?>" rel="noopener noreferrer" target="_blank">
								<span class="screen-reader-text"><?php echo esc_html( ucfirst( $nq_net ) ); ?></span>
								<?php \Noviq\Child\the_icon( $nq_net, array( 'size' => 18 ) ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
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

		<div class="nq-footer__nl">
			<p class="nq-footer__nl-kicker"><?php esc_html_e( 'NEWSLETTER', 'noviq-child' ); ?></p>
			<p class="nq-footer__nl-copy">
				<?php esc_html_e( 'Research updates, new products, and exclusive offers, delivered to your inbox.', 'noviq-child' ); ?>
			</p>
			<?php
			if ( shortcode_exists( 'noviq_newsletter' ) ) {
				echo do_shortcode( '[noviq_newsletter]' );
			}
			?>
		</div>
	</div>

	<div class="nq-wrap nq-footer__legal">
		<p><?php echo esc_html( $nq_has_core ? \Noviq\Core\Claims::ruo_full() : '' ); ?></p>
	</div>

	<div class="nq-footer__bar">
		<div class="nq-wrap nq-footer__bar-inner">
			<p class="nq-footer__copy noviq-num">
				<?php
				$nq_entity = ( $nq_has_core && ! empty( $nq_site['legal_entity'] ) )
					? (string) $nq_site['legal_entity']
					: null;
				if ( null !== $nq_entity ) {
					printf(
						/* translators: 1: year, 2: legal entity. */
						esc_html__( '© %1$s %2$s. All rights reserved.', 'noviq-child' ),
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

			<p class="nq-footer__pay">
				<?php esc_html_e( 'Payment by PayPal invoice link emailed after checkout.', 'noviq-child' ); ?>
			</p>

			<ul class="nq-footer__chips">
				<?php foreach ( $nq_chips as $nq_chip ) : ?>
					<li><?php echo esc_html( $nq_chip ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
