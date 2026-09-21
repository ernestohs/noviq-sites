<?php
/**
 * Scheduled payment reminders for unpaid PayPal invoice orders.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules Action Scheduler jobs that re-send the PayPal payment link while
 * an order remains on-hold. Interval and cap come from gateway settings.
 */
final class PaymentReminders {

	public const HOOK = 'noviq_payment_reminder';

	public const GROUP = 'noviq-payment-reminders';

	public const META_SENT = '_noviq_payment_reminder_sent';

	public const META_LAST_AT = '_noviq_payment_reminder_last_at';

	private const LOG_SOURCE = 'noviq-payment-reminders';

	public static function init(): void {
		add_action( 'woocommerce_order_status_changed', array( self::class, 'on_status_changed' ), 10, 4 );
		add_action( self::HOOK, array( self::class, 'run' ), 10, 2 );
	}

	/**
	 * Schedule the first reminder when an order becomes on-hold; cancel pending
	 * actions when it leaves that status.
	 *
	 * @param int             $order_id Order ID.
	 * @param string          $from     Previous status (without wc- prefix).
	 * @param string          $to       New status (without wc- prefix).
	 * @param \WC_Order|mixed $order    Order object when available.
	 */
	public static function on_status_changed( $order_id, $from, $to, $order = null ): void {
		$order_id = (int) $order_id;
		if ( $order_id <= 0 ) {
			return;
		}

		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( 'on-hold' !== (string) $to ) {
			self::unschedule_for_order( $order_id );

			return;
		}

		if ( ! self::is_eligible( $order ) ) {
			return;
		}

		$sent = self::sent_count( $order );
		if ( $sent >= self::max_count() ) {
			return;
		}

		self::unschedule_for_order( $order_id );
		self::schedule( $order_id, $sent + 1, time() + self::interval_seconds() );
	}

	/**
	 * Action Scheduler callback.
	 *
	 * @param int $order_id Order ID.
	 * @param int $sequence 1-based reminder number.
	 */
	public static function run( $order_id, $sequence = 1 ): void {
		$order_id = (int) $order_id;
		$sequence = (int) $sequence;

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			self::log( 'info', sprintf( 'Skip reminder: order %d not found.', $order_id ) );

			return;
		}

		if ( ! self::is_eligible( $order ) ) {
			self::log(
				'info',
				sprintf( 'Skip reminder for order %d: not eligible (status=%s).', $order_id, $order->get_status() )
			);

			return;
		}

		$sent = self::sent_count( $order );
		if ( $sequence !== $sent + 1 ) {
			self::log(
				'warning',
				sprintf(
					'Skip reminder for order %d: sequence %d does not match sent+1 (%d).',
					$order_id,
					$sequence,
					$sent + 1
				)
			);

			return;
		}

		$email = self::reminder_email();
		if ( null === $email ) {
			self::log( 'warning', sprintf( 'Skip reminder for order %d: email class not registered.', $order_id ) );

			return;
		}

		if ( ! $email->is_enabled() ) {
			self::log( 'info', sprintf( 'Skip reminder for order %d: reminder email disabled.', $order_id ) );

			return;
		}

		$email->trigger( $order_id, $sequence );

		$max = self::max_count();
		$order->update_meta_data( self::META_SENT, $sequence );
		$order->update_meta_data( self::META_LAST_AT, gmdate( 'Y-m-d H:i:s' ) );
		$order->add_order_note(
			sprintf(
				/* translators: 1: reminder number, 2: max reminders */
				__( 'Payment reminder %1$d of %2$d emailed to customer.', 'noviq-core' ),
				$sequence,
				$max
			)
		);
		$order->save();

		self::log(
			'info',
			sprintf( 'Sent payment reminder %d of %d for order %d.', $sequence, $max, $order_id )
		);

		if ( $sequence < $max ) {
			self::schedule( $order_id, $sequence + 1, time() + self::interval_seconds() );
		}
	}

	/**
	 * Whether the order should receive (or continue) payment reminders.
	 */
	public static function is_eligible( \WC_Order $order ): bool {
		if ( PaypalInvoiceGateway::ID !== $order->get_payment_method() ) {
			return false;
		}

		if ( 'on-hold' !== $order->get_status() ) {
			return false;
		}

		if ( '' === PaypalInvoiceGateway::payment_url_for_order( $order ) ) {
			return false;
		}

		if ( ! self::reminders_enabled() ) {
			return false;
		}

		return self::sent_count( $order ) < self::max_count();
	}

	public static function sent_count( \WC_Order $order ): int {
		return max( 0, (int) $order->get_meta( self::META_SENT, true ) );
	}

	public static function last_sent_at( \WC_Order $order ): string {
		return (string) $order->get_meta( self::META_LAST_AT, true );
	}

	/**
	 * Cancel all pending reminder actions for one order.
	 */
	public static function unschedule_for_order( int $order_id ): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		$max = self::max_count();
		for ( $sequence = 1; $sequence <= $max + 1; $sequence++ ) {
			as_unschedule_all_actions(
				self::HOOK,
				array( $order_id, $sequence ),
				self::GROUP
			);
		}
	}

	/**
	 * Schedule a single reminder action.
	 */
	public static function schedule( int $order_id, int $sequence, int $timestamp ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			self::log( 'error', 'Action Scheduler unavailable; cannot schedule payment reminder.' );

			return;
		}

		as_schedule_single_action(
			$timestamp,
			self::HOOK,
			array( $order_id, $sequence ),
			self::GROUP
		);
	}

	/**
	 * Next pending scheduled timestamp for an order, or 0 if none.
	 */
	public static function next_scheduled_timestamp( int $order_id ): int {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return 0;
		}

		$max      = self::max_count();
		$earliest = 0;

		for ( $sequence = 1; $sequence <= $max + 1; $sequence++ ) {
			$next = as_next_scheduled_action(
				self::HOOK,
				array( $order_id, $sequence ),
				self::GROUP
			);
			if ( false === $next || $next <= 0 ) {
				continue;
			}
			if ( 0 === $earliest || $next < $earliest ) {
				$earliest = (int) $next;
			}
		}

		return $earliest;
	}

	public static function reminders_enabled(): bool {
		$settings = self::gateway_settings();

		return ! isset( $settings['reminders_enabled'] ) || 'yes' === $settings['reminders_enabled'];
	}

	public static function interval_hours(): int {
		$settings = self::gateway_settings();
		$hours    = isset( $settings['reminder_interval_hours'] ) ? (int) $settings['reminder_interval_hours'] : 24;

		return max( 1, $hours );
	}

	public static function interval_seconds(): int {
		return self::interval_hours() * HOUR_IN_SECONDS;
	}

	public static function max_count(): int {
		$settings = self::gateway_settings();
		$count    = isset( $settings['reminder_max_count'] ) ? (int) $settings['reminder_max_count'] : 30;

		return max( 0, min( 365, $count ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function gateway_settings(): array {
		$settings = get_option( 'woocommerce_' . PaypalInvoiceGateway::ID . '_settings', array() );

		return is_array( $settings ) ? $settings : array();
	}

	private static function reminder_email(): ?PaypalInvoiceReminderEmail {
		if ( ! function_exists( 'WC' ) || null === WC()->mailer() ) {
			return null;
		}

		$emails = WC()->mailer()->get_emails();
		$email  = $emails['Noviq_Paypal_Invoice_Reminder_Email'] ?? null;

		return $email instanceof PaypalInvoiceReminderEmail ? $email : null;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private static function log( string $level, string $message, array $context = array() ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$extra = array() !== $context ? ' ' . wp_json_encode( $context ) : '';
		wc_get_logger()->log( $level, $message . $extra, array( 'source' => self::LOG_SOURCE ) );
	}
}
