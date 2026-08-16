<?php
/**
 * 21+ age gate.
 *
 * @package NoviqPeptides
 */

declare(strict_types=1);

namespace NoviqPeptides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Age_Gate {

	public const COPY_VERSION = '1';
	public const COOKIE_NAME  = 'noviq_age_ok';

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'wp_footer', array( self::class, 'render' ), 5 );
		add_action( 'admin_post_nopriv_noviq_age_gate', array( self::class, 'handle' ) );
		add_action( 'admin_post_noviq_age_gate', array( self::class, 'handle' ) );
	}

	public static function copy_version(): string {
		return (string) get_option( 'noviq_age_gate_copy_version', self::COPY_VERSION );
	}

	public static function is_verified(): bool {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return false;
		}
		$raw = sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE_NAME ] ) );
		$parts = explode( ':', $raw, 2 );
		return isset( $parts[0], $parts[1] ) && hash_equals( self::copy_version(), $parts[0] ) && '1' === $parts[1];
	}

	public static function assets(): void {
		if ( is_admin() || self::is_verified() ) {
			return;
		}
		wp_enqueue_style(
			'noviq-age-gate',
			NOVIQ_PEPTIDES_URL . 'assets/age-gate.css',
			array(),
			NOVIQ_PEPTIDES_VERSION
		);
		wp_enqueue_script(
			'noviq-age-gate',
			NOVIQ_PEPTIDES_URL . 'assets/age-gate.js',
			array(),
			NOVIQ_PEPTIDES_VERSION,
			true
		);
	}

	public static function render(): void {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		$action = esc_url( admin_url( 'admin-post.php' ) );
		$version = esc_attr( self::copy_version() );
		$verified = self::is_verified();
		?>
		<div id="noviq-age-gate" class="noviq-age-gate<?php echo $verified ? ' is-dismissed' : ''; ?>" data-version="<?php echo $version; ?>" <?php echo $verified ? 'hidden' : ''; ?> role="dialog" aria-modal="true" aria-labelledby="noviq-age-gate-title">
			<div class="noviq-age-gate__panel">
				<h2 id="noviq-age-gate-title">Age confirmation</h2>
				<p>You must be 21 years of age or older to enter this research catalog.</p>
				<form method="post" action="<?php echo $action; ?>" class="noviq-age-gate__form">
					<input type="hidden" name="action" value="noviq_age_gate" />
					<?php wp_nonce_field( 'noviq_age_gate', 'noviq_age_nonce' ); ?>
					<input type="hidden" name="noviq_age_version" value="<?php echo $version; ?>" />
					<button type="submit" name="noviq_age_answer" value="yes" class="noviq-btn noviq-btn--primary">I am 21 or older</button>
					<button type="submit" name="noviq_age_answer" value="no" class="noviq-btn noviq-btn--secondary">I am under 21</button>
				</form>
				<div class="noviq-age-gate__refused" hidden>
					<p>Access refused. This catalog is limited to adults 21 years of age or older.</p>
				</div>
			</div>
		</div>
		<?php
	}

	public static function handle(): void {
		if ( ! isset( $_POST['noviq_age_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noviq_age_nonce'] ) ), 'noviq_age_gate' ) ) {
			wp_die( 'Invalid request.' );
		}
		$answer  = isset( $_POST['noviq_age_answer'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['noviq_age_answer'] ) ) : '';
		$version = isset( $_POST['noviq_age_version'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['noviq_age_version'] ) ) : self::copy_version();
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );

		if ( 'yes' === $answer && hash_equals( self::copy_version(), $version ) ) {
			$value = self::copy_version() . ':1';
			setcookie( self::COOKIE_NAME, $value, time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
			wp_safe_redirect( $redirect );
			exit;
		}

		setcookie( self::COOKIE_NAME, self::copy_version() . ':0', time() + HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		wp_safe_redirect( add_query_arg( 'noviq_age_refused', '1', $redirect ) );
		exit;
	}
}
