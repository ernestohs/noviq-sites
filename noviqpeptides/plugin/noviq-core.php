<?php
/**
 * Plugin Name:       Noviq Core
 * Plugin URI:        https://noviqpeptides.com
 * Description:       Brand-specific data model, pricing rules and compliance gates for Noviq Peptides. Everything here must survive a theme change.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Author:            Noviq Labs, Inc.
 * Text Domain:       noviq-core
 * Domain Path:       /languages
 *
 * WC requires at least: 9.0
 * WC tested up to:      10.9
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.1.0';
const PLUGIN_FILE = __FILE__;

define( 'NOVIQ_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'NOVIQ_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4 autoloader for the Noviq\Core namespace.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = NOVIQ_CORE_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PLUGIN_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', PLUGIN_FILE, true );
		}
	}
);

Plugin::instance()->boot();

/**
 * Rewrite rules for the compound and comparison post types are registered on
 * activation; flush once so /learn/{slug} and /compare/{slug} resolve.
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		PostTypes::register();
		Taxonomies::register();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);
