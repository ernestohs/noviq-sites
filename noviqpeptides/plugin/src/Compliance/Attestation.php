<?php
/**
 * Researcher / age attestation at checkout.
 *
 * The order cannot be placed until the buyer explicitly certifies that they are
 * 21 or older and are a qualified researcher or purchasing on behalf of a
 * research institution. Validation is server-side: a client that strips the
 * checkbox still fails.
 *
 * What is recorded on the order is not just a boolean but the exact wording the
 * buyer agreed to and when. If the copy is revised later, existing orders still
 * evidence what was actually shown at the time.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Compliance;

use Noviq\Core\Profile;

defined( 'ABSPATH' ) || exit;

final class Attestation {

	public const FIELD = 'noviq_attestation';

	public const META_ACCEPTED = '_noviq_attestation';
	public const META_TEXT     = '_noviq_attestation_text';
	public const META_TIME     = '_noviq_attestation_at';

	/** Block-checkout field id must be namespaced group/name. */
	public const BLOCK_FIELD = 'noviq/attestation';

	/**
	 * The certification wording. Kept in one place so the string stored on the
	 * order and the string shown at checkout can never drift apart.
	 */
	public static function text(): string {
		$text = Profile::attestation_text();

		return '' !== $text
			? $text
			: __(
				'I certify that I am 21 years of age or older, and that I am a qualified researcher or am purchasing on behalf of a research institution. I understand these materials are supplied for in-vitro laboratory research only and are not for human or veterinary use.',
				'noviq-core'
			);
	}

	/**
	 * Registration is deferred to woocommerce_init: this plugin loads before
	 * WooCommerce, so wc_get_page_id() does not exist yet at boot.
	 */
	public static function init(): void {
		add_action( 'woocommerce_init', array( self::class, 'register' ) );
	}

	public static function register(): void {
		if ( self::uses_block_checkout() ) {
			self::register_block_field();

			return;
		}

		add_action( 'woocommerce_review_order_before_submit', array( self::class, 'render_field' ), 20 );
		add_action( 'woocommerce_checkout_process', array( self::class, 'validate' ) );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'record' ), 10, 2 );

		add_action( 'woocommerce_admin_order_data_after_billing_address', array( self::class, 'render_admin' ) );
		add_filter( 'woocommerce_email_order_meta_fields', array( self::class, 'email_field' ), 10, 3 );
	}

	/**
	 * True when the checkout page is the block-based checkout, which validates
	 * through the Store API rather than the classic POST handler.
	 */
	public static function uses_block_checkout(): bool {
		$page_id = (int) wc_get_page_id( 'checkout' );
		if ( $page_id <= 0 ) {
			return false;
		}

		$page = get_post( $page_id );

		return $page instanceof \WP_Post && has_block( 'woocommerce/checkout', $page );
	}

	public static function render_field(): void {
		$checked = isset( $_POST[ self::FIELD ] ) ? 'yes' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Woo nonce-checks the checkout POST; this only repopulates on validation failure.

		printf(
			'<div class="noviq-attestation" id="noviq-attestation">
				<label class="noviq-attestation__label" for="%1$s">
					<input type="checkbox" name="%1$s" id="%1$s" value="yes" %2$s required aria-required="true" />
					<span class="noviq-attestation__text">%3$s</span>
				</label>
			</div>',
			esc_attr( self::FIELD ),
			checked( $checked, 'yes', false ),
			esc_html( self::text() )
		);
	}

	/**
	 * Server-side gate. wc_add_notice with 'error' is what stops the order.
	 */
	public static function validate(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before this hook fires.
		if ( empty( $_POST[ self::FIELD ] ) ) {
			wc_add_notice(
				__( 'You must certify that you are 21 or older and a qualified researcher before this order can be placed.', 'noviq-core' ),
				'error'
			);
		}
	}

	/**
	 * Persist the attestation onto the order. HPOS-safe: written through the
	 * order object, never with update_post_meta.
	 *
	 * @param array<string, mixed> $data Posted checkout data.
	 */
	public static function record( \WC_Order $order, array $data ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before this hook fires.
		if ( empty( $_POST[ self::FIELD ] ) ) {
			return;
		}

		$order->update_meta_data( self::META_ACCEPTED, 'yes' );
		$order->update_meta_data( self::META_TEXT, self::text() );
		$order->update_meta_data( self::META_TIME, gmdate( 'c' ) );
	}

	/**
	 * Block checkout equivalent. The Store API enforces `required` server-side,
	 * so the gate holds without a bespoke validator.
	 */
	public static function register_block_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'            => self::BLOCK_FIELD,
				'label'         => self::text(),
				'location'      => 'order',
				'type'          => 'checkbox',
				'required'      => true,
				'error_message' => __( 'You must certify that you are 21 or older and a qualified researcher before this order can be placed.', 'noviq-core' ),
			)
		);
	}

	public static function render_admin( \WC_Order $order ): void {
		$accepted = $order->get_meta( self::META_ACCEPTED );
		if ( 'yes' !== $accepted ) {
			return;
		}

		printf(
			'<div class="noviq-attestation-admin"><h3>%1$s</h3><p><strong>%2$s</strong> %3$s</p><p><em>%4$s</em></p></div>',
			esc_html__( 'Researcher attestation', 'noviq-core' ),
			esc_html__( 'Accepted', 'noviq-core' ),
			esc_html( (string) $order->get_meta( self::META_TIME ) ),
			esc_html( (string) $order->get_meta( self::META_TEXT ) )
		);
	}

	/**
	 * @param array<string, array{label: string, value: string}> $fields Email fields.
	 * @return array<string, array{label: string, value: string}>
	 */
	public static function email_field( array $fields, bool $sent_to_admin, \WC_Order $order ): array {
		if ( 'yes' !== $order->get_meta( self::META_ACCEPTED ) ) {
			return $fields;
		}

		$fields['noviq_attestation'] = array(
			'label' => __( 'Researcher attestation', 'noviq-core' ),
			'value' => sprintf(
				/* translators: %s: ISO-8601 timestamp. */
				__( 'Accepted %s', 'noviq-core' ),
				(string) $order->get_meta( self::META_TIME )
			),
		);

		return $fields;
	}
}
