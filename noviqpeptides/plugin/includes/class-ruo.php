<?php
/**
 * RUO notice single source.
 *
 * @package NoviqPeptides
 */

declare(strict_types=1);

namespace NoviqPeptides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RUO {

	public const NOTICE_VERSION = '1';

	public static function init(): void {
		// Getter only; theme prints.
	}

	/**
	 * Verbatim RUO notice. Do not paraphrase in templates.
	 */
	public static function notice(): string {
		$text = (string) apply_filters(
			'noviq_ruo_notice',
			'Research Use Only. Products are intended for laboratory research purposes. Not for human or veterinary use, diagnostic use, or any therapeutic application.'
		);
		return $text;
	}

	public static function notice_version(): string {
		return (string) get_option( 'noviq_ruo_notice_version', self::NOTICE_VERSION );
	}

	public static function render(): void {
		printf(
			'<aside class="noviq-ruo-notice" data-noviq-ruo-version="%s" role="note"><p>%s</p></aside>',
			esc_attr( self::notice_version() ),
			esc_html( self::notice() )
		);
	}
}
