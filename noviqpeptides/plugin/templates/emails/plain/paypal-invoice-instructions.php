<?php
/**
 * PayPal invoice instructions email (plain text).
 *
 * @package Noviq\Core
 * @var \WC_Order $order
 * @var string    $email_heading
 * @var string    $additional_content
 * @var bool      $sent_to_admin
 * @var bool      $plain_text
 * @var \WC_Email $email
 * @var string    $paypal_url
 * @var string    $instructions
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

printf(
	/* translators: %s: customer first name */
	esc_html__( 'Hi %s,', 'noviq-core' ) . "\n\n",
	esc_html( $order->get_billing_first_name() )
);

echo esc_html__( 'Thank you for your order. Please complete payment via PayPal using the link below.', 'noviq-core' ) . "\n\n";

if ( '' !== $instructions ) {
	echo esc_html( wp_strip_all_tags( $instructions ) ) . "\n\n";
}

if ( '' !== $paypal_url ) {
	echo esc_html__( 'PayPal payment link:', 'noviq-core' ) . ' ' . esc_url( $paypal_url ) . "\n\n";
}

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

if ( '' !== $additional_content ) {
	echo esc_html( wp_strip_all_tags( $additional_content ) ) . "\n";
}

echo "\n\n----------------------------------------\n\n";

do_action( 'woocommerce_email_footer', $email );
