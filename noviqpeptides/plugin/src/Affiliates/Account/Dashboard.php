<?php
/**
 * My Account screen for representatives and recruiters.
 *
 * WordPress blocks wp-admin for these roles, so the storefront account page is
 * the dashboard. Administrators use WooCommerce → Affiliates instead.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates\Account;

use Noviq\Core\Affiliates\Money;
use Noviq\Core\Affiliates\Program;
use Noviq\Core\Affiliates\Reports;
use Noviq\Core\Affiliates\Viewer;

defined( 'ABSPATH' ) || exit;

final class Dashboard {

	public const ENDPOINT = 'affiliate-dashboard';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( self::class, 'menu' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( self::class, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
	}

	public static function register_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * @param array<string, string> $items Menu items.
	 * @return array<string, string>
	 */
	public static function menu( array $items ): array {
		if ( null === Viewer::for_user( get_current_user_id() ) ) {
			return $items;
		}

		$updated  = array();
		$inserted = false;
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$updated[ self::ENDPOINT ] = __( 'Affiliate', 'noviq-core' );
				$inserted                  = true;
			}
			$updated[ $key ] = $label;
		}
		if ( ! $inserted ) {
			$updated[ self::ENDPOINT ] = __( 'Affiliate', 'noviq-core' );
		}

		return $updated;
	}

	public static function assets(): void {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		if ( null === Viewer::for_user( get_current_user_id() ) ) {
			return;
		}

		wp_enqueue_style(
			'noviq-affiliates',
			NOVIQ_CORE_URL . 'assets/affiliates.css',
			array(),
			\Noviq\Core\VERSION
		);
	}

	public static function render(): void {
		$viewer = Viewer::for_user( get_current_user_id() );
		if ( null === $viewer ) {
			echo '<p>' . esc_html__( 'This account does not have affiliate access.', 'noviq-core' ) . '</p>';
			return;
		}
		if ( $viewer->is_admin() ) {
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=noviq-affiliates' ) ) . '">' . esc_html__( 'Open the affiliate admin.', 'noviq-core' ) . '</a></p>';
			return;
		}
		if ( 'active' !== $viewer->status ) {
			echo '<p>' . esc_html__( 'This affiliate account is inactive. Historical commissions are unchanged.', 'noviq-core' ) . '</p>';
			return;
		}

		$filters = Reports::filters_from_request( wp_unslash( $_GET ) );
		self::filter_form( $filters );

		if ( Viewer::RECRUITER === $viewer->scope ) {
			self::render_recruiter( $viewer, $filters );
			return;
		}

		self::render_rep( $viewer, $filters );
	}

	/**
	 * @param array{from: string, to: string, period: string, rep_id: int, recruiter_id: int} $filters Filters.
	 */
	private static function render_rep( Viewer $viewer, array $filters ): void {
		$summary = Reports::summary( $viewer, $filters );
		self::cards(
			array(
				__( 'Net sales', 'noviq-core' )      => $summary['sales_cents'],
				__( 'Net commission', 'noviq-core' ) => $summary['commission_cents'],
			),
			$summary['orders']
		);
		self::orders_table( Reports::orders( $viewer, $filters, self::page() ), 'rep', $viewer->rep_id );
		echo '<h3>' . esc_html__( 'Commission entries', 'noviq-core' ) . '</h3>';
		self::ledger_table( Reports::ledger( $viewer, $filters, self::page() ) );
	}

	/**
	 * @param array{from: string, to: string, period: string, rep_id: int, recruiter_id: int} $filters Filters.
	 */
	private static function render_recruiter( Viewer $viewer, array $filters ): void {
		$sales = Reports::summary( $viewer, $filters );
		$own   = Reports::payee_total( 'recruiter', $viewer->recruiter_id, $filters );
		self::cards(
			array(
				__( 'Team net sales', 'noviq-core' ) => $sales['sales_cents'],
				__( 'Your commission', 'noviq-core' ) => $own,
			),
			$sales['orders']
		);

		echo '<h3>' . esc_html__( 'Current team', 'noviq-core' ) . '</h3>';
		echo '<table class="noviq-aff-table"><thead><tr>';
		foreach ( array( __( 'Representative', 'noviq-core' ), __( 'Code', 'noviq-core' ), __( 'Orders', 'noviq-core' ), __( 'Net sales', 'noviq-core' ), __( 'Rep commission', 'noviq-core' ) ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		$team = Reports::team( $viewer->recruiter_id, $filters );
		if ( array() === $team ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No representatives are assigned.', 'noviq-core' ) . '</td></tr>';
		}
		foreach ( $team as $rep ) {
			$url = add_query_arg( 'rep_id', (string) $rep['id'] );
			echo '<tr>';
			echo '<td><a href="' . esc_url( $url ) . '">' . esc_html( (string) $rep['name'] ) . '</a></td>';
			echo '<td><code>' . esc_html( (string) $rep['code'] ) . '</code></td>';
			echo '<td class="noviq-num">' . esc_html( (string) $rep['orders'] ) . '</td>';
			echo '<td>' . wp_kses_post( Money::format_html( (int) $rep['sales_cents'] ) ) . '</td>';
			echo '<td>' . wp_kses_post( Money::format_html( (int) $rep['commission_cents'] ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		if ( $filters['rep_id'] > 0 ) {
			$rep = Program::rep( $filters['rep_id'] );
			echo '<h3>' . esc_html(
				sprintf(
					/* translators: %s: representative name */
					__( 'Orders for %s', 'noviq-core' ),
					null !== $rep ? (string) $rep['name'] : ''
				)
			) . '</h3>';
			self::orders_table( Reports::orders( $viewer, $filters, self::page() ), 'rep', $filters['rep_id'] );
		}

		echo '<h3>' . esc_html__( 'Your commission entries', 'noviq-core' ) . '</h3>';
		self::ledger_table( Reports::ledger( $viewer, $filters, self::page() ) );
	}

	/**
	 * @param array{from: string, to: string, period: string, rep_id: int, recruiter_id: int} $filters Filters.
	 */
	private static function filter_form( array $filters ): void {
		echo '<form method="get" class="noviq-aff-filters" action="' . esc_url( wc_get_account_endpoint_url( self::ENDPOINT ) ) . '">';
		printf(
			'<label>%s <input type="date" name="from" value="%s" /></label> ',
			esc_html__( 'From', 'noviq-core' ),
			esc_attr( $filters['from'] )
		);
		printf(
			'<label>%s <input type="date" name="to" value="%s" /></label> ',
			esc_html__( 'To', 'noviq-core' ),
			esc_attr( $filters['to'] )
		);
		printf(
			'<label>%s <input type="month" name="period" value="%s" /></label> ',
			esc_html__( 'Month', 'noviq-core' ),
			esc_attr( $filters['period'] )
		);
		if ( $filters['rep_id'] > 0 ) {
			echo '<input type="hidden" name="rep_id" value="' . esc_attr( (string) $filters['rep_id'] ) . '" />';
		}
		echo '<button type="submit" class="button">' . esc_html__( 'Filter', 'noviq-core' ) . '</button>';
		echo '</form>';
		echo '<p class="noviq-aff-muted">' . esc_html__( 'Leave the dates empty to see all activity. Refunds appear in red and reduce the net totals.', 'noviq-core' ) . '</p>';
	}

	/**
	 * @param array<string, int> $amounts Labels to cent amounts.
	 */
	private static function cards( array $amounts, int $orders ): void {
		echo '<div class="noviq-aff-cards">';
		foreach ( $amounts as $label => $cents ) {
			echo '<div class="noviq-aff-card"><span>' . esc_html( $label ) . '</span><strong>' . wp_kses_post( Money::format_html( $cents ) ) . '</strong></div>';
		}
		echo '<div class="noviq-aff-card"><span>' . esc_html__( 'Orders', 'noviq-core' ) . '</span><strong class="noviq-num">' . esc_html( (string) $orders ) . '</strong></div>';
		echo '</div>';
	}

	/**
	 * @param array{rows: list<array<string, mixed>>, page: int, pages: int} $page Orders.
	 */
	private static function orders_table( array $page, string $payee_type, int $payee_id ): void {
		echo '<table class="noviq-aff-table"><thead><tr>';
		foreach ( array( __( 'Order', 'noviq-core' ), __( 'Date', 'noviq-core' ), __( 'Net sales', 'noviq-core' ), __( 'Commission', 'noviq-core' ) ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( array() === $page['rows'] ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No orders in this period.', 'noviq-core' ) . '</td></tr>';
		}
		foreach ( $page['rows'] as $row ) {
			$commission = Reports::order_commission( (int) $row['order_id'], $payee_type, $payee_id );
			echo '<tr>';
			echo '<td class="noviq-num">' . esc_html( '#' . (string) $row['order_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['attributed_at'] ) . '</td>';
			echo '<td>' . wp_kses_post( Money::format_html( (int) $row['base_cents'] ) ) . '</td>';
			echo '<td>' . wp_kses_post( Money::format_html( $commission ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		self::pager( (int) $page['page'], (int) $page['pages'] );
	}

	/**
	 * @param array{rows: list<array<string, mixed>>, page: int, pages: int} $page Ledger rows.
	 */
	private static function ledger_table( array $page ): void {
		echo '<table class="noviq-aff-table"><thead><tr>';
		foreach ( array( __( 'Date', 'noviq-core' ), __( 'Order', 'noviq-core' ), __( 'Type', 'noviq-core' ), __( 'Amount', 'noviq-core' ) ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( array() === $page['rows'] ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No commission entries.', 'noviq-core' ) . '</td></tr>';
		}
		foreach ( $page['rows'] as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $row['created_at'] ) . '</td>';
			echo '<td class="noviq-num">' . esc_html( '#' . (string) $row['order_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['entry_type'] ) . '</td>';
			echo '<td>' . wp_kses_post( Money::format_html( (int) $row['amount_cents'] ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		self::pager( (int) $page['page'], (int) $page['pages'] );
	}

	private static function pager( int $page, int $pages ): void {
		if ( $pages <= 1 ) {
			return;
		}
		echo '<p class="noviq-aff-pager">';
		if ( $page > 1 ) {
			echo '<a href="' . esc_url( add_query_arg( 'paged', (string) ( $page - 1 ) ) ) . '">' . esc_html__( 'Previous', 'noviq-core' ) . '</a> ';
		}
		echo esc_html( $page . ' / ' . $pages );
		if ( $page < $pages ) {
			echo ' <a href="' . esc_url( add_query_arg( 'paged', (string) ( $page + 1 ) ) ) . '">' . esc_html__( 'Next', 'noviq-core' ) . '</a>';
		}
		echo '</p>';
	}

	private static function page(): int {
		return isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	}
}
