<?php
/**
 * Subscribe & save — UI only, behind a feature flag that is OFF by default.
 *
 * A real recurring order needs the paid WooCommerce Subscriptions extension.
 * Rather than fake a recurring charge (which would take a payment the customer
 * did not agree to a schedule for), this ships the interface disabled and the
 * licence question is escalated to the client in OPEN-QUESTIONS.md.
 *
 * When the flag is on, the control renders as a disabled preview with an
 * explicit "not yet available" note. It never touches price or order state.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

use Noviq\Core\Claims;

defined( 'ABSPATH' ) || exit;

final class Subscriptions {

	public const OPTION_ENABLED = 'noviq_subscriptions_enabled';

	/** Advertised saving, for the preview copy only. Applied to nothing. */
	public const DISCOUNT = 0.10;

	public static function init(): void {
		add_action( 'woocommerce_single_product_summary', array( self::class, 'render_preview' ), 26 );
	}

	/**
	 * Off unless the option is explicitly turned on. The filter exists so the
	 * client can demo it without a database change.
	 */
	public static function is_enabled(): bool {
		$enabled = (bool) get_option( self::OPTION_ENABLED, false );

		return (bool) apply_filters( 'noviq_subscriptions_enabled', $enabled );
	}

	public static function render_preview(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		printf(
			'<div class="noviq-subscribe is-disabled">
				<label class="noviq-subscribe__label">
					<input type="checkbox" disabled aria-describedby="noviq-subscribe-note" />
					<span>%1$s <span class="noviq-num">%2$d%%</span></span>
				</label>
				<p class="noviq-subscribe__note" id="noviq-subscribe-note">%3$s</p>
			</div>',
			esc_html__( 'Subscribe &amp; save', 'noviq-core' ),
			(int) round( self::DISCOUNT * 100 ),
			esc_html__( 'Preview only — recurring orders are not yet available.', 'noviq-core' )
		);
	}
}
