<?php
/**
 * Plugin Name: Noviq Peptides
 * Description: Domain plugin for the Noviq Peptides store: compounds, lots, compliance, and pricing rules.
 * Version: 0.1.0
 * Author: Noviq Peptides
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: noviq-peptides
 *
 * @package NoviqPeptides
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NOVIQ_PEPTIDES_VERSION', '0.1.0' );
define( 'NOVIQ_PEPTIDES_FILE', __FILE__ );
define( 'NOVIQ_PEPTIDES_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOVIQ_PEPTIDES_URL', plugin_dir_url( __FILE__ ) );

require_once NOVIQ_PEPTIDES_DIR . 'includes/class-ruo.php';
require_once NOVIQ_PEPTIDES_DIR . 'includes/class-post-types.php';
require_once NOVIQ_PEPTIDES_DIR . 'includes/class-meta.php';
require_once NOVIQ_PEPTIDES_DIR . 'includes/class-rewrites.php';
require_once NOVIQ_PEPTIDES_DIR . 'includes/class-age-gate.php';
require_once NOVIQ_PEPTIDES_DIR . 'includes/class-attestation.php';
require_once NOVIQ_PEPTIDES_DIR . 'includes/class-lots.php';
require_once NOVIQ_PEPTIDES_DIR . 'includes/class-commerce.php';
require_once NOVIQ_PEPTIDES_DIR . 'includes/class-catalog-guard.php';
require_once NOVIQ_PEPTIDES_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'NoviqPeptides\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NoviqPeptides\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		NoviqPeptides\Plugin::instance()->boot();
	}
);
