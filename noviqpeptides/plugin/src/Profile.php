<?php
/**
 * Tenant profile for a multi-site install of noviq-core.
 *
 * Each WordPress install sets NOVIQ_PROFILE in wp-config.php. Undefined falls
 * back to "noviq" so the existing peptide storefront is unchanged.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core;

defined( 'ABSPATH' ) || exit;

final class Profile {

	private static ?array $config = null;

	/**
	 * Active profile id. This install only ships the noviq profile.
	 */
	public static function id(): string {
		if ( defined( 'NOVIQ_PROFILE' ) && is_string( NOVIQ_PROFILE ) && '' !== NOVIQ_PROFILE ) {
			return NOVIQ_PROFILE;
		}

		return 'noviq';
	}

	/**
	 * Full profile array from profiles/{id}.php.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null !== self::$config ) {
			return self::$config;
		}

		$path = NOVIQ_CORE_PATH . 'profiles/' . self::id() . '.php';

		if ( ! is_readable( $path ) ) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: profile id */
						__( 'Unknown NOVIQ_PROFILE "%s". Expected a file in noviq-core/profiles/.', 'noviq-core' ),
						self::id()
					)
				)
			);
		}

		/** @var array<string, mixed> $config */
		$config = require $path;
		self::$config = $config;

		return self::$config;
	}

	/**
	 * Whether a named feature is enabled for this profile.
	 */
	public static function feature( string $name ): bool {
		$features = self::all()['features'] ?? array();

		return ! empty( $features[ $name ] );
	}

	/**
	 * Theme stylesheet directory name this profile expects.
	 */
	public static function theme(): string {
		$theme = self::all()['theme'] ?? 'noviq-peptides';

		return is_string( $theme ) ? $theme : 'noviq-peptides';
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function site(): array {
		$site = self::all()['site'] ?? array();

		return is_array( $site ) ? $site : array();
	}

	/**
	 * @return array<string, int|float|string|null>
	 */
	public static function claims(): array {
		$claims = self::all()['claims'] ?? array();

		return is_array( $claims ) ? $claims : array();
	}

	public static function ruo_short(): string {
		$value = self::all()['ruo_short'] ?? '';

		return is_string( $value ) ? $value : '';
	}

	public static function ruo_full(): string {
		$value = self::all()['ruo_full'] ?? '';

		return is_string( $value ) ? $value : '';
	}

	public static function attestation_text(): string {
		$value = self::all()['attestation_text'] ?? '';

		return is_string( $value ) ? $value : '';
	}

	/**
	 * @return array{copy_version: string, question: string}
	 */
	public static function age_gate(): array {
		$gate = self::all()['age_gate'] ?? array();

		return array(
			'copy_version' => is_array( $gate ) && isset( $gate['copy_version'] ) ? (string) $gate['copy_version'] : '1',
			'question'     => is_array( $gate ) && isset( $gate['question'] ) ? (string) $gate['question'] : '',
		);
	}

	/**
	 * Registration wall copy and research-field options.
	 *
	 * @return array{
	 *     copy_version: string,
	 *     heading: string,
	 *     intro: string,
	 *     attestation: string,
	 *     research_fields: array<string, string>
	 * }
	 */
	public static function registration_gate(): array {
		$gate   = self::all()['registration_gate'] ?? array();
		$fields = array();

		if ( is_array( $gate ) && isset( $gate['research_fields'] ) && is_array( $gate['research_fields'] ) ) {
			foreach ( $gate['research_fields'] as $slug => $label ) {
				if ( is_string( $slug ) && is_string( $label ) && '' !== $slug && '' !== $label ) {
					$fields[ $slug ] = $label;
				}
			}
		}

		return array(
			'copy_version'    => is_array( $gate ) && isset( $gate['copy_version'] ) ? (string) $gate['copy_version'] : '1',
			'heading'         => is_array( $gate ) && isset( $gate['heading'] ) ? (string) $gate['heading'] : '',
			'intro'           => is_array( $gate ) && isset( $gate['intro'] ) ? (string) $gate['intro'] : '',
			'attestation'     => is_array( $gate ) && isset( $gate['attestation'] ) ? (string) $gate['attestation'] : '',
			'research_fields' => $fields,
		);
	}

	/**
	 * Explicit ticker items, or null to build from claims.
	 *
	 * @return string[]|null
	 */
	public static function ticker_items(): ?array {
		$items = self::all()['ticker_items'] ?? null;

		if ( null === $items ) {
			return null;
		}

		if ( ! is_array( $items ) ) {
			return array();
		}

		return array_values( array_filter( $items, 'is_string' ) );
	}

	/**
	 * Home page body when the seeder creates the static front page.
	 * Null means use the Noviq default blocks in Library.
	 */
	public static function home_content(): ?string {
		$value = self::all()['home_content'] ?? null;

		return is_string( $value ) ? $value : null;
	}
}
