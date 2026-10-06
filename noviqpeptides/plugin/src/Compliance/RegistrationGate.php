<?php
/**
 * Require a registered account before any catalog content is shown.
 *
 * Tagada (and the processor risk model) needs visitors registered before they
 * see products. Unlike AgeGate, this is real access control: product HTML is
 * never sent to a logged-out visitor. Legal pages, password-reset endpoints,
 * and payment/admin side channels stay reachable.
 *
 * Evidence of the researcher attestation and terms acceptance is stored as
 * user meta at registration time (mirroring Attestation on orders).
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

	public const ACTION_REGISTER = 'noviq_register';
	public const ACTION_SIGNIN   = 'noviq_signin';

	public const META_FIELD       = '_noviq_reg_field';
	public const META_ATTESTATION = '_noviq_reg_attestation_text';
	public const META_TERMS       = '_noviq_reg_terms_accepted';
	public const META_AT          = '_noviq_reg_at';
	public const META_IP          = '_noviq_reg_ip';
	public const META_COPY        = '_noviq_reg_copy_version';

	/** Guards against printing the wall twice if both hooks somehow fire. */
	private static bool $rendered = false;

	public static function init(): void {
		// After ReferralCoupon::handle_go (priority 5) so VIP stash/OG preview
		// still run before the wall takes the request.
		add_action( 'template_redirect', array( self::class, 'maybe_wall' ), 9 );

		add_action( 'admin_post_nopriv_' . self::ACTION_REGISTER, array( self::class, 'handle_register' ) );
		add_action( 'admin_post_' . self::ACTION_REGISTER, array( self::class, 'handle_register' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SIGNIN, array( self::class, 'handle_signin' ) );
		add_action( 'admin_post_' . self::ACTION_SIGNIN, array( self::class, 'handle_signin' ) );

		add_filter( 'rest_authentication_errors', array( self::class, 'rest_gate' ) );
		add_filter( 'wp_sitemaps_post_types', array( self::class, 'filter_sitemap_post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( self::class, 'filter_sitemap_taxonomies' ) );

		add_action( 'show_user_profile', array( self::class, 'render_admin_profile' ) );
		add_action( 'edit_user_profile', array( self::class, 'render_admin_profile' ) );
	}

	/**
	 * Heading shown on the wall. Profile-driven so copy can change without a
	 * code edit.
	 */
	public static function heading(): string {
		$cfg = Profile::registration_gate();

		return '' !== $cfg['heading']
			? $cfg['heading']
			: __( 'Researcher access', 'noviq-core' );
	}

	/**
	 * Intro paragraph under the heading.
	 */
	public static function intro(): string {
		$cfg = Profile::registration_gate();

		if ( '' !== $cfg['intro'] ) {
			return $cfg['intro'];
		}

		$ruo = Claims::ruo_short();

		return '' !== $ruo
			? $ruo . ' ' . __( 'Confirm once to enter; we will remember you on this device while you stay signed in.', 'noviq-core' )
			: __( 'Products are for laboratory research use only. Confirm once to enter; we will remember you on this device while you stay signed in.', 'noviq-core' );
	}

	/**
	 * Researcher attestation checkbox wording. Defaults to the checkout
	 * attestation so the two records cannot drift apart by accident.
	 */
	public static function attestation_text(): string {
		$cfg = Profile::registration_gate();

		if ( '' !== $cfg['attestation'] ) {
			return $cfg['attestation'];
		}

		return Attestation::text();
	}

	/**
	 * @return array<string, string> slug => label
	 */
	public static function research_fields(): array {
		return Profile::registration_gate()['research_fields'];
	}

	public static function copy_version(): string {
		return Profile::registration_gate()['copy_version'];
	}

	/**
	 * Front-end wall. Logged-in visitors and allowlisted URLs pass through.
	 */
	public static function maybe_wall(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		if ( self::is_allowlisted() ) {
			return;
		}

		if ( is_feed() ) {
			status_header( 401 );
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow', true );
			exit;
		}

		self::render_wall();
		exit;
	}

	/**
	 * Paths a logged-out visitor may still reach without registering.
	 */
	public static function is_allowlisted(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			// REST is gated separately in rest_gate(); do not wall HTML here.
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		if ( is_robots() || is_favicon() ) {
			return true;
		}

		// Core XML sitemaps: allow the request so filters can strip catalog
		// entries; do not serve the registration HTML document as XML.
		if ( get_query_var( 'sitemap' ) || get_query_var( 'sitemap-stylesheet' ) ) {
			return true;
		}

		if ( is_customize_preview() ) {
			return true;
		}

		// Password reset must stay reachable from the welcome / reset email.
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			if ( function_exists( 'is_wc_endpoint_url' )
				&& ( is_wc_endpoint_url( 'lost-password' ) || is_wc_endpoint_url( 'reset-password' ) )
			) {
				return true;
			}
		}

		if ( self::is_policy_page() ) {
			return true;
		}

		return false;
	}

	/**
	 * True when the main queried object is a page under policies/.
	 */
	private static function is_policy_page(): bool {
		if ( ! is_page() ) {
			return false;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$path = get_page_uri( $post );

		return is_string( $path ) && str_starts_with( $path, 'policies/' );
	}

	/**
	 * Block product/catalog REST for guests. Other routes (Tagada webhooks,
	 * Woo order webhooks, etc.) stay open.
	 *
	 * @param \WP_Error|null|true $result Auth result so far.
	 * @return \WP_Error|null|true
	 */
	public static function rest_gate( $result ) {
		if ( ! empty( $result ) ) {
			return $result;
		}

		if ( is_user_logged_in() ) {
			return $result;
		}

		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] )
			? (string) $GLOBALS['wp']->query_vars['rest_route']
			: '';

		if ( '' === $route && isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
			if ( preg_match( '#/wp-json(/.*)?$#', $uri, $m ) ) {
				$route = $m[1] ?? '/';
			}
		}

		$route = '/' . ltrim( $route, '/' );

		if ( self::is_blocked_rest_route( $route ) ) {
			return new \WP_Error(
				'noviq_registration_required',
				__( 'An account is required to view catalog data.', 'noviq-core' ),
				array( 'status' => 401 )
			);
		}

		return $result;
	}

	/**
	 * Store API, product REST, and public CPT catalogs.
	 */
	private static function is_blocked_rest_route( string $route ): bool {
		$blocked_prefixes = array(
			'/wc/store',
			'/wc/v3/products',
			'/wc/v2/products',
			'/wc/v1/products',
			'/wp/v2/product',
			'/wp/v2/compounds',
			'/wp/v2/lots',
			'/wp/v2/comparisons',
		);

		foreach ( $blocked_prefixes as $prefix ) {
			if ( str_starts_with( $route, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, \WP_Post_Type> $post_types Sitemap post types.
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
	 * @param array<string, \WP_Taxonomy> $taxonomies Sitemap taxonomies.
	 * @return array<string, \WP_Taxonomy>
	 */
	public static function filter_sitemap_taxonomies( array $taxonomies ): array {
		unset(
			$taxonomies['product_cat'],
			$taxonomies['product_tag'],
			$taxonomies['product_shipping_class']
		);

		return $taxonomies;
	}

	public static function handle_register(): void {
		if ( ! isset( $_POST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::ACTION_REGISTER )
		) {
			self::redirect_error( 'nonce', 'register' );
		}

		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$field    = isset( $_POST['research_field'] ) ? sanitize_key( wp_unslash( (string) $_POST['research_field'] ) ) : '';
		$attest   = ! empty( $_POST['attestation'] );

		if ( '' === $email || '' === $password || '' === $field ) {
			self::redirect_error( 'missing_fields', 'register', $email );
		}

		if ( ! is_email( $email ) ) {
			self::redirect_error( 'invalid_email', 'register', $email );
		}

		if ( email_exists( $email ) ) {
			self::redirect_error( 'email_exists', 'register', $email );
		}

		$fields = self::research_fields();
		if ( ! isset( $fields[ $field ] ) ) {
			self::redirect_error( 'invalid_field', 'register', $email );
		}

		// One checkbox covers researcher attestation and Terms/Privacy; both
		// are recorded as separate user-meta evidence below.
		if ( ! $attest ) {
			self::redirect_error( 'attestation_required', 'register', $email );
		}

		if ( strlen( $password ) < 8 ) {
			self::redirect_error( 'weak_password', 'register', $email );
		}

		if ( ! function_exists( 'wc_create_new_customer' ) ) {
			self::redirect_error( 'create_failed', 'register', $email );
		}

		$user_id = wc_create_new_customer( $email, '', $password );
		if ( is_wp_error( $user_id ) ) {
			$code = $user_id->get_error_code();
			if ( 'registration-error-email-exists' === $code ) {
				self::redirect_error( 'email_exists', 'register', $email );
			}
			self::redirect_error( 'create_failed', 'register', $email );
		}

		$user_id = (int) $user_id;

		update_user_meta( $user_id, self::META_FIELD, $field );
		update_user_meta( $user_id, self::META_ATTESTATION, self::attestation_text() );
		update_user_meta( $user_id, self::META_TERMS, 'yes' );
		update_user_meta( $user_id, self::META_AT, gmdate( 'c' ) );
		update_user_meta( $user_id, self::META_COPY, self::copy_version() );

		$ip = self::request_ip();
		if ( '' !== $ip ) {
			update_user_meta( $user_id, self::META_IP, $ip );
		}

		if ( function_exists( 'wc_set_customer_auth_cookie' ) ) {
			wc_set_customer_auth_cookie( $user_id );
		} else {
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true );
		}

		wp_safe_redirect( self::safe_redirect_target() );
		exit;
	}

	public static function handle_signin(): void {
		if ( ! isset( $_POST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::ACTION_SIGNIN )
		) {
			self::redirect_error( 'nonce', 'signin' );
		}

		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		if ( '' === $email || '' === $password || ! is_email( $email ) ) {
			self::redirect_error( 'bad_credentials', 'signin', $email );
		}

		$user = wp_signon(
			array(
				'user_login'    => $email,
				'user_password' => $password,
				'remember'      => true,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			// Woo stores customers with email as login when generate_username
			// is off; when on, login may be a derived username. Retry by email.
			$by_email = get_user_by( 'email', $email );
			if ( $by_email instanceof \WP_User ) {
				$user = wp_signon(
					array(
						'user_login'    => $by_email->user_login,
						'user_password' => $password,
						'remember'      => true,
					),
					is_ssl()
				);
			}
		}

		if ( is_wp_error( $user ) ) {
			self::redirect_error( 'bad_credentials', 'signin', $email );
		}

		wp_safe_redirect( self::safe_redirect_target() );
		exit;
	}

	/**
	 * @param 'register'|'signin' $view
	 */
	private static function redirect_error( string $code, string $view, string $email = '' ): void {
		$args = array(
			'nq_reg'  => $code,
			'nq_view' => $view,
		);

		if ( '' !== $email ) {
			$args['nq_email'] = $email;
		}

		$redirect = isset( $_POST['redirect_to'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller verified nonce.
			? self::sanitize_redirect( (string) wp_unslash( $_POST['redirect_to'] ) )
			: home_url( '/' );

		$target = add_query_arg( $args, $redirect );
		wp_safe_redirect( $target );
		exit;
	}

	private static function safe_redirect_target(): string {
		$raw = isset( $_POST['redirect_to'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller verified nonce.
			? (string) wp_unslash( $_POST['redirect_to'] )
			: '';

		return self::sanitize_redirect( $raw );
	}

	private static function sanitize_redirect( string $raw ): string {
		$fallback = home_url( '/' );
		$raw      = esc_url_raw( $raw );

		if ( '' === $raw ) {
			return $fallback;
		}

		return wp_validate_redirect( $raw, $fallback );
	}

	private static function request_ip(): string {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );

			return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
		}

		return '';
	}

	private static function current_url(): string {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return home_url( '/' );
		}

		return home_url( esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) );
	}

	/**
	 * Strip gate status query args so a successful submit does not re-show them.
	 */
	private static function clean_redirect_url(): string {
		$url = self::current_url();

		return remove_query_arg( array( 'nq_reg', 'nq_view', 'nq_email' ), $url );
	}

	/**
	 * @return 'register'|'signin'
	 */
	private static function current_view(): string {
		$view = isset( $_GET['nq_view'] ) ? sanitize_key( wp_unslash( (string) $_GET['nq_view'] ) ) : 'register';

		return 'signin' === $view ? 'signin' : 'register';
	}

	private static function sticky_email(): string {
		if ( ! isset( $_GET['nq_email'] ) ) {
			return '';
		}

		$email = sanitize_email( wp_unslash( (string) $_GET['nq_email'] ) );

		return is_email( $email ) ? $email : '';
	}

	private static function error_message(): string {
		if ( ! isset( $_GET['nq_reg'] ) ) {
			return '';
		}

		$code = sanitize_key( wp_unslash( (string) $_GET['nq_reg'] ) );

		$messages = array(
			'nonce'                => __( 'Your session expired. Please try again.', 'noviq-core' ),
			'missing_fields'       => __( 'Please fill in every required field.', 'noviq-core' ),
			'invalid_email'        => __( 'Enter a valid email address.', 'noviq-core' ),
			'email_exists'         => __( 'An account with that email already exists. Sign in instead.', 'noviq-core' ),
			'weak_password'        => __( 'Choose a password of at least 8 characters.', 'noviq-core' ),
			'invalid_field'        => __( 'Select a field of research.', 'noviq-core' ),
			'attestation_required' => __( 'You must confirm the researcher attestation and accept the Terms & Conditions and Privacy Policy.', 'noviq-core' ),
			'create_failed'        => __( 'We could not create your account. Please try again.', 'noviq-core' ),
			'bad_credentials'      => __( 'That email and password combination is not correct.', 'noviq-core' ),
		);

		return $messages[ $code ] ?? __( 'Something went wrong. Please try again.', 'noviq-core' );
	}

	private static function terms_url(): string {
		$id = (int) get_option( 'woocommerce_terms_page_id' );
		if ( $id > 0 ) {
			$url = get_permalink( $id );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		$page = get_page_by_path( 'policies/terms' );

		return $page instanceof \WP_Post ? (string) get_permalink( $page ) : home_url( '/policies/terms/' );
	}

	private static function privacy_url(): string {
		$id = (int) get_option( 'woocommerce_privacy_policy_page_id' );
		if ( $id <= 0 ) {
			$id = (int) get_option( 'wp_page_for_privacy_policy' );
		}
		if ( $id > 0 ) {
			$url = get_permalink( $id );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		$page = get_page_by_path( 'policies/privacy' );

		return $page instanceof \WP_Post ? (string) get_permalink( $page ) : home_url( '/policies/privacy/' );
	}

	private static function lost_password_url(): string {
		if ( function_exists( 'wc_lostpassword_url' ) ) {
			return (string) wc_lostpassword_url();
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$base = (string) wc_get_page_permalink( 'myaccount' );
			if ( '' !== $base ) {
				return wc_get_endpoint_url( 'lost-password', '', $base );
			}
		}

		return wp_lostpassword_url();
	}

	private static function render_wall(): void {
		if ( self::$rendered ) {
			return;
		}
		self::$rendered = true;

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		status_header( 200 );

		$brand   = Claims::fact( 'name' ) ?? get_bloginfo( 'name' );
		$view    = self::current_view();
		$error   = self::error_message();
		$email   = self::sticky_email();
		$redirect = self::clean_redirect_url();
		$fields  = self::research_fields();
		$css_url = NOVIQ_CORE_URL . 'assets/noviq-core.css?ver=' . rawurlencode( VERSION );
		$js_url  = NOVIQ_CORE_URL . 'assets/registration-gate.js?ver=' . rawurlencode( VERSION );

		$fonts_url = '';
		$theme_fonts = get_stylesheet_directory() . '/assets/css/fonts.css';
		if ( is_readable( $theme_fonts ) ) {
			$fonts_url = get_stylesheet_directory_uri() . '/assets/css/fonts.css';
		}

		$icon_base = get_stylesheet_directory_uri() . '/assets/';

		$register_action = esc_url( admin_url( 'admin-post.php' ) );
		$signin_action   = $register_action;
		$terms_url       = self::terms_url();
		$privacy_url     = self::privacy_url();
		$lost_url        = self::lost_password_url();
		$attest_text     = self::attestation_text();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- full HTML document; values escaped below.
		echo '<!DOCTYPE html>' . "\n";
		?>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( self::heading() . ' — ' . $brand ); ?></title>
	<link rel="icon" href="<?php echo esc_url( $icon_base . 'favicon.svg' ); ?>" type="image/svg+xml">
	<link rel="icon" href="<?php echo esc_url( $icon_base . 'favicon-32x32.png' ); ?>" type="image/png" sizes="32x32">
	<link rel="icon" href="<?php echo esc_url( $icon_base . 'favicon-16x16.png' ); ?>" type="image/png" sizes="16x16">
	<link rel="shortcut icon" href="<?php echo esc_url( $icon_base . 'favicon.ico' ); ?>">
	<link rel="apple-touch-icon" href="<?php echo esc_url( $icon_base . 'apple-touch-icon.png' ); ?>" sizes="180x180">
	<?php if ( '' !== $fonts_url ) : ?>
		<link rel="stylesheet" href="<?php echo esc_url( $fonts_url ); ?>">
	<?php endif; ?>
	<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>">
</head>
<body class="noviq-reg-gate-body">
	<main class="noviq-reg-gate" data-noviq-reg-gate data-view="<?php echo esc_attr( $view ); ?>">
		<div class="noviq-reg-gate__panel">
			<p class="noviq-reg-gate__brand"><?php echo esc_html( $brand ); ?></p>
			<h1 class="noviq-reg-gate__heading"><?php echo esc_html( self::heading() ); ?></h1>
			<p class="noviq-reg-gate__intro"><?php echo esc_html( self::intro() ); ?></p>

			<?php if ( '' !== $error ) : ?>
				<p class="noviq-reg-gate__error" role="alert"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>

			<nav class="noviq-reg-gate__tabs" aria-label="<?php esc_attr_e( 'Account', 'noviq-core' ); ?>">
				<a class="noviq-reg-gate__tab<?php echo 'register' === $view ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( 'nq_view', 'register', $redirect ) ); ?>"
					data-noviq-reg-tab="register"
					<?php echo 'register' === $view ? 'aria-current="page"' : ''; ?>>
					<?php esc_html_e( 'Register', 'noviq-core' ); ?>
				</a>
				<a class="noviq-reg-gate__tab<?php echo 'signin' === $view ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( 'nq_view', 'signin', $redirect ) ); ?>"
					data-noviq-reg-tab="signin"
					<?php echo 'signin' === $view ? 'aria-current="page"' : ''; ?>>
					<?php esc_html_e( 'Sign in', 'noviq-core' ); ?>
				</a>
			</nav>

			<form class="noviq-reg-gate__form<?php echo 'register' === $view ? ' is-active' : ''; ?>"
				method="post" action="<?php echo $register_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>"
				data-noviq-reg-form="register"
				<?php echo 'register' !== $view ? 'hidden' : ''; ?>>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REGISTER ); ?>">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
				<?php wp_nonce_field( self::ACTION_REGISTER ); ?>

				<label class="noviq-reg-gate__label" for="noviq-reg-email">
					<?php esc_html_e( 'Email', 'noviq-core' ); ?>
				</label>
				<input class="noviq-reg-gate__input" type="email" name="email" id="noviq-reg-email"
					value="<?php echo esc_attr( $email ); ?>" required autocomplete="email"
					placeholder="<?php esc_attr_e( 'you@lab.com', 'noviq-core' ); ?>">

				<label class="noviq-reg-gate__label" for="noviq-reg-password">
					<?php esc_html_e( 'Password', 'noviq-core' ); ?>
				</label>
				<input class="noviq-reg-gate__input" type="password" name="password" id="noviq-reg-password"
					required autocomplete="new-password" minlength="8">

				<label class="noviq-reg-gate__label" for="noviq-reg-field">
					<?php esc_html_e( 'Field of research', 'noviq-core' ); ?>
				</label>
				<select class="noviq-reg-gate__input" name="research_field" id="noviq-reg-field" required>
					<option value=""><?php esc_html_e( 'Select…', 'noviq-core' ); ?></option>
					<?php foreach ( $fields as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<label class="noviq-reg-gate__check">
					<input type="checkbox" name="attestation" value="1" required aria-required="true" data-noviq-reg-check>
					<span>
						<?php
						echo esc_html( $attest_text );
						echo ' ';
						echo wp_kses(
							sprintf(
								/* translators: 1: terms URL, 2: privacy URL */
								__( 'I also accept the <a href="%1$s" target="_blank" rel="noopener noreferrer">Terms &amp; Conditions</a> and <a href="%2$s" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.', 'noviq-core' ),
								esc_url( $terms_url ),
								esc_url( $privacy_url )
							),
							array(
								'a' => array(
									'href'   => array(),
									'target' => array(),
									'rel'    => array(),
								),
							)
						);
						?>
					</span>
				</label>

				<button type="submit" class="noviq-btn noviq-reg-gate__submit" data-noviq-reg-submit>
					<?php esc_html_e( 'Confirm & enter', 'noviq-core' ); ?>
				</button>
			</form>

			<form class="noviq-reg-gate__form<?php echo 'signin' === $view ? ' is-active' : ''; ?>"
				method="post" action="<?php echo $signin_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>"
				data-noviq-reg-form="signin"
				<?php echo 'signin' !== $view ? 'hidden' : ''; ?>>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SIGNIN ); ?>">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
				<?php wp_nonce_field( self::ACTION_SIGNIN ); ?>

				<label class="noviq-reg-gate__label" for="noviq-signin-email">
					<?php esc_html_e( 'Email', 'noviq-core' ); ?>
				</label>
				<input class="noviq-reg-gate__input" type="email" name="email" id="noviq-signin-email"
					value="<?php echo esc_attr( $email ); ?>" required autocomplete="email">

				<label class="noviq-reg-gate__label" for="noviq-signin-password">
					<?php esc_html_e( 'Password', 'noviq-core' ); ?>
				</label>
				<input class="noviq-reg-gate__input" type="password" name="password" id="noviq-signin-password"
					required autocomplete="current-password">

				<button type="submit" class="noviq-btn noviq-reg-gate__submit">
					<?php esc_html_e( 'Sign in', 'noviq-core' ); ?>
				</button>

				<p class="noviq-reg-gate__forgot">
					<a href="<?php echo esc_url( $lost_url ); ?>"><?php esc_html_e( 'Forgot password?', 'noviq-core' ); ?></a>
				</p>
			</form>

			<p class="noviq-reg-gate__ruo"><?php echo esc_html( Claims::ruo_short() ); ?></p>
		</div>
	</main>
	<script src="<?php echo esc_url( $js_url ); ?>" defer></script>
</body>
</html>
		<?php
	}

	/**
	 * Show registration evidence on the admin user profile.
	 */
	public static function render_admin_profile( \WP_User $user ): void {
		$at = (string) get_user_meta( $user->ID, self::META_AT, true );
		if ( '' === $at ) {
			return;
		}

		$field_slug = (string) get_user_meta( $user->ID, self::META_FIELD, true );
		$fields     = self::research_fields();
		$field_label = $fields[ $field_slug ] ?? $field_slug;
		$attest     = (string) get_user_meta( $user->ID, self::META_ATTESTATION, true );
		$terms      = (string) get_user_meta( $user->ID, self::META_TERMS, true );
		$ip         = (string) get_user_meta( $user->ID, self::META_IP, true );
		$copy       = (string) get_user_meta( $user->ID, self::META_COPY, true );

		?>
		<h2><?php esc_html_e( 'Researcher registration', 'noviq-core' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Accepted', 'noviq-core' ); ?></th>
				<td><?php echo esc_html( $at ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Field of research', 'noviq-core' ); ?></th>
				<td><?php echo esc_html( $field_label ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Terms accepted', 'noviq-core' ); ?></th>
				<td><?php echo esc_html( 'yes' === $terms ? __( 'Yes', 'noviq-core' ) : __( 'No', 'noviq-core' ) ); ?></td>
			</tr>
			<?php if ( '' !== $copy ) : ?>
			<tr>
				<th><?php esc_html_e( 'Copy version', 'noviq-core' ); ?></th>
				<td><?php echo esc_html( $copy ); ?></td>
			</tr>
			<?php endif; ?>
			<?php if ( '' !== $ip ) : ?>
			<tr>
				<th><?php esc_html_e( 'IP at registration', 'noviq-core' ); ?></th>
				<td><?php echo esc_html( $ip ); ?></td>
			</tr>
			<?php endif; ?>
			<?php if ( '' !== $attest ) : ?>
			<tr>
				<th><?php esc_html_e( 'Attestation text', 'noviq-core' ); ?></th>
				<td><em><?php echo esc_html( $attest ); ?></em></td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}
}
