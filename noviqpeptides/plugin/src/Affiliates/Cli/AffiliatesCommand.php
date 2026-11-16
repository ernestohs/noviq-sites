<?php
/**
 * wp noviq affiliates
 *
 * Seed does not create WordPress users and does not invent email addresses.
 * Attach logins later, when the client supplies them.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates\Cli;

use Noviq\Core\Affiliates\Codes;
use Noviq\Core\Affiliates\Commissions;
use Noviq\Core\Affiliates\Program;
use Noviq\Core\Affiliates\Roles;
use Noviq\Core\Profile;

defined( 'ABSPATH' ) || exit;

final class AffiliatesCommand {

	/**
	 * Create recruiter and representative records from affiliates.json.
	 *
	 * Idempotent on representative code. An existing code is left where an
	 * administrator moved it. Does not create user accounts.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print the mapping without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp noviq affiliates seed --dry-run
	 *     wp noviq affiliates seed
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function seed( array $args, array $assoc_args ): void {
		unset( $args );
		$dry  = isset( $assoc_args['dry-run'] );
		$data = self::mapping();
		if ( null === $data ) {
			\WP_CLI::error( 'Could not read affiliates.json for this profile.' );
		}

		$recruiter_ids = array();
		foreach ( $data['recruiters'] as $row ) {
			$name = (string) $row['name'];
			$existing = Program::recruiter_by_name( $name );
			if ( null !== $existing ) {
				$recruiter_ids[ $name ] = (int) $existing['id'];
				\WP_CLI::log( 'recruiter exists: ' . $name );
				continue;
			}
			\WP_CLI::log( ( $dry ? 'would create recruiter: ' : 'create recruiter: ' ) . $name );
			if ( $dry ) {
				$recruiter_ids[ $name ] = 0;
				continue;
			}
			$id = Program::create_recruiter( $name, null );
			if ( is_wp_error( $id ) ) {
				\WP_CLI::warning( $id->get_error_message() );
				continue;
			}
			$recruiter_ids[ $name ] = $id;
		}

		foreach ( $data['reps'] as $row ) {
			$code = Program::format_code( (string) $row['code'] );
			$name = (string) $row['name'];
			$boss = isset( $row['recruiter'] ) && is_string( $row['recruiter'] ) && '' !== $row['recruiter']
				? $row['recruiter']
				: null;

			if ( null !== Program::rep_by_code( $code ) ) {
				\WP_CLI::log( 'rep exists: ' . $code );
				continue;
			}

			$recruiter_id = null;
			if ( null !== $boss ) {
				if ( ! isset( $recruiter_ids[ $boss ] ) ) {
					\WP_CLI::warning( 'No recruiter named ' . $boss . ' for ' . $code );
					continue;
				}
				$recruiter_id = $recruiter_ids[ $boss ] > 0 ? $recruiter_ids[ $boss ] : null;
				if ( $dry ) {
					$recruiter_id = null;
				}
			}

			\WP_CLI::log(
				sprintf(
					'%s rep %s (%s) under %s',
					$dry ? 'would create' : 'create',
					$code,
					$name,
					$boss ?? 'house'
				)
			);
			if ( $dry ) {
				continue;
			}

			$coupon_id = Codes::ensure_coupon( $code );
			if ( is_wp_error( $coupon_id ) ) {
				\WP_CLI::warning( $code . ': ' . $coupon_id->get_error_message() );
				continue;
			}
			$rep_id = Program::create_rep( $name, $code, $coupon_id, $recruiter_id, null );
			if ( is_wp_error( $rep_id ) ) {
				\WP_CLI::warning( $code . ': ' . $rep_id->get_error_message() );
			}
		}

		\WP_CLI::success( $dry ? 'Dry run complete.' : 'Affiliate seed complete.' );
	}

	/**
	 * Attach a WordPress user to a representative code.
	 *
	 * When the code is that recruiter's own selling code (the rep name matches
	 * the recruiter they are assigned to), the same user is also attached to
	 * the recruiter record and receives both roles.
	 *
	 * ## OPTIONS
	 *
	 * <code>
	 * : Affiliate code, for example LELE10.
	 *
	 * <email>
	 * : Login email. Created if it does not exist.
	 *
	 * [--recruiter]
	 * : Also attach this user to the rep's current recruiter.
	 *
	 * ## EXAMPLES
	 *
	 *     wp noviq affiliates attach-user LELE10 lele@example.com --recruiter
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function attach_user( array $args, array $assoc_args ): void {
		$code  = Program::format_code( (string) ( $args[0] ?? '' ) );
		$email = sanitize_email( (string) ( $args[1] ?? '' ) );
		$rep   = Program::rep_by_code( $code );
		if ( null === $rep ) {
			\WP_CLI::error( 'No representative uses that code. Seed the program first.' );
		}

		$user_id = Roles::ensure_user( $email, Roles::REP, (string) $rep['name'] );
		if ( is_wp_error( $user_id ) ) {
			\WP_CLI::error( $user_id->get_error_message() );
		}
		if ( ! Program::attach_rep_user( (int) $rep['id'], $user_id ) ) {
			\WP_CLI::error( 'That account is already linked to another representative.' );
		}

		$recruiter_id = Program::current_recruiter_id( (int) $rep['id'] );
		$recruiter    = null !== $recruiter_id ? Program::recruiter( $recruiter_id ) : null;
		$same_person  = null !== $recruiter && (string) $recruiter['name'] === (string) $rep['name'];
		if ( null !== $recruiter && ( $same_person || isset( $assoc_args['recruiter'] ) ) ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user instanceof \WP_User ) {
				$user->add_role( Roles::RECRUITER );
			}
			if ( ! Program::attach_recruiter_user( (int) $recruiter['id'], $user_id ) ) {
				\WP_CLI::warning( 'Recruiter record is already linked to a different account.' );
			} else {
				\WP_CLI::log( 'Attached recruiter ' . (string) $recruiter['name'] . ' as well.' );
			}
		}

		\WP_CLI::success( 'Attached ' . $email . ' to ' . $code . '.' );
	}

	/**
	 * Record commissions for paid orders created on or after a date.
	 *
	 * Orders with no rep code and no existing customer owner are skipped.
	 * Safe to repeat: ledger keys are unique.
	 *
	 * ## OPTIONS
	 *
	 * --since=<date>
	 * : YYYY-MM-DD, inclusive, compared to the order created date.
	 *
	 * [--dry-run]
	 * : List orders that would be recorded.
	 *
	 * ## EXAMPLES
	 *
	 *     wp noviq affiliates backfill --since=2026-10-01 --dry-run
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function backfill( array $args, array $assoc_args ): void {
		unset( $args );
		$since = isset( $assoc_args['since'] ) ? (string) $assoc_args['since'] : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $since ) ) {
			\WP_CLI::error( 'Pass --since=YYYY-MM-DD.' );
		}

		$lines = Commissions::backfill( $since, isset( $assoc_args['dry-run'] ) );
		foreach ( $lines as $line ) {
			\WP_CLI::log( $line );
		}
		\WP_CLI::success( sprintf( '%d order(s).', count( $lines ) ) );
	}

	/**
	 * @return array{recruiters: list<array<string, mixed>>, reps: list<array<string, mixed>>}|null
	 */
	private static function mapping(): ?array {
		$path = NOVIQ_CORE_PATH . 'data/' . Profile::id() . '/affiliates.json';
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$decoded = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['recruiters'], $decoded['reps'] ) || ! is_array( $decoded['recruiters'] ) || ! is_array( $decoded['reps'] ) ) {
			return null;
		}

		return array(
			'recruiters' => array_values( array_filter( $decoded['recruiters'], 'is_array' ) ),
			'reps'       => array_values( array_filter( $decoded['reps'], 'is_array' ) ),
		);
	}
}
