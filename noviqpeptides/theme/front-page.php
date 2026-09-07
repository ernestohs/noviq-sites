<?php
/**
 * Front page.
 *
 * Section order matches Figma frame 5:2. Each band lives in template-parts/home/.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="nq-main nq-home">
	<?php
	get_template_part( 'template-parts/home/hero' );
	get_template_part( 'template-parts/home/assurance' );
	get_template_part( 'template-parts/home/research-areas' );
	get_template_part( 'template-parts/home/popular' );
	get_template_part( 'template-parts/home/service' );
	get_template_part( 'template-parts/home/testing' );
	get_template_part( 'template-parts/home/verify' );
	get_template_part( 'template-parts/home/faq' );
	get_template_part( 'template-parts/home/library' );
	?>
</main>

<?php
get_footer();
