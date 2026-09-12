<?php
/**
 * PayPal REST client for Invoicing API payment links.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Creates PayPal invoices and returns the payer-facing payment URL.
 */
final class PaypalApiClient {

	private const SANDBOX_BASE = 'https://api-m.sandbox.paypal.com';
	private const LIVE_BASE    = 'https://api-m.paypal.com';
	private const LOG_SOURCE   = 'noviq-paypal-invoice';
	private const OAUTH_SCOPE  = 'https://uri.paypal.com/services/invoicing/invoices/readwrite';

	private string $client_id;
	private string $client_secret;
	private bool $sandbox;

	public function __construct( string $client_id, string $client_secret, bool $sandbox = true ) {
		$this->client_id     = trim( $client_id );
		$this->client_secret = trim( $client_secret );
		$this->sandbox       = $sandbox;
	}

	public static function from_gateway_settings( array $settings ): self {
		$sandbox = ! isset( $settings['sandbox'] ) || 'yes' === $settings['sandbox'];

		return new self(
			(string) ( $settings['client_id'] ?? '' ),
			(string) ( $settings['client_secret'] ?? '' ),
			$sandbox
		);
	}

	public function is_configured(): bool {
		return '' !== $this->client_id && '' !== $this->client_secret;
	}

	/**
	 * Create a draft invoice, mark it payable without PayPal emailing the buyer,
	 * and return the recipient payment URL.
	 *
	 * @return array{invoice_id: string, payment_url: string}|\WP_Error
	 */
	public function create_payment_link_for_order( \WC_Order $order ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'noviq_paypal_not_configured', __( 'PayPal API credentials are not configured.', 'noviq-core' ) );
		}

		$invoice = $this->create_invoice_payment_link( $order );
		if ( ! is_wp_error( $invoice ) ) {
			return $invoice;
		}

		if ( $this->should_fallback_to_checkout_order( $invoice ) ) {
			$this->log( 'info', 'Invoicing API unavailable; creating Checkout order payment link instead.' );

			return $this->create_checkout_order_payment_link( $order );
		}

		return $invoice;
	}

	/**
	 * @return array{invoice_id: string, payment_url: string}|\WP_Error
	 */
	private function create_invoice_payment_link( \WC_Order $order ) {
		$payload = $this->build_invoice_payload( $order );
		$created = $this->request( 'POST', '/v2/invoicing/invoices', $payload, true );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$invoice_id = (string) ( $created['id'] ?? '' );
		if ( '' === $invoice_id ) {
			$this->log( 'error', 'Create invoice response missing id.', array( 'body' => $created ) );

			return new \WP_Error( 'noviq_paypal_create_failed', __( 'PayPal did not return an invoice id.', 'noviq-core' ) );
		}

		$sent = $this->request(
			'POST',
			'/v2/invoicing/invoices/' . rawurlencode( $invoice_id ) . '/send',
			array(
				'send_to_recipient' => false,
				'send_to_invoicer'  => false,
			),
			true
		);

		if ( is_wp_error( $sent ) ) {
			return $sent;
		}

		$payment_url = $this->extract_recipient_view_url( is_array( $sent ) ? $sent : array() );
		if ( '' === $payment_url ) {
			$details = $this->request( 'GET', '/v2/invoicing/invoices/' . rawurlencode( $invoice_id ), null, true );
			if ( is_wp_error( $details ) ) {
				return $details;
			}
			$payment_url = $this->extract_recipient_view_url( $details );
		}

		if ( '' === $payment_url ) {
			$this->log( 'error', 'Invoice sent but recipient_view_url missing.', array( 'invoice_id' => $invoice_id ) );

			return new \WP_Error( 'noviq_paypal_url_missing', __( 'PayPal did not return a payment link.', 'noviq-core' ) );
		}

		return array(
			'invoice_id'  => $invoice_id,
			'payment_url' => $payment_url,
		);
	}

	/**
	 * @return array{invoice_id: string, payment_url: string}|\WP_Error
	 */
	private function create_checkout_order_payment_link( \WC_Order $order ) {
		$created = $this->request( 'POST', '/v2/checkout/orders', $this->build_checkout_order_payload( $order ) );
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$order_id = (string) ( $created['id'] ?? '' );
		if ( '' === $order_id ) {
			$this->log( 'error', 'Create checkout order response missing id.', array( 'body' => $created ) );

			return new \WP_Error( 'noviq_paypal_create_failed', __( 'PayPal did not return an order id.', 'noviq-core' ) );
		}

		$payment_url = $this->extract_checkout_approve_url( is_array( $created ) ? $created : array() );
		if ( '' === $payment_url ) {
			$this->log( 'error', 'Checkout order created but approve link missing.', array( 'order_id' => $order_id ) );

			return new \WP_Error( 'noviq_paypal_url_missing', __( 'PayPal did not return a payment link.', 'noviq-core' ) );
		}

		return array(
			'invoice_id'  => $order_id,
			'payment_url' => $payment_url,
		);
	}

	private function should_fallback_to_checkout_order( \WP_Error $error ): bool {
		if ( 'noviq_paypal_api' !== $error->get_error_code() ) {
			return false;
		}

		$data = $error->get_error_data();
		if ( ! is_array( $data ) ) {
			return false;
		}

		return 'NOT_AUTHORIZED' === ( $data['paypal_name'] ?? '' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_checkout_order_payload( \WC_Order $order ): array {
		$currency = strtoupper( (string) $order->get_currency() );
		if ( '' === $currency ) {
			$currency = 'USD';
		}

		$order_number = (string) $order->get_order_number();
		$return_url   = $order->get_checkout_order_received_url();
		$cancel_url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );

		return array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array(
				array(
					'reference_id' => (string) $order->get_id(),
					'custom_id'    => $order_number,
					'description'  => sprintf(
						/* translators: %s: order number */
						__( 'Noviq Peptides order #%s', 'noviq-core' ),
						$order_number
					),
					'amount'       => array(
						'currency_code' => $currency,
						'value'         => $this->money( (float) $order->get_total() ),
					),
				),
			),
			'application_context' => array(
				'return_url'  => $return_url,
				'cancel_url'  => $cancel_url,
				'brand_name'  => get_bloginfo( 'name' ),
				'user_action' => 'PAY_NOW',
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_invoice_payload( \WC_Order $order ): array {
		$currency = strtoupper( (string) $order->get_currency() );
		if ( '' === $currency ) {
			$currency = 'USD';
		}

		$invoice_number = (string) $order->get_order_number();
		$items          = $this->build_line_items( $order, $currency );
		$shipping_total = (float) $order->get_shipping_total();

		$payload = array(
			'detail'             => array(
				'invoice_number' => $invoice_number,
				'invoice_date'   => gmdate( 'Y-m-d' ),
				'currency_code'  => $currency,
				'note'           => sprintf(
					/* translators: %s: order number */
					__( 'Order #%s. Please include this order number with your payment.', 'noviq-core' ),
					$invoice_number
				),
				'payment_term'   => array(
					'term_type' => 'DUE_ON_RECEIPT',
				),
			),
			'primary_recipients' => array(
				array(
					'billing_info' => $this->build_billing_info( $order ),
				),
			),
			'items'              => $items,
			'configuration'      => array(
				'allow_tip'                     => false,
				'tax_calculated_after_discount' => true,
				'tax_inclusive'                 => false,
				'partial_payment'               => array(
					'allow_partial_payment' => false,
				),
			),
		);

		$shipping_info = $this->build_shipping_info( $order );
		if ( null !== $shipping_info ) {
			$payload['primary_recipients'][0]['shipping_info'] = $shipping_info;
		}

		$breakdown = array();
		if ( $shipping_total > 0 ) {
			$shipping = array(
				'amount' => array(
					'currency_code' => $currency,
					'value'         => $this->money( $shipping_total ),
				),
			);
			$shipping_tax = (float) $order->get_shipping_tax();
			if ( $shipping_tax > 0 ) {
				$shipping['tax'] = array(
					'name'    => __( 'Tax', 'noviq-core' ),
					'percent' => $this->money( round( ( $shipping_tax / $shipping_total ) * 100, 2 ) ),
				);
			}
			$breakdown['shipping'] = $shipping;
		}

		$fee_total = 0.0;
		foreach ( $order->get_fees() as $fee ) {
			if ( $fee instanceof \WC_Order_Item_Fee ) {
				$fee_total += (float) $fee->get_total();
			}
		}
		if ( $fee_total > 0 ) {
			$breakdown['custom'] = array(
				'label'  => __( 'Fees', 'noviq-core' ),
				'amount' => array(
					'currency_code' => $currency,
					'value'         => $this->money( $fee_total ),
				),
			);
		}

		if ( array() !== $breakdown ) {
			$payload['amount'] = array(
				'breakdown' => $breakdown,
			);
		}

		return $payload;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function build_line_items( \WC_Order $order, string $currency ): array {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$qty = (float) $item->get_quantity();
			if ( $qty <= 0 ) {
				continue;
			}

			$line_total = (float) $item->get_total();
			$unit       = $line_total / $qty;
			$name       = $item->get_name();
			if ( '' === $name ) {
				$name = __( 'Item', 'noviq-core' );
			}
			$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 120 ) : substr( $name, 0, 120 );

			$line = array(
				'name'            => $name,
				'quantity'        => $this->quantity( $qty ),
				'unit_amount'     => array(
					'currency_code' => $currency,
					'value'         => $this->money( $unit ),
				),
				'unit_of_measure' => 'QUANTITY',
			);

			$tax_total = (float) $item->get_total_tax();
			if ( $tax_total > 0 && $line_total > 0 ) {
				$percent = round( ( $tax_total / $line_total ) * 100, 2 );
				$line['tax'] = array(
					'name'    => __( 'Tax', 'noviq-core' ),
					'percent' => $this->money( $percent ),
				);
			}

			$items[] = $line;
		}

		if ( array() === $items ) {
			$items[] = array(
				'name'        => sprintf(
					/* translators: %s: order number */
					__( 'Order #%s', 'noviq-core' ),
					$order->get_order_number()
				),
				'quantity'    => '1',
				'unit_amount' => array(
					'currency_code' => $currency,
					'value'         => $this->money( (float) $order->get_total() ),
				),
				'unit_of_measure' => 'QUANTITY',
			);
		}

		return $items;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_billing_info( \WC_Order $order ): array {
		$info = array(
			'email_address' => (string) $order->get_billing_email(),
		);

		$given  = trim( (string) $order->get_billing_first_name() );
		$surname = trim( (string) $order->get_billing_last_name() );
		if ( '' !== $given || '' !== $surname ) {
			$info['name'] = array_filter(
				array(
					'given_name' => $given,
					'surname'    => $surname,
				)
			);
		}

		$address = $this->build_address(
			(string) $order->get_billing_address_1(),
			(string) $order->get_billing_address_2(),
			(string) $order->get_billing_city(),
			(string) $order->get_billing_state(),
			(string) $order->get_billing_postcode(),
			(string) $order->get_billing_country()
		);
		if ( null !== $address ) {
			$info['address'] = $address;
		}

		return $info;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function build_shipping_info( \WC_Order $order ): ?array {
		$line1 = trim( (string) $order->get_shipping_address_1() );
		if ( '' === $line1 ) {
			return null;
		}

		$info = array();

		$given   = trim( (string) $order->get_shipping_first_name() );
		$surname = trim( (string) $order->get_shipping_last_name() );
		if ( '' !== $given || '' !== $surname ) {
			$info['name'] = array_filter(
				array(
					'given_name' => $given,
					'surname'    => $surname,
				)
			);
		}

		$address = $this->build_address(
			$line1,
			(string) $order->get_shipping_address_2(),
			(string) $order->get_shipping_city(),
			(string) $order->get_shipping_state(),
			(string) $order->get_shipping_postcode(),
			(string) $order->get_shipping_country()
		);
		if ( null !== $address ) {
			$info['address'] = $address;
		}

		return array() !== $info ? $info : null;
	}

	/**
	 * @return array<string, string>|null
	 */
	private function build_address(
		string $line1,
		string $line2,
		string $city,
		string $state,
		string $postcode,
		string $country
	): ?array {
		$line1 = trim( $line1 );
		if ( '' === $line1 || '' === trim( $country ) ) {
			return null;
		}

		$address = array(
			'address_line_1' => $line1,
			'admin_area_2'   => trim( $city ),
			'admin_area_1'   => trim( $state ),
			'postal_code'    => trim( $postcode ),
			'country_code'   => strtoupper( trim( $country ) ),
		);

		$line2 = trim( $line2 );
		if ( '' !== $line2 ) {
			$address['address_line_2'] = $line2;
		}

		return array_filter( $address, static fn( string $v ): bool => '' !== $v );
	}

	/**
	 * @param array<string, mixed> $invoice
	 */
	private function extract_recipient_view_url( array $invoice ): string {
		$detail = $invoice['detail'] ?? null;
		if ( ! is_array( $detail ) ) {
			return '';
		}

		$metadata = $detail['metadata'] ?? null;
		if ( ! is_array( $metadata ) ) {
			return '';
		}

		$url = $metadata['recipient_view_url'] ?? '';

		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param array<string, mixed> $order_response
	 */
	private function extract_checkout_approve_url( array $order_response ): string {
		foreach ( (array) ( $order_response['links'] ?? array() ) as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}

			$rel = $link['rel'] ?? '';
			if ( in_array( $rel, array( 'approve', 'payer-action' ), true ) ) {
				$href = $link['href'] ?? '';

				return is_string( $href ) ? $href : '';
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed>|null $body
	 * @return array<string, mixed>|\WP_Error
	 */
	private function request( string $method, string $path, ?array $body = null, bool $invoicing_token = false ) {
		$token = $this->get_access_token( $invoicing_token );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $this->base_url() . $path, $args );
		if ( is_wp_error( $response ) ) {
			$this->log( 'error', 'HTTP request failed: ' . $response->get_error_message(), array( 'path' => $path ) );

			return new \WP_Error( 'noviq_paypal_http', __( 'Unable to reach PayPal. Please try again.', 'noviq-core' ) );
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw      = (string) wp_remote_retrieve_body( $response );
		$decoded  = json_decode( $raw, true );
		$payload  = is_array( $decoded ) ? $decoded : array();

		if ( $code < 200 || $code >= 300 ) {
			$this->log(
				'error',
				sprintf( 'PayPal API %s %s failed with HTTP %d.', $method, $path, $code ),
				array(
					'name'     => $payload['name'] ?? '',
					'message'  => $payload['message'] ?? '',
					'debug_id' => $payload['debug_id'] ?? '',
				)
			);

			return new \WP_Error(
				'noviq_paypal_api',
				__( 'PayPal could not create a payment link for this order.', 'noviq-core' ),
				array(
					'paypal_name'    => $payload['name'] ?? '',
					'paypal_message' => $payload['message'] ?? '',
					'http_code'      => $code,
				)
			);
		}

		return $payload;
	}

	/**
	 * @return string|\WP_Error
	 */
	private function get_access_token( bool $invoicing = false ) {
		$scope_key = $invoicing ? self::OAUTH_SCOPE : 'default';
		$cache_key = 'noviq_paypal_token_' . md5( $this->client_id . ( $this->sandbox ? '1' : '0' ) . $scope_key );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$body = array(
			'grant_type' => 'client_credentials',
		);
		if ( $invoicing ) {
			$body['scope'] = self::OAUTH_SCOPE;
		}

		$response = wp_remote_post(
			$this->base_url() . '/v1/oauth2/token',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ),
					'Accept'        => 'application/json',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'error', 'OAuth request failed: ' . $response->get_error_message() );

			return new \WP_Error( 'noviq_paypal_oauth', __( 'Unable to authenticate with PayPal.', 'noviq-core' ) );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$token   = is_array( $decoded ) ? (string) ( $decoded['access_token'] ?? '' ) : '';
		$expires = is_array( $decoded ) ? (int) ( $decoded['expires_in'] ?? 0 ) : 0;

		if ( 200 !== $code || '' === $token ) {
			$this->log(
				'error',
				sprintf( 'OAuth failed with HTTP %d.', $code ),
				array(
					'name'     => is_array( $decoded ) ? ( $decoded['error'] ?? '' ) : '',
					'debug_id' => is_array( $decoded ) ? ( $decoded['debug_id'] ?? '' ) : '',
				)
			);

			return new \WP_Error( 'noviq_paypal_oauth', __( 'Unable to authenticate with PayPal.', 'noviq-core' ) );
		}

		$ttl = max( 60, $expires - 60 );
		set_transient( $cache_key, $token, $ttl );

		return $token;
	}

	private function base_url(): string {
		return $this->sandbox ? self::SANDBOX_BASE : self::LIVE_BASE;
	}

	private function money( float $amount ): string {
		return number_format( $amount, 2, '.', '' );
	}

	private function quantity( float $qty ): string {
		if ( abs( $qty - round( $qty ) ) < 0.00001 ) {
			return (string) (int) round( $qty );
		}

		return rtrim( rtrim( number_format( $qty, 4, '.', '' ), '0' ), '.' );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger = wc_get_logger();
		$extra  = array() !== $context ? ' ' . wp_json_encode( $context ) : '';
		$logger->log( $level, $message . $extra, array( 'source' => self::LOG_SOURCE ) );
	}
}
