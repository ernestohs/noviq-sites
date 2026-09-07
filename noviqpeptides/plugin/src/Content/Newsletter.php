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

	public static function init(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
		add_shortcode( 'noviq_newsletter', array( self::class, 'shortcode' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
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
			$message = '<p class="nq-nl__msg nq-nl__msg--err" role="alert">' . esc_html__( 'Please enter a valid email address.', 'noviq-core' ) . '</p>';
		}

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
				<button class="nq-nl__submit" type="submit">%8$s</button>
			</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$message,
			esc_attr( self::ACTION ),
			wp_nonce_field( self::NONCE, '_wpnonce', true, false ),
			esc_html__( 'Company', 'noviq-core' ),
			esc_html__( 'Email address', 'noviq-core' ),
			esc_attr__( 'your@email.com', 'noviq-core' ),
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
						'key'   => 'noviq_subscriber_email',
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

		update_post_meta( $post_id, 'noviq_subscriber_email', $email );
		update_post_meta( $post_id, 'noviq_subscriber_ip', isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '' );

		wp_safe_redirect( add_query_arg( 'nq_nl', 'ok', $redirect ) );
		exit;
	}
}
