<?php
/**
 * Affiliate tables.
 *
 * Created with dbDelta and versioned in noviq_affiliates_db_version. Migration
 * runs on init, not only on activation, because rsync deploys do not re-fire
 * the activation hook.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates;

defined( 'ABSPATH' ) || exit;

final class Schema {

	public const DB_VERSION = 1;

	private const OPTION = 'noviq_affiliates_db_version';

	public static function init(): void {
		add_action( 'init', array( self::class, 'maybe_migrate' ), 20 );
	}

	public static function maybe_migrate(): void {
		$current = (int) get_option( self::OPTION, 0 );
		if ( $current >= self::DB_VERSION ) {
			return;
		}

		self::install();
		update_option( self::OPTION, self::DB_VERSION, false );
		flush_rewrite_rules( false );
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$tables  = self::definitions( $charset );

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		Roles::install();
	}

	/**
	 * @return list<string>
	 */
	private static function definitions( string $charset ): array {
		global $wpdb;
		$p = $wpdb->prefix;

		return array(
			"CREATE TABLE {$p}noviq_aff_recruiters (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned default NULL,
				name varchar(191) NOT NULL default '',
				status varchar(20) NOT NULL default 'active',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_id (user_id),
				KEY status (status)
			) {$charset};",
			"CREATE TABLE {$p}noviq_aff_reps (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned default NULL,
				name varchar(191) NOT NULL default '',
				code varchar(100) NOT NULL default '',
				coupon_id bigint(20) unsigned NOT NULL default 0,
				status varchar(20) NOT NULL default 'active',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_id (user_id),
				UNIQUE KEY code (code),
				KEY status (status)
			) {$charset};",
			"CREATE TABLE {$p}noviq_aff_rep_assignments (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				rep_id bigint(20) unsigned NOT NULL default 0,
				recruiter_id bigint(20) unsigned default NULL,
				starts_at datetime NOT NULL,
				ends_at datetime default NULL,
				changed_by bigint(20) unsigned NOT NULL default 0,
				PRIMARY KEY  (id),
				KEY rep_current (rep_id, ends_at),
				KEY recruiter_id (recruiter_id)
			) {$charset};",
			"CREATE TABLE {$p}noviq_aff_customers (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				customer_id bigint(20) unsigned default NULL,
				email_hash char(64) default NULL,
				rep_id bigint(20) unsigned NOT NULL default 0,
				source_order_id bigint(20) unsigned NOT NULL default 0,
				method varchar(20) NOT NULL default 'first_order',
				assigned_at datetime NOT NULL,
				assigned_by bigint(20) unsigned NOT NULL default 0,
				PRIMARY KEY  (id),
				UNIQUE KEY customer_id (customer_id),
				UNIQUE KEY email_hash (email_hash),
				KEY rep_id (rep_id)
			) {$charset};",
			"CREATE TABLE {$p}noviq_aff_customer_history (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				customer_row_id bigint(20) unsigned NOT NULL default 0,
				from_rep_id bigint(20) unsigned NOT NULL default 0,
				to_rep_id bigint(20) unsigned NOT NULL default 0,
				changed_by bigint(20) unsigned NOT NULL default 0,
				note varchar(255) NOT NULL default '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY customer_row_id (customer_row_id)
			) {$charset};",
			"CREATE TABLE {$p}noviq_aff_orders (
				order_id bigint(20) unsigned NOT NULL,
				rep_id bigint(20) unsigned NOT NULL default 0,
				recruiter_id bigint(20) unsigned default NULL,
				source varchar(20) NOT NULL default 'code',
				code_entered varchar(100) NOT NULL default '',
				discount_applied tinyint(1) NOT NULL default 0,
				base_cents bigint(20) NOT NULL default 0,
				attributed_at datetime NOT NULL,
				PRIMARY KEY  (order_id),
				KEY rep_id (rep_id),
				KEY recruiter_id (recruiter_id)
			) {$charset};",
			"CREATE TABLE {$p}noviq_aff_ledger (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				order_id bigint(20) unsigned NOT NULL default 0,
				refund_id bigint(20) unsigned NOT NULL default 0,
				entry_type varchar(20) NOT NULL default '',
				payee_type varchar(20) NOT NULL default '',
				payee_id bigint(20) unsigned NOT NULL default 0,
				rep_id bigint(20) unsigned NOT NULL default 0,
				recruiter_id bigint(20) unsigned default NULL,
				base_cents bigint(20) NOT NULL default 0,
				rate_bp int(11) NOT NULL default 0,
				amount_cents bigint(20) NOT NULL default 0,
				currency varchar(10) NOT NULL default '',
				period char(7) NOT NULL default '',
				inputs_json longtext NULL,
				idempotency_key varchar(191) NOT NULL default '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY idempotency_key (idempotency_key),
				KEY order_id (order_id),
				KEY payee_period (payee_type, payee_id, period),
				KEY period (period),
				KEY rep_id (rep_id),
				KEY recruiter_id (recruiter_id)
			) {$charset};",
		);
	}

	public static function table( string $name ): string {
		$allowed = array(
			'recruiters'       => 'noviq_aff_recruiters',
			'reps'             => 'noviq_aff_reps',
			'assignments'      => 'noviq_aff_rep_assignments',
			'customers'        => 'noviq_aff_customers',
			'customer_history' => 'noviq_aff_customer_history',
			'orders'           => 'noviq_aff_orders',
			'ledger'           => 'noviq_aff_ledger',
		);

		if ( ! isset( $allowed[ $name ] ) ) {
			throw new \InvalidArgumentException( 'Unknown affiliate table.' );
		}

		global $wpdb;

		return $wpdb->prefix . $allowed[ $name ];
	}

	/**
	 * Insert a row. Null values are SQL NULL so unique nullable columns stay empty.
	 *
	 * @param array<string, int|string|null> $data Column values.
	 */
	public static function insert( string $table, array $data ): int {
		global $wpdb;

		$sql      = self::prepare_insert( $table, $data );
		$suppress = $wpdb->suppress_errors( true );
		$wpdb->last_error = '';
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepare_insert() binds values.
		$wpdb->suppress_errors( $suppress );

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string, int|string|null> $data Column values.
	 */
	public static function prepare_insert( string $table, array $data ): string {
		global $wpdb;

		$columns      = array();
		$placeholders = array();
		$values       = array();

		foreach ( $data as $key => $value ) {
			$columns[] = '`' . $key . '`';
			if ( null === $value ) {
				$placeholders[] = 'NULL';
				continue;
			}
			$placeholders[] = is_int( $value ) ? '%d' : '%s';
			$values[]       = $value;
		}

		$sql = 'INSERT INTO ' . $table . ' (' . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $placeholders ) . ')';
		if ( array() === $values ) {
			return $sql;
		}

		$prepared = $wpdb->prepare( $sql, $values );

		return is_string( $prepared ) ? $prepared : $sql;
	}

	public static function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Serialize commission work for one order across webhook retries.
	 *
	 * @param callable():void $callback Work to run while holding the lock.
	 */
	public static function with_order_lock( int $order_id, callable $callback ): void {
		global $wpdb;

		$name = 'noviq_aff_order_' . $order_id;
		$got  = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 15)', $name ) );

		try {
			$callback();
		} finally {
			if ( '1' === (string) $got ) {
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
			}
		}
	}
}
