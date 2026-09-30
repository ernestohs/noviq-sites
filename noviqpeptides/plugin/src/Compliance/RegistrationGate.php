<?php
/**
 * Site-wide registration gate.
 *
 * Payment processors require a registered account before catalog access. Unlike
 * AgeGate (a cookie overlay over live markup), this decides on template_redirect
 * and exits with a standalone document: no theme header, nav, or products.
 *
 * New emails create a WooCommerce customer, record consent, and log in
 * immediately. Existing emails (and the Sign in form) always go through a
 * single-use magic link so typing someone else's address cannot take over
 * their account.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Compliance;

use Noviq\Core\Claims;
use Noviq\Core\PostTypes;
use Noviq\Core\Profile;
use const Noviq\Core\VERSION;

defined( 'ABSPATH' ) || exit;

final class RegistrationGate {

	public const ACTION_REGISTER = 'noviq_gate_register';
	public const ACTION_SIGNIN   = 'noviq_gate_signin';
	public const NONCE           = 'noviq_registration_gate';

	public const META_CONSENT_TEXT    = '_noviq_gate_consent_text';
	public const META_CONSENT_VERSION = '_noviq_gate_consent_version';
	public const META_CONSENT_AT      = '_noviq_gate_consent_at';
	public const META_CONSENT_IP      = '_noviq_gate_consent_ip';
	public const META_TOKEN_HASH      = '_noviq_gate_token_hash';
	public const META_TOKEN_EXPIRES   = '_noviq_gate_token_expires';

	private const TOKEN_TTL       = 15 * MINUTE_IN_SECONDS;
	private const RATE_LIMIT_TTL  = 60;
	private const QUERY_TOKEN     = 'noviq_signin';
	private const QUERY_USER      = 'u';
	private const QUERY_REDIRECT  = 'noviq_redirect';
	private const QUERY_STATUS    = 'nq_gate';

	/**
	 * Bump whenever consent_text() changes so revised wording re-prompts.
	 */
	public static function copy_version(): string {
		return Profile::registration_gate()['copy_version'];
	}

	/**
	 * Exact wording shown and stored. Do not paraphrase at call sites.
	 */
	public static function consent_text(): string {
		$text = Profile::registration_gate()['consent_text'];

		return '' !== $text
			? $text
			: __(
				'I am 21 years of age or older, and I am a qualified researcher or am purchasing on behalf of a research institution. I understand these materials are supplied for in-vitro laboratory research only.',
				'noviq-core'
			);
	}

	public static function init(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_gate' ), 0 );
		add_action( 'template_redirect', array( self::class, 'handle_magic_link' ), 0 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );

		add_action( 'admin_post_nopriv_' . self::ACTION_REGISTER, array( self::class, 'handle_register' ) );
		add_action( 'admin_post_' . self::ACTION_REGISTER, array( self::class, 'handle_register' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SIGNIN, array( self::class, 'handle_signin' ) );
		add_action( 'admin_post_' . self::ACTION_SIGNIN, array( self::class, 'handle_signin' ) );

		add_filter( 'rest_pre_dispatch', array( self::class, 'block_store_api_products' ), 10, 3 );
		add_filter( 'wp_sitemaps_post_types', array( self::class, 'filter_sitemap_post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( self::class, 'filter_sitemap_taxonomies' ) );
	}

	/**
	 * Hard gate: logged-out visitors on non-allowlisted paths see only the gate.
	 */
	public static function maybe_gate(): void {
		if ( is_user_logged_in() || self::is_allowlisted() ) {
			return;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );

		$brand = Claims::fact( 'name' ) ?? get_bloginfo( 'name' );
		add_filter(
			'pre_get_document_title',
			static function () use ( $brand ): string {
				return sprintf(
					/* translators: %s: site name. */
					__( 'Researcher access: %s', 'noviq-core' ),
					$brand
				);
			},
			99
		);

		self::render_document();
		exit;
	}

	/**
	 * Paths that stay reachable without an account.
	 */
	public static function is_allowlisted(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}

		$script = isset( $_SERVER['SCRIPT_NAME'] )
			? (string) wp_unslash( $_SERVER['SCRIPT_NAME'] )
			: '';
		$base = basename( $script );
		if ( in_array( $base, array( 'wp-login.php', 'wp-register.php', 'admin-post.php' ), true ) ) {
			return true;
		}

		// Magic-link verification runs on the front door before the gate.
		if ( isset( $_GET[ self::QUERY_TOKEN ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public token link.
			return true;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? (string) wp_unslash( $_SERVER['REQUEST_URI'] )
			: '';
		$path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$path = untrailingslashit( $path );

		// Policy pages linked from the consent checkboxes.
		if ( 1 === preg_match( '#(?:^|/)policies(?:/|$)#', $path ) ) {
			return true;
		}

		// Sitemap index stays public; product/CPT entries are stripped by filters.
		if ( 1 === preg_match( '#(?:^|/)wp-sitemap(?:|\.xml|-[^/]+\.xml)$#', $path ) ) {
			return true;
		}

		if ( function_exists( 'is_robots' ) && is_robots() ) {
			return true;
		}

		return false;
	}

	/**
	 * Consume a magic-link token and log the user in.
	 */
	public static function handle_magic_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public single-use token; compared via hash_equals.
		if ( ! isset( $_GET[ self::QUERY_TOKEN ] ) ) {
			return;
		}

		$token   = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_TOKEN ] ) );
		$user_id = isset( $_GET[ self::QUERY_USER ] ) ? absint( $_GET[ self::QUERY_USER ] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$redirect = self::safe_redirect_target(
			isset( $_GET[ self::QUERY_REDIRECT ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				? (string) wp_unslash( $_GET[ self::QUERY_REDIRECT ] )
				: ''
		);

		if ( '' === $token || $user_id <= 0 || ! self::consume_token( $user_id, $token ) ) {
			self::redirect_with_status( $redirect, 'bad_link' );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		do_action( 'wp_login', wp_get_current_user()->user_login, wp_get_current_user() );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Register form: new email creates an account; existing email gets a magic link.
	 */
	public static function handle_register(): void {
		$redirect = self::posted_redirect();

		if ( ! self::verify_nonce() ) {
			self::redirect_with_status( $redirect, 'err' );
		}

		// Honeypot: bots fill this; humans leave it empty.
		$trap = isset( $_POST['nq_gate_company'] ) ? trim( (string) wp_unslash( $_POST['nq_gate_company'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		if ( '' !== $trap ) {
			self::redirect_with_status( $redirect, 'sent' );
		}

		$email = self::posted_email();
		if ( '' === $email ) {
			self::redirect_with_status( $redirect, 'err' );
		}

		$consent = isset( $_POST['nq_gate_consent'] ) && '1' === (string) wp_unslash( $_POST['nq_gate_consent'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$terms   = isset( $_POST['nq_gate_terms'] ) && '1' === (string) wp_unslash( $_POST['nq_gate_terms'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $consent || ! $terms ) {
			self::redirect_with_status( $redirect, 'err' );
		}

		$existing_id = email_exists( $email );
		if ( is_int( $existing_id ) && $existing_id > 0 ) {
			self::send_magic_link_rate_limited( $existing_id, $email, $redirect );
			self::redirect_with_status( $redirect, 'sent' );
		}

		if ( ! function_exists( 'wc_create_new_customer' ) ) {
			self::redirect_with_status( $redirect, 'err' );
		}

		// Password is never shown; sign-in is magic-link only. Suppress Woo's
		// new-account email that would otherwise carry the generated password.
		add_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false', 99 );

		$user_id = wc_create_new_customer( $email, '', wp_generate_password( 32, true, true ) );

		remove_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false', 99 );

		if ( is_wp_error( $user_id ) || ! is_int( $user_id ) || $user_id <= 0 ) {
			self::redirect_with_status( $redirect, 'err' );
		}

		self::record_consent( $user_id );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		do_action( 'wp_login', wp_get_current_user()->user_login, wp_get_current_user() );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Sign-in form: always respond with "check your email", never reveal existence.
	 */
	public static function handle_signin(): void {
		$redirect = self::posted_redirect();

		if ( ! self::verify_nonce() ) {
			self::redirect_with_status( $redirect, 'err' );
		}

		$trap = isset( $_POST['nq_gate_company'] ) ? trim( (string) wp_unslash( $_POST['nq_gate_company'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' !== $trap ) {
			self::redirect_with_status( $redirect, 'sent' );
		}

		$email = self::posted_email();
		if ( '' === $email ) {
			self::redirect_with_status( $redirect, 'err' );
		}

		$existing_id = email_exists( $email );
		if ( is_int( $existing_id ) && $existing_id > 0 ) {
			self::send_magic_link_rate_limited( $existing_id, $email, $redirect );
		}

		self::redirect_with_status( $redirect, 'sent' );
	}

	/**
	 * Block Store API product listing for logged-out clients.
	 *
	 * @param mixed            $result  Response to replace the requested.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 * @return mixed
	 */
	public static function block_store_api_products( $result, $server, $request ) {
		unset( $server );

		if ( is_user_logged_in() || ! $request instanceof \WP_REST_Request ) {
			return $result;
		}

		$route = $request->get_route();
		if ( 1 !== preg_match( '#^/wc/store(?:/v\d+)?/products#', $route ) ) {
			return $result;
		}

		return new \WP_Error(
			'noviq_registration_required',
			__( 'A registered account is required to view the catalog.', 'noviq-core' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * @param array<string, \WP_Post_Type> $post_types Post types.
	 * @return array<string, \WP_Post_Type>
	 */
	public static function filter_sitemap_post_types( array $post_types ): array {
		unset(
			$post_types['product'],
			$post_types[ PostTypes::COMPOUND ],
			$post_types[ PostTypes::LOT ],
			$post_types[ PostTypes::COMPARISON ]
		);

		return $post_types;
	}

	/**
	 * @param array<string, \WP_Taxonomy> $taxonomies Taxonomies.
	 * @return array<string, \WP_Taxonomy>
	 */
	public static function filter_sitemap_taxonomies( array $taxonomies ): array {
		unset( $taxonomies['product_cat'], $taxonomies['product_tag'] );

		return $taxonomies;
	}

	public static function enqueue(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		// Gate document and allowlisted policy pages may both need the script
		// when the gate is shown; enqueue only when we will render the gate.
		if ( self::is_allowlisted() && ! self::is_gate_status_request() ) {
			return;
		}

		wp_enqueue_script(
			'noviq-registration-gate',
			NOVIQ_CORE_URL . 'assets/registration-gate.js',
			array(),
			VERSION,
			true
		);
	}

	/**
	 * Standalone gate document. Theme chrome and product markup never run.
	 */
	private static function render_document(): void {
		$brand   = Claims::fact( 'name' ) ?? get_bloginfo( 'name' );
		$status  = self::current_status();
		$panel   = self::panel_for_status( $status );
		$redirect = self::current_url_for_redirect();
		$terms    = self::terms_url();
		$privacy  = self::privacy_url();

		$terms_label = sprintf(
			/* translators: 1: terms URL, 2: privacy URL. */
			__( 'I accept the <a href="%1$s">Terms &amp; Conditions</a> and <a href="%2$s">Privacy Policy</a>.', 'noviq-core' ),
			esc_url( $terms ),
			esc_url( $privacy )
		);

		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<?php
	// template_redirect exits before the normal enqueue cycle; fire it so
	// noviq-core.css and registration-gate.js still load through wp_head.
	do_action( 'wp_enqueue_scripts' );
	wp_head();
	?>
</head>
<body class="noviq-registration-gate-page">
	<div class="noviq-registration-gate" data-noviq-registration-gate role="dialog" aria-modal="true"
		aria-label="<?php esc_attr_e( 'Researcher access', 'noviq-core' ); ?>">
		<div class="noviq-registration-gate__panel">
			<p class="noviq-registration-gate__brand"><?php echo esc_html( $brand ); ?></p>

			<?php self::render_status_notice( $status ); ?>

			<div class="noviq-registration-gate__ask" data-noviq-gate-panel="register" <?php echo 'register' === $panel ? '' : 'hidden'; ?>>
				<h1 class="noviq-registration-gate__heading"><?php esc_html_e( 'Researcher access', 'noviq-core' ); ?></h1>
				<p class="noviq-registration-gate__body"><?php echo esc_html( Claims::ruo_full() ); ?></p>
				<p class="noviq-registration-gate__body"><?php esc_html_e( 'Confirm once to enter. We will remember you on this device.', 'noviq-core' ); ?></p>

				<form class="noviq-registration-gate__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REGISTER ); ?>" />
					<input type="hidden" name="<?php echo esc_attr( self::QUERY_REDIRECT ); ?>" value="<?php echo esc_attr( $redirect ); ?>" />
					<?php wp_nonce_field( self::NONCE, '_wpnonce' ); ?>

					<p class="noviq-registration-gate__honeypot" aria-hidden="true">
						<label for="nq_gate_company"><?php esc_html_e( 'Company', 'noviq-core' ); ?></label>
						<input type="text" name="nq_gate_company" id="nq_gate_company" tabindex="-1" autocomplete="off" />
					</p>

					<label class="noviq-registration-gate__label" for="nq_gate_email"><?php esc_html_e( 'Email', 'noviq-core' ); ?></label>
					<input class="noviq-registration-gate__input" type="email" name="nq_gate_email" id="nq_gate_email" required autocomplete="email" placeholder="<?php esc_attr_e( 'you@lab.com', 'noviq-core' ); ?>" />

					<label class="noviq-registration-gate__check" for="nq_gate_consent">
						<input type="checkbox" name="nq_gate_consent" id="nq_gate_consent" value="1" required aria-required="true" />
						<span><?php echo esc_html( self::consent_text() ); ?></span>
					</label>

					<label class="noviq-registration-gate__check" for="nq_gate_terms">
						<input type="checkbox" name="nq_gate_terms" id="nq_gate_terms" value="1" required aria-required="true" />
						<span><?php echo wp_kses( $terms_label, array( 'a' => array( 'href' => true ) ) ); ?></span>
					</label>

					<button type="submit" class="noviq-btn noviq-registration-gate__submit">
						<?php esc_html_e( 'Confirm & enter', 'noviq-core' ); ?>
					</button>
				</form>

				<p class="noviq-registration-gate__footer">
					<a class="noviq-registration-gate__link" href="<?php echo esc_url( add_query_arg( self::QUERY_STATUS, 'signin', self::current_url() ) ); ?>" data-noviq-gate-show="signin">
						<?php esc_html_e( 'Already confirmed on another device? Sign in', 'noviq-core' ); ?>
					</a>
					<span aria-hidden="true"> · </span>
					<a class="noviq-registration-gate__link" href="<?php echo esc_url( add_query_arg( self::QUERY_STATUS, 'denied', self::current_url() ) ); ?>" data-noviq-gate-show="denied">
						<?php esc_html_e( 'Exit', 'noviq-core' ); ?>
					</a>
				</p>

				<p class="noviq-registration-gate__ruo"><?php echo esc_html( Claims::ruo_short() ); ?></p>
			</div>

			<div class="noviq-registration-gate__ask" data-noviq-gate-panel="signin" <?php echo 'signin' === $panel ? '' : 'hidden'; ?>>
				<h1 class="noviq-registration-gate__heading"><?php esc_html_e( 'Sign in', 'noviq-core' ); ?></h1>
				<p class="noviq-registration-gate__body">
					<?php esc_html_e( 'Enter the email you registered with. We will send a one-time sign-in link.', 'noviq-core' ); ?>
				</p>

				<form class="noviq-registration-gate__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SIGNIN ); ?>" />
					<input type="hidden" name="<?php echo esc_attr( self::QUERY_REDIRECT ); ?>" value="<?php echo esc_attr( $redirect ); ?>" />
					<?php wp_nonce_field( self::NONCE, '_wpnonce' ); ?>

					<p class="noviq-registration-gate__honeypot" aria-hidden="true">
						<label for="nq_gate_company_signin"><?php esc_html_e( 'Company', 'noviq-core' ); ?></label>
						<input type="text" name="nq_gate_company" id="nq_gate_company_signin" tabindex="-1" autocomplete="off" />
					</p>

					<label class="noviq-registration-gate__label" for="nq_gate_email_signin"><?php esc_html_e( 'Email', 'noviq-core' ); ?></label>
					<input class="noviq-registration-gate__input" type="email" name="nq_gate_email" id="nq_gate_email_signin" required autocomplete="email" placeholder="<?php esc_attr_e( 'you@lab.com', 'noviq-core' ); ?>" />

					<button type="submit" class="noviq-btn noviq-registration-gate__submit">
						<?php esc_html_e( 'Email me a sign-in link', 'noviq-core' ); ?>
					</button>
				</form>

				<p class="noviq-registration-gate__footer">
					<a class="noviq-registration-gate__link" href="<?php echo esc_url( remove_query_arg( self::QUERY_STATUS, self::current_url() ) ); ?>" data-noviq-gate-show="register">
						<?php esc_html_e( 'Back to registration', 'noviq-core' ); ?>
					</a>
				</p>
			</div>

			<div class="noviq-registration-gate__ask" data-noviq-gate-panel="sent" <?php echo 'sent' === $panel ? '' : 'hidden'; ?>>
				<h1 class="noviq-registration-gate__heading"><?php esc_html_e( 'Check your email', 'noviq-core' ); ?></h1>
				<p class="noviq-registration-gate__body">
					<?php esc_html_e( 'If that address is registered, a one-time sign-in link is on its way. The link expires in 15 minutes and can be used once.', 'noviq-core' ); ?>
				</p>
				<p class="noviq-registration-gate__footer">
					<a class="noviq-registration-gate__link" href="<?php echo esc_url( add_query_arg( self::QUERY_STATUS, 'signin', self::current_url() ) ); ?>" data-noviq-gate-show="signin">
						<?php esc_html_e( 'Send another link', 'noviq-core' ); ?>
					</a>
					<span aria-hidden="true"> · </span>
					<a class="noviq-registration-gate__link" href="<?php echo esc_url( remove_query_arg( self::QUERY_STATUS, self::current_url() ) ); ?>" data-noviq-gate-show="register">
						<?php esc_html_e( 'Register', 'noviq-core' ); ?>
					</a>
				</p>
			</div>

			<div class="noviq-registration-gate__ask" data-noviq-gate-panel="denied" <?php echo 'denied' === $panel ? '' : 'hidden'; ?>>
				<h1 class="noviq-registration-gate__heading"><?php esc_html_e( 'You must be 21 or older to enter', 'noviq-core' ); ?></h1>
				<p class="noviq-registration-gate__body">
					<?php esc_html_e( 'These materials are supplied for in-vitro laboratory research only, to researchers aged 21 or over. We cannot give you access to this site.', 'noviq-core' ); ?>
				</p>
				<p class="noviq-registration-gate__footer">
					<a class="noviq-registration-gate__link" href="<?php echo esc_url( remove_query_arg( self::QUERY_STATUS, self::current_url() ) ); ?>" data-noviq-gate-show="register">
						<?php esc_html_e( 'Go back', 'noviq-core' ); ?>
					</a>
				</p>
			</div>
		</div>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
		<?php
	}

	private static function render_status_notice( string $status ): void {
		if ( 'err' === $status ) {
			echo '<p class="noviq-registration-gate__notice noviq-registration-gate__notice--err" role="alert">'
				. esc_html__( 'Please enter a valid email and accept both confirmations.', 'noviq-core' )
				. '</p>';
		} elseif ( 'bad_link' === $status ) {
			echo '<p class="noviq-registration-gate__notice noviq-registration-gate__notice--err" role="alert">'
				. esc_html__( 'That sign-in link is invalid or has expired. Request a new one.', 'noviq-core' )
				. '</p>';
		}
	}

	private static function panel_for_status( string $status ): string {
		if ( in_array( $status, array( 'signin', 'sent', 'denied' ), true ) ) {
			return $status;
		}

		return 'register';
	}

	private static function current_status(): string {
		return isset( $_GET[ self::QUERY_STATUS ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( (string) wp_unslash( $_GET[ self::QUERY_STATUS ] ) )
			: '';
	}

	private static function is_gate_status_request(): bool {
		return '' !== self::current_status();
	}

	private static function verify_nonce(): bool {
		return isset( $_POST['_wpnonce'] )
			&& false !== wp_verify_nonce(
				sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ),
				self::NONCE
			);
	}

	private static function posted_email(): string {
		$email = isset( $_POST['nq_gate_email'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_email( wp_unslash( (string) $_POST['nq_gate_email'] ) )
			: '';

		return ( '' !== $email && is_email( $email ) ) ? $email : '';
	}

	private static function posted_redirect(): string {
		$raw = isset( $_POST[ self::QUERY_REDIRECT ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? (string) wp_unslash( $_POST[ self::QUERY_REDIRECT ] )
			: '';

		return self::safe_redirect_target( $raw );
	}

	private static function safe_redirect_target( string $candidate ): string {
		$fallback = home_url( '/' );
		$candidate = trim( $candidate );
		if ( '' === $candidate ) {
			return $fallback;
		}

		$validated = wp_validate_redirect( $candidate, $fallback );

		return is_string( $validated ) && '' !== $validated ? $validated : $fallback;
	}

	private static function redirect_with_status( string $redirect, string $status ): void {
		// After form POST, land on the gate with a status panel. Prefer the
		// original destination stripped of prior status, then attach ours.
		$base = remove_query_arg( array( self::QUERY_STATUS, self::QUERY_TOKEN, self::QUERY_USER ), $redirect );
		$url  = add_query_arg( self::QUERY_STATUS, $status, $base );

		// For "sent" / "err" / "bad_link" the visitor is still logged out, so
		// any non-allowlisted URL will re-render the gate. Use home with status.
		if ( ! is_user_logged_in() ) {
			$url = add_query_arg(
				array(
					self::QUERY_STATUS   => $status,
					self::QUERY_REDIRECT => $redirect,
				),
				home_url( '/' )
			);
		}

		wp_safe_redirect( $url );
		exit;
	}

	private static function record_consent( int $user_id ): void {
		update_user_meta( $user_id, self::META_CONSENT_TEXT, self::consent_text() );
		update_user_meta( $user_id, self::META_CONSENT_VERSION, self::copy_version() );
		update_user_meta( $user_id, self::META_CONSENT_AT, gmdate( 'c' ) );

		$ip = self::request_ip();
		if ( '' !== $ip ) {
			update_user_meta( $user_id, self::META_CONSENT_IP, $ip );
		}
	}

	private static function request_ip(): string {
		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		$ip = sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Issue a magic link at most once per email/IP per RATE_LIMIT_TTL seconds.
	 */
	private static function send_magic_link_rate_limited( int $user_id, string $email, string $redirect ): void {
		$ip        = self::request_ip();
		$email_key = 'noviq_gate_rl_e_' . md5( strtolower( $email ) );
		$ip_key    = '' !== $ip ? 'noviq_gate_rl_i_' . md5( $ip ) : '';

		if ( false !== get_transient( $email_key ) ) {
			return;
		}
		if ( '' !== $ip_key && false !== get_transient( $ip_key ) ) {
			return;
		}

		set_transient( $email_key, '1', self::RATE_LIMIT_TTL );
		if ( '' !== $ip_key ) {
			set_transient( $ip_key, '1', self::RATE_LIMIT_TTL );
		}

		self::send_magic_link( $user_id, $email, $redirect );
	}

	private static function send_magic_link( int $user_id, string $email, string $redirect ): void {
		$token = bin2hex( random_bytes( 32 ) );
		$hash  = wp_hash( $token );

		update_user_meta( $user_id, self::META_TOKEN_HASH, $hash );
		update_user_meta( $user_id, self::META_TOKEN_EXPIRES, (string) ( time() + self::TOKEN_TTL ) );

		$link = add_query_arg(
			array(
				self::QUERY_TOKEN    => $token,
				self::QUERY_USER     => $user_id,
				self::QUERY_REDIRECT => $redirect,
			),
			home_url( '/' )
		);

		$brand   = Claims::fact( 'name' ) ?? get_bloginfo( 'name' );
		$subject = sprintf(
			/* translators: %s: site name. */
			__( 'Your sign-in link for %s', 'noviq-core' ),
			$brand
		);
		$body = sprintf(
			/* translators: 1: site name, 2: magic-link URL. */
			__(
				"Use this one-time link to sign in to %1\$s. It expires in 15 minutes and can be used once.\n\n%2\$s\n\nIf you did not request this, you can ignore this email.",
				'noviq-core'
			),
			$brand,
			$link
		);

		$from = Claims::fact( 'support_email' );
		if ( null === $from || '' === $from || ! is_email( $from ) ) {
			$from = get_option( 'admin_email' );
		}
		$from = is_string( $from ) ? sanitize_email( $from ) : '';

		$headers = array();
		if ( '' !== $from && is_email( $from ) ) {
			$headers[] = 'From: ' . $brand . ' <' . $from . '>';
		}

		wp_mail( $email, $subject, $body, $headers );
	}

	private static function consume_token( int $user_id, string $token ): bool {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		$stored = (string) get_user_meta( $user_id, self::META_TOKEN_HASH, true );
		$expires = (int) get_user_meta( $user_id, self::META_TOKEN_EXPIRES, true );

		if ( '' === $stored || $expires < time() ) {
			self::clear_token( $user_id );

			return false;
		}

		if ( ! hash_equals( $stored, wp_hash( $token ) ) ) {
			return false;
		}

		self::clear_token( $user_id );

		return true;
	}

	private static function clear_token( int $user_id ): void {
		delete_user_meta( $user_id, self::META_TOKEN_HASH );
		delete_user_meta( $user_id, self::META_TOKEN_EXPIRES );
	}

	private static function current_url(): string {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return home_url( '/' );
		}

		return esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	/**
	 * Destination after successful auth. Prefer an explicit redirect query arg
	 * (set when bouncing through a status panel), else the current request.
	 */
	private static function current_url_for_redirect(): string {
		if ( isset( $_GET[ self::QUERY_REDIRECT ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return self::safe_redirect_target( (string) wp_unslash( $_GET[ self::QUERY_REDIRECT ] ) );
		}

		$url = self::current_url();
		$url = remove_query_arg(
			array( self::QUERY_STATUS, self::QUERY_TOKEN, self::QUERY_USER, self::QUERY_REDIRECT ),
			$url
		);

		return self::safe_redirect_target( $url );
	}

	private static function terms_url(): string {
		$page_id = function_exists( 'wc_terms_and_conditions_page_id' )
			? (int) wc_terms_and_conditions_page_id()
			: (int) get_option( 'woocommerce_terms_page_id' );
		if ( $page_id <= 0 ) {
			$by_path = get_page_by_path( 'policies/terms' );
			$page_id = $by_path instanceof \WP_Post ? (int) $by_path->ID : 0;
		}

		return $page_id > 0 ? (string) get_permalink( $page_id ) : home_url( '/policies/terms/' );
	}

	private static function privacy_url(): string {
		$page_id = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $page_id <= 0 && function_exists( 'wc_privacy_policy_page_id' ) ) {
			$page_id = (int) wc_privacy_policy_page_id();
		}
		if ( $page_id <= 0 ) {
			$by_path = get_page_by_path( 'policies/privacy' );
			$page_id = $by_path instanceof \WP_Post ? (int) $by_path->ID : 0;
		}

		return $page_id > 0 ? (string) get_permalink( $page_id ) : home_url( '/policies/privacy/' );
	}
}
