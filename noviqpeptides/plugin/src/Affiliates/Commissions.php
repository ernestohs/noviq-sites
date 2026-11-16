<?php
/**
 * Commission ledger.
 *
 * Rep commission is 25% of commissionable product revenue. Recruiter commission
 * is 7% of the same base. They are not compounded. Rows are written when the
 * order is paid, not when it is placed. Refunds and payment reversals append
 * negative rows. Idempotency keys make webhook retries safe:
 *
 *   earn:{order}:rep|recruiter
 *   refund:{refund}:{rep|recruiter}
 *   reversal:{order}:{rep|recruiter}
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates;

defined( 'ABSPATH' ) || exit;

final class Commissions {

	/** Stops order saves during recording from re-entering the same request. */
	private static bool $recording = false;

	public static function init(): void {
		add_action( 'woocommerce_payment_complete', array( self::class, 'on_paid' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( self::class, 'on_paid' ), 20, 1 );
		add_action( 'woocommerce_order_status_completed', array( self::class, 'on_paid' ), 20, 1 );
		add_action( 'woocommerce_order_refunded', array( self::class, 'on_refunded' ), 20, 2 );
		add_action( 'woocommerce_order_status_cancelled', array( self::class, 'on_reversed' ), 20, 1 );
		add_action( 'woocommerce_order_status_failed', array( self::class, 'on_reversed' ), 20, 1 );
	}

	/**
	 * @param int|\WC_Order $order_id Order id or object, depending on the hook.
	 */
	public static function on_paid( $order_id ): void {
		$order = self::order( $order_id );
		if ( null === $order || ! $order->is_paid() ) {
			return;
		}

		Schema::with_order_lock(
			$order->get_id(),
			static function () use ( $order ): void {
				self::record_earn( $order, false );
			}
		);
	}

	/**
	 * @param int|\WC_Order $order_id  Order id.
	 * @param int           $refund_id Refund id.
	 */
	public static function on_refunded( $order_id, $refund_id ): void {
		$order  = self::order( $order_id );
		$refund = wc_get_order( (int) $refund_id );
		if ( null === $order || ! $refund instanceof \WC_Order_Refund ) {
			return;
		}

		Schema::with_order_lock(
			$order->get_id(),
			static function () use ( $order, $refund ): void {
				self::record_refund( $order, $refund );
			}
		);
	}

	/**
	 * @param int|\WC_Order $order_id Order id.
	 */
	public static function on_reversed( $order_id ): void {
		$order = self::order( $order_id );
		if ( null === $order ) {
			return;
		}

		Schema::with_order_lock(
			$order->get_id(),
			static function () use ( $order ): void {
				self::record_reversal( $order );
			}
		);
	}

	/**
	 * @return list<string> Human-readable lines. Dry runs write nothing.
	 */
	public static function backfill( string $since, bool $dry_run ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array( 'WooCommerce is not available.' );
		}

		$timestamp = strtotime( $since . ' 00:00:00 UTC' );
		if ( false === $timestamp ) {
			return array( 'Invalid date.' );
		}

		$ids = wc_get_orders(
			array(
				'status'       => array( 'processing', 'completed', 'refunded', 'cancelled' ),
				'limit'        => -1,
				'return'       => 'ids',
				'type'         => 'shop_order',
				'date_created' => '>=' . $timestamp,
			)
		);

		$lines = array();
		if ( ! is_array( $ids ) ) {
			return $lines;
		}

		foreach ( $ids as $order_id ) {
			$order = wc_get_order( (int) $order_id );
			if ( ! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund ) {
				continue;
			}
			if ( ! self::was_paid( $order ) ) {
				continue;
			}

			$snapshot = Attribution::order_row( $order->get_id() );
			if ( null === $snapshot ) {
				Attribution::capture_order( $order );
				$snapshot = Attribution::order_row( $order->get_id() );
			}
			if ( null === $snapshot ) {
				continue;
			}

			$lines[] = sprintf(
				'%s order #%d rep %d base %d',
				$dry_run ? 'would record' : 'recorded',
				$order->get_id(),
				(int) $snapshot['rep_id'],
				Attribution::line_base_cents( $order )
			);

			if ( $dry_run ) {
				continue;
			}

			Schema::with_order_lock(
				$order->get_id(),
				static function () use ( $order ): void {
					self::record_earn( $order, true );
					foreach ( $order->get_refunds() as $refund ) {
						if ( $refund instanceof \WC_Order_Refund ) {
							self::record_refund( $order, $refund );
						}
					}
					if ( in_array( $order->get_status(), array( 'cancelled', 'failed' ), true ) ) {
						self::record_reversal( $order );
					}
				}
			);
		}

		return $lines;
	}

	private static function record_earn( \WC_Order $order, bool $allow_refunded ): void {
		if ( self::$recording ) {
			return;
		}
		if ( ! $allow_refunded && ! $order->is_paid() ) {
			return;
		}
		if ( $allow_refunded && ! self::was_paid( $order ) ) {
			return;
		}

		self::$recording = true;
		try {
			if ( null === Attribution::order_row( $order->get_id() ) ) {
				Attribution::capture_order( $order );
			}
			$existing = Attribution::order_row( $order->get_id() );
			if ( null === $existing ) {
				return;
			}
			$rep = Program::rep( (int) $existing['rep_id'] );
			if ( null === $rep || 'active' !== (string) $rep['status'] ) {
				return;
			}

			$snapshot = Attribution::lock_in_owner( $order );
			if ( null === $snapshot ) {
				return;
			}

			self::write_earn( $order, $snapshot );
		} finally {
			self::$recording = false;
		}
	}

	/**
	 * @param array<string, mixed> $snapshot Order attribution row.
	 */
	private static function write_earn( \WC_Order $order, array $snapshot ): void {
		$rep = Program::rep( (int) $snapshot['rep_id'] );
		if ( null === $rep || 'active' !== (string) $rep['status'] ) {
			return;
		}

		$base         = Attribution::line_base_cents( $order );
		$recruiter_id = ! empty( $snapshot['recruiter_id'] ) ? (int) $snapshot['recruiter_id'] : null;
		$discount     = self::order_has_affiliate_coupon( $order );
		Attribution::update_base( $order->get_id(), $base, $discount );

		if ( $base <= 0 ) {
			return;
		}

		$currency = (string) $order->get_currency();
		$period   = self::period_for_order( $order );
		$context  = array(
			'base_cents'       => $base,
			'rep_id'           => (int) $rep['id'],
			'recruiter_id'     => $recruiter_id,
			'source'           => (string) $snapshot['source'],
			'code'             => (string) $snapshot['code_entered'],
			'discount_applied' => $discount,
		);

		self::insert_entry(
			$order,
			0,
			'earn',
			'rep',
			(int) $rep['id'],
			(int) $rep['id'],
			$recruiter_id,
			$base,
			Money::REP_BP,
			Money::apply_rate( $base, Money::REP_BP ),
			$currency,
			$period,
			$context,
			'earn:' . $order->get_id() . ':rep'
		);

		if ( null !== $recruiter_id ) {
			$context['rate_bp'] = Money::RECRUITER_BP;
			self::insert_entry(
				$order,
				0,
				'earn',
				'recruiter',
				$recruiter_id,
				(int) $rep['id'],
				$recruiter_id,
				$base,
				Money::RECRUITER_BP,
				Money::apply_rate( $base, Money::RECRUITER_BP ),
				$currency,
				$period,
				$context,
				'earn:' . $order->get_id() . ':recruiter'
			);
		}
	}

	public static function record_refund( \WC_Order $order, \WC_Order_Refund $refund ): void {
		$earns = self::earn_rows( $order->get_id() );
		if ( array() === $earns ) {
			return;
		}

		$original_base = (int) $earns[0]['base_cents'];
		$refunded_base = self::refunded_base_cents( $order, $refund, $original_base );
		if ( $refunded_base <= 0 ) {
			return;
		}

		$currency = (string) $order->get_currency();
		$period   = wp_date( 'Y-m' );

		foreach ( $earns as $earn ) {
			$payee          = (string) $earn['payee_type'];
			$already_base   = self::recorded_refund_base( $order->get_id(), $payee );
			$cumulative     = $already_base + $refunded_base;
			$already_amount = self::reversed_amount( $order->get_id(), $payee );
			$delta          = Money::refund_delta( (int) $earn['amount_cents'], $original_base, $cumulative, $already_amount );
			if ( 0 === $delta ) {
				continue;
			}

			self::insert_entry(
				$order,
				$refund->get_id(),
				'refund',
				$payee,
				(int) $earn['payee_id'],
				(int) $earn['rep_id'],
				! empty( $earn['recruiter_id'] ) ? (int) $earn['recruiter_id'] : null,
				$refunded_base,
				(int) $earn['rate_bp'],
				$delta,
				$currency,
				$period,
				array(
					'refund_id'                     => $refund->get_id(),
					'refunded_base_cents'           => $refunded_base,
					'cumulative_refunded_base_cents'=> min( $cumulative, $original_base ),
					'original_amount_cents'         => (int) $earn['amount_cents'],
					'original_base_cents'           => $original_base,
				),
				'refund:' . $refund->get_id() . ':' . $payee
			);
		}
	}

	public static function record_reversal( \WC_Order $order ): void {
		$earns = self::earn_rows( $order->get_id() );
		if ( array() === $earns ) {
			return;
		}

		$currency = (string) $order->get_currency();
		$period   = wp_date( 'Y-m' );

		foreach ( $earns as $earn ) {
			$payee          = (string) $earn['payee_type'];
			$original       = (int) $earn['amount_cents'];
			$original_base  = (int) $earn['base_cents'];
			$already_amount = self::reversed_amount( $order->get_id(), $payee );
			$delta          = Money::refund_delta( $original, $original_base, $original_base, $already_amount );
			if ( 0 === $delta ) {
				continue;
			}

			self::insert_entry(
				$order,
				0,
				'reversal',
				$payee,
				(int) $earn['payee_id'],
				(int) $earn['rep_id'],
				! empty( $earn['recruiter_id'] ) ? (int) $earn['recruiter_id'] : null,
				$original_base,
				(int) $earn['rate_bp'],
				$delta,
				$currency,
				$period,
				array(
					'original_amount_cents' => $original,
					'original_base_cents'   => $original_base,
					'refunded_base_cents'   => max( 0, $original_base - self::recorded_refund_base( $order->get_id(), $payee ) ),
					'reason'                => 'payment_reversal',
				),
				'reversal:' . $order->get_id() . ':' . $payee
			);
		}
	}

	/**
	 * Product line totals after discounts, in cents. Excludes tax, shipping, and fees.
	 */
	public static function refunded_base_cents( \WC_Order $order, \WC_Order_Refund $refund, int $original_base ): int {
		$line = 0;
		$has  = false;
		foreach ( $refund->get_items( 'line_item' ) as $item ) {
			$has   = true;
			$line += abs( Money::to_cents( $item->get_total( 'edit' ) ) );
		}
		if ( $has ) {
			return min( $line, max( 0, $original_base ) );
		}

		$order_total   = abs( Money::to_cents( $order->get_total( 'edit' ) ) );
		$refund_amount = abs( Money::to_cents( $refund->get_amount() ) );
		if ( $order_total <= 0 || $refund_amount <= 0 || $original_base <= 0 ) {
			return 0;
		}

		return min( Money::prorate( $original_base, $refund_amount, $order_total ), $original_base );
	}

	/**
	 * @param array<string, mixed> $context Snapshot stored with the row.
	 */
	private static function insert_entry(
		\WC_Order $order,
		int $refund_id,
		string $entry_type,
		string $payee_type,
		int $payee_id,
		int $rep_id,
		?int $recruiter_id,
		int $base_cents,
		int $rate_bp,
		int $amount_cents,
		string $currency,
		string $period,
		array $context,
		string $key
	): void {
		if ( 0 === $amount_cents && 'earn' === $entry_type ) {
			return;
		}

		global $wpdb;
		Schema::insert(
			Schema::table( 'ledger' ),
			array(
				'order_id'        => $order->get_id(),
				'refund_id'       => $refund_id,
				'entry_type'      => $entry_type,
				'payee_type'      => $payee_type,
				'payee_id'        => $payee_id,
				'rep_id'          => $rep_id,
				'recruiter_id'    => $recruiter_id,
				'base_cents'      => $base_cents,
				'rate_bp'         => $rate_bp,
				'amount_cents'    => $amount_cents,
				'currency'        => $currency,
				'period'          => $period,
				'inputs_json'     => wp_json_encode( $context ),
				'idempotency_key' => $key,
				'created_at'      => Schema::now(),
			)
		);

		if ( '' !== $wpdb->last_error && ! str_contains( strtolower( $wpdb->last_error ), 'duplicate' ) && function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error(
				'Affiliate ledger insert failed: ' . $wpdb->last_error,
				array( 'source' => 'noviq-affiliates' )
			);
		}
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function earn_rows( int $order_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::table( 'ledger' ) . ' WHERE order_id = %d AND entry_type = %s ORDER BY id ASC',
				$order_id,
				'earn'
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	private static function reversed_amount( int $order_id, string $payee_type ): int {
		global $wpdb;
		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(-amount_cents), 0) FROM ' . Schema::table( 'ledger' ) . '
				WHERE order_id = %d AND payee_type = %s AND entry_type IN (%s, %s) AND amount_cents < 0',
				$order_id,
				$payee_type,
				'refund',
				'reversal'
			)
		);

		return (int) $value;
	}

	private static function recorded_refund_base( int $order_id, string $payee_type ): int {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT inputs_json FROM ' . Schema::table( 'ledger' ) . ' WHERE order_id = %d AND payee_type = %s AND entry_type = %s',
				$order_id,
				$payee_type,
				'refund'
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$sum = 0;
		foreach ( $rows as $row ) {
			$decoded = json_decode( (string) ( $row['inputs_json'] ?? '' ), true );
			if ( is_array( $decoded ) && isset( $decoded['refunded_base_cents'] ) ) {
				$sum += (int) $decoded['refunded_base_cents'];
			}
		}

		return $sum;
	}

	private static function order_has_affiliate_coupon( \WC_Order $order ): bool {
		foreach ( $order->get_items( 'coupon' ) as $item ) {
			if ( $item instanceof \WC_Order_Item_Coupon && Codes::is_affiliate_code( $item->get_code() ) ) {
				return true;
			}
		}

		return false;
	}

	private static function was_paid( \WC_Order $order ): bool {
		if ( $order->is_paid() ) {
			return true;
		}

		$paid = $order->get_date_paid();

		return $paid instanceof \WC_DateTime || $paid instanceof \DateTimeInterface;
	}

	private static function period_for_order( \WC_Order $order ): string {
		$paid = $order->get_date_paid();
		if ( ! $paid instanceof \DateTimeInterface ) {
			$paid = $order->get_date_created();
		}
		$timestamp = $paid instanceof \DateTimeInterface ? $paid->getTimestamp() : time();

		return wp_date( 'Y-m', $timestamp );
	}

	/**
	 * @param int|\WC_Order $order_id Order id or object.
	 */
	private static function order( $order_id ): ?\WC_Order {
		if ( $order_id instanceof \WC_Order_Refund ) {
			return null;
		}
		if ( $order_id instanceof \WC_Order ) {
			return $order_id;
		}
		$order = wc_get_order( (int) $order_id );

		return ( $order instanceof \WC_Order && ! $order instanceof \WC_Order_Refund ) ? $order : null;
	}
}
