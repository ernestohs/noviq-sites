<?php
/**
 * Storefront quantity style: dropdown (default) or stepper via cookie.
 *
 * Override with ?qty=stepper or ?qty=dropdown (sets noviq_qty_style for one year).
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

namespace Noviq\Child;

defined( 'ABSPATH' ) || exit;

const QTY_COOKIE         = 'noviq_qty_style';
const QTY_DROPDOWN_MAX   = 25;

/**
 * Current quantity control style (PDP, cart, checkout).
 */
function quantity_style(): string {
	if ( ! isset( $_COOKIE[ QTY_COOKIE ] ) ) {
		return 'dropdown';
	}

	$raw = sanitize_key( wp_unslash( (string) $_COOKIE[ QTY_COOKIE ] ) );

	return 'stepper' === $raw ? 'stepper' : 'dropdown';
}

function quantity_dropdown_max(): int {
	return QTY_DROPDOWN_MAX;
}

/**
 * ?qty=dropdown or ?qty=stepper writes the cookie and redirects clean.
 */
add_action(
	'template_redirect',
	static function (): void {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		if ( ! isset( $_GET['qty'] ) ) {
			return;
		}

		$qty = sanitize_key( wp_unslash( (string) $_GET['qty'] ) );
		if ( 'dropdown' !== $qty && 'stepper' !== $qty ) {
			return;
		}

		$path = COOKIEPATH ? COOKIEPATH : '/';
		setcookie(
			QTY_COOKIE,
			$qty,
			array(
				'expires'  => time() + \YEAR_IN_SECONDS,
				'path'     => $path,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		wp_safe_redirect( remove_query_arg( 'qty' ) );
		exit;
	},
	1
);
