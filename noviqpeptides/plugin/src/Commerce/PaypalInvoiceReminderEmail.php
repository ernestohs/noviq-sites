<?php
/**
 * PayPal invoice payment reminder email.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Follow-up customer email with the PayPal payment link for an on-hold order.
 * Triggered by PaymentReminders, not by WooCommerce status transitions.
 */
final class PaypalInvoiceReminderEmail extends \WC_Email {

	/** @var int */
	private int $sequence = 0;

	public function __construct() {
		$this->id             = 'noviq_paypal_invoice_reminder';
		$this->customer_email = true;
		$this->title          = __( 'PayPal invoice payment reminder', 'noviq-core' );
		$this->description    = __( 'Sent on a schedule while a PayPal invoice order remains unpaid on hold.', 'noviq-core' );
		$this->template_html  = 'emails/paypal-invoice-reminder.php';
		$this->template_plain = 'emails/plain/paypal-invoice-reminder.php';
		$this->template_base  = NOVIQ_CORE_PATH . 'templates/';
		$this->placeholders   = array(
			'{order_date}'   => '',
			'{order_number}' => '',
		);

		parent::__construct();
	}

	/**
	 * @param int $order_id Order ID.
	 * @param int $sequence 1-based reminder number.
	 */
	public function trigger( int $order_id, int $sequence = 1 ): void {
		$this->setup_locale();

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			$this->restore_locale();

			return;
		}

		if ( PaypalInvoiceGateway::ID !== $order->get_payment_method() ) {
			$this->restore_locale();

			return;
		}

		$this->object                         = $order;
		$this->sequence                       = max( 1, $sequence );
		$this->recipient                      = $this->object->get_billing_email();
		$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
		$this->placeholders['{order_number}'] = $this->object->get_order_number();

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject(): string {
		return __( 'Reminder: order #{order_number} is awaiting payment', 'noviq-core' );
	}

	public function get_default_heading(): string {
		return __( 'Your order is awaiting payment', 'noviq-core' );
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
				'sequence'           => $this->sequence,
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
				'sequence'           => $this->sequence,
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
