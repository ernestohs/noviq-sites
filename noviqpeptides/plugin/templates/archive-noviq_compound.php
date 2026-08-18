<?php
/**
 * Compound index — /learn
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="noviq-page noviq-hub__main">
	<header class="noviq-page__header">
		<p class="noviq-kicker"><?php esc_html_e( 'Compound reference', 'noviq-core' ); ?></p>
		<h1><?php esc_html_e( 'Learn', 'noviq-core' ); ?></h1>
		<p class="noviq-page__lede">
			<?php esc_html_e( 'Monographs describing molecular profile, handling and analytics for each compound in the catalog.', 'noviq-core' ); ?>
		</p>
	</header>

	<?php echo do_shortcode( '[noviq_research_hub]' ); ?>
</main>

<?php
get_footer();
