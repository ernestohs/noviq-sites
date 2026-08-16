<?php
/**
 * Homepage: hero, credibility, catalog, reference, FAQ.
 *
 * @package Noviq_Peptides
 */

get_header();
?>
<section class="noviq-band noviq-hero">
	<div class="noviq-container">
		<h1>Research-grade peptides for laboratory use</h1>
		<p>Catalog built for research teams. Clear documentation surfaces. Research use only.</p>
		<div class="noviq-hero__actions">
			<a class="noviq-btn noviq-btn--primary" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>">Browse catalog</a>
			<a class="noviq-btn noviq-btn--secondary" href="<?php echo esc_url( home_url( '/quality-standard/' ) ); ?>">Quality standard</a>
		</div>
	</div>
</section>

<section class="noviq-section">
	<div class="noviq-container">
		<h2>Credibility</h2>
		<p class="noviq-empty">Figures appear here only when substantiated. No placeholders. No zeros for missing data.</p>
	</div>
</section>

<section class="noviq-band noviq-section">
	<div class="noviq-container">
		<h2>Catalog</h2>
		<?php if ( function_exists( 'woocommerce_product_loop' ) ) : ?>
			<?php
			echo do_shortcode( '[products limit="8" columns="4" orderby="menu_order"]' );
			?>
		<?php else : ?>
			<p class="noviq-empty">WooCommerce catalog will appear here once activated.</p>
		<?php endif; ?>
		<p><a class="noviq-btn noviq-btn--secondary" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>">View all</a></p>
	</div>
</section>

<section class="noviq-section">
	<div class="noviq-container">
		<h2>Reference library</h2>
		<div class="noviq-grid">
			<a class="noviq-card" href="<?php echo esc_url( home_url( '/research-hub/' ) ); ?>"><h3>Learn</h3><p>Compound reference pages.</p></a>
			<a class="noviq-card" href="<?php echo esc_url( home_url( '/compare/' ) ); ?>"><h3>Compare</h3><p>Side-by-side compound tables.</p></a>
			<a class="noviq-card" href="<?php echo esc_url( home_url( '/coa/' ) ); ?>"><h3>COA library</h3><p>Published lot certificates.</p></a>
			<a class="noviq-card" href="<?php echo esc_url( home_url( '/quality-standard/' ) ); ?>"><h3>Quality standard</h3><p>Release specifications.</p></a>
		</div>
	</div>
</section>

<section class="noviq-band noviq-section noviq-faq">
	<div class="noviq-container">
		<h2>FAQ</h2>
		<details>
			<summary>What does Research Use Only mean?</summary>
			<?php noviq_ruo_notice(); ?>
		</details>
		<details>
			<summary>Where are certificates of analysis?</summary>
			<p>The lot registry ships empty until real laboratory documents are supplied. Use the COA library and lot verify tools when documents exist.</p>
		</details>
		<details>
			<summary>Do you sell bacteriostatic water here?</summary>
			<p>No. This catalog does not sell bacteriostatic water or injection consumables.</p>
		</details>
	</div>
</section>
<?php
get_footer();
