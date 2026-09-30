<?php
/**
 * Route paid-order admin mail to the sales notification address on production.
 *
 * WooCommerce's "New order" email fires when an order moves to processing or
 * completed (Tagada webhook path). Local and staging hosts are left alone so
 * test checkouts do not spam the live recipient.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

use Noviq\Core\Claims;

defined( 'ABSPATH' ) || exit;

final class OrderNotifications {

	/** @var list<string> */
	private const PRODUCTION_HOSTS = array(
		'noviqpeptides.com',
		'www.noviqpeptides.com',
	);

	public static function init(): void {
		add_filter( 'woocommerce_email_recipient_new_order', array( self::class, 'recipient' ), 20, 2 );
		add_filter( 'woocommerce_email_enabled_new_order', array( self::class, 'enabled' ), 20, 2 );
	}

	/**
	 * Keep the New order email on for live sales alerts.
	 *
	 * @param bool            $enabled Whether the email is enabled.
	 * @param \WC_Order|false $order   Order being emailed, when available.
	 */
	public static function enabled( bool $enabled, $order = null ): bool {
		unset( $order );

		if ( self::is_production_host() && null !== self::sales_email() ) {
			return true;
		}

		return $enabled;
	}

	/**
	 * Append the profile sales address on production; leave other hosts unchanged.
	 *
	 * @param string          $recipient Comma-separated recipients.
	 * @param \WC_Order|false $order     Order being emailed, when available.
	 */
	public static function recipient( string $recipient, $order = null ): string {
		unset( $order );

		if ( ! self::is_production_host() ) {
			return $recipient;
		}

		$extra = self::sales_email();
		if ( null === $extra ) {
			return $recipient;
		}

		$emails = array();
		foreach ( array_map( 'trim', explode( ',', $recipient ) ) as $email ) {
			$clean = sanitize_email( $email );
			if ( '' !== $clean && is_email( $clean ) ) {
				$emails[ strtolower( $clean ) ] = $clean;
			}
		}
		$emails[ strtolower( $extra ) ] = $extra;

		return implode( ', ', array_values( $emails ) );
	}

	private static function sales_email(): ?string {
		$email = Claims::fact( 'sales_notification_email' );
		if ( null === $email || '' === $email ) {
			return null;
		}

		$clean = sanitize_email( $email );

		return ( '' !== $clean && is_email( $clean ) ) ? $clean : null;
	}

	private static function is_production_host(): bool {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		return in_array( strtolower( $host ), self::PRODUCTION_HOSTS, true );
	}
}
