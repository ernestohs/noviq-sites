<?php
/**
 * Open Graph / Twitter Card HTML for VIP share links.
 *
 * Social crawlers receive a minimal document with per-creator meta. Humans
 * keep the existing stash-and-redirect flow in ReferralCoupon.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Commerce;

defined( 'ABSPATH' ) || exit;

final class VipSharePreview {

	/**
	 * User-Agent substrings used by major social preview crawlers.
	 *
	 * @var list<string>
	 */
	private const CRAWLER_MARKERS = array(
		'facebookexternalhit',
		'Facebot',
		'Twitterbot',
		'LinkedInBot',
		'Slackbot',
		'Discordbot',
		'WhatsApp',
		'TelegramBot',
		'Pinterest',
	);

	public static function init(): void {
		// No standalone hooks; ReferralCoupon::handle_go() calls render().
	}

	public static function is_social_crawler(): bool {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}

		$ua = (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] );
		if ( '' === $ua ) {
			return false;
		}

		foreach ( self::CRAWLER_MARKERS as $marker ) {
			if ( false !== stripos( $ua, $marker ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Emit OG HTML for a VIP code and exit.
	 *
	 * Returns without output when the code is not VIP; the caller must exit
	 * so crawlers do not fall through to stash/redirect.
	 */
	public static function render( string $code ): void {
		if ( ! ReferralCoupon::is_vip_code( $code ) ) {
			return;
		}

		$via_go = self::request_is_go_path();
		$meta   = VipCouponsRegistry::share_meta( $code, $via_go );

		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: text/html; charset=UTF-8' );
			nocache_headers();
		}

		$title       = $meta['title'];
		$description = $meta['description'];
		$image       = $meta['image_url'];
		$url         = $meta['canonical_url'];

		echo '<!doctype html>' . "\n";
		echo '<html lang="en">' . "\n";
		echo '<head>' . "\n";
		echo '<meta charset="utf-8" />' . "\n";
		printf( '<title>%s</title>' . "\n", esc_html( $title ) );
		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		echo '<meta property="og:type" content="website" />' . "\n";
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
		printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $description ) );
		printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $image ) );
		echo '</head>' . "\n";
		echo '<body></body>' . "\n";
		echo '</html>' . "\n";
		exit;
	}

	private static function request_is_go_path(): bool {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		$path = trim( $path, '/' );

		return str_starts_with( strtolower( $path ), 'go/' );
	}
}
