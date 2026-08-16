<?php
/**
 * Research hub.
 *
 * @package Noviq_Peptides
 */

get_header();
?>
<div class="noviq-container noviq-section">
	<h1>Research hub</h1>
	<p>Compounds indexed by research area.</p>
	<?php
	$terms = get_terms(
		array(
			'taxonomy'   => 'noviq_research',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) || array() === $terms ) {
		echo '<p class="noviq-empty">No research areas published yet.</p>';
	} else {
		echo '<div class="noviq-grid">';
		foreach ( $terms as $term ) {
			printf(
				'<a class="noviq-card" href="%s"><h3>%s</h3><p>%s compounds</p></a>',
				esc_url( get_term_link( $term ) ),
				esc_html( $term->name ),
				esc_html( (string) $term->count )
			);
		}
		echo '</div>';
	}
	?>
</div>
<?php
get_footer();
