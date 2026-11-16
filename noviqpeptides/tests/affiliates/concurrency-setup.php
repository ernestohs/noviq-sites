<?php
/**
 * Prepare one paid order without recording commission, for the lock test.
 *
 * @package Noviq\Core
 */

use Noviq\Core\Affiliates\Attribution;
use Noviq\Core\Affiliates\Codes;
use Noviq\Core\Affiliates\Commissions;
use Noviq\Core\Affiliates\Program;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

add_filter( 'pre_wp_mail', static fn() => true );

$recruiter_id = Program::create_recruiter( 'Concurrency Rec', null );
$code         = Program::format_code( 'CONCUR' . strtoupper( wp_generate_password( 4, false, false ) ) );
$coupon_id    = Codes::ensure_coupon( $code );
$rep_id       = Program::create_rep( 'Concurrency Rep', $code, (int) $coupon_id, (int) $recruiter_id, null );
Codes::flush_cache();

$product = new WC_Product_Simple();
$product->set_name( 'Concurrency vial' );
$product->set_regular_price( '100' );
$product->set_status( 'publish' );
$product->set_catalog_visibility( 'hidden' );
$product->save();

$email   = 'concurrency-' . strtolower( wp_generate_password( 6, false, false ) ) . '@example.com';
$user_id = wp_insert_user(
	array(
		'user_login' => 'concurrency' . strtolower( wp_generate_password( 6, false, false ) ),
		'user_email' => $email,
		'user_pass'  => wp_generate_password( 24 ),
		'role'       => 'customer',
	)
);

$order = wc_create_order(
	array(
		'customer_id' => (int) $user_id,
		'status'      => 'pending',
	)
);
$order->add_product( $product, 1 );
$order->set_billing_email( $email );
$order->set_billing_first_name( 'Concurrency' );
$order->set_billing_last_name( 'Buyer' );
$order->set_payment_method( 'cheque' );
$order->apply_coupon( $code );
$order->calculate_totals( false );
$order->save();
Attribution::capture_order( $order );

remove_action( 'woocommerce_payment_complete', array( Commissions::class, 'on_paid' ), 20 );
remove_action( 'woocommerce_order_status_processing', array( Commissions::class, 'on_paid' ), 20 );
remove_action( 'woocommerce_order_status_completed', array( Commissions::class, 'on_paid' ), 20 );
$order->payment_complete( 'concurrency' );

$state = array(
	'order_id'     => $order->get_id(),
	'rep_id'       => (int) $rep_id,
	'recruiter_id' => (int) $recruiter_id,
	'coupon_id'    => (int) $coupon_id,
	'product_id'   => $product->get_id(),
	'user_id'      => (int) $user_id,
	'email'        => $email,
);
file_put_contents( '/tmp/noviq-aff-concurrency.json', wp_json_encode( $state ) );
echo $order->get_id() . "\n";
