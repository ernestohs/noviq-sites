<?php
/**
 * Lot verify lookup.
 *
 * @package Noviq_Peptides
 */

get_header();

$lot_number = isset( $_GET['lot'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['lot'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$result     = null;
if ( '' !== $lot_number && class_exists( '\\NoviqPeptides\\Lots' ) ) {
	$result = \NoviqPeptides\Lots::find_by_lot_number( $lot_number );
}
?>
<div class="noviq-container noviq-section">
	<h1>Verify lot</h1>
	<form method="get" action="<?php echo esc_url( home_url( '/verify/' ) ); ?>">
		<label for="lot">Lot number</label>
		<input type="text" id="lot" name="lot" value="<?php echo esc_attr( $lot_number ); ?>" required />
		<button type="submit" class="noviq-btn noviq-btn--primary">Look up</button>
	</form>
	<?php if ( '' !== $lot_number ) : ?>
		<?php if ( $result ) : ?>
			<p>Lot <strong><?php echo esc_html( $lot_number ); ?></strong> is on file: <?php echo esc_html( $result->post_title ); ?>.</p>
		<?php else : ?>
			<p class="noviq-empty">
				<?php
				echo esc_html(
					class_exists( '\\NoviqPeptides\\Lots' )
						? \NoviqPeptides\Lots::empty_message()
						: 'No matching lot found.'
				);
				?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php
get_footer();
