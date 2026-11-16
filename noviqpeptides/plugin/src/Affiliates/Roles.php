<?php
/**
 * Representative and recruiter roles.
 *
 * There is no public registration. Administrators create accounts. Both roles
 * can shop (customer caps are runtime WooCommerce checks, plus the customer
 * role when we create the user) and can open the My Account affiliate screen.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates;

defined( 'ABSPATH' ) || exit;

final class Roles {

	public const REP       = 'noviq_rep';
	public const RECRUITER = 'noviq_recruiter';

	public const CAP_VIEW   = 'noviq_view_affiliate_dashboard';
	public const CAP_MANAGE = 'noviq_manage_affiliates';

	public static function install(): void {
		$caps = array(
			'read'         => true,
			self::CAP_VIEW => true,
		);

		if ( null === get_role( self::REP ) ) {
			add_role( self::REP, __( 'Sales Representative', 'noviq-core' ), $caps );
		}
		if ( null === get_role( self::RECRUITER ) ) {
			add_role( self::RECRUITER, __( 'Recruiter', 'noviq-core' ), $caps );
		}

		foreach ( array( self::REP, self::RECRUITER ) as $slug ) {
			$role = get_role( $slug );
			if ( $role instanceof \WP_Role && ! $role->has_cap( self::CAP_VIEW ) ) {
				$role->add_cap( self::CAP_VIEW );
			}
		}

		$admin = get_role( 'administrator' );
		if ( $admin instanceof \WP_Role ) {
			$admin->add_cap( self::CAP_MANAGE );
			$admin->add_cap( self::CAP_VIEW );
		}
	}

	/**
	 * Find or create a login for a program record and grant $role.
	 *
	 * Shoppers also keep the customer role so checkout account screens stay
	 * available. An existing user is never demoted.
	 *
	 * @return int|\WP_Error
	 */
	public static function ensure_user( string $email, string $role, string $display_name ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'noviq_aff_email', __( 'Enter a valid email address.', 'noviq-core' ) );
		}
		if ( self::REP !== $role && self::RECRUITER !== $role ) {
			return new \WP_Error( 'noviq_aff_role', __( 'Unknown affiliate role.', 'noviq-core' ) );
		}

		$existing = get_user_by( 'email', $email );
		if ( $existing instanceof \WP_User ) {
			$existing->add_role( $role );
			if ( '' !== $display_name && '' === $existing->display_name ) {
				wp_update_user(
					array(
						'ID'           => $existing->ID,
						'display_name' => $display_name,
					)
				);
			}

			return (int) $existing->ID;
		}

		$login = sanitize_user( (string) strstr( $email, '@', true ), true );
		if ( '' === $login ) {
			$login = 'affiliate';
		}
		$base = $login;
		$n    = 0;
		while ( username_exists( $login ) ) {
			++$n;
			$login = $base . $n;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24 ),
				'display_name' => '' !== $display_name ? $display_name : $login,
				'role'         => 'customer',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = get_user_by( 'id', (int) $user_id );
		if ( $user instanceof \WP_User ) {
			$user->add_role( $role );
		}

		if ( function_exists( 'wp_send_new_user_notifications' ) ) {
			wp_send_new_user_notifications( (int) $user_id, 'user' );
		}

		return (int) $user_id;
	}
}
