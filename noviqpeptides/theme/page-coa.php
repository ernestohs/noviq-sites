<?php
/**
 * COA library empty state.
 *
 * @package Noviq_Peptides
 */

get_header();
?>
<div class="noviq-container noviq-section">
	<h1>Certificates of Analysis</h1>
	<?php
	$lots = class_exists( '\\NoviqPeptides\\Lots' ) ? \NoviqPeptides\Lots::published_lots() : array();
	if ( array() === $lots ) {
		$message = class_exists( '\\NoviqPeptides\\Lots' ) ? \NoviqPeptides\Lots::empty_message() : 'No certificates published yet.';
		echo '<p class="noviq-empty">' . esc_html( $message ) . '</p>';
	} else {
		echo '<ul>';
		foreach ( $lots as $lot ) {
			$number = get_post_meta( $lot->ID, '_noviq_lot_number', true );
			printf(
				'<li><strong>%s</strong> %s</li>',
				esc_html( is_string( $number ) && '' !== $number ? $number : $lot->post_title ),
				esc_html( get_the_date( '', $lot ) )
			);
		}
		echo '</ul>';
	}
	?>
	<p><a href="<?php echo esc_url( home_url( '/verify/' ) ); ?>">Verify a lot number</a></p>
</div>
<?php
get_footer();
