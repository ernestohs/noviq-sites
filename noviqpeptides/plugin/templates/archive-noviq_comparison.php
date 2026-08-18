<?php
/**
 * Comparison index — /compare
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="noviq-page noviq-compare__main">
	<header class="noviq-page__header">
		<p class="noviq-kicker"><?php esc_html_e( 'Reference', 'noviq-core' ); ?></p>
		<h1><?php esc_html_e( 'Comparisons', 'noviq-core' ); ?></h1>
		<p class="noviq-page__lede">
			<?php esc_html_e( 'Head-to-head specification tables generated from the compound records.', 'noviq-core' ); ?>
		</p>
	</header>

	<?php echo do_shortcode( '[noviq_compare_index]' ); ?>
</main>

<?php
get_footer();
