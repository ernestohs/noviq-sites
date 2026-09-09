<?php
/**
 * Interim PayPal invoice payment gateway.
 *
 * Places orders on hold and emails a PayPal Invoicing API payment link.
 * The admin reconciles payment manually and moves the order to Processing.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce payment gateway for PayPal invoice payment links.
 */
final class PaypalInvoiceGateway extends \WC_Payment_Gateway {

	public const ID = 'noviq_paypal_invoice';

	public const META_INVOICE_ID  = '_noviq_paypal_invoice_id';
	public const META_PAYMENT_URL = '_noviq_paypal_payment_url';

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
		if ( ! class_exists( \WC_Email::class ) ) {
			return $emails;
		}

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
		$this->method_description = __( 'Accept orders now and collect payment via a PayPal invoice link created through the Invoicing API and sent by email. Orders stay on hold until payment is confirmed manually.', 'noviq-core' );
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

	/**
	 * Keep the existing Client Secret when the password field is left blank.
	 */
	public function process_admin_options(): bool {
		$post_key = 'woocommerce_' . $this->id . '_client_secret';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce settings form.
		if ( isset( $_POST[ $post_key ] ) && '' === trim( (string) wp_unslash( $_POST[ $post_key ] ) ) ) {
			$_POST[ $post_key ] = $this->get_option( 'client_secret', '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		return parent::process_admin_options();
	}

	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'       => array(
				'title'   => __( 'Enable/Disable', 'noviq-core' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayPal invoice payments', 'noviq-core' ),
				'default' => 'no',
			),
			'title'         => array(
				'title'       => __( 'Title', 'noviq-core' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown at checkout.', 'noviq-core' ),
				'default'     => __( 'Pay via PayPal', 'noviq-core' ),
				'desc_tip'    => true,
			),
			'description'   => array(
				'title'       => __( 'Description', 'noviq-core' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown at checkout.', 'noviq-core' ),
				'default'     => __( 'Place your order now. You will receive an email with a PayPal payment link. Your order ships after payment is confirmed.', 'noviq-core' ),
				'desc_tip'    => true,
			),
			'sandbox'       => array(
				'title'       => __( 'Sandbox mode', 'noviq-core' ),
				'type'        => 'checkbox',
				'label'       => __( 'Use PayPal sandbox API endpoints', 'noviq-core' ),
				'default'     => 'yes',
				'description' => __( 'Leave enabled for local and staging. Uncheck only when using Live Client ID and Secret on a signed-off store.', 'noviq-core' ),
			),
			'client_id'     => array(
				'title'       => __( 'Client ID', 'noviq-core' ),
				'type'        => 'text',
				'description' => __( 'PayPal REST API Client ID from the developer dashboard.', 'noviq-core' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'client_secret' => array(
				'title'       => __( 'Client Secret', 'noviq-core' ),
				'type'        => 'password',
				'description' => __( 'PayPal REST API Client Secret. Stored in WordPress options; never commit to git.', 'noviq-core' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'instructions'  => array(
				'title'       => __( 'Instructions', 'noviq-core' ),
				'type'        => 'textarea',
				'description' => __( 'Shown on the thank-you page and in the payment email.', 'noviq-core' ),
				'default'     => __( 'Please complete payment via PayPal using the link below. Include your order number in the PayPal note if prompted.', 'noviq-core' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Payment URL stored on the order after invoice creation.
	 */
	public static function payment_url_for_order( \WC_Order $order ): string {
		$url = (string) $order->get_meta( self::META_PAYMENT_URL, true );

		return esc_url_raw( $url );
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

		$client = PaypalApiClient::from_gateway_settings(
			array(
				'sandbox'       => $this->get_option( 'sandbox', 'yes' ),
				'client_id'     => $this->get_option( 'client_id', '' ),
				'client_secret' => $this->get_option( 'client_secret', '' ),
			)
		);

		if ( ! $client->is_configured() ) {
			wc_add_notice( __( 'PayPal payments are not configured yet. Please contact support.', 'noviq-core' ), 'error' );

			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}

		$result = $client->create_payment_link_for_order( $order );
		if ( is_wp_error( $result ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: error message */
					__( 'PayPal invoice creation failed: %s', 'noviq-core' ),
					$result->get_error_message()
				)
			);
			wc_add_notice( __( 'Unable to create a PayPal payment link. Please try again or contact support.', 'noviq-core' ), 'error' );

			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}

		$order->update_meta_data( self::META_INVOICE_ID, $result['invoice_id'] );
		$order->update_meta_data( self::META_PAYMENT_URL, $result['payment_url'] );
		$order->save();

		$order->update_status(
			'on-hold',
			sprintf(
				/* translators: %s: PayPal invoice id */
				__( 'Awaiting PayPal payment. Invoice %s.', 'noviq-core' ),
				$result['invoice_id']
			)
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
