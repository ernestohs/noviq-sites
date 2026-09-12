<?php
/**
 * Production mail transport: Resend HTTP API (port 443).
 *
 * DigitalOcean and many VPS providers block outbound SMTP (25/465/587).
 * Resend API avoids that. Requires NOVIQ_RESEND_API_KEY from deploy/configure-resend.sh.
 *
 * @package Noviq\Deploy
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'NOVIQ_RESEND_API_KEY' ) || '' === NOVIQ_RESEND_API_KEY ) {
	return;
}

/**
 * @param null|bool $short_circuit Existing short-circuit value.
 * @param array<string, mixed> $atts wp_mail() arguments.
 * @return null|bool
 */
function noviq_resend_pre_wp_mail( $short_circuit, array $atts ) {
	if ( null !== $short_circuit ) {
		return $short_circuit;
	}

	$to = $atts['to'] ?? '';
	if ( is_array( $to ) ) {
		$recipients = array_values( array_filter( array_map( 'sanitize_email', $to ) ) );
	} else {
		$recipients = array_values(
			array_filter(
				array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $to ) ) )
			)
		);
	}

	if ( array() === $recipients ) {
		return false;
	}

	$subject = isset( $atts['subject'] ) ? (string) $atts['subject'] : '';
	$message = isset( $atts['message'] ) ? (string) $atts['message'] : '';

	if ( '' === $subject || '' === $message ) {
		return false;
	}

	$from_email = defined( 'NOVIQ_RESEND_FROM' ) && is_string( NOVIQ_RESEND_FROM ) && '' !== NOVIQ_RESEND_FROM
		? NOVIQ_RESEND_FROM
		: (string) get_option( 'admin_email' );

	$from_name = defined( 'NOVIQ_RESEND_FROM_NAME' ) && is_string( NOVIQ_RESEND_FROM_NAME ) && '' !== NOVIQ_RESEND_FROM_NAME
		? NOVIQ_RESEND_FROM_NAME
		: (string) get_bloginfo( 'name' );

	$from = '' !== $from_name
		? sprintf( '%s <%s>', $from_name, $from_email )
		: $from_email;

	$payload = array(
		'from'    => $from,
		'to'      => $recipients,
		'subject' => $subject,
	);

	if ( str_contains( $message, '<' ) && str_contains( $message, '>' ) ) {
		$payload['html'] = $message;
	} else {
		$payload['text'] = $message;
	}

	$response = wp_remote_post(
		'https://api.resend.com/emails',
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . NOVIQ_RESEND_API_KEY,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	return $code >= 200 && $code < 300;
}

add_filter( 'pre_wp_mail', 'noviq_resend_pre_wp_mail', 10, 2 );
