<?php
/**
 * Interim PayPal invoice payment gateway.
 *
 * Places orders on hold and sends the customer a PayPal.me link by email.
 * The admin reconciles payment manually and moves the order to Processing.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce payment gateway for PayPal.me invoice links.
 */
final class PaypalInvoiceGateway extends \WC_Payment_Gateway {

	public const ID = 'noviq_paypal_invoice';

	public static function init(): void {
		add_filter( 'woocommerce_payment_gateways', array( self::class, 'register_gateway' ) );
		add_filter( 'woocommerce_email_classes', array( self::class, 'register_email' ) );
		add_filter( 'woocommerce_email_enabled_customer_on_hold_order', array( self::class, 'disable_default_on_hold_email' ), 10, 2 );
	}

	/**
	 * @param array<int, string> $gateways Registered gateway class names.
	 * @return array<int, string>
	 */
	public static function register_gateway( array $gateways ): array {
		$gateways[] = self::class;

		return $gateways;
	}

	/**
	 * @param array<string, \WC_Email> $emails Registered email classes.
	 * @return array<string, \WC_Email>
	 */
	public static function register_email( array $emails ): array {
		$emails['Noviq_Paypal_Invoice_Email'] = new PaypalInvoiceEmail();

		return $emails;
	}

	/**
	 * Skip the generic on-hold email; the PayPal invoice email replaces it.
	 *
	 * @param bool|\WC_Order $enabled Current enabled state or order when Woo passes it.
	 */
	public static function disable_default_on_hold_email( $enabled, $order = null, $email = null ): bool {
		if ( ! $order instanceof \WC_Order ) {
			return (bool) $enabled;
		}

		if ( self::ID !== $order->get_payment_method() ) {
			return (bool) $enabled;
		}

		return false;
	}

	public function __construct() {
		$this->id                 = self::ID;
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = __( 'PayPal Invoice', 'noviq-core' );
		$this->method_description = __( 'Accept orders now and collect payment via a PayPal.me link sent by email. Orders stay on hold until payment is confirmed manually.', 'noviq-core' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 4 );
	}

	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable/Disable', 'noviq-core' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayPal invoice payments', 'noviq-core' ),
				'default' => 'no',
			),
			'title'          => array(
				'title'       => __( 'Title', 'noviq-core' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown at checkout.', 'noviq-core' ),
				'default'     => __( 'Pay via PayPal', 'noviq-core' ),
				'desc_tip'    => true,
			),
			'description'    => array(
				'title'       => __( 'Description', 'noviq-core' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown at checkout.', 'noviq-core' ),
				'default'     => __( 'Place your order now. You will receive an email with a PayPal payment link. Your order ships after payment is confirmed.', 'noviq-core' ),
				'desc_tip'    => true,
			),
			'paypal_handle'  => array(
				'title'       => __( 'PayPal.me handle', 'noviq-core' ),
				'type'        => 'text',
				'description' => __( 'Your PayPal.me username without the URL prefix.', 'noviq-core' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'instructions'   => array(
				'title'       => __( 'Instructions', 'noviq-core' ),
				'type'        => 'textarea',
				'description' => __( 'Shown on the thank-you page and in the payment email.', 'noviq-core' ),
				'default'     => __( 'Please complete payment via PayPal using the link below. Include your order number in the PayPal note if prompted.', 'noviq-core' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Build a PayPal.me URL for an order total.
	 */
	public static function payment_url_for_order( \WC_Order $order ): string {
		$settings = get_option( 'woocommerce_' . self::ID . '_settings', array() );
		$handle   = is_array( $settings ) ? (string) ( $settings['paypal_handle'] ?? '' ) : '';
		$handle   = self::sanitize_handle( $handle );

		if ( '' === $handle ) {
			return '';
		}

		$currency = strtoupper( (string) $order->get_currency() );
		if ( '' === $currency ) {
			$currency = 'USD';
		}

		$amount = number_format( (float) $order->get_total(), 2, '.', '' );

		return sprintf( 'https://paypal.me/%s/%s%s', rawurlencode( $handle ), $amount, rawurlencode( $currency ) );
	}

	/**
	 * Strip URL fragments and leading @ from a PayPal.me handle.
	 */
	public static function sanitize_handle( string $handle ): string {
		$handle = trim( $handle );
		$handle = ltrim( $handle, '@' );

		if ( str_contains( $handle, 'paypal.me/' ) ) {
			$parts  = explode( 'paypal.me/', $handle, 2 );
			$handle = $parts[1] ?? $handle;
		}

		$handle = trim( $handle, '/' );
		$handle = explode( '/', $handle )[0];
		$handle = preg_replace( '/[^a-zA-Z0-9\-_]/', '', $handle ) ?? '';

		return $handle;
	}

	/**
	 * @param int $order_id Order ID.
	 * @return array{result: string, redirect: string}
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			wc_add_notice( __( 'Unable to process this order.', 'noviq-core' ), 'error' );

			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}

		if ( '' === self::sanitize_handle( (string) $this->get_option( 'paypal_handle' ) ) ) {
			wc_add_notice( __( 'PayPal payments are not configured yet. Please contact support.', 'noviq-core' ), 'error' );

			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}

		$order->update_status(
			'on-hold',
			__( 'Awaiting PayPal payment via invoice link.', 'noviq-core' )
		);

		wc_reduce_stock_levels( $order_id );
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	public function thankyou_page( int $order_id ): void {
		$this->render_payment_instructions( $order_id, 'thankyou' );
	}

	/**
	 * Append instructions to Woo emails when this gateway is used.
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Whether the email is for admin.
	 * @param bool      $plain_text    Plain-text email.
	 * @param \WC_Email $email         Email instance.
	 */
	public function email_instructions( \WC_Order $order, bool $sent_to_admin, bool $plain_text, \WC_Email $email ): void {
		if ( $sent_to_admin || self::ID !== $order->get_payment_method() ) {
			return;
		}

		if ( ! in_array( $email->id, array( 'customer_on_hold_order', 'customer_processing_order' ), true ) ) {
			return;
		}

		$this->render_payment_instructions( $order->get_id(), $plain_text ? 'plain' : 'html' );
	}

	/**
	 * @param int    $order_id Order ID.
	 * @param string $context  thankyou|html|plain
	 */
	private function render_payment_instructions( int $order_id, string $context ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order || self::ID !== $order->get_payment_method() ) {
			return;
		}

		$instructions = trim( (string) $this->instructions );
		$paypal_url   = self::payment_url_for_order( $order );

		if ( '' === $instructions && '' === $paypal_url ) {
			return;
		}

		if ( 'plain' === $context ) {
			if ( '' !== $instructions ) {
				echo esc_html( wp_strip_all_tags( $instructions ) ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			if ( '' !== $paypal_url ) {
				echo esc_html__( 'PayPal payment link:', 'noviq-core' ) . ' ' . esc_url( $paypal_url ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			return;
		}

		echo '<div class="noviq-paypal-instructions">';

		if ( '' !== $instructions ) {
			echo wp_kses_post( wpautop( wptexturize( $instructions ) ) );
		}

		if ( '' !== $paypal_url ) {
			printf(
				'<p><a class="button" href="%1$s" style="display:inline-block;padding:12px 24px;background:#0A4DA8;color:#ffffff;text-decoration:none;border-radius:4px;">%2$s</a></p><p><a href="%1$s">%1$s</a></p>',
				esc_url( $paypal_url ),
				esc_html__( 'Pay with PayPal', 'noviq-core' )
			);
		}

		echo '</div>';
	}
}
