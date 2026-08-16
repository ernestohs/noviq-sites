<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
	<div class="noviq-container site-header__inner">
		<div class="site-branding">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Noviq Peptides</a>
		</div>
		<nav class="primary-nav" aria-label="Primary">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'fallback_cb'    => 'noviq_primary_menu_fallback',
				)
			);
			?>
		</nav>
		<div class="site-header__cart">
			<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
				<a href="<?php echo esc_url( wc_get_cart_url() ); ?>">Cart<?php
				if ( function_exists( 'WC' ) && WC()->cart ) {
					$count = WC()->cart->get_cart_contents_count();
					if ( $count > 0 ) {
						echo ' (' . esc_html( (string) $count ) . ')';
					}
				}
				?></a>
			<?php endif; ?>
		</div>
	</div>
</header>
<main class="site-main">
