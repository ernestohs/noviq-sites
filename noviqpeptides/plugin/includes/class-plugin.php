<?php
/**
 * Plugin bootstrap.
 *
 * @package NoviqPeptides
 */

declare(strict_types=1);

namespace NoviqPeptides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate(): void {
		Post_Types::register();
		Rewrites::register();
		flush_rewrite_rules();
		if ( false === get_option( 'noviq_ruo_notice_version', false ) ) {
			update_option( 'noviq_ruo_notice_version', RUO::NOTICE_VERSION );
		}
		if ( false === get_option( 'noviq_age_gate_copy_version', false ) ) {
			update_option( 'noviq_age_gate_copy_version', Age_Gate::COPY_VERSION );
		}
		if ( false === get_option( 'noviq_attestation_copy_version', false ) ) {
			update_option( 'noviq_attestation_copy_version', Attestation::COPY_VERSION );
		}
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public function boot(): void {
		Post_Types::init();
		Meta::init();
		Rewrites::init();
		RUO::init();
		Age_Gate::init();
		Attestation::init();
		Lots::init();
		Commerce::init();
		Catalog_Guard::init();
	}

	public static function woo_active(): bool {
		return class_exists( 'WooCommerce' );
	}
}
