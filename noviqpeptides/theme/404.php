<?php
/**
 * 404.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="noviq-page">
	<header class="nq-page-head">
		<p class="nq-eyebrow"><?php esc_html_e( 'Not found', 'noviq-child' ); ?></p>
		<h1 class="nq-page-head__title"><?php esc_html_e( 'That page does not exist', 'noviq-child' ); ?></h1>
	</header>

	<p class="noviq-page__lede">
		<?php esc_html_e( 'The link may be out of date. The catalog and the compound reference are the two places most links point at.', 'noviq-child' ); ?>
	</p>

	<p>
		<a class="nq-btn nq-btn--signal" href="<?php echo esc_url( (string) get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">
			<?php esc_html_e( 'Browse the catalog', 'noviq-child' ); ?>
		</a>
	</p>
</main>

<?php
get_footer();
