<?php
/**
 * Compound monograph — /learn/{slug}
 *
 * Fallback template. A theme may override it with its own
 * single-noviq_compound.php; see Content\Templates::route().
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

use Noviq\Core\Content\Compound;

defined( 'ABSPATH' ) || exit;

get_header();

the_post();

$compound = Compound::from_id( get_the_ID() );

if ( null === $compound ) {
	get_footer();

	return;
}

$area      = $compound->research_area();
$product_ids = $compound->product_ids();
?>

<main id="main" class="noviq-page noviq-monograph__main">
	<article class="noviq-monograph__article">

		<header class="noviq-monograph__header">
			<?php if ( $area instanceof WP_Term ) : ?>
				<p class="noviq-kicker">
					<a href="<?php echo esc_url( (string) get_term_link( $area ) ); ?>"><?php echo esc_html( $area->name ); ?></a>
				</p>
			<?php endif; ?>

			<h1 class="noviq-monograph__title"><?php echo esc_html( $compound->name() ); ?></h1>

			<?php
			$synonyms = $compound->synonyms();
			if ( array() !== $synonyms ) :
				?>
				<p class="noviq-monograph__synonyms"><?php echo esc_html( implode( ' · ', $synonyms ) ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $compound->precis() ) : ?>
				<p class="noviq-monograph__precis"><?php echo esc_html( $compound->precis() ); ?></p>
			<?php endif; ?>
		</header>

		<section class="noviq-monograph__spec">
			<h2><?php esc_html_e( 'Analytical specification', 'noviq-core' ); ?></h2>
			<?php
			// Escaped inside render_spec_table().
			echo $compound->render_spec_table(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			<p class="noviq-monograph__source">
				<?php esc_html_e( 'Reference values from public analytical chemistry. Confirm against the lot Certificate of Analysis before use in an assay.', 'noviq-core' ); ?>
			</p>
		</section>

		<?php if ( '' !== trim( $compound->narrative() ) ) : ?>
			<section class="noviq-monograph__narrative">
				<?php the_content(); ?>
			</section>
		<?php endif; ?>

		<?php if ( array() !== $product_ids ) : ?>
			<section class="noviq-monograph__products">
				<h2><?php esc_html_e( 'Available as', 'noviq-core' ); ?></h2>
				<ul class="noviq-monograph__product-list">
					<?php
					foreach ( $product_ids as $product_id ) :
						$product = wc_get_product( $product_id );
						if ( ! $product instanceof WC_Product ) {
							continue;
						}
						?>
						<li>
							<a href="<?php echo esc_url( (string) $product->get_permalink() ); ?>">
								<span class="noviq-monograph__product-name"><?php echo esc_html( $product->get_name() ); ?></span>
								<span class="noviq-num noviq-monograph__product-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<section class="noviq-monograph__docs">
			<h2><?php esc_html_e( 'Documentation', 'noviq-core' ); ?></h2>
			<div class="noviq-empty" role="status">
				<p class="noviq-empty__body">
					<?php esc_html_e( 'Certificates of Analysis are published per lot on release. Look up a lot number to retrieve its certificate.', 'noviq-core' ); ?>
				</p>
				<p><a class="noviq-btn" href="<?php echo esc_url( home_url( '/verify' ) ); ?>"><?php esc_html_e( 'Verify a lot number', 'noviq-core' ); ?></a></p>
			</div>
		</section>

	</article>
</main>

<?php
get_footer();
