<?php
/**
 * Creator coupon referral links.
 *
 * VIP codes (data/{profile}/vip-coupons.json) resolve at the site root:
 * noviqpeptides.com/LELE10. Any other usable WooCommerce coupon still works
 * via /go/{CODE}. Coupons are never created from the URL.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

defined( 'ABSPATH' ) || exit;

final class ReferralCoupon {

	public const QUERY_VAR   = 'noviq_go_coupon';
	public const SESSION_KEY = 'noviq_pending_coupon';
	public const COOKIE      = 'noviq_pending_coupon';

	private const COOKIE_LIFETIME = WEEK_IN_SECONDS;

	/** Guard against apply_coupon → calculate_totals re-entrancy. */
	private static bool $applying = false;

	public static function init(): void {
		add_action( 'init', array( self::class, 'register_rewrite' ) );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_filter( 'request', array( self::class, 'claim_vip_root' ) );
		add_action( 'template_redirect', array( self::class, 'handle_go' ), 5 );

		add_action( 'woocommerce_cart_loaded_from_session', array( self::class, 'maybe_apply' ), 20 );
		add_action( 'woocommerce_add_to_cart', array( self::class, 'maybe_apply' ), 20 );
		add_action( 'woocommerce_before_cart', array( self::class, 'maybe_apply' ), 5 );
		add_action( 'woocommerce_before_checkout_form', array( self::class, 'maybe_apply' ), 5 );
		add_action( 'wp_loaded', array( self::class, 'maybe_apply' ), 25 );
	}

	/**
	 * Pretty URL: /go/{CODE} → noviq_go_coupon query var.
	 * Also called from the activation hook before flush_rewrite_rules().
	 */
	public static function register_rewrite(): void {
		add_rewrite_rule(
			'^go/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * @param list<string> $vars Registered query vars.
	 * @return list<string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Map /LELE10 (VIP allowlist only) onto the same handler as /go/LELE10.
	 *
	 * Non-VIP single segments are left alone so pages and products keep working.
	 *
	 * @param array<string, mixed> $query_vars Parsed request vars.
	 * @return array<string, mixed>
	 */
	public static function claim_vip_root( array $query_vars ): array {
		if ( isset( $query_vars[ self::QUERY_VAR ] ) && '' !== (string) $query_vars[ self::QUERY_VAR ] ) {
			return $query_vars;
		}

		// Same WC gate as handle_go(): vip formatting needs wc_format_coupon_code().
		if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_format_coupon_code' ) ) {
			return $query_vars;
		}

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return $query_vars;
		}

		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		$path = trim( $path, '/' );
		if ( '' === $path || str_contains( $path, '/' ) ) {
			return $query_vars;
		}

		$code = wc_format_coupon_code( rawurldecode( $path ) );
		if ( ! self::is_vip_code( $code ) ) {
			return $query_vars;
		}

		$query_vars[ self::QUERY_VAR ] = $code;

		return $query_vars;
	}

	/**
	 * Validate the code, stash it, notice, redirect to shop.
	 */
	public static function handle_go(): void {
		$code = get_query_var( self::QUERY_VAR );
		if ( ! is_string( $code ) || '' === $code ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! class_exists( \WC_Coupon::class ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$code = wc_format_coupon_code( rawurldecode( $code ) );

		if ( ! self::coupon_is_usable( $code ) ) {
			self::ensure_session();
			wc_add_notice(
				__( 'That referral code is not valid or is no longer available.', 'noviq-core' ),
				'error'
			);
			self::redirect_to_shop();
		}

		// Social crawlers: OG HTML for VIP only; never stash or redirect.
		if ( VipSharePreview::is_social_crawler() ) {
			VipSharePreview::render( $code );
			exit;
		}

		self::ensure_session();
		self::stash( $code );
		self::maybe_apply();

		wc_add_notice(
			sprintf(
				/* translators: %s: coupon code */
				__( 'Referral code %s will be applied at checkout.', 'noviq-core' ),
				$code
			),
			'success'
		);
		self::redirect_to_shop();
	}

	/**
	 * Apply a pending coupon once the cart exists.
	 */
	public static function maybe_apply(): void {
		if ( self::$applying ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$code = self::pending_code();
		if ( '' === $code ) {
			return;
		}

		if ( WC()->cart->has_discount( $code ) ) {
			self::clear_pending();
			return;
		}

		if ( ! self::coupon_is_usable( $code ) ) {
			self::clear_pending();
			return;
		}

		self::$applying = true;
		$result         = WC()->cart->apply_coupon( $code );
		self::$applying = false;

		if ( $result ) {
			self::clear_pending();
		}
	}

	/**
	 * @return list<string>
	 */
	public static function vip_codes(): array {
		return VipCouponsRegistry::codes();
	}

	public static function is_vip_code( string $code ): bool {
		if ( ! function_exists( 'wc_format_coupon_code' ) ) {
			return false;
		}

		$code = wc_format_coupon_code( $code );

		return '' !== $code && null !== VipCouponsRegistry::row( $code );
	}

	/**
	 * Existence + expiry + global usage limit. Product/cart constraints are
	 * enforced later by WooCommerce when apply_coupon runs.
	 */
	private static function coupon_is_usable( string $code ): bool {
		if ( '' === $code ) {
			return false;
		}

		$coupon = new \WC_Coupon( $code );
		if ( 0 === (int) $coupon->get_id() ) {
			return false;
		}

		if ( $coupon->get_virtual() ) {
			return false;
		}

		$expires = $coupon->get_date_expires();
		if ( $expires && $expires->getTimestamp() < time() ) {
			return false;
		}

		$limit = (int) $coupon->get_usage_limit();
		if ( $limit > 0 && (int) $coupon->get_usage_count() >= $limit ) {
			return false;
		}

		return true;
	}

	private static function stash( string $code ): void {
		if ( WC()->session ) {
			WC()->session->set( self::SESSION_KEY, $code );
		}

		setcookie(
			self::COOKIE,
			$code,
			array(
				'expires'  => time() + self::COOKIE_LIFETIME,
				'path'     => self::cookie_path(),
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $code;
	}

	private static function pending_code(): string {
		$code = '';

		if ( function_exists( 'WC' ) && WC()->session ) {
			$from_session = WC()->session->get( self::SESSION_KEY );
			if ( is_string( $from_session ) && '' !== $from_session ) {
				$code = $from_session;
			}
		}

		if ( '' === $code && isset( $_COOKIE[ self::COOKIE ] ) ) {
			$code = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		}

		if ( '' === $code ) {
			return '';
		}

		return wc_format_coupon_code( $code );
	}

	private static function clear_pending(): void {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_KEY, null );
		}

		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			unset( $_COOKIE[ self::COOKIE ] );
		}

		setcookie(
			self::COOKIE,
			'',
			array(
				'expires'  => time() - YEAR_IN_SECONDS,
				'path'     => self::cookie_path(),
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	private static function ensure_session(): void {
		if ( ! WC()->session ) {
			return;
		}

		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}

	private static function redirect_to_shop(): void {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
		$url  = ( is_string( $shop ) && '' !== $shop ) ? $shop : home_url( '/' );

		wp_safe_redirect( $url );
		exit;
	}

	private static function cookie_path(): string {
		return COOKIEPATH ? COOKIEPATH : '/';
	}
}
