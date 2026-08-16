<?php
/**
 * Checkout researcher attestation.
 *
 * @package NoviqPeptides
 */

declare(strict_types=1);

namespace NoviqPeptides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Attestation {

	public const COPY_VERSION = '1';

	public static function init(): void {
		if ( ! Plugin::woo_active() ) {
			add_action( 'woocommerce_loaded', array( self::class, 'hooks' ) );
			return;
		}
		self::hooks();
	}

	public static function hooks(): void {
		add_action( 'woocommerce_review_order_before_submit', array( self::class, 'field' ) );
		add_action( 'woocommerce_checkout_process', array( self::class, 'validate' ) );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'save' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( self::class, 'admin_display' ) );
	}

	public static function copy(): string {
		return (string) apply_filters(
			'noviq_attestation_copy',
			'I confirm that I am purchasing these materials for laboratory research use only, and that I will not use them in humans or animals, or for diagnostic or therapeutic purposes.'
		);
	}

	public static function copy_version(): string {
		return (string) get_option( 'noviq_attestation_copy_version', self::COPY_VERSION );
	}

	public static function field(): void {
		woocommerce_form_field(
			'noviq_research_attestation',
			array(
				'type'     => 'checkbox',
				'class'    => array( 'form-row-wide', 'noviq-attestation' ),
				'label'    => esc_html( self::copy() ),
				'required' => true,
			)
		);
		echo '<input type="hidden" name="noviq_attestation_version" value="' . esc_attr( self::copy_version() ) . '" />';
	}

	public static function validate(): void {
		if ( empty( $_POST['noviq_research_attestation'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wc_add_notice( 'Research attestation is required to place an order.', 'error' );
		}
	}

	/**
	 * @param \WC_Order $order Order.
	 * @param array     $data  Posted data.
	 */
	public static function save( $order, $data ): void {
		if ( empty( $_POST['noviq_research_attestation'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$version = isset( $_POST['noviq_attestation_version'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_text_field( wp_unslash( (string) $_POST['noviq_attestation_version'] ) )
			: self::copy_version();
		$order->update_meta_data( '_noviq_attestation_accepted', 'yes' );
		$order->update_meta_data( '_noviq_attestation_wording', self::copy() );
		$order->update_meta_data( '_noviq_attestation_version', $version );
		$order->update_meta_data( '_noviq_attestation_timestamp', gmdate( 'c' ) );
	}

	/**
	 * @param \WC_Order $order Order.
	 */
	public static function admin_display( $order ): void {
		$accepted = $order->get_meta( '_noviq_attestation_accepted' );
		if ( 'yes' !== $accepted ) {
			echo '<p><strong>Research attestation:</strong> missing</p>';
			return;
		}
		echo '<p><strong>Research attestation:</strong> accepted</p>';
		echo '<p><strong>Wording:</strong> ' . esc_html( (string) $order->get_meta( '_noviq_attestation_wording' ) ) . '</p>';
		echo '<p><strong>Version:</strong> ' . esc_html( (string) $order->get_meta( '_noviq_attestation_version' ) ) . '</p>';
		echo '<p><strong>Timestamp:</strong> ' . esc_html( (string) $order->get_meta( '_noviq_attestation_timestamp' ) ) . '</p>';
	}
}
