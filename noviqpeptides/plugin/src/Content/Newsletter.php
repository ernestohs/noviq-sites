<?php
/**
 * Storefront newsletter signup.
 *
 * Submissions land as `noviq_subscriber` posts so nothing is lost before an ESP
 * is wired. Presentation stays in the theme via the shortcode.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Content;

defined( 'ABSPATH' ) || exit;

final class Newsletter {

	public const POST_TYPE = 'noviq_subscriber';
	public const ACTION    = 'noviq_newsletter_subscribe';
	public const NONCE     = 'noviq_newsletter';

	public const META_EMAIL      = 'noviq_subscriber_email';
	public const META_CONSENT_AT = 'noviq_subscriber_consent_at';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
		add_shortcode( 'noviq_newsletter', array( self::class, 'shortcode' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );

		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
	}

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Newsletter subscribers', 'noviq-core' ),
					'singular_name' => __( 'Subscriber', 'noviq-core' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'menu_icon'           => 'dashicons-email-alt',
				'exclude_from_search' => true,
			)
		);
	}

	/**
	 * Footer / landing newsletter form.
	 */
	public static function shortcode(): string {
		$status = isset( $_GET['nq_nl'] ) ? sanitize_key( (string) wp_unslash( $_GET['nq_nl'] ) ) : '';

		$message = '';
		if ( 'ok' === $status ) {
			$message = '<p class="nq-nl__msg nq-nl__msg--ok" role="status">' . esc_html__( 'Thanks. You are on the list.', 'noviq-core' ) . '</p>';
		} elseif ( 'dup' === $status ) {
			$message = '<p class="nq-nl__msg nq-nl__msg--ok" role="status">' . esc_html__( 'That email is already subscribed.', 'noviq-core' ) . '</p>';
		} elseif ( 'err' === $status ) {
			$message = '<p class="nq-nl__msg nq-nl__msg--err" role="alert">' . esc_html__( 'Please enter a valid email address and confirm consent.', 'noviq-core' ) . '</p>';
		}

		$privacy_url = self::privacy_url();
		$consent     = sprintf(
			/* translators: %s: privacy policy URL. */
			__( 'I agree to receive product and documentation updates by email. See the <a href="%s">privacy policy</a>.', 'noviq-core' ),
			esc_url( $privacy_url )
		);

		return sprintf(
			'<form class="nq-nl" method="post" action="%1$s">
				%2$s
				<input type="hidden" name="action" value="%3$s" />
				%4$s
				<p class="nq-nl__honeypot" aria-hidden="true">
					<label for="nq_nl_company">%5$s</label>
					<input type="text" name="nq_nl_company" id="nq_nl_company" tabindex="-1" autocomplete="off" />
				</p>
				<label class="screen-reader-text" for="nq_nl_email">%6$s</label>
				<input class="nq-nl__input" type="email" name="nq_nl_email" id="nq_nl_email" required placeholder="%7$s" autocomplete="email" />
				<label class="nq-nl__consent" for="nq_nl_consent">
					<input type="checkbox" name="nq_nl_consent" id="nq_nl_consent" value="1" required aria-required="true" />
					<span>%8$s</span>
				</label>
				<button class="nq-nl__submit" type="submit">%9$s</button>
			</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$message,
			esc_attr( self::ACTION ),
			wp_nonce_field( self::NONCE, '_wpnonce', true, false ),
			esc_html__( 'Company', 'noviq-core' ),
			esc_html__( 'Email address', 'noviq-core' ),
			esc_attr__( 'your@email.com', 'noviq-core' ),
			wp_kses( $consent, array( 'a' => array( 'href' => true ) ) ),
			esc_html__( 'Subscribe', 'noviq-core' )
		);
	}

	public static function handle(): void {
		$redirect = wp_get_referer() ?: home_url( '/' );
		$redirect = remove_query_arg( 'nq_nl', $redirect );

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), self::NONCE ) ) {
			wp_safe_redirect( add_query_arg( 'nq_nl', 'err', $redirect ) );
			exit;
		}

		// Honeypot: bots fill this; humans leave it empty.
		$trap = isset( $_POST['nq_nl_company'] ) ? trim( (string) wp_unslash( $_POST['nq_nl_company'] ) ) : '';
		if ( '' !== $trap ) {
			wp_safe_redirect( add_query_arg( 'nq_nl', 'ok', $redirect ) );
			exit;
		}

		$consent = isset( $_POST['nq_nl_consent'] ) && '1' === (string) wp_unslash( $_POST['nq_nl_consent'] );
		if ( ! $consent ) {
			wp_safe_redirect( add_query_arg( 'nq_nl', 'err', $redirect ) );
			exit;
		}

		$email = isset( $_POST['nq_nl_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['nq_nl_email'] ) ) : '';
		if ( '' === $email || ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'nq_nl', 'err', $redirect ) );
			exit;
		}

		$existing = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => self::META_EMAIL,
						'value' => $email,
					),
				),
			)
		);

		if ( array() !== $existing ) {
			wp_safe_redirect( add_query_arg( 'nq_nl', 'dup', $redirect ) );
			exit;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $email,
				'post_status' => 'private',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_safe_redirect( add_query_arg( 'nq_nl', 'err', $redirect ) );
			exit;
		}

		update_post_meta( $post_id, self::META_EMAIL, $email );
		update_post_meta( $post_id, self::META_CONSENT_AT, gmdate( 'c' ) );

		wp_safe_redirect( add_query_arg( 'nq_nl', 'ok', $redirect ) );
		exit;
	}

	/**
	 * @param array<string, array<string, mixed>> $exporters Exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['noviq-newsletter'] = array(
			'exporter_friendly_name' => __( 'Noviq newsletter subscribers', 'noviq-core' ),
			'callback'               => array( self::class, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * @param array<string, array<string, mixed>> $erasers Erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['noviq-newsletter'] = array(
			'eraser_friendly_name' => __( 'Noviq newsletter subscribers', 'noviq-core' ),
			'callback'             => array( self::class, 'erase_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * @return array{data: array<int, array{name: string, value: string}>, done: bool}
	 */
	public static function export_personal_data( string $email_address, int $page = 1 ): array {
		$ids = self::subscriber_ids_for_email( $email_address );
		$data = array();

		foreach ( $ids as $id ) {
			$consent = (string) get_post_meta( $id, self::META_CONSENT_AT, true );
			$item    = array(
				'group_id'          => 'noviq-newsletter',
				'group_label'       => __( 'Newsletter subscription', 'noviq-core' ),
				'group_description' => __( 'Email address used for product and documentation updates.', 'noviq-core' ),
				'item_id'           => 'noviq-subscriber-' . $id,
				'data'              => array(
					array(
						'name'  => __( 'Email', 'noviq-core' ),
						'value' => (string) get_post_meta( $id, self::META_EMAIL, true ),
					),
					array(
						'name'  => __( 'Consent timestamp (UTC)', 'noviq-core' ),
						'value' => '' !== $consent ? $consent : __( 'Not recorded', 'noviq-core' ),
					),
				),
			);
			$data[] = $item;
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public static function erase_personal_data( string $email_address, int $page = 1 ): array {
		$ids      = self::subscriber_ids_for_email( $email_address );
		$removed  = false;
		$messages = array();

		foreach ( $ids as $id ) {
			$result = wp_delete_post( $id, true );
			if ( $result ) {
				$removed    = true;
				$messages[] = sprintf(
					/* translators: %d: subscriber post ID. */
					__( 'Removed newsletter subscriber record #%d.', 'noviq-core' ),
					$id
				);
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * @return array<int, int>
	 */
	private static function subscriber_ids_for_email( string $email_address ): array {
		$email = sanitize_email( $email_address );
		if ( '' === $email || ! is_email( $email ) ) {
			return array();
		}

		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 100,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => self::META_EMAIL,
						'value' => $email,
					),
				),
			)
		);

		return array_map( 'intval', $ids );
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
