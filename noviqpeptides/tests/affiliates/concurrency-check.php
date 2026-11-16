<?php
/**
 * Assert the parallel payment calls produced exactly one earn per payee, then delete fixtures.
 *
 * @package Noviq\Core
 */

use Noviq\Core\Affiliates\Attribution;
use Noviq\Core\Affiliates\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$raw   = file_get_contents( '/tmp/noviq-aff-concurrency.json' );
$state = is_string( $raw ) ? json_decode( $raw, true ) : null;
if ( ! is_array( $state ) || empty( $state['order_id'] ) ) {
	fwrite( STDERR, "missing concurrency fixture\n" );
	exit( 1 );
}

global $wpdb;
$order_id = (int) $state['order_id'];
$rows     = $wpdb->get_results(
	$wpdb->prepare(
		'SELECT payee_type, entry_type, amount_cents FROM ' . Schema::table( 'ledger' ) . ' WHERE order_id = %d AND entry_type = %s',
		$order_id,
		'earn'
	),
	ARRAY_A
);
$rows  = is_array( $rows ) ? $rows : array();
$ok    = 2 === count( $rows );
$seen  = array();
foreach ( $rows as $row ) {
	$seen[ (string) $row['payee_type'] ] = (int) $row['amount_cents'];
}
$ok = $ok && 2250 === ( $seen['rep'] ?? 0 ) && 630 === ( $seen['recruiter'] ?? 0 );

$order = wc_get_order( $order_id );
if ( $order instanceof WC_Order ) {
	foreach ( $order->get_refunds() as $refund ) {
		if ( ! $refund->get_date_created( 'edit' ) ) {
			$refund->set_date_created( time() );
		}
		$refund->delete( true );
	}
	if ( ! $order->get_date_created( 'edit' ) ) {
		$order->set_date_created( time() );
	}
	remove_action( 'woocommerce_before_delete_order', 'wc_update_total_sales_counts' );
	$order->delete( true );
}
$wpdb->delete( Schema::table( 'ledger' ), array( 'order_id' => $order_id ), array( '%d' ) );
$wpdb->delete( Schema::table( 'orders' ), array( 'order_id' => $order_id ), array( '%d' ) );
$hash = Attribution::email_hash( (string) $state['email'] );
if ( '' !== $hash ) {
	$wpdb->delete( Schema::table( 'customers' ), array( 'email_hash' => $hash ), array( '%s' ) );
}
$wpdb->delete( Schema::table( 'assignments' ), array( 'rep_id' => (int) $state['rep_id'] ), array( '%d' ) );
$wpdb->delete( Schema::table( 'reps' ), array( 'id' => (int) $state['rep_id'] ), array( '%d' ) );
$wpdb->delete( Schema::table( 'recruiters' ), array( 'id' => (int) $state['recruiter_id'] ), array( '%d' ) );
wp_delete_post( (int) $state['coupon_id'], true );
wp_delete_post( (int) $state['product_id'], true );
wp_delete_user( (int) $state['user_id'] );
unlink( '/tmp/noviq-aff-concurrency.json' );

if ( ! $ok ) {
	fwrite( STDERR, 'concurrency ledger rows: ' . wp_json_encode( $rows ) . "\n" );
	exit( 1 );
}

echo "concurrency ok\n";
