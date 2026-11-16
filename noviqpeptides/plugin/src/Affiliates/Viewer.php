<?php
/**
 * Who is asking for affiliate data.
 *
 * Reps see their own payee rows. Recruiters see rows snapshotted to them.
 * Administrators see everything. A person who is both uses the recruiter view,
 * which includes their own selling code.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates;

defined( 'ABSPATH' ) || exit;

final class Viewer {

	public const ADMIN     = 'admin';
	public const RECRUITER = 'recruiter';
	public const REP       = 'rep';

	public function __construct(
		public readonly string $scope,
		public readonly int $rep_id = 0,
		public readonly int $recruiter_id = 0,
		public readonly string $status = 'active'
	) {}

	public static function admin(): self {
		return new self( self::ADMIN );
	}

	public static function for_user( int $user_id ): ?self {
		if ( $user_id <= 0 ) {
			return null;
		}

		if ( user_can( $user_id, Roles::CAP_MANAGE ) ) {
			return self::admin();
		}

		$recruiter = Program::recruiter_by_user( $user_id );
		if ( null !== $recruiter ) {
			return new self( self::RECRUITER, 0, (int) $recruiter['id'], (string) $recruiter['status'] );
		}

		$rep = Program::rep_by_user( $user_id );
		if ( null !== $rep ) {
			return new self( self::REP, (int) $rep['id'], 0, (string) $rep['status'] );
		}

		return null;
	}

	public function is_admin(): bool {
		return self::ADMIN === $this->scope;
	}

	public function can_see_rep( int $rep_id ): bool {
		if ( $rep_id <= 0 ) {
			return false;
		}
		if ( $this->is_admin() ) {
			return true;
		}
		if ( self::REP === $this->scope ) {
			return $rep_id === $this->rep_id;
		}
		if ( self::RECRUITER === $this->scope ) {
			return Reports::recruiter_knows_rep( $this->recruiter_id, $rep_id );
		}

		return false;
	}
}
