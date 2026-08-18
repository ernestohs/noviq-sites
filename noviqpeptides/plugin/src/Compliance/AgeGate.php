<?php
/**
 * 21-and-up age gate on entry.
 *
 * A one-time interstitial: the visitor confirms they are 21 or older, the
 * answer is stored in a cookie, and the gate never appears again until the
 * cookie expires or the wording changes.
 *
 * Two decisions worth knowing about:
 *
 * 1. The page still renders behind the overlay. The gate is a fixed-position
 *    cover, not a redirect, so crawlers index the site normally and a visitor
 *    who has already answered never pays for a round trip. Nothing behind it is
 *    private — the control is a declaration of age, not an access control.
 *
 * 2. It works without JavaScript. Both buttons submit a real form that this
 *    class handles on template_redirect; JavaScript only upgrades that to a
 *    same-page dismissal. A client with scripting off is gated, not stranded.
 *
 * The order-time researcher attestation in Attestation.php is a separate and
 * stronger control. This one is the doorway; that one is the record.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Compliance;

use Noviq\Core\Claims;
use Noviq\Core\Profile;
use const Noviq\Core\VERSION;

defined( 'ABSPATH' ) || exit;

final class AgeGate {

	public const COOKIE = 'noviq_age_verified';
	public const FIELD  = 'noviq_age_gate';

	private const LIFETIME = YEAR_IN_SECONDS;

	/** Set when a no-JS visitor answered "no" on this request. */
	private static bool $declined = false;

	/** Guards against the gate printing twice on themes that fire both hooks. */
	private static bool $rendered = false;

	/**
	 * Bump whenever question() changes. Stored in the cookie so revised wording
	 * re-prompts rather than inheriting consent to different words.
	 */
	public static function copy_version(): string {
		return Profile::age_gate()['copy_version'];
	}

	/**
	 * The question. Kept here rather than in the template for the same reason
	 * Attestation::text() is: the wording is the control.
	 */
	public static function question(): string {
		$question = Profile::age_gate()['question'];

		return '' !== $question
			? $question
			: __( 'Are you 21 years of age or older?', 'noviq-core' );
	}

	public static function init(): void {
		add_action( 'template_redirect', array( self::class, 'handle_submission' ), 1 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'body_class', array( self::class, 'body_class' ) );

		// wp_body_open keeps the overlay ahead of the content it covers, so
		// there is no flash of the page underneath. wp_footer is the fallback
		// for a theme that never calls wp_body_open — the gate must not fail
		// open just because a template forgot a hook.
		add_action( 'wp_body_open', array( self::class, 'render' ) );
		add_action( 'wp_footer', array( self::class, 'render' ), 1 );
	}

	/**
	 * True once the visitor has answered yes to the current wording.
	 */
	public static function is_verified(): bool {
		if ( ! isset( $_COOKIE[ self::COOKIE ] ) ) {
			return false;
		}

		return self::copy_version() === sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
	}

	/**
	 * Whether this request should carry the gate at all.
	 */
	public static function should_render(): bool {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_robots() || is_customize_preview() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		return ! self::is_verified();
	}

	/**
	 * No-JS path. Runs before any output so the cookie can still be set.
	 *
	 * There is no nonce: the form is public, it takes no privileged action, and
	 * a forged submission would only let a third party set the visitor's own age
	 * cookie. A stale nonce on a cached page would break the gate for real
	 * visitors, which is the worse failure.
	 */
	public static function handle_submission(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public, unprivileged form; see docblock.
		if ( ! isset( $_POST[ self::FIELD ] ) ) {
			return;
		}

		if ( 'yes' !== sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) ) {
			self::$declined = true;

			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		self::set_cookie();

		// So the rest of this request already sees the visitor as verified.
		$_COOKIE[ self::COOKIE ] = self::copy_version();

		// Redirect back to the same URL so a refresh does not re-post.
		wp_safe_redirect( self::current_url() );
		exit;
	}

	/**
	 * The path being requested, for posting and redirecting back to.
	 */
	private static function current_url(): string {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return home_url( '/' );
		}

		return esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	private static function set_cookie(): void {
		setcookie(
			self::COOKIE,
			self::copy_version(),
			array(
				'expires'  => time() + self::LIFETIME,
				'path'     => self::cookie_path(),
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				// Readable by script: the JavaScript path writes the same cookie
				// without a round trip. It carries no authority, so HttpOnly
				// would buy nothing.
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Shared by the PHP and JavaScript paths so a subdirectory install does not
	 * end up with two cookies of the same name at different paths.
	 */
	private static function cookie_path(): string {
		return COOKIEPATH ? COOKIEPATH : '/';
	}

	public static function enqueue(): void {
		if ( ! self::should_render() ) {
			return;
		}

		wp_enqueue_script(
			'noviq-age-gate',
			NOVIQ_CORE_URL . 'assets/age-gate.js',
			array(),
			VERSION,
			true
		);

		wp_localize_script(
			'noviq-age-gate',
			'noviqAgeGate',
			array(
				'cookie'   => self::COOKIE,
				'value'    => self::copy_version(),
				'path'     => self::cookie_path(),
				'lifetime' => (string) self::LIFETIME,
				'secure'   => is_ssl() ? '1' : '',
			)
		);
	}

	/**
	 * Scroll lock, applied server-side so the page cannot be scrolled before
	 * the script loads.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( array $classes ): array {
		if ( self::should_render() ) {
			$classes[] = 'noviq-age-gate-open';
		}

		return $classes;
	}

	public static function render(): void {
		if ( self::$rendered || ! self::should_render() ) {
			return;
		}
		self::$rendered = true;

		$brand = Claims::fact( 'name' ) ?? get_bloginfo( 'name' );

		?>
		<div class="noviq-age-gate" data-noviq-age-gate role="dialog" aria-modal="true"
			aria-label="<?php esc_attr_e( 'Age verification', 'noviq-core' ); ?>">
			<div class="noviq-age-gate__panel">
				<p class="noviq-age-gate__brand"><?php echo esc_html( $brand ); ?></p>

				<div class="noviq-age-gate__ask" data-noviq-age-ask <?php echo self::$declined ? 'hidden' : ''; ?>>
					<h2 class="noviq-age-gate__heading"><?php echo esc_html( self::question() ); ?></h2>

					<p class="noviq-age-gate__body">
						<?php esc_html_e( 'This site supplies reference materials for laboratory research and is intended for researchers aged 21 or over. Please confirm your age to continue.', 'noviq-core' ); ?>
					</p>

					<form class="noviq-age-gate__actions" method="post" action="">
						<button type="submit" class="noviq-btn noviq-age-gate__yes"
							name="<?php echo esc_attr( self::FIELD ); ?>" value="yes" data-noviq-age-yes>
							<?php esc_html_e( 'Yes, I am 21 or older', 'noviq-core' ); ?>
						</button>
						<button type="submit" class="noviq-age-gate__no"
							name="<?php echo esc_attr( self::FIELD ); ?>" value="no" data-noviq-age-no>
							<?php esc_html_e( 'No, I am under 21', 'noviq-core' ); ?>
						</button>
					</form>

					<p class="noviq-age-gate__ruo"><?php echo esc_html( Claims::ruo_short() ); ?></p>
				</div>

				<div class="noviq-age-gate__denied" data-noviq-age-denied <?php echo self::$declined ? '' : 'hidden'; ?>>
					<h2 class="noviq-age-gate__heading"><?php esc_html_e( 'You must be 21 or older to enter', 'noviq-core' ); ?></h2>

					<p class="noviq-age-gate__body">
						<?php esc_html_e( 'These materials are supplied for in-vitro laboratory research only, to researchers aged 21 or over. We cannot give you access to this site.', 'noviq-core' ); ?>
					</p>

					<?php // A link, not a button: without JavaScript, reloading the page is what returns to the question. ?>
					<a class="noviq-age-gate__back" href="<?php echo esc_url( self::current_url() ); ?>" data-noviq-age-back>
						<?php esc_html_e( 'Go back', 'noviq-core' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}
}
