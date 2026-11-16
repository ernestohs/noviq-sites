<?php
/**
 * Recruiters, representatives, assignments, and customer ownership.
 *
 * A rep belongs to one recruiter at a time. Reassignment closes the open
 * assignment row and opens another, so historical commissions keep the
 * recruiter stored on the ledger. House reps have a null recruiter.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates;

defined( 'ABSPATH' ) || exit;

final class Program {

	/**
	 * @return int|\WP_Error
	 */
	public static function create_recruiter( string $name, ?int $user_id = null ) {
		$name = trim( $name );
		if ( '' === $name ) {
			return new \WP_Error( 'noviq_aff_name', __( 'Enter a recruiter name.', 'noviq-core' ) );
		}

		$existing = self::recruiter_by_name( $name );
		if ( null !== $existing ) {
			return (int) $existing['id'];
		}

		if ( null !== $user_id && $user_id > 0 && null !== self::recruiter_by_user( $user_id ) ) {
			return new \WP_Error( 'noviq_aff_user', __( 'That account is already a recruiter.', 'noviq-core' ) );
		}

		return Schema::insert(
			Schema::table( 'recruiters' ),
			array(
				'user_id'    => ( null !== $user_id && $user_id > 0 ) ? $user_id : null,
				'name'       => $name,
				'status'     => 'active',
				'created_at' => Schema::now(),
			)
		);
	}

	/**
	 * @return int|\WP_Error
	 */
	public static function create_rep( string $name, string $code, int $coupon_id, ?int $recruiter_id, ?int $user_id = null ) {
		$name = trim( $name );
		$code = self::format_code( $code );
		if ( '' === $name || '' === $code ) {
			return new \WP_Error( 'noviq_aff_rep', __( 'A representative needs a name and a code.', 'noviq-core' ) );
		}
		if ( null !== self::rep_by_code( $code ) ) {
			return new \WP_Error( 'noviq_aff_code', __( 'That affiliate code is already assigned.', 'noviq-core' ) );
		}
		if ( null !== $user_id && $user_id > 0 && null !== self::rep_by_user( $user_id ) ) {
			return new \WP_Error( 'noviq_aff_user', __( 'That account is already a representative.', 'noviq-core' ) );
		}
		if ( null !== $recruiter_id && $recruiter_id > 0 && null === self::recruiter( $recruiter_id ) ) {
			return new \WP_Error( 'noviq_aff_recruiter', __( 'That recruiter does not exist.', 'noviq-core' ) );
		}

		$rep_id = Schema::insert(
			Schema::table( 'reps' ),
			array(
				'user_id'    => ( null !== $user_id && $user_id > 0 ) ? $user_id : null,
				'name'       => $name,
				'code'       => $code,
				'coupon_id'  => $coupon_id,
				'status'     => 'active',
				'created_at' => Schema::now(),
			)
		);

		self::open_assignment( $rep_id, ( null !== $recruiter_id && $recruiter_id > 0 ) ? $recruiter_id : null, get_current_user_id() );
		Codes::flush_cache();

		return $rep_id;
	}

	/**
	 * Point a rep at a recruiter, or null for a house rep. No-op when unchanged.
	 * Past assignment rows stay closed so historical orders keep their snapshot.
	 *
	 * @return true|\WP_Error
	 */
	public static function assign_rep( int $rep_id, ?int $recruiter_id, int $changed_by = 0 ) {
		$rep = self::rep( $rep_id );
		if ( null === $rep ) {
			return new \WP_Error( 'noviq_aff_rep', __( 'That representative does not exist.', 'noviq-core' ) );
		}
		if ( null !== $recruiter_id && $recruiter_id > 0 && null === self::recruiter( $recruiter_id ) ) {
			return new \WP_Error( 'noviq_aff_recruiter', __( 'That recruiter does not exist.', 'noviq-core' ) );
		}

		$recruiter_id = ( null !== $recruiter_id && $recruiter_id > 0 ) ? $recruiter_id : null;
		$current      = self::current_recruiter_id( $rep_id );
		if ( $current === $recruiter_id ) {
			return true;
		}

		self::close_assignment( $rep_id );
		self::open_assignment( $rep_id, $recruiter_id, $changed_by );

		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function set_rep_status( int $rep_id, string $status ) {
		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			return new \WP_Error( 'noviq_aff_status', __( 'Unknown status.', 'noviq-core' ) );
		}
		if ( null === self::rep( $rep_id ) ) {
			return new \WP_Error( 'noviq_aff_rep', __( 'That representative does not exist.', 'noviq-core' ) );
		}

		global $wpdb;
		$wpdb->update(
			Schema::table( 'reps' ),
			array( 'status' => $status ),
			array( 'id' => $rep_id ),
			array( '%s' ),
			array( '%d' )
		);
		Codes::flush_cache();

		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function set_recruiter_status( int $recruiter_id, string $status ) {
		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			return new \WP_Error( 'noviq_aff_status', __( 'Unknown status.', 'noviq-core' ) );
		}
		if ( null === self::recruiter( $recruiter_id ) ) {
			return new \WP_Error( 'noviq_aff_recruiter', __( 'That recruiter does not exist.', 'noviq-core' ) );
		}

		global $wpdb;
		$wpdb->update(
			Schema::table( 'recruiters' ),
			array( 'status' => $status ),
			array( 'id' => $recruiter_id ),
			array( '%s' ),
			array( '%d' )
		);

		return true;
	}

	public static function attach_rep_user( int $rep_id, int $user_id ): bool {
		$other = self::rep_by_user( $user_id );
		if ( null !== $other && (int) $other['id'] !== $rep_id ) {
			return false;
		}

		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::table( 'reps' ) . ' SET user_id = %d WHERE id = %d',
				$user_id,
				$rep_id
			)
		);
		Codes::flush_cache();

		return true;
	}

	public static function attach_recruiter_user( int $recruiter_id, int $user_id ): bool {
		$other = self::recruiter_by_user( $user_id );
		if ( null !== $other && (int) $other['id'] !== $recruiter_id ) {
			return false;
		}

		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::table( 'recruiters' ) . ' SET user_id = %d WHERE id = %d',
				$user_id,
				$recruiter_id
			)
		);

		return true;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function rep( int $rep_id ): ?array {
		return self::row( 'reps', $rep_id );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function recruiter( int $recruiter_id ): ?array {
		return self::row( 'recruiters', $recruiter_id );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function rep_by_code( string $code ): ?array {
		$code = self::format_code( $code );
		if ( '' === $code ) {
			return null;
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'reps' ) . ' WHERE code = %s', $code ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function rep_by_user( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'reps' ) . ' WHERE user_id = %d', $user_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function recruiter_by_user( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'recruiters' ) . ' WHERE user_id = %d', $user_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function recruiter_by_name( string $name ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'recruiters' ) . ' WHERE name = %s', $name ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	public static function current_recruiter_id( int $rep_id ): ?int {
		global $wpdb;
		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT recruiter_id FROM ' . Schema::table( 'assignments' ) . ' WHERE rep_id = %d AND ends_at IS NULL ORDER BY id DESC LIMIT 1',
				$rep_id
			)
		);
		if ( null === $value || '' === $value ) {
			return null;
		}

		$id = (int) $value;

		return $id > 0 ? $id : null;
	}

	/**
	 * Recruiter id to snapshot, or null when the rep is house or the recruiter is inactive.
	 * The assignment row itself is not changed.
	 */
	public static function commissionable_recruiter_id( int $rep_id ): ?int {
		$recruiter_id = self::current_recruiter_id( $rep_id );
		if ( null === $recruiter_id ) {
			return null;
		}
		$recruiter = self::recruiter( $recruiter_id );
		if ( null === $recruiter || 'active' !== $recruiter['status'] ) {
			return null;
		}

		return $recruiter_id;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function recruiters(): array {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . Schema::table( 'recruiters' ) . ' ORDER BY name ASC', ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Reps with their current recruiter, for the admin list.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function reps_with_recruiters(): array {
		global $wpdb;
		$sql  = 'SELECT r.*, a.recruiter_id, rec.name AS recruiter_name
			FROM ' . Schema::table( 'reps' ) . ' r
			LEFT JOIN ' . Schema::table( 'assignments' ) . ' a ON a.rep_id = r.id AND a.ends_at IS NULL
			LEFT JOIN ' . Schema::table( 'recruiters' ) . ' rec ON rec.id = a.recruiter_id
			ORDER BY r.name ASC';
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Reps whose open assignment points at this recruiter.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function team( int $recruiter_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.* FROM ' . Schema::table( 'reps' ) . ' r
				INNER JOIN ' . Schema::table( 'assignments' ) . ' a ON a.rep_id = r.id AND a.ends_at IS NULL
				WHERE a.recruiter_id = %d
				ORDER BY r.name ASC',
				$recruiter_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	public static function format_code( string $code ): string {
		$code = trim( $code );
		if ( '' === $code ) {
			return '';
		}
		if ( function_exists( 'wc_format_coupon_code' ) ) {
			return wc_format_coupon_code( $code );
		}

		return strtolower( $code );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function row( string $table, int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( $table ) . ' WHERE id = %d', $id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	private static function close_assignment( int $rep_id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::table( 'assignments' ) . ' SET ends_at = %s WHERE rep_id = %d AND ends_at IS NULL',
				Schema::now(),
				$rep_id
			)
		);
	}

	private static function open_assignment( int $rep_id, ?int $recruiter_id, int $changed_by ): void {
		Schema::insert(
			Schema::table( 'assignments' ),
			array(
				'rep_id'       => $rep_id,
				'recruiter_id' => $recruiter_id,
				'starts_at'    => Schema::now(),
				'ends_at'      => null,
				'changed_by'   => $changed_by,
			)
		);
	}
}
