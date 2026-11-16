<?php
/**
 * Affiliate integration scenarios.
 *
 * Run inside the wpcli container:
 *   wp eval-file /tests/affiliates/run.php
 *
 * HPOS order saves issue their own COMMIT, so a surrounding transaction cannot
 * roll the order back. Each scenario still opens a transaction and rolls it
 * back, then deletes every row it recorded.
 *
 * @package Noviq\Core
 */

use Noviq\Core\Affiliates\Attribution;
use Noviq\Core\Affiliates\Codes;
use Noviq\Core\Affiliates\Commissions;
use Noviq\Core\Affiliates\Money;
use Noviq\Core\Affiliates\Program;
use Noviq\Core\Affiliates\Reports;
use Noviq\Core\Affiliates\Schema;
use Noviq\Core\Affiliates\Viewer;
use Noviq\Core\Commerce\VolumeBreaks;
use Noviq\Core\Meta;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Load this file with wp eval-file.\n" );
	exit( 1 );
}

add_filter( 'pre_wp_mail', static fn() => true );

/**
 * Failure count lives in a function static because wp eval runs this file
 * inside a function, where a bare $failed is not the global aff_assert writes.
 */
function &aff_failures(): int {
	static $count = 0;

	return $count;
}

/**
 * @param array<string, list<int|string>> $bag Fixture ids.
 */
function aff_bag(): array {
	return array(
		'orders'     => array(),
		'users'      => array(),
		'emails'     => array(),
		'coupons'    => array(),
		'products'   => array(),
		'reps'       => array(),
		'recruiters' => array(),
	);
}

function aff_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		echo "PASS {$label}\n";
		return;
	}
	$count = &aff_failures();
	++$count;
	echo "FAIL {$label}\n";
}

/**
 * @param array<string, list<int|string>> $bag Fixture ids.
 * @return array{recruiter_id: int, rep_id: int, code: string}
 */
function aff_team( array &$bag, ?int $recruiter_id = null ): array {
	if ( null === $recruiter_id ) {
		$recruiter_id = Program::create_recruiter( 'Rec ' . wp_generate_password( 6, false, false ), null );
		if ( is_wp_error( $recruiter_id ) ) {
			throw new RuntimeException( $recruiter_id->get_error_message() );
		}
		$bag['recruiters'][] = $recruiter_id;
	}

	$code      = Program::format_code( 'T' . strtoupper( wp_generate_password( 8, false, false ) ) );
	$coupon_id = Codes::ensure_coupon( $code );
	if ( is_wp_error( $coupon_id ) ) {
		throw new RuntimeException( $coupon_id->get_error_message() );
	}
	$bag['coupons'][] = $coupon_id;

	$rep_id = Program::create_rep( 'Rep ' . $code, $code, $coupon_id, $recruiter_id, null );
	if ( is_wp_error( $rep_id ) ) {
		throw new RuntimeException( $rep_id->get_error_message() );
	}
	$bag['reps'][] = $rep_id;
	Codes::flush_cache();

	return array(
		'recruiter_id' => (int) $recruiter_id,
		'rep_id'       => (int) $rep_id,
		'code'         => $code,
	);
}

/**
 * @param array<string, list<int|string>> $bag Fixture ids.
 */
function aff_product( array &$bag, string $price = '100' ): \WC_Product_Simple {
	$product = new \WC_Product_Simple();
	$product->set_name( 'Affiliate test vial' );
	$product->set_regular_price( $price );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'hidden' );
	$product->save();
	$bag['products'][] = $product->get_id();

	return $product;
}

/**
 * @param array<string, list<int|string>> $bag Fixture ids.
 * @return array{id: int, email: string}
 */
function aff_customer( array &$bag ): array {
	$email   = 'aff' . strtolower( wp_generate_password( 8, false, false ) ) . '@example.com';
	$user_id = wp_insert_user(
		array(
			'user_login' => 'aff' . strtolower( wp_generate_password( 8, false, false ) ),
			'user_email' => $email,
			'user_pass'  => wp_generate_password( 24 ),
			'role'       => 'customer',
		)
	);
	if ( is_wp_error( $user_id ) ) {
		throw new RuntimeException( $user_id->get_error_message() );
	}
	$bag['users'][]  = (int) $user_id;
	$bag['emails'][] = $email;

	return array(
		'id'    => (int) $user_id,
		'email' => $email,
	);
}

/**
 * @param array<string, list<int|string>> $bag Fixture ids.
 */
function aff_order( array &$bag, int $customer_id, string $email, \WC_Product $product, ?string $coupon, int $qty = 1 ): \WC_Order {
	$order = wc_create_order(
		array(
			'customer_id' => $customer_id,
			'status'      => 'pending',
		)
	);
	if ( is_wp_error( $order ) || ! $order instanceof \WC_Order ) {
		throw new RuntimeException( 'Could not create order.' );
	}
	$order->add_product( $product, $qty );
	$order->set_billing_email( $email );
	$order->set_billing_first_name( 'Test' );
	$order->set_billing_last_name( 'Buyer' );
	$order->set_payment_method( 'cheque' );
	if ( null !== $coupon && '' !== $coupon ) {
		$applied = $order->apply_coupon( $coupon );
		if ( is_wp_error( $applied ) ) {
			throw new RuntimeException( $applied->get_error_message() );
		}
	}
	$order->calculate_totals( false );
	$order->save();
	$bag['orders'][] = $order->get_id();

	return $order;
}

function aff_pay( \WC_Order $order ): void {
	$order->payment_complete( 'aff-test-' . $order->get_id() );
}

/**
 * @return list<array<string, mixed>>
 */
function aff_ledger( int $order_id ): array {
	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . Schema::table( 'ledger' ) . ' WHERE order_id = %d ORDER BY id ASC',
			$order_id
		),
		ARRAY_A
	);

	return is_array( $rows ) ? $rows : array();
}

function aff_amount( int $order_id, string $payee ): int {
	foreach ( aff_ledger( $order_id ) as $row ) {
		if ( (string) $row['payee_type'] === $payee && 'earn' === (string) $row['entry_type'] ) {
			return (int) $row['amount_cents'];
		}
	}

	return 0;
}

/**
 * @param array<string, list<int|string>> $bag Fixture ids.
 */
function aff_cleanup( array $bag ): void {
	global $wpdb;

	foreach ( $bag['orders'] as $order_id ) {
		$order_id = (int) $order_id;
		$order    = wc_get_order( $order_id );
		if ( $order instanceof \WC_Order ) {
			foreach ( $order->get_refunds() as $refund ) {
				if ( ! $refund->get_date_created( 'edit' ) ) {
					$refund->set_date_created( time() );
				}
				$refund->delete( true );
			}
			// HPOS compatibility mode backfills a post during delete and fatals
			// when date_created is missing. The sales-count hook saves the order
			// again from inside delete, which is the path that hits that bug.
			if ( ! $order->get_date_created( 'edit' ) ) {
				$order->set_date_created( time() );
			}
			remove_action( 'woocommerce_before_delete_order', 'wc_update_total_sales_counts' );
			$order->delete( true );
		}
		$wpdb->delete( Schema::table( 'ledger' ), array( 'order_id' => $order_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'orders' ), array( 'order_id' => $order_id ), array( '%d' ) );
		$customer_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . Schema::table( 'customers' ) . ' WHERE source_order_id = %d',
				$order_id
			)
		);
		foreach ( (array) $customer_ids as $row_id ) {
			$wpdb->delete( Schema::table( 'customer_history' ), array( 'customer_row_id' => (int) $row_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'customers' ), array( 'source_order_id' => $order_id ), array( '%d' ) );
	}

	foreach ( $bag['emails'] as $email ) {
		$hash = Attribution::email_hash( (string) $email );
		if ( '' === $hash ) {
			continue;
		}
		$row_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . Schema::table( 'customers' ) . ' WHERE email_hash = %s',
				$hash
			)
		);
		foreach ( (array) $row_ids as $row_id ) {
			$wpdb->delete( Schema::table( 'customer_history' ), array( 'customer_row_id' => (int) $row_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'customers' ), array( 'email_hash' => $hash ), array( '%s' ) );
	}

	foreach ( $bag['reps'] as $rep_id ) {
		$wpdb->delete( Schema::table( 'assignments' ), array( 'rep_id' => (int) $rep_id ), array( '%d' ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Schema::table( 'ledger' ) . ' WHERE rep_id = %d', (int) $rep_id ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Schema::table( 'customers' ) . ' WHERE rep_id = %d', (int) $rep_id ) );
		$wpdb->delete( Schema::table( 'reps' ), array( 'id' => (int) $rep_id ), array( '%d' ) );
	}
	foreach ( $bag['recruiters'] as $recruiter_id ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Schema::table( 'ledger' ) . ' WHERE recruiter_id = %d', (int) $recruiter_id ) );
		$wpdb->delete( Schema::table( 'recruiters' ), array( 'id' => (int) $recruiter_id ), array( '%d' ) );
	}
	foreach ( $bag['coupons'] as $coupon_id ) {
		wp_delete_post( (int) $coupon_id, true );
	}
	foreach ( $bag['products'] as $product_id ) {
		wp_delete_post( (int) $product_id, true );
	}
	foreach ( $bag['users'] as $user_id ) {
		wp_delete_user( (int) $user_id );
	}

	Codes::flush_cache();
	wp_cache_flush();
	if ( function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ) {
		WC()->cart->empty_cart();
	}
	if ( function_exists( 'wc_clear_notices' ) ) {
		wc_clear_notices();
	}
}

/**
 * @param callable(array<string, list<int|string>>): void $fn Scenario.
 */
function aff_scenario( string $name, callable $fn ): void {
	global $wpdb;
	$bag = aff_bag();
	$wpdb->query( 'START TRANSACTION' );
	try {
		$fn( $bag );
	} catch ( \Throwable $error ) {
		$count = &aff_failures();
		++$count;
		echo "FAIL {$name}: " . $error->getMessage() . "\n";
	} finally {
		$wpdb->query( 'ROLLBACK' );
		aff_cleanup( $bag );
	}
}

function aff_cart(): void {
	if ( ! WC()->session ) {
		WC()->initialize_session();
	}
	if ( null === WC()->cart ) {
		wc_load_cart();
	}
	WC()->cart->empty_cart();
}

Schema::maybe_migrate();

aff_scenario(
	'create rep and 10% code',
	static function ( array &$bag ): void {
		$team   = aff_team( $bag );
		$coupon = new \WC_Coupon( $team['code'] );
		aff_assert( 'percent' === $coupon->get_discount_type(), 'code is a percent coupon' );
		aff_assert( 10.0 === (float) $coupon->get_amount(), 'code is 10%' );
		aff_assert( true === $coupon->get_individual_use(), 'code does not stack with other coupons' );
		$again = Program::create_rep( 'Other', $team['code'], (int) $coupon->get_id(), $team['recruiter_id'], null );
		aff_assert( is_wp_error( $again ), 'code cannot be issued twice' );
	}
);

aff_scenario(
	'paid 100 with 10% code',
	static function ( array &$bag ): void {
		$team     = aff_team( $bag );
		$product  = aff_product( $bag );
		$customer = aff_customer( $bag );
		$order    = aff_order( $bag, $customer['id'], $customer['email'], $product, $team['code'] );
		Attribution::capture_order( $order );
		aff_pay( $order );

		$base = Attribution::line_base_cents( $order );
		aff_assert( 9000 === $base, 'net commissionable revenue is 9000 cents, got ' . $base );
		aff_assert( 2250 === aff_amount( $order->get_id(), 'rep' ), 'rep earns 2250' );
		aff_assert( 630 === aff_amount( $order->get_id(), 'recruiter' ), 'recruiter earns 630' );
		aff_assert( 2 === count( aff_ledger( $order->get_id() ) ), 'one earn row per payee' );

		$owner = Attribution::owner_rep_id( $customer['id'], $customer['email'] );
		aff_assert( $team['rep_id'] === $owner, 'first paid referral owns the customer' );

		do_action( 'woocommerce_payment_complete', $order->get_id() );
		do_action( 'woocommerce_order_status_processing', $order->get_id() );
		do_action( 'woocommerce_order_status_completed', $order->get_id() );
		Commissions::on_paid( $order->get_id() );
		aff_assert( 2 === count( aff_ledger( $order->get_id() ) ), 'duplicate payment events do not double-record' );
	}
);

aff_scenario(
	'unpaid order earns nothing',
	static function ( array &$bag ): void {
		$team     = aff_team( $bag );
		$product  = aff_product( $bag );
		$customer = aff_customer( $bag );
		$order    = aff_order( $bag, $customer['id'], $customer['email'], $product, $team['code'] );
		Attribution::capture_order( $order );
		Commissions::on_paid( $order->get_id() );
		aff_assert( array() === aff_ledger( $order->get_id() ), 'pending order has no ledger rows' );
		$order->update_status( 'cancelled' );
		aff_assert( array() === aff_ledger( $order->get_id() ), 'cancelling an unpaid order still has no ledger rows' );
		aff_assert( null === Attribution::owner_rep_id( $customer['id'], $customer['email'] ), 'unpaid order does not own the customer' );
	}
);

aff_scenario(
	'returning customer without a code',
	static function ( array &$bag ): void {
		$team     = aff_team( $bag );
		$product  = aff_product( $bag );
		$customer = aff_customer( $bag );
		$first    = aff_order( $bag, $customer['id'], $customer['email'], $product, $team['code'] );
		Attribution::capture_order( $first );
		aff_pay( $first );

		$second = aff_order( $bag, $customer['id'], $customer['email'], $product, null );
		Attribution::capture_order( $second );
		$row = Attribution::order_row( $second->get_id() );
		aff_assert( null !== $row && (int) $row['rep_id'] === $team['rep_id'], 'second order snapshots the owning rep' );
		aff_assert( null !== $row && 'owner' === $row['source'], 'second order source is the saved owner' );
		aff_pay( $second );
		aff_assert( 2500 === aff_amount( $second->get_id(), 'rep' ), 'owning rep earns 25% of the undiscounted repeat order, got ' . aff_amount( $second->get_id(), 'rep' ) );
		aff_assert( 700 === aff_amount( $second->get_id(), 'recruiter' ), 'recruiter earns 7% of the same repeat order' );
	}
);

aff_scenario(
	'promotion blocks the affiliate discount and still attributes',
	static function ( array &$bag ): void {
		$team       = aff_team( $bag );
		$product    = aff_product( $bag );
		$promo_code = Program::format_code( 'PROMO' . strtoupper( wp_generate_password( 5, false, false ) ) );
		$promo      = new \WC_Coupon();
		$promo->set_code( $promo_code );
		$promo->set_discount_type( 'percent' );
		$promo->set_amount( 20 );
		$promo->set_individual_use( false );
		$promo->save();
		$bag['coupons'][] = (int) $promo->get_id();

		aff_cart();
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		aff_assert( true === WC()->cart->apply_coupon( $promo_code ), 'promotion applies' );
		aff_assert( false === WC()->cart->apply_coupon( $team['code'] ), 'affiliate code is rejected while the promotion is applied' );
		aff_assert( WC()->cart->has_discount( $promo_code ), 'promotion stays applied' );
		aff_assert( ! WC()->cart->has_discount( $team['code'] ), 'affiliate coupon is not in the cart' );
		aff_assert( $team['code'] === Attribution::pending_code(), 'rejected code is remembered' );

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		aff_assert( true === WC()->cart->apply_coupon( $team['code'] ), 'affiliate code applies on its own' );
		aff_assert( true === WC()->cart->apply_coupon( $promo_code ), 'promotion replaces the affiliate discount' );
		aff_assert( WC()->cart->has_discount( $promo_code ), 'promotion is the discount that remains' );
		aff_assert( ! WC()->cart->has_discount( $team['code'] ), 'affiliate coupon was removed' );
		aff_assert( $team['code'] === Attribution::pending_code(), 'removed affiliate code is still remembered' );

		$customer = aff_customer( $bag );
		$order    = aff_order( $bag, $customer['id'], $customer['email'], $product, $promo_code );
		Attribution::capture_order( $order, $team['code'] );
		aff_pay( $order );
		$base = Attribution::line_base_cents( $order );
		aff_assert( 8000 === $base, 'commission uses the 20% promotion net, got ' . $base );
		aff_assert( Money::apply_rate( 8000, Money::REP_BP ) === aff_amount( $order->get_id(), 'rep' ), 'rep commission follows the amount actually paid' );
		aff_assert( Money::apply_rate( 8000, Money::RECRUITER_BP ) === aff_amount( $order->get_id(), 'recruiter' ), 'recruiter commission follows the same base' );
		$snapshot = Attribution::order_row( $order->get_id() );
		aff_assert( null !== $snapshot && 0 === (int) $snapshot['discount_applied'], 'affiliate discount was not applied' );
	}
);

aff_scenario(
	'another reps code does not move ownership',
	static function ( array &$bag ): void {
		$first    = aff_team( $bag );
		$second   = aff_team( $bag );
		$product  = aff_product( $bag );
		$customer = aff_customer( $bag );
		$order    = aff_order( $bag, $customer['id'], $customer['email'], $product, $first['code'] );
		Attribution::capture_order( $order );
		aff_pay( $order );

		$_POST['billing_email']      = $customer['email'];
		$_POST['noviq_aff_referral'] = $second['code'];
		Attribution::maybe_notice();
		$notices = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'notice' ) : array();
		aff_assert( array() !== $notices, 'checkout explains that ownership does not move' );
		wc_clear_notices();

		$later = aff_order( $bag, $customer['id'], $customer['email'], $product, $second['code'] );
		Attribution::capture_order( $later, $second['code'] );
		aff_pay( $later );
		aff_assert( $first['rep_id'] === Attribution::owner_rep_id( $customer['id'], $customer['email'] ), 'owner is still the first rep' );
		aff_assert( $first['rep_id'] === (int) Attribution::order_row( $later->get_id() )['rep_id'], 'later order is attributed to the first rep' );
		$paid_second = false;
		foreach ( aff_ledger( $later->get_id() ) as $row ) {
			if ( (int) $row['payee_id'] === $second['rep_id'] ) {
				$paid_second = true;
			}
		}
		aff_assert( ! $paid_second, 'the second rep is not paid' );
		unset( $_POST['billing_email'], $_POST['noviq_aff_referral'] );
	}
);

aff_scenario(
	'reassigning a rep changes future recruiter only',
	static function ( array &$bag ): void {
		$team     = aff_team( $bag );
		$other_id = Program::create_recruiter( 'Rec ' . wp_generate_password( 6, false, false ), null );
		if ( is_wp_error( $other_id ) ) {
			throw new RuntimeException( $other_id->get_error_message() );
		}
		$bag['recruiters'][] = $other_id;
		$product             = aff_product( $bag );
		$customer            = aff_customer( $bag );
		$first               = aff_order( $bag, $customer['id'], $customer['email'], $product, $team['code'] );
		Attribution::capture_order( $first );
		aff_pay( $first );

		$moved = Program::assign_rep( $team['rep_id'], $other_id, 1 );
		aff_assert( true === $moved, 'rep can be moved' );

		$second = aff_order( $bag, $customer['id'], $customer['email'], $product, null );
		Attribution::capture_order( $second );
		aff_pay( $second );

		$old = aff_ledger( $first->get_id() );
		$new = aff_ledger( $second->get_id() );
		$old_recruiter = 0;
		$new_recruiter = 0;
		foreach ( $old as $row ) {
			if ( 'recruiter' === $row['payee_type'] && 'earn' === $row['entry_type'] ) {
				$old_recruiter = (int) $row['payee_id'];
			}
		}
		foreach ( $new as $row ) {
			if ( 'recruiter' === $row['payee_type'] && 'earn' === $row['entry_type'] ) {
				$new_recruiter = (int) $row['payee_id'];
			}
		}
		aff_assert( $team['recruiter_id'] === $old_recruiter, 'historical recruiter commission stays' );
		aff_assert( $other_id === $new_recruiter, 'new order credits the new recruiter' );
	}
);

aff_scenario(
	'full refund appends negatives and a repeat does not',
	static function ( array &$bag ): void {
		$team     = aff_team( $bag );
		$product  = aff_product( $bag );
		$customer = aff_customer( $bag );
		$order    = aff_order( $bag, $customer['id'], $customer['email'], $product, $team['code'] );
		Attribution::capture_order( $order );
		aff_pay( $order );

		$line_items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$line_items[ $item_id ] = array(
				'qty'          => $item->get_quantity(),
				'refund_total' => $item->get_total(),
				'refund_tax'   => $item->get_total_tax(),
			);
		}
		$refund = wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => $order->get_total(),
				'reason'         => 'affiliate test',
				'line_items'     => $line_items,
				'refund_payment' => false,
			)
		);
		if ( is_wp_error( $refund ) || ! $refund instanceof \WC_Order_Refund ) {
			throw new RuntimeException( is_wp_error( $refund ) ? $refund->get_error_message() : 'refund failed' );
		}

		$earns    = array();
		$refunds  = array();
		foreach ( aff_ledger( $order->get_id() ) as $row ) {
			if ( 'earn' === $row['entry_type'] ) {
				$earns[ (string) $row['payee_type'] ] = (int) $row['amount_cents'];
			}
			if ( 'refund' === $row['entry_type'] ) {
				$refunds[ (string) $row['payee_type'] ] = (int) $row['amount_cents'];
			}
		}
		aff_assert( 2250 === ( $earns['rep'] ?? 0 ), 'original rep earn remains' );
		aff_assert( 630 === ( $earns['recruiter'] ?? 0 ), 'original recruiter earn remains' );
		aff_assert( -2250 === ( $refunds['rep'] ?? 0 ), 'rep refund reverses 2250' );
		aff_assert( -630 === ( $refunds['recruiter'] ?? 0 ), 'recruiter refund reverses 630' );

		$before = count( aff_ledger( $order->get_id() ) );
		Commissions::on_refunded( $order->get_id(), $refund->get_id() );
		aff_assert( $before === count( aff_ledger( $order->get_id() ) ), 'repeating the refund callback does not duplicate rows' );
	}
);

aff_scenario(
	'partial refunds prorate and do not exceed the original',
	static function ( array &$bag ): void {
		$team     = aff_team( $bag );
		$product  = aff_product( $bag );
		$customer = aff_customer( $bag );
		$order    = aff_order( $bag, $customer['id'], $customer['email'], $product, $team['code'] );
		Attribution::capture_order( $order );
		aff_pay( $order );

		$item_id = (int) array_key_first( $order->get_items() );
		$item    = $order->get_items()[ $item_id ];
		$half    = wc_format_decimal( (float) $item->get_total() / 2, 2 );
		$refund  = wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => $half,
				'reason'         => 'half',
				'line_items'     => array(
					$item_id => array(
						'qty'          => 0,
						'refund_total' => $half,
						'refund_tax'   => 0,
					),
				),
				'refund_payment' => false,
			)
		);
		if ( is_wp_error( $refund ) || ! $refund instanceof \WC_Order_Refund ) {
			throw new RuntimeException( is_wp_error( $refund ) ? $refund->get_error_message() : 'partial refund failed' );
		}

		aff_assert( -1125 === aff_refund_amount( $order->get_id(), 'rep' ), 'half refund reverses 1125 for the rep' );
		aff_assert( -315 === aff_refund_amount( $order->get_id(), 'recruiter' ), 'half refund reverses 315 for the recruiter' );

		$rest = wc_format_decimal( (float) $item->get_total() - (float) $half, 2 );
		$second = wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => $rest,
				'reason'         => 'rest',
				'line_items'     => array(
					$item_id => array(
						'qty'          => 1,
						'refund_total' => $rest,
						'refund_tax'   => 0,
					),
				),
				'refund_payment' => false,
			)
		);
		if ( is_wp_error( $second ) ) {
			throw new RuntimeException( $second->get_error_message() );
		}
		aff_assert( -2250 === aff_refund_amount( $order->get_id(), 'rep' ), 'partials sum to the original rep commission' );
		aff_assert( -630 === aff_refund_amount( $order->get_id(), 'recruiter' ), 'partials sum to the original recruiter commission' );

		$before = count( aff_ledger( $order->get_id() ) );
		if ( $second instanceof \WC_Order_Refund ) {
			Commissions::on_refunded( $order->get_id(), $second->get_id() );
		}
		aff_assert( $before === count( aff_ledger( $order->get_id() ) ), 'repeating a partial refund callback does not duplicate rows' );
	}
);

aff_scenario(
	'cancelling a paid order reverses the unreversed balance once',
	static function ( array &$bag ): void {
		$team     = aff_team( $bag );
		$product  = aff_product( $bag );
		$customer = aff_customer( $bag );
		$order    = aff_order( $bag, $customer['id'], $customer['email'], $product, $team['code'] );
		Attribution::capture_order( $order );
		aff_pay( $order );
		$order->update_status( 'cancelled' );

		$earn = 0;
		$reversal = 0;
		foreach ( aff_ledger( $order->get_id() ) as $row ) {
			if ( 'rep' !== $row['payee_type'] ) {
				continue;
			}
			if ( 'earn' === $row['entry_type'] ) {
				$earn += (int) $row['amount_cents'];
			}
			if ( 'reversal' === $row['entry_type'] ) {
				$reversal += (int) $row['amount_cents'];
			}
		}
		aff_assert( 2250 === $earn && -2250 === $reversal, 'cancellation reverses the rep earn without deleting it' );
		$before = count( aff_ledger( $order->get_id() ) );
		Commissions::on_reversed( $order->get_id() );
		aff_assert( $before === count( aff_ledger( $order->get_id() ) ), 'a second reversal does not double-reverse' );
	}
);

aff_scenario(
	'dashboards are scoped and the csv includes negatives',
	static function ( array &$bag ): void {
		$team_a   = aff_team( $bag );
		$team_b   = aff_team( $bag );
		$product  = aff_product( $bag );
		$buyer_a  = aff_customer( $bag );
		$buyer_b  = aff_customer( $bag );
		$order_a  = aff_order( $bag, $buyer_a['id'], $buyer_a['email'], $product, $team_a['code'] );
		$order_b  = aff_order( $bag, $buyer_b['id'], $buyer_b['email'], $product, $team_b['code'] );
		Attribution::capture_order( $order_a );
		Attribution::capture_order( $order_b );
		aff_pay( $order_a );
		aff_pay( $order_b );

		$rep_view = new Viewer( Viewer::REP, $team_a['rep_id'] );
		$rep_rows = Reports::ledger( $rep_view, array() );
		$seen_b   = false;
		foreach ( $rep_rows['rows'] as $row ) {
			if ( (int) $row['order_id'] === $order_b->get_id() || 'recruiter' === $row['payee_type'] ) {
				$seen_b = true;
			}
		}
		aff_assert( ! $seen_b && $rep_rows['total'] >= 1, 'rep ledger is only that rep commission' );

		$boss_view = new Viewer( Viewer::RECRUITER, 0, $team_a['recruiter_id'] );
		$boss_rows = Reports::ledger( $boss_view, array( 'rep_id' => $team_b['rep_id'] ) );
		aff_assert( 0 === $boss_rows['total'], 'recruiter cannot drill into another team' );
		$boss_own = Reports::ledger( $boss_view, array() );
		$foreign  = false;
		foreach ( $boss_own['rows'] as $row ) {
			if ( (int) $row['recruiter_id'] !== $team_a['recruiter_id'] ) {
				$foreign = true;
			}
		}
		$own_only = $boss_own['total'] >= 1;
		foreach ( $boss_own['rows'] as $row ) {
			if ( 'recruiter' !== (string) $row['payee_type'] || (int) $row['payee_id'] !== $team_a['recruiter_id'] ) {
				$own_only = false;
			}
		}
		aff_assert( ! $foreign && $own_only, 'recruiter ledger stays on their snapshot and lists only their own commission' );

		$admin = Reports::ledger( Viewer::admin(), array() );
		$ids   = array_map( static fn( array $row ): int => (int) $row['order_id'], $admin['rows'] );
		aff_assert( in_array( $order_a->get_id(), $ids, true ) && in_array( $order_b->get_id(), $ids, true ), 'administrator sees both orders' );

		$period = wp_date( 'Y-m' );
		$csv    = Reports::payout_csv( $period );
		aff_assert( str_contains( $csv, '22.50' ) && str_contains( $csv, '6.30' ), 'csv lists the earned amounts' );

		$line_items = array();
		foreach ( $order_a->get_items() as $item_id => $item ) {
			$line_items[ $item_id ] = array(
				'qty'          => $item->get_quantity(),
				'refund_total' => $item->get_total(),
				'refund_tax'   => $item->get_total_tax(),
			);
		}
		$refund = wc_create_refund(
			array(
				'order_id'       => $order_a->get_id(),
				'amount'         => $order_a->get_total(),
				'line_items'     => $line_items,
				'refund_payment' => false,
			)
		);
		if ( is_wp_error( $refund ) ) {
			throw new RuntimeException( $refund->get_error_message() );
		}
		$csv = Reports::payout_csv( $period );
		aff_assert( str_contains( $csv, '-22.50' ) && str_contains( $csv, '-6.30' ), 'csv includes negative adjustments' );
	}
);

aff_scenario(
	'house rep has no recruiter commission',
	static function ( array &$bag ): void {
		$code      = Program::format_code( 'H' . strtoupper( wp_generate_password( 8, false, false ) ) );
		$coupon_id = Codes::ensure_coupon( $code );
		if ( is_wp_error( $coupon_id ) ) {
			throw new RuntimeException( $coupon_id->get_error_message() );
		}
		$bag['coupons'][] = $coupon_id;
		$rep_id           = Program::create_rep( 'House ' . $code, $code, $coupon_id, null, null );
		if ( is_wp_error( $rep_id ) ) {
			throw new RuntimeException( $rep_id->get_error_message() );
		}
		$bag['reps'][] = $rep_id;
		Codes::flush_cache();

		$product  = aff_product( $bag );
		$customer = aff_customer( $bag );
		$order    = aff_order( $bag, $customer['id'], $customer['email'], $product, $code );
		Attribution::capture_order( $order );
		aff_pay( $order );
		aff_assert( 2250 === aff_amount( $order->get_id(), 'rep' ), 'house rep earns 25%' );
		aff_assert( 0 === aff_amount( $order->get_id(), 'recruiter' ), 'house order has no recruiter commission' );
	}
);

aff_scenario(
	'admin reassignment does not rewrite recorded commissions',
	static function ( array &$bag ): void {
		$first    = aff_team( $bag );
		$second   = aff_team( $bag );
		$product  = aff_product( $bag );
		$customer = aff_customer( $bag );
		$order    = aff_order( $bag, $customer['id'], $customer['email'], $product, $first['code'] );
		Attribution::capture_order( $order );
		aff_pay( $order );

		$assigned = Attribution::assign_by_email( $customer['email'], $second['rep_id'], 1, 'test move' );
		aff_assert( true === $assigned, 'admin can move the customer' );
		aff_assert( $first['rep_id'] === (int) aff_ledger( $order->get_id() )[0]['rep_id'] || aff_payee_is( $order->get_id(), $first['rep_id'] ), 'recorded earn stays with the original rep' );

		$later = aff_order( $bag, $customer['id'], $customer['email'], $product, $first['code'] );
		Attribution::capture_order( $later, $first['code'] );
		aff_pay( $later );
		aff_assert( $second['rep_id'] === (int) Attribution::order_row( $later->get_id() )['rep_id'], 'future order follows the new assignment' );
	}
);

aff_scenario(
	'volume price and affiliate coupon both apply',
	static function ( array &$bag ): void {
		$team    = aff_team( $bag );
		$product = aff_product( $bag );
		update_post_meta( $product->get_id(), Meta::PRODUCT_TIERS, wp_json_encode( VolumeBreaks::default_tiers() ) );
		$unit = VolumeBreaks::unit_price_cents( 10000, VolumeBreaks::default_tiers(), 5 );
		aff_assert( 9000 === $unit, 'qty 5 is the 10% volume price' );

		aff_cart();
		WC()->cart->add_to_cart( $product->get_id(), 5 );
		// VolumeBreaks::apply_to_cart() bails in WP-CLI because is_admin() is true.
		// Priority 30 runs after that bail and applies the same unit price the
		// storefront hook would have written.
		add_action(
			'woocommerce_before_calculate_totals',
			static function ( \WC_Cart $cart ) use ( $unit ): void {
				foreach ( $cart->get_cart() as $item ) {
					if ( isset( $item['data'] ) && $item['data'] instanceof \WC_Product ) {
						$item['data']->set_price( $unit / 100 );
					}
				}
			},
			30
		);
		aff_assert( true === WC()->cart->apply_coupon( $team['code'] ), 'affiliate coupon applies on top of the volume price' );
		WC()->cart->calculate_totals();
		aff_assert( WC()->cart->has_discount( $team['code'] ), 'volume pricing does not remove the affiliate coupon' );
		$discount = (int) Money::to_cents( (string) WC()->cart->get_discount_total() );
		aff_assert( 4500 === $discount, '10% of the volume-adjusted $450, got ' . $discount );
	}
);

aff_scenario(
	'inactive code is rejected',
	static function ( array &$bag ): void {
		$team = aff_team( $bag );
		Program::set_rep_status( $team['rep_id'], 'inactive' );
		Codes::flush_cache();
		$coupon = new \WC_Coupon( $team['code'] );
		aff_assert( false === $coupon->is_valid(), 'inactive rep code is not a valid coupon' );
	}
);

/**
 * Sum of refund and reversal amounts for one payee.
 */
function aff_refund_amount( int $order_id, string $payee ): int {
	$sum = 0;
	foreach ( aff_ledger( $order_id ) as $row ) {
		if ( (string) $row['payee_type'] !== $payee ) {
			continue;
		}
		if ( in_array( (string) $row['entry_type'], array( 'refund', 'reversal' ), true ) ) {
			$sum += (int) $row['amount_cents'];
		}
	}

	return $sum;
}

function aff_payee_is( int $order_id, int $rep_id ): bool {
	foreach ( aff_ledger( $order_id ) as $row ) {
		if ( 'earn' === $row['entry_type'] && 'rep' === $row['payee_type'] && (int) $row['payee_id'] === $rep_id ) {
			return true;
		}
	}

	return false;
}

if ( aff_failures() > 0 ) {
	fwrite( STDERR, aff_failures() . " assertion(s) failed\n" );
	exit( 1 );
}

echo "integration ok\n";
