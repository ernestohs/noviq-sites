<?php
/**
 * Site header.
 *
 * Four bands, in the reference's order: claims marquee, RUO strip, sticky
 * masthead, category chip bar. The RUO strip lives in the chrome rather than
 * only on commerce pages so it is present on every route without a template
 * having to remember it.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$nq_has_core = class_exists( \Noviq\Core\Claims::class );
$nq_ticker   = $nq_has_core ? \Noviq\Core\Claims::ticker_items() : array();
$nq_ruo      = $nq_has_core ? \Noviq\Core\Claims::ruo_short() : '';
$nq_name     = $nq_has_core ? (string) \Noviq\Core\Claims::site()['short_name'] : get_bloginfo( 'name' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="nq-skip" href="#main"><?php esc_html_e( 'Skip to content', 'noviq-child' ); ?></a>

<?php if ( array() !== $nq_ticker ) : ?>
	<div class="nq-marquee" role="complementary" aria-label="<?php esc_attr_e( 'Product claims', 'noviq-child' ); ?>">
		<div class="nq-marquee__track">
			<?php
			// Printed twice so the loop is seamless; the duplicate is hidden
			// from assistive technology.
			for ( $nq_pass = 0; $nq_pass < 2; $nq_pass++ ) :
				foreach ( $nq_ticker as $nq_item ) :
					?>
					<span class="nq-marquee__item" <?php echo 1 === $nq_pass ? 'aria-hidden="true"' : ''; ?>>
						<span class="nq-marquee__dot" aria-hidden="true">&#9670;</span>
						<?php echo esc_html( $nq_item ); ?>
					</span>
					<?php
				endforeach;
			endfor;
			?>
		</div>
	</div>
<?php endif; ?>

<?php if ( '' !== $nq_ruo ) : ?>
	<p class="nq-ruo-strip"><?php echo esc_html( $nq_ruo ); ?></p>
<?php endif; ?>

<header class="nq-masthead">
	<div class="nq-wrap nq-wrap--wide nq-masthead__inner">
		<a class="nq-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<span class="nq-logo__mark" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none">
					<path d="M2 13h4l2.5-7 3.5 14 3-9 2 2h5" stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round" />
				</svg>
			</span>
			<span class="nq-logo__word"><?php echo esc_html( $nq_name ); ?></span>
		</a>

		<nav class="nq-nav" aria-label="<?php esc_attr_e( 'Primary', 'noviq-child' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'nq-nav__list',
					'depth'          => 1,
					'fallback_cb'    => '__return_empty_string',
				)
			);
			?>
		</nav>

		<div class="nq-masthead__actions">
			<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
				<?php
				// Rendered through the same helper the AJAX fragment uses, so the
				// count refreshes on add-to-cart without a page load.
				echo \Noviq\Child\cart_pill_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			<?php endif; ?>

			<button class="nq-burger" type="button" aria-expanded="false" aria-controls="nq-mobile-nav">
				<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'noviq-child' ); ?></span>
				<span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
			</button>
		</div>
	</div>

	<div class="nq-mobile-nav" id="nq-mobile-nav" hidden>
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'nq-mobile-nav__list',
				'depth'          => 1,
				'fallback_cb'    => '__return_empty_string',
			)
		);
		?>
	</div>
</header>

<?php
/*
 * Categories in information-architecture order for the peptide catalog. Other
 * profiles (NOVIQ Bio) only have a subset of categories, so fall back to every
 * non-default term that exists.
 */
$nq_cat_order = array(
	'metabolic',
	'peptides',
	'blends',
	'sprays',
	'strips',
	'bioregulators',
	'bundles',
	'supplies',
	'apparel',
	'testing',
);

$nq_cats = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
	)
);

if ( is_array( $nq_cats ) && array() !== $nq_cats ) {
	usort(
		$nq_cats,
		static function ( WP_Term $a, WP_Term $b ) use ( $nq_cat_order ): int {
			$ai = array_search( $a->slug, $nq_cat_order, true );
			$bi = array_search( $b->slug, $nq_cat_order, true );
			$ai = false === $ai ? 999 : $ai;
			$bi = false === $bi ? 999 : $bi;

			return $ai === $bi ? strcasecmp( $a->name, $b->name ) : $ai <=> $bi;
		}
	);
}

if ( is_array( $nq_cats ) && array() !== $nq_cats ) :
	$nq_shop = (int) wc_get_page_id( 'shop' );
	?>
	<nav class="nq-collbar" aria-label="<?php esc_attr_e( 'Product categories', 'noviq-child' ); ?>">
		<div class="nq-collbar__track">
			<?php if ( $nq_shop > 0 ) : ?>
				<a class="nq-collbar__chip<?php echo is_shop() ? ' is-current' : ''; ?>" href="<?php echo esc_url( (string) get_permalink( $nq_shop ) ); ?>">
					<?php esc_html_e( 'All products', 'noviq-child' ); ?>
					<span class="noviq-num nq-collbar__count"><?php echo esc_html( (string) wp_count_posts( 'product' )->publish ); ?></span>
				</a>
			<?php endif; ?>

			<?php
			foreach ( $nq_cats as $nq_cat ) :
				if ( ! $nq_cat instanceof WP_Term ) {
					continue;
				}
				?>
				<a class="nq-collbar__chip<?php echo is_tax( 'product_cat', $nq_cat->term_id ) ? ' is-current' : ''; ?>"
					href="<?php echo esc_url( (string) get_term_link( $nq_cat ) ); ?>">
					<?php echo esc_html( $nq_cat->name ); ?>
					<span class="noviq-num nq-collbar__count"><?php echo esc_html( (string) $nq_cat->count ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</nav>
<?php endif; ?>

<div class="nq-shell">
