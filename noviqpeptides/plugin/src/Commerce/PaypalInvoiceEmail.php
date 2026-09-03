<?php
/**
 * PayPal invoice payment instructions email.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Customer email with order summary and PayPal.me payment link.
 */
final class PaypalInvoiceEmail extends \WC_Email {

	public function __construct() {
		$this->id             = 'noviq_paypal_invoice_instructions';
		$this->customer_email = true;
		$this->title          = __( 'PayPal invoice instructions', 'noviq-core' );
		$this->description    = __( 'Sent to customers when an order is placed with the PayPal invoice gateway.', 'noviq-core' );
		$this->template_html  = 'emails/paypal-invoice-instructions.php';
		$this->template_plain = 'emails/plain/paypal-invoice-instructions.php';
		$this->template_base  = NOVIQ_CORE_PATH . 'templates/';
		$this->placeholders   = array(
			'{order_date}'   => '',
			'{order_number}' => '',
		);

		add_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $this, 'trigger' ), 10, 2 );
		add_action( 'woocommerce_order_status_failed_to_on-hold_notification', array( $this, 'trigger' ), 10, 2 );

		parent::__construct();
	}

	/**
	 * @param int            $order_id Order ID.
	 * @param \WC_Order|bool $order    Order object when available.
	 */
	public function trigger( $order_id, $order = false ): void {
		$this->setup_locale();

		if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order instanceof \WC_Order ) {
			$this->restore_locale();

			return;
		}

		if ( PaypalInvoiceGateway::ID !== $order->get_payment_method() ) {
			$this->restore_locale();

			return;
		}

		$this->object                         = $order;
		$this->recipient                      = $this->object->get_billing_email();
		$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
		$this->placeholders['{order_number}'] = $this->object->get_order_number();

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject(): string {
		return __( 'Payment instructions for your order #{order_number}', 'noviq-core' );
	}

	public function get_default_heading(): string {
		return __( 'Complete your PayPal payment', 'noviq-core' );
	}

	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
				'paypal_url'         => $this->object instanceof \WC_Order ? PaypalInvoiceGateway::payment_url_for_order( $this->object ) : '',
				'instructions'       => $this->gateway_instructions(),
			),
			'',
			$this->template_base
		);
	}

	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
				'paypal_url'         => $this->object instanceof \WC_Order ? PaypalInvoiceGateway::payment_url_for_order( $this->object ) : '',
				'instructions'       => $this->gateway_instructions(),
			),
			'',
			$this->template_base
		);
	}

	private function gateway_instructions(): string {
		$settings = get_option( 'woocommerce_' . PaypalInvoiceGateway::ID . '_settings', array() );

		return is_array( $settings ) ? trim( (string) ( $settings['instructions'] ?? '' ) ) : '';
	}
}
