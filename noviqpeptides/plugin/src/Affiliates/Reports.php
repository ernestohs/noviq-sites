<?php
/**
 * Scoped reads for dashboards and the payout export.
 *
 * Every query takes a Viewer. Rep and recruiter constraints are applied here,
 * not only in the screen that called in.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates;

defined( 'ABSPATH' ) || exit;

final class Reports {

	public const PER_PAGE = 20;

	/**
	 * @param array{from?: string, to?: string, period?: string, rep_id?: int, recruiter_id?: int} $filters Filters.
	 * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
	 */
	public static function ledger( Viewer $viewer, array $filters, int $page = 1 ): array {
		$filters = self::narrow( $viewer, $filters );
		if ( null === $filters ) {
			return self::empty_page( $page );
		}

		global $wpdb;
		$args  = array();
		$where = self::ledger_where( $viewer, $filters, $args );
		$table = Schema::table( 'ledger' );

		return self::paginate(
			"SELECT COUNT(*) FROM {$table} l {$where}",
			"SELECT l.* FROM {$table} l {$where} ORDER BY l.created_at DESC, l.id DESC",
			$args,
			$page
		);
	}

	/**
	 * @param array{from?: string, to?: string, period?: string, rep_id?: int, recruiter_id?: int} $filters Filters.
	 * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
	 */
	public static function orders( Viewer $viewer, array $filters, int $page = 1 ): array {
		$filters = self::narrow( $viewer, $filters );
		if ( null === $filters ) {
			return self::empty_page( $page );
		}

		global $wpdb;
		$args  = array();
		$where = self::orders_where( $viewer, $filters, $args );
		$table = Schema::table( 'orders' );

		return self::paginate(
			"SELECT COUNT(*) FROM {$table} o {$where}",
			"SELECT o.* FROM {$table} o {$where} ORDER BY o.attributed_at DESC, o.order_id DESC",
			$args,
			$page
		);
	}

	/**
	 * Net commission and net sales for the viewer inside the filter.
	 *
	 * @param array{from?: string, to?: string, period?: string, rep_id?: int, recruiter_id?: int} $filters Filters.
	 * @return array{sales_cents: int, commission_cents: int, orders: int}
	 */
	public static function summary( Viewer $viewer, array $filters ): array {
		$filters = self::narrow( $viewer, $filters );
		if ( null === $filters ) {
			return array(
				'sales_cents'      => 0,
				'commission_cents' => 0,
				'orders'           => 0,
			);
		}

		global $wpdb;

		$order_args = array();
		$order_sql  = 'SELECT COALESCE(SUM(o.base_cents), 0), COUNT(*) FROM ' . Schema::table( 'orders' ) . ' o ' . self::orders_where( $viewer, $filters, $order_args );
		$order_row  = $order_args
			? $wpdb->get_row( $wpdb->prepare( $order_sql, $order_args ), ARRAY_N )
			: $wpdb->get_row( $order_sql, ARRAY_N );

		$ledger_args = array();
		$ledger_sql  = 'SELECT COALESCE(SUM(l.amount_cents), 0) FROM ' . Schema::table( 'ledger' ) . ' l ' . self::ledger_where( $viewer, $filters, $ledger_args );
		$commission  = $ledger_args
			? $wpdb->get_var( $wpdb->prepare( $ledger_sql, $ledger_args ) )
			: $wpdb->get_var( $ledger_sql );

		$sales = is_array( $order_row ) ? (int) $order_row[0] : 0;
		$sales -= self::refunded_product_cents( $viewer, $filters );

		return array(
			'sales_cents'      => $sales,
			'commission_cents' => (int) $commission,
			'orders'           => is_array( $order_row ) ? (int) $order_row[1] : 0,
		);
	}

	/**
	 * Current team, with sales and rep commissions snapshotted to this recruiter.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function team( int $recruiter_id, array $filters ): array {
		global $wpdb;

		$reps = Program::team( $recruiter_id );
		$rows = array();
		foreach ( $reps as $rep ) {
			$rep_filters = array_merge(
				$filters,
				array(
					'rep_id'       => (int) $rep['id'],
					'recruiter_id' => $recruiter_id,
				)
			);
			$summary = self::summary( Viewer::admin(), $rep_filters );
			$rows[]  = array(
				'id'               => (int) $rep['id'],
				'name'             => (string) $rep['name'],
				'code'             => (string) $rep['code'],
				'status'           => (string) $rep['status'],
				'sales_cents'      => $summary['sales_cents'],
				'commission_cents' => self::rep_commission_for_recruiter( (int) $rep['id'], $recruiter_id, $filters ),
				'orders'           => $summary['orders'],
			);
		}

		return $rows;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function payout_rows( string $period ): array {
		if ( ! self::valid_period( $period ) ) {
			return array();
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT payee_type, payee_id,
					COALESCE(SUM(CASE WHEN amount_cents > 0 THEN amount_cents ELSE 0 END), 0) AS earned_cents,
					COALESCE(SUM(CASE WHEN amount_cents < 0 THEN amount_cents ELSE 0 END), 0) AS adjustment_cents,
					COALESCE(SUM(amount_cents), 0) AS net_cents,
					MAX(currency) AS currency
				FROM ' . Schema::table( 'ledger' ) . '
				WHERE period = %s
				GROUP BY payee_type, payee_id
				ORDER BY payee_type ASC, payee_id ASC',
				$period
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['payee_name'] = self::payee_name( (string) $row['payee_type'], (int) $row['payee_id'] );
		}
		unset( $row );

		return $rows;
	}

	public static function payout_csv( string $period ): string {
		$handle = fopen( 'php://temp', 'w+' );
		if ( false === $handle ) {
			return '';
		}

		fputcsv(
			$handle,
			array( 'period', 'payee_type', 'payee_id', 'payee_name', 'earned', 'adjustments', 'net', 'currency' )
		);

		foreach ( self::payout_rows( $period ) as $row ) {
			fputcsv(
				$handle,
				array(
					self::csv_cell( $period ),
					self::csv_cell( (string) $row['payee_type'] ),
					(string) (int) $row['payee_id'],
					self::csv_cell( (string) $row['payee_name'] ),
					self::csv_money( (int) $row['earned_cents'] ),
					self::csv_money( (int) $row['adjustment_cents'] ),
					self::csv_money( (int) $row['net_cents'] ),
					self::csv_cell( (string) $row['currency'] ),
				)
			);
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		return is_string( $csv ) ? $csv : '';
	}

	public static function recruiter_knows_rep( int $recruiter_id, int $rep_id ): bool {
		global $wpdb;

		$assigned = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . Schema::table( 'assignments' ) . ' WHERE rep_id = %d AND recruiter_id = %d LIMIT 1',
				$rep_id,
				$recruiter_id
			)
		);
		if ( null !== $assigned && '' !== $assigned ) {
			return true;
		}

		$ordered = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT order_id FROM ' . Schema::table( 'orders' ) . ' WHERE rep_id = %d AND recruiter_id = %d LIMIT 1',
				$rep_id,
				$recruiter_id
			)
		);

		return null !== $ordered && '' !== $ordered;
	}

	/**
	 * Commission recorded for one order and one payee type. Used by order tables.
	 */
	public static function order_commission( int $order_id, string $payee_type, int $payee_id ): int {
		global $wpdb;
		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount_cents), 0) FROM ' . Schema::table( 'ledger' ) . '
				WHERE order_id = %d AND payee_type = %s AND payee_id = %d',
				$order_id,
				$payee_type,
				$payee_id
			)
		);

		return (int) $value;
	}

	/**
	 * Net amount for one payee inside the date filter. Not scoped by a Viewer;
	 * callers must already know the payee is theirs.
	 *
	 * @param array{from?: string, to?: string, period?: string} $filters Dates.
	 */
	public static function payee_total( string $payee_type, int $payee_id, array $filters ): int {
		global $wpdb;

		$args   = array( $payee_type, $payee_id );
		$where  = 'WHERE l.payee_type = %s AND l.payee_id = %d';
		$where .= self::date_sql(
			array(
				'from'   => isset( $filters['from'] ) ? (string) $filters['from'] : '',
				'to'     => isset( $filters['to'] ) ? (string) $filters['to'] : '',
				'period' => isset( $filters['period'] ) ? (string) $filters['period'] : '',
			),
			'l.created_at',
			'l.period',
			$args
		);

		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(l.amount_cents), 0) FROM ' . Schema::table( 'ledger' ) . ' l ' . $where,
				$args
			)
		);

		return (int) $value;
	}

	public static function payee_name( string $payee_type, int $payee_id ): string {
		if ( 'recruiter' === $payee_type ) {
			$row = Program::recruiter( $payee_id );

			return null !== $row ? (string) $row['name'] : '';
		}

		$row = Program::rep( $payee_id );

		return null !== $row ? (string) $row['name'] : '';
	}

	/**
	 * @param array<string, mixed> $filters Raw request filters.
	 * @return array{from: string, to: string, period: string, rep_id: int, recruiter_id: int}
	 */
	public static function filters_from_request( array $source ): array {
		$from   = isset( $source['from'] ) ? sanitize_text_field( (string) $source['from'] ) : '';
		$to     = isset( $source['to'] ) ? sanitize_text_field( (string) $source['to'] ) : '';
		$period = isset( $source['period'] ) ? sanitize_text_field( (string) $source['period'] ) : '';

		return array(
			'from'         => self::valid_date( $from ) ? $from : '',
			'to'           => self::valid_date( $to ) ? $to : '',
			'period'       => self::valid_period( $period ) ? $period : '',
			'rep_id'       => isset( $source['rep_id'] ) ? absint( $source['rep_id'] ) : 0,
			'recruiter_id' => isset( $source['recruiter_id'] ) ? absint( $source['recruiter_id'] ) : 0,
		);
	}

	public static function valid_period( string $period ): bool {
		return 1 === preg_match( '/^\d{4}-\d{2}$/', $period );
	}

	public static function valid_date( string $date ): bool {
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );

		return $parsed instanceof \DateTimeImmutable && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * @param array{from?: string, to?: string, period?: string, rep_id?: int, recruiter_id?: int} $filters Filters.
	 * @return array{from: string, to: string, period: string, rep_id: int, recruiter_id: int}|null
	 */
	private static function narrow( Viewer $viewer, array $filters ): ?array {
		$filters = array(
			'from'         => isset( $filters['from'] ) ? (string) $filters['from'] : '',
			'to'           => isset( $filters['to'] ) ? (string) $filters['to'] : '',
			'period'       => isset( $filters['period'] ) ? (string) $filters['period'] : '',
			'rep_id'       => isset( $filters['rep_id'] ) ? (int) $filters['rep_id'] : 0,
			'recruiter_id' => isset( $filters['recruiter_id'] ) ? (int) $filters['recruiter_id'] : 0,
		);

		if ( Viewer::REP === $viewer->scope ) {
			$filters['rep_id']       = $viewer->rep_id;
			$filters['recruiter_id'] = 0;
		} elseif ( Viewer::RECRUITER === $viewer->scope ) {
			$filters['recruiter_id'] = $viewer->recruiter_id;
			if ( $filters['rep_id'] > 0 && ! $viewer->can_see_rep( $filters['rep_id'] ) ) {
				return null;
			}
		}

		return $filters;
	}

	/**
	 * @param list<int|string> $args Bound parameters.
	 */
	private static function ledger_where( Viewer $viewer, array $filters, array &$args ): string {
		$where = 'WHERE 1=1';
		if ( Viewer::REP === $viewer->scope ) {
			$where .= ' AND l.payee_type = %s AND l.payee_id = %d';
			$args[] = 'rep';
			$args[] = $viewer->rep_id;
		} elseif ( Viewer::RECRUITER === $viewer->scope ) {
			$where .= ' AND l.payee_type = %s AND l.payee_id = %d';
			$args[] = 'recruiter';
			$args[] = $viewer->recruiter_id;
			if ( $filters['rep_id'] > 0 ) {
				$where .= ' AND l.rep_id = %d';
				$args[] = $filters['rep_id'];
			}
		} else {
			if ( $filters['rep_id'] > 0 ) {
				$where .= ' AND l.rep_id = %d';
				$args[] = $filters['rep_id'];
			}
			if ( $filters['recruiter_id'] > 0 ) {
				$where .= ' AND l.recruiter_id = %d';
				$args[] = $filters['recruiter_id'];
			}
		}

		return $where . self::date_sql( $filters, 'l.created_at', 'l.period', $args );
	}

	/**
	 * @param list<int|string> $args Bound parameters.
	 */
	private static function orders_where( Viewer $viewer, array $filters, array &$args ): string {
		$where = 'WHERE 1=1';
		if ( Viewer::REP === $viewer->scope ) {
			$where .= ' AND o.rep_id = %d';
			$args[] = $viewer->rep_id;
		} elseif ( Viewer::RECRUITER === $viewer->scope ) {
			$where .= ' AND o.recruiter_id = %d';
			$args[] = $viewer->recruiter_id;
			if ( $filters['rep_id'] > 0 ) {
				$where .= ' AND o.rep_id = %d';
				$args[] = $filters['rep_id'];
			}
		} else {
			if ( $filters['rep_id'] > 0 ) {
				$where .= ' AND o.rep_id = %d';
				$args[] = $filters['rep_id'];
			}
			if ( $filters['recruiter_id'] > 0 ) {
				$where .= ' AND o.recruiter_id = %d';
				$args[] = $filters['recruiter_id'];
			}
		}

		$where .= self::date_sql(
			array(
				'from'   => $filters['from'],
				'to'     => $filters['to'],
				'period' => '',
			),
			'o.attributed_at',
			'',
			$args
		);

		if ( self::valid_period( (string) $filters['period'] ) ) {
			$where .= ' AND o.attributed_at >= %s AND o.attributed_at < %s';
			$args[] = get_gmt_from_date( $filters['period'] . '-01 00:00:00' );
			$start  = \DateTimeImmutable::createFromFormat( '!Y-m-d', $filters['period'] . '-01' );
			$args[] = $start instanceof \DateTimeImmutable
				? get_gmt_from_date( $start->modify( '+1 month' )->format( 'Y-m-d' ) . ' 00:00:00' )
				: get_gmt_from_date( $filters['period'] . '-01 00:00:00' );
		}

		return $where;
	}

	/**
	 * @param array{from: string, to: string, period: string} $filters Dates.
	 * @param list<int|string>                                $args    Bound parameters.
	 */
	private static function date_sql( array $filters, string $created_column, string $period_column, array &$args ): string {
		$sql = '';
		if ( '' !== $period_column && self::valid_period( $filters['period'] ) ) {
			$sql   .= " AND {$period_column} = %s";
			$args[] = $filters['period'];
		}
		if ( self::valid_date( $filters['from'] ) ) {
			$sql   .= " AND {$created_column} >= %s";
			$args[] = get_gmt_from_date( $filters['from'] . ' 00:00:00' );
		}
		if ( self::valid_date( $filters['to'] ) ) {
			$sql   .= " AND {$created_column} <= %s";
			$args[] = get_gmt_from_date( $filters['to'] . ' 23:59:59' );
		}

		return $sql;
	}

	/**
	 * @param list<int|string> $args Bound parameters.
	 * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
	 */
	private static function paginate( string $count_sql, string $select_sql, array $args, int $page ): array {
		global $wpdb;

		$page    = max( 1, $page );
		$total   = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql ) );
		$pages   = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page    = min( $page, $pages );
		$offset  = ( $page - 1 ) * self::PER_PAGE;
		$select  = $select_sql . ' LIMIT %d OFFSET %d';
		$qargs   = array_merge( $args, array( self::PER_PAGE, $offset ) );
		$rows    = $wpdb->get_results( $wpdb->prepare( $select, $qargs ), ARRAY_A );

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
			'page'  => $page,
			'pages' => $pages,
		);
	}

	/**
	 * @param array{from: string, to: string, period: string, rep_id: int, recruiter_id: int} $filters Filters.
	 */
	private static function refunded_product_cents( Viewer $viewer, array $filters ): int {
		global $wpdb;

		$args    = array();
		$where   = self::ledger_where( $viewer, $filters, $args );
		$where  .= " AND l.entry_type IN ('refund', 'reversal') AND l.payee_type = %s";
		$args[]  = 'rep';
		$sql     = 'SELECT l.inputs_json FROM ' . Schema::table( 'ledger' ) . ' l ' . $where;
		$rows    = $args ? $wpdb->get_col( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_col( $sql );
		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$sum = 0;
		foreach ( $rows as $json ) {
			$decoded = json_decode( (string) $json, true );
			if ( is_array( $decoded ) && isset( $decoded['refunded_base_cents'] ) ) {
				$sum += (int) $decoded['refunded_base_cents'];
			}
		}

		return $sum;
	}

	/**
	 * @param array{from?: string, to?: string, period?: string} $filters Dates.
	 */
	private static function rep_commission_for_recruiter( int $rep_id, int $recruiter_id, array $filters ): int {
		$summary_filters = array(
			'from'         => isset( $filters['from'] ) ? (string) $filters['from'] : '',
			'to'           => isset( $filters['to'] ) ? (string) $filters['to'] : '',
			'period'       => isset( $filters['period'] ) ? (string) $filters['period'] : '',
			'rep_id'       => $rep_id,
			'recruiter_id' => $recruiter_id,
		);

		global $wpdb;
		$args  = array();
		$where = 'WHERE l.payee_type = %s AND l.payee_id = %d AND l.recruiter_id = %d';
		$args[] = 'rep';
		$args[] = $rep_id;
		$args[] = $recruiter_id;
		$where .= self::date_sql(
			array(
				'from'   => $summary_filters['from'],
				'to'     => $summary_filters['to'],
				'period' => $summary_filters['period'],
			),
			'l.created_at',
			'l.period',
			$args
		);

		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(l.amount_cents), 0) FROM ' . Schema::table( 'ledger' ) . ' l ' . $where,
				$args
			)
		);

		return (int) $value;
	}

	/**
	 * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
	 */
	private static function empty_page( int $page ): array {
		return array(
			'rows'  => array(),
			'total' => 0,
			'page'  => max( 1, $page ),
			'pages' => 1,
		);
	}

	private static function csv_cell( string $value ): string {
		if ( preg_match( '/^[=+\-@]/', $value ) ) {
			return "'" . $value;
		}

		return $value;
	}

	private static function csv_money( int $cents ): string {
		$negative = $cents < 0;
		$abs      = abs( $cents );
		$text     = intdiv( $abs, 100 ) . '.' . str_pad( (string) ( $abs % 100 ), 2, '0', STR_PAD_LEFT );

		return ( $negative ? '-' : '' ) . $text;
	}
}
