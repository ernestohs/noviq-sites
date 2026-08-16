</main>
<footer class="site-footer">
	<div class="noviq-container">
		<?php noviq_ruo_notice(); ?>
		<nav class="site-footer__nav" aria-label="Footer">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'fallback_cb'    => static function (): void {
						$items = array(
							'/about/'                        => 'About',
							'/contact/'                      => 'Contact',
							'/policies/shipping-returns/'    => 'Shipping',
							'/policies/terms/'               => 'Terms',
							'/policies/privacy/'             => 'Privacy',
						);
						echo '<ul>';
						foreach ( $items as $url => $label ) {
							printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( $url ) ), esc_html( $label ) );
						}
						echo '</ul>';
					},
				)
			);
			?>
		</nav>
		<p class="site-footer__copy">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Noviq Peptides</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
