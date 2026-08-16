<?php
/**
 * Single compound (/learn/{slug}).
 *
 * @package Noviq_Peptides
 */

get_header();
?>
<div class="noviq-container noviq-section">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<article <?php post_class(); ?>>
			<h1><?php the_title(); ?></h1>
			<?php
			if ( has_post_thumbnail() ) {
				the_post_thumbnail( 'large' );
			} else {
				noviq_image_tbd();
			}
			?>
			<div class="noviq-compound-spec">
				<?php if ( class_exists( '\\NoviqPeptides\\Meta' ) ) : ?>
					<dl>
						<?php
						$fields = array(
							'_noviq_cas'     => 'CAS',
							'_noviq_formula' => 'Formula',
							'_noviq_mw'      => 'MW',
							'_noviq_class'   => 'Class',
						);
						foreach ( $fields as $key => $label ) {
							$value = \NoviqPeptides\Meta::compound_field( get_the_ID(), $key );
							if ( '' === $value ) {
								continue;
							}
							echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
						}
						?>
					</dl>
				<?php endif; ?>
			</div>
			<?php the_content(); ?>
			<?php noviq_ruo_notice(); ?>
		</article>
	<?php endwhile; ?>
</div>
<?php
get_footer();
