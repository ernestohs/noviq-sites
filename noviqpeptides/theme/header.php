<?php
/**
 * Site header.
 *
 * Marquee (claims), masthead (logo / search / account / cart), then the primary
 * nav bar with dropdown groups. RUO short notice remains in the chrome.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$nq_has_core = class_exists( \Noviq\Core\Claims::class );
$nq_ticker   = $nq_has_core ? \Noviq\Core\Claims::ticker_items() : array();
$nq_ruo      = $nq_has_core ? \Noviq\Core\Claims::ruo_short() : '';
$nq_name     = $nq_has_core ? (string) \Noviq\Core\Claims::site()['short_name'] : get_bloginfo( 'name' );
$nq_account  = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
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
				<?php \Noviq\Child\the_icon( 'pulse', array( 'size' => 28 ) ); ?>
			</span>
			<span class="nq-logo__text">
				<span class="nq-logo__word"><?php echo esc_html( strtoupper( $nq_name ) ); ?></span>
				<span class="nq-logo__sub"><?php esc_html_e( 'PEPTIDES', 'noviq-child' ); ?></span>
			</span>
		</a>

		<form class="nq-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="nq-search-field"><?php esc_html_e( 'Search', 'noviq-child' ); ?></label>
			<input
				id="nq-search-field"
				class="nq-search__input"
				type="search"
				name="s"
				placeholder="<?php esc_attr_e( 'Search peptides, blends or lot #...', 'noviq-child' ); ?>"
				value="<?php echo esc_attr( get_search_query() ); ?>"
			/>
			<?php if ( function_exists( 'WC' ) ) : ?>
				<input type="hidden" name="post_type" value="product" />
			<?php endif; ?>
			<button class="nq-search__btn" type="submit">
				<span class="screen-reader-text"><?php esc_html_e( 'Search', 'noviq-child' ); ?></span>
				<?php \Noviq\Child\the_icon( 'search', array( 'size' => 24 ) ); ?>
			</button>
		</form>

		<div class="nq-masthead__actions">
			<a class="nq-account" href="<?php echo esc_url( $nq_account ); ?>">
				<span class="nq-account__label"><?php esc_html_e( 'Account', 'noviq-child' ); ?></span>
				<?php \Noviq\Child\the_icon( 'account', array( 'size' => 24 ) ); ?>
			</a>

			<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
				<?php
				echo \Noviq\Child\cart_pill_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			<?php endif; ?>

			<button class="nq-burger" type="button" aria-expanded="false" aria-controls="nq-mobile-nav">
				<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'noviq-child' ); ?></span>
				<span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
			</button>
		</div>
	</div>
</header>

<nav class="nq-menubar" aria-label="<?php esc_attr_e( 'Primary', 'noviq-child' ); ?>">
	<div class="nq-wrap nq-menubar__inner">
		<?php \Noviq\Child\render_primary_menubar(); ?>
	</div>
</nav>

<div class="nq-mobile-nav" id="nq-mobile-nav" hidden>
	<?php \Noviq\Child\render_primary_menubar( 'nq-mobile-nav__list' ); ?>
</div>

<div class="nq-shell">
