<?php
/**
 * Plugin bootstrap.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every subsystem onto WordPress hooks. Nothing else calls add_action at
 * file scope, so the load order of the plugin is readable in one place.
 *
 * Subsystems are gated by Profile::feature() so a spinoff install only loads
 * what it needs.
 */
final class Plugin {

	private static ?self $instance = null;

	private bool $booted = false;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	private function __construct() {}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( PostTypes::class, 'register' ), 5 );
		add_action( 'init', array( Taxonomies::class, 'register' ), 5 );
		add_action( 'init', array( Meta::class, 'register' ), 6 );

		if ( Profile::feature( 'meta_boxes' ) ) {
			Admin\MetaBoxes::init();
		}
		if ( Profile::feature( 'volume_breaks' ) ) {
			Commerce\VolumeBreaks::init();
		}
		if ( Profile::feature( 'price_display' ) ) {
			Commerce\PriceDisplay::init();
		}
		if ( Profile::feature( 'referral_coupons' ) ) {
			Commerce\ReferralCoupon::init();
		}
		if ( Profile::feature( 'subscriptions' ) ) {
			Commerce\Subscriptions::init();
		}
		if ( Profile::feature( 'paypal_invoice' ) ) {
			// After WooCommerce loads gateway/email classes (plugins_loaded @20 is too early on WC 11+).
			add_action(
				'woocommerce_init',
				static function (): void {
					Commerce\PaypalInvoiceGateway::init();
				}
			);
		}
		if ( Profile::feature( 'age_gate' ) ) {
			Compliance\AgeGate::init();
		}
		if ( Profile::feature( 'ruo' ) ) {
			Compliance\Ruo::init();
		}
		if ( Profile::feature( 'attestation' ) ) {
			Compliance\Attestation::init();
		}
		Compliance\CatalogGuard::init();
		if ( Profile::feature( 'templates' ) ) {
			Content\Templates::init();
		}
		if ( Profile::feature( 'shortcodes' ) ) {
			Content\Shortcodes::init();
		}
		if ( Profile::feature( 'product_panels' ) ) {
			Content\ProductPanels::init();
		}
		if ( Profile::feature( 'seo' ) ) {
			Content\Seo::init();
		}
		if ( Profile::feature( 'newsletter' ) ) {
			Content\Newsletter::init();
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'noviq', Cli\Commands::class );
		}
	}

	/**
	 * Front-end assets owned by the plugin rather than the theme. These style
	 * plugin-rendered surfaces (spec tables, tier ladders) so they
	 * survive a parent-theme swap.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'noviq-core',
			NOVIQ_CORE_URL . 'assets/noviq-core.css',
			array(),
			VERSION
		);
	}
}
