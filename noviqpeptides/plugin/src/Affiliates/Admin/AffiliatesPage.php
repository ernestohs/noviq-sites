<?php
/**
 * WooCommerce → Affiliates.
 *
 * Administrators manage recruiters, reps, codes, and customer ownership, and
 * export the monthly payout sheet. The screen does not pay anyone.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates\Admin;

use Noviq\Core\Affiliates\Attribution;
use Noviq\Core\Affiliates\Codes;
use Noviq\Core\Affiliates\Money;
use Noviq\Core\Affiliates\Program;
use Noviq\Core\Affiliates\Reports;
use Noviq\Core\Affiliates\Roles;
use Noviq\Core\Affiliates\Viewer;

defined( 'ABSPATH' ) || exit;

final class AffiliatesPage {

	private const SLUG = 'noviq-affiliates';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_noviq_aff_admin', array( self::class, 'handle' ) );
		add_action( 'admin_post_noviq_aff_export', array( self::class, 'export' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Affiliates', 'noviq-core' ),
			__( 'Affiliates', 'noviq-core' ),
			Roles::CAP_MANAGE,
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	public static function assets( string $hook ): void {
		if ( ! str_contains( $hook, self::SLUG ) ) {
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
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You cannot manage affiliates.', 'noviq-core' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'reports';
		$tabs = array(
			'reports'     => __( 'Reports', 'noviq-core' ),
			'reps'        => __( 'Representatives', 'noviq-core' ),
			'recruiters'  => __( 'Recruiters', 'noviq-core' ),
			'customers'   => __( 'Customers', 'noviq-core' ),
			'orders'      => __( 'Orders', 'noviq-core' ),
			'payout'      => __( 'Monthly payout', 'noviq-core' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'reports';
		}

		echo '<div class="wrap noviq-aff">';
		echo '<h1>' . esc_html__( 'Affiliates', 'noviq-core' ) . '</h1>';
		if ( isset( $_GET['noviq_aff_msg'] ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['noviq_aff_msg'] ) ) ) . '</p></div>';
		}
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$url = add_query_arg(
				array(
					'page' => self::SLUG,
					'tab'  => $key,
				),
				admin_url( 'admin.php' )
			);
			printf(
				'<a class="nav-tab%s" href="%s">%s</a>',
				$tab === $key ? ' nav-tab-active' : '',
				esc_url( $url ),
				esc_html( $label )
			);
		}
		echo '</nav>';

		match ( $tab ) {
			'reps' => self::render_reps(),
			'recruiters' => self::render_recruiters(),
			'customers' => self::render_customers(),
			'orders' => self::render_orders(),
			'payout' => self::render_payout(),
			default => self::render_reports(),
		};

		echo '</div>';
	}

	public static function handle(): void {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You cannot manage affiliates.', 'noviq-core' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'noviq_aff_admin', 'noviq_aff_nonce' );

		$do = isset( $_POST['noviq_aff_do'] ) ? sanitize_key( wp_unslash( $_POST['noviq_aff_do'] ) ) : '';
		$result = match ( $do ) {
			'create_recruiter' => self::create_recruiter(),
			'create_rep' => self::create_rep(),
			'set_rep_status' => self::set_status( 'rep' ),
			'set_recruiter_status' => self::set_status( 'recruiter' ),
			'assign_rep' => self::assign_rep(),
			'attach_rep' => self::attach( 'rep' ),
			'attach_recruiter' => self::attach( 'recruiter' ),
			'assign_customer' => self::assign_customer(),
			default => new \WP_Error( 'noviq_aff', __( 'Unknown action.', 'noviq-core' ) ),
		};

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'reports';
		$msg = is_wp_error( $result ) ? $result->get_error_message() : (string) $result;

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => self::SLUG,
					'tab'           => $tab,
					'noviq_aff_msg' => $msg,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function export(): void {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You cannot manage affiliates.', 'noviq-core' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'noviq_aff_export' );

		$period = isset( $_REQUEST['period'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['period'] ) ) : wp_date( 'Y-m' );
		if ( ! Reports::valid_period( $period ) ) {
			wp_die( esc_html__( 'Choose a month.', 'noviq-core' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="noviq-commissions-' . $period . '.csv"' );
		echo Reports::payout_csv( $period ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download.
		exit;
	}

	private static function render_reports(): void {
		$filters = self::request_filters();
		echo '<form method="get" class="noviq-aff-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="reports" />';
		self::filter_fields( $filters, true );
		submit_button( __( 'Filter', 'noviq-core' ), 'secondary', '', false );
		echo '</form>';

		$viewer  = Viewer::admin();
		$summary = Reports::summary( $viewer, $filters );
		self::cards( $summary );
		self::ledger_table( Reports::ledger( $viewer, $filters, self::page() ) );
	}

	private static function render_orders(): void {
		$filters = self::request_filters();
		echo '<form method="get" class="noviq-aff-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="orders" />';
		self::filter_fields( $filters, true );
		submit_button( __( 'Filter', 'noviq-core' ), 'secondary', '', false );
		echo '</form>';
		self::orders_table( Reports::orders( Viewer::admin(), $filters, self::page() ), Viewer::admin() );
	}

	private static function render_payout(): void {
		$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : wp_date( 'Y-m' );
		if ( ! Reports::valid_period( $period ) ) {
			$period = wp_date( 'Y-m' );
		}

		echo '<form method="get" class="noviq-aff-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="payout" />';
		printf(
			'<label>%s <input type="month" name="period" value="%s" /></label> ',
			esc_html__( 'Month', 'noviq-core' ),
			esc_attr( $period )
		);
		submit_button( __( 'Show', 'noviq-core' ), 'secondary', '', false );
		echo '</form>';

		$export = wp_nonce_url(
			admin_url( 'admin-post.php?action=noviq_aff_export&period=' . rawurlencode( $period ) ),
			'noviq_aff_export'
		);
		echo '<p><a class="button button-primary" href="' . esc_url( $export ) . '">' . esc_html__( 'Download CSV', 'noviq-core' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Totals are for manual payout. Negative adjustments are refunds and payment reversals. Nothing here sends money.', 'noviq-core' ) . '</p>';

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Payee', 'noviq-core' ), __( 'Role', 'noviq-core' ), __( 'Earned', 'noviq-core' ), __( 'Adjustments', 'noviq-core' ), __( 'Net', 'noviq-core' ) ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		$rows = Reports::payout_rows( $period );
		if ( array() === $rows ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No commission activity in this month.', 'noviq-core' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $row['payee_name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['payee_type'] ) . '</td>';
			echo '<td>' . wp_kses_post( Money::format_html( (int) $row['earned_cents'] ) ) . '</td>';
			echo '<td>' . wp_kses_post( Money::format_html( (int) $row['adjustment_cents'] ) ) . '</td>';
			echo '<td>' . wp_kses_post( Money::format_html( (int) $row['net_cents'] ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_reps(): void {
		echo '<h2>' . esc_html__( 'Add a representative', 'noviq-core' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="noviq-aff-form">';
		self::nonce( 'reps' );
		echo '<input type="hidden" name="noviq_aff_do" value="create_rep" />';
		echo '<p><label>' . esc_html__( 'Name', 'noviq-core' ) . ' <input type="text" name="name" required /></label></p>';
		echo '<p><label>' . esc_html__( 'Email', 'noviq-core' ) . ' <input type="email" name="email" /></label></p>';
		echo '<p><label>' . esc_html__( 'Code', 'noviq-core' ) . ' <input type="text" name="code" placeholder="' . esc_attr__( 'Leave blank to generate', 'noviq-core' ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Recruiter', 'noviq-core' ) . ' ';
		self::recruiter_select( 'house' );
		echo '</label></p>';
		submit_button( __( 'Create representative', 'noviq-core' ) );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Roster', 'noviq-core' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Name', 'noviq-core' ), __( 'Code', 'noviq-core' ), __( 'Recruiter', 'noviq-core' ), __( 'Status', 'noviq-core' ), __( 'Account', 'noviq-core' ) ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( Program::reps_with_recruiters() as $rep ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $rep['name'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $rep['code'] ) . '</code></td>';
			echo '<td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			self::nonce( 'reps' );
			echo '<input type="hidden" name="noviq_aff_do" value="assign_rep" />';
			echo '<input type="hidden" name="rep_id" value="' . esc_attr( (string) $rep['id'] ) . '" />';
			self::recruiter_select( ! empty( $rep['recruiter_id'] ) ? (string) $rep['recruiter_id'] : 'house' );
			submit_button( __( 'Move', 'noviq-core' ), 'secondary', '', false );
			echo '</form></td>';
			echo '<td>' . esc_html( (string) $rep['status'] );
			echo ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			self::nonce( 'reps' );
			echo '<input type="hidden" name="noviq_aff_do" value="set_rep_status" />';
			echo '<input type="hidden" name="rep_id" value="' . esc_attr( (string) $rep['id'] ) . '" />';
			echo '<input type="hidden" name="status" value="' . esc_attr( 'active' === $rep['status'] ? 'inactive' : 'active' ) . '" />';
			submit_button( 'active' === $rep['status'] ? __( 'Deactivate', 'noviq-core' ) : __( 'Activate', 'noviq-core' ), 'secondary', '', false );
			echo '</form></td>';
			echo '<td>';
			if ( ! empty( $rep['user_id'] ) ) {
				$user = get_user_by( 'id', (int) $rep['user_id'] );
				echo esc_html( $user instanceof \WP_User ? $user->user_email : (string) $rep['user_id'] );
			} else {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				self::nonce( 'reps' );
				echo '<input type="hidden" name="noviq_aff_do" value="attach_rep" />';
				echo '<input type="hidden" name="rep_id" value="' . esc_attr( (string) $rep['id'] ) . '" />';
				echo '<input type="email" name="email" required /> ';
				submit_button( __( 'Attach', 'noviq-core' ), 'secondary', '', false );
				echo '</form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_recruiters(): void {
		echo '<h2>' . esc_html__( 'Add a recruiter', 'noviq-core' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		self::nonce( 'recruiters' );
		echo '<input type="hidden" name="noviq_aff_do" value="create_recruiter" />';
		echo '<p><label>' . esc_html__( 'Name', 'noviq-core' ) . ' <input type="text" name="name" required /></label></p>';
		echo '<p><label>' . esc_html__( 'Email', 'noviq-core' ) . ' <input type="email" name="email" /></label></p>';
		echo '<p><label>' . esc_html__( 'Own selling code', 'noviq-core' ) . ' <input type="text" name="code" /></label> ';
		echo '<span class="description">' . esc_html__( 'Optional. Creates a representative record assigned to this recruiter, so their own sales earn both commissions.', 'noviq-core' ) . '</span></p>';
		submit_button( __( 'Create recruiter', 'noviq-core' ) );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'noviq-core' ) . '</th><th>' . esc_html__( 'Status', 'noviq-core' ) . '</th><th>' . esc_html__( 'Account', 'noviq-core' ) . '</th></tr></thead><tbody>';
		foreach ( Program::recruiters() as $recruiter ) {
			echo '<tr><td>' . esc_html( (string) $recruiter['name'] ) . '</td><td>' . esc_html( (string) $recruiter['status'] );
			echo ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			self::nonce( 'recruiters' );
			echo '<input type="hidden" name="noviq_aff_do" value="set_recruiter_status" />';
			echo '<input type="hidden" name="recruiter_id" value="' . esc_attr( (string) $recruiter['id'] ) . '" />';
			echo '<input type="hidden" name="status" value="' . esc_attr( 'active' === $recruiter['status'] ? 'inactive' : 'active' ) . '" />';
			submit_button( 'active' === $recruiter['status'] ? __( 'Deactivate', 'noviq-core' ) : __( 'Activate', 'noviq-core' ), 'secondary', '', false );
			echo '</form></td><td>';
			if ( ! empty( $recruiter['user_id'] ) ) {
				$user = get_user_by( 'id', (int) $recruiter['user_id'] );
				echo esc_html( $user instanceof \WP_User ? $user->user_email : (string) $recruiter['user_id'] );
			} else {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				self::nonce( 'recruiters' );
				echo '<input type="hidden" name="noviq_aff_do" value="attach_recruiter" />';
				echo '<input type="hidden" name="recruiter_id" value="' . esc_attr( (string) $recruiter['id'] ) . '" />';
				echo '<input type="email" name="email" required /> ';
				submit_button( __( 'Attach', 'noviq-core' ), 'secondary', '', false );
				echo '</form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_customers(): void {
		$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="customers" />';
		echo '<label>' . esc_html__( 'Customer email', 'noviq-core' ) . ' <input type="email" name="email" value="' . esc_attr( $email ) . '" required /></label> ';
		submit_button( __( 'Find', 'noviq-core' ), 'secondary', '', false );
		echo '</form>';

		if ( '' === $email ) {
			echo '<p class="description">' . esc_html__( 'Search by the billing email used at checkout. Changing the representative affects future orders only.', 'noviq-core' ) . '</p>';
			return;
		}

		$row = Attribution::customer_by_email( $email );
		echo '<h2>' . esc_html( $email ) . '</h2>';
		if ( null === $row ) {
			echo '<p>' . esc_html__( 'No representative is assigned yet.', 'noviq-core' ) . '</p>';
		} else {
			$rep = Program::rep( (int) $row['rep_id'] );
			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: rep name, 2: how the assignment was made */
					__( 'Current representative: %1$s. Set by %2$s.', 'noviq-core' ),
					null !== $rep ? (string) $rep['name'] . ' (' . (string) $rep['code'] . ')' : (string) $row['rep_id'],
					(string) $row['method']
				)
			) . '</p>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		self::nonce( 'customers' );
		echo '<input type="hidden" name="noviq_aff_do" value="assign_customer" />';
		echo '<input type="hidden" name="email" value="' . esc_attr( $email ) . '" />';
		echo '<p><label>' . esc_html__( 'Representative', 'noviq-core' ) . ' <select name="rep_id">';
		foreach ( Program::reps_with_recruiters() as $rep ) {
			printf(
				'<option value="%d">%s</option>',
				(int) $rep['id'],
				esc_html( (string) $rep['name'] . ' (' . (string) $rep['code'] . ')' )
			);
		}
		echo '</select></label></p>';
		echo '<p><label>' . esc_html__( 'Note', 'noviq-core' ) . ' <input type="text" name="note" /></label></p>';
		submit_button( __( 'Save assignment', 'noviq-core' ) );
		echo '</form>';
	}

	/**
	 * @param array{sales_cents: int, commission_cents: int, orders: int} $summary Totals.
	 */
	private static function cards( array $summary ): void {
		echo '<div class="noviq-aff-cards">';
		self::card( __( 'Net sales', 'noviq-core' ), (int) $summary['sales_cents'] );
		self::card( __( 'Net commission', 'noviq-core' ), (int) $summary['commission_cents'] );
		echo '<div class="noviq-aff-card"><span>' . esc_html__( 'Orders', 'noviq-core' ) . '</span><strong class="noviq-num">' . esc_html( (string) $summary['orders'] ) . '</strong></div>';
		echo '</div>';
	}

	private static function card( string $label, int $cents ): void {
		echo '<div class="noviq-aff-card"><span>' . esc_html( $label ) . '</span><strong>' . wp_kses_post( Money::format_html( $cents ) ) . '</strong></div>';
	}

	/**
	 * @param array{rows: list<array<string, mixed>>, total: int, page: int, pages: int} $page Page of ledger rows.
	 */
	private static function ledger_table( array $page ): void {
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Date', 'noviq-core' ), __( 'Order', 'noviq-core' ), __( 'Type', 'noviq-core' ), __( 'Payee', 'noviq-core' ), __( 'Base', 'noviq-core' ), __( 'Amount', 'noviq-core' ) ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( array() === $page['rows'] ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No commission entries.', 'noviq-core' ) . '</td></tr>';
		}
		foreach ( $page['rows'] as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $row['created_at'] ) . '</td>';
			echo '<td>' . self::order_link( (int) $row['order_id'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- order_link() escapes.
			echo '<td>' . esc_html( (string) $row['entry_type'] ) . '</td>';
			echo '<td>' . esc_html( Reports::payee_name( (string) $row['payee_type'], (int) $row['payee_id'] ) . ' (' . (string) $row['payee_type'] . ')' ) . '</td>';
			echo '<td>' . wp_kses_post( Money::format_html( (int) $row['base_cents'] ) ) . '</td>';
			echo '<td>' . wp_kses_post( Money::format_html( (int) $row['amount_cents'] ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		self::pager( (int) $page['page'], (int) $page['pages'] );
	}

	/**
	 * @param array{rows: list<array<string, mixed>>, total: int, page: int, pages: int} $page Page of orders.
	 */
	private static function orders_table( array $page, Viewer $viewer ): void {
		unset( $viewer );
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Order', 'noviq-core' ), __( 'Date', 'noviq-core' ), __( 'Representative', 'noviq-core' ), __( 'Recruiter', 'noviq-core' ), __( 'Source', 'noviq-core' ), __( 'Net sales', 'noviq-core' ) ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( array() === $page['rows'] ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No attributed orders.', 'noviq-core' ) . '</td></tr>';
		}
		foreach ( $page['rows'] as $row ) {
			$rep = Program::rep( (int) $row['rep_id'] );
			$rec = ! empty( $row['recruiter_id'] ) ? Program::recruiter( (int) $row['recruiter_id'] ) : null;
			echo '<tr>';
			echo '<td>' . self::order_link( (int) $row['order_id'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- order_link() escapes.
			echo '<td>' . esc_html( (string) $row['attributed_at'] ) . '</td>';
			echo '<td>' . esc_html( null !== $rep ? (string) $rep['name'] : '' ) . '</td>';
			echo '<td>' . esc_html( null !== $rec ? (string) $rec['name'] : __( 'House', 'noviq-core' ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['source'] ) . '</td>';
			echo '<td>' . wp_kses_post( Money::format_html( (int) $row['base_cents'] ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		self::pager( (int) $page['page'], (int) $page['pages'] );
	}

	/**
	 * @param array{from: string, to: string, period: string, rep_id: int, recruiter_id: int} $filters Filters.
	 */
	private static function filter_fields( array $filters, bool $with_people ): void {
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
		if ( ! $with_people ) {
			return;
		}
		echo '<label>' . esc_html__( 'Recruiter', 'noviq-core' ) . ' <select name="recruiter_id"><option value="0">' . esc_html__( 'All', 'noviq-core' ) . '</option>';
		foreach ( Program::recruiters() as $recruiter ) {
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $recruiter['id'],
				selected( $filters['recruiter_id'], (int) $recruiter['id'], false ),
				esc_html( (string) $recruiter['name'] )
			);
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__( 'Representative', 'noviq-core' ) . ' <select name="rep_id"><option value="0">' . esc_html__( 'All', 'noviq-core' ) . '</option>';
		foreach ( Program::reps_with_recruiters() as $rep ) {
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $rep['id'],
				selected( $filters['rep_id'], (int) $rep['id'], false ),
				esc_html( (string) $rep['name'] )
			);
		}
		echo '</select></label> ';
	}

	private static function recruiter_select( string $selected ): void {
		echo '<select name="recruiter_id">';
		printf( '<option value="house"%s>%s</option>', selected( $selected, 'house', false ), esc_html__( 'House (no recruiter)', 'noviq-core' ) );
		foreach ( Program::recruiters() as $recruiter ) {
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $recruiter['id'],
				selected( $selected, (string) $recruiter['id'], false ),
				esc_html( (string) $recruiter['name'] )
			);
		}
		echo '</select>';
	}

	private static function nonce( string $tab ): void {
		wp_nonce_field( 'noviq_aff_admin', 'noviq_aff_nonce' );
		echo '<input type="hidden" name="action" value="noviq_aff_admin" />';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />';
	}

	private static function order_link( int $order_id ): string {
		$order = wc_get_order( $order_id );
		$label = '#' . $order_id;
		if ( $order instanceof \WC_Order ) {
			$url = $order->get_edit_order_url();
			if ( is_string( $url ) && '' !== $url ) {
				return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			}
		}

		return esc_html( $label );
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

	/**
	 * @return array{from: string, to: string, period: string, rep_id: int, recruiter_id: int}
	 */
	private static function request_filters(): array {
		return Reports::filters_from_request( wp_unslash( $_GET ) );
	}

	private static function page(): int {
		return isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function create_recruiter() {
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$code  = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		$user_id = null;
		if ( '' !== $email ) {
			$ensured = Roles::ensure_user( $email, Roles::RECRUITER, $name );
			if ( is_wp_error( $ensured ) ) {
				return $ensured;
			}
			$user_id = $ensured;
		}

		$recruiter_id = Program::create_recruiter( $name, $user_id );
		if ( is_wp_error( $recruiter_id ) ) {
			return $recruiter_id;
		}
		if ( null !== $user_id ) {
			Program::attach_recruiter_user( $recruiter_id, $user_id );
			$user = get_user_by( 'id', $user_id );
			if ( $user instanceof \WP_User ) {
				$user->add_role( Roles::RECRUITER );
			}
		}

		if ( '' !== trim( $code ) ) {
			$made = self::make_rep( $name, $email, $code, $recruiter_id, $user_id );
			if ( is_wp_error( $made ) ) {
				return $made;
			}
		}

		return __( 'Recruiter saved.', 'noviq-core' );
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function create_rep() {
		$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$code      = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$recruiter = isset( $_POST['recruiter_id'] ) ? sanitize_text_field( wp_unslash( $_POST['recruiter_id'] ) ) : 'house';
		$recruiter_id = ( 'house' === $recruiter || '' === $recruiter ) ? null : absint( $recruiter );

		$made = self::make_rep( $name, $email, $code, $recruiter_id, null );
		if ( is_wp_error( $made ) ) {
			return $made;
		}

		return __( 'Representative saved.', 'noviq-core' );
	}

	/**
	 * @return true|\WP_Error
	 */
	private static function make_rep( string $name, string $email, string $code, ?int $recruiter_id, ?int $existing_user ) {
		if ( '' === trim( $code ) ) {
			$code = Codes::suggest_code( $name );
		}
		$coupon_id = Codes::ensure_coupon( $code );
		if ( is_wp_error( $coupon_id ) ) {
			return $coupon_id;
		}

		$user_id = $existing_user;
		if ( null === $user_id && '' !== $email ) {
			$ensured = Roles::ensure_user( $email, Roles::REP, $name );
			if ( is_wp_error( $ensured ) ) {
				return $ensured;
			}
			$user_id = $ensured;
		}

		$rep_id = Program::create_rep( $name, $code, $coupon_id, $recruiter_id, $user_id );
		if ( is_wp_error( $rep_id ) ) {
			return $rep_id;
		}
		if ( null !== $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user instanceof \WP_User ) {
				$user->add_role( Roles::REP );
			}
		}

		return true;
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function set_status( string $which ) {
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( 'rep' === $which ) {
			$result = Program::set_rep_status( isset( $_POST['rep_id'] ) ? absint( $_POST['rep_id'] ) : 0, $status );
		} else {
			$result = Program::set_recruiter_status( isset( $_POST['recruiter_id'] ) ? absint( $_POST['recruiter_id'] ) : 0, $status );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return __( 'Status updated. Past commissions were not changed.', 'noviq-core' );
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function assign_rep() {
		$rep_id    = isset( $_POST['rep_id'] ) ? absint( $_POST['rep_id'] ) : 0;
		$recruiter = isset( $_POST['recruiter_id'] ) ? sanitize_text_field( wp_unslash( $_POST['recruiter_id'] ) ) : 'house';
		$target    = ( 'house' === $recruiter || '' === $recruiter ) ? null : absint( $recruiter );
		$result    = Program::assign_rep( $rep_id, $target, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return __( 'Representative moved. Future orders use the new recruiter.', 'noviq-core' );
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function attach( string $which ) {
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( 'rep' === $which ) {
			$rep = Program::rep( isset( $_POST['rep_id'] ) ? absint( $_POST['rep_id'] ) : 0 );
			if ( null === $rep ) {
				return new \WP_Error( 'noviq_aff_rep', __( 'That representative does not exist.', 'noviq-core' ) );
			}
			$user_id = Roles::ensure_user( $email, Roles::REP, (string) $rep['name'] );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			if ( ! Program::attach_rep_user( (int) $rep['id'], $user_id ) ) {
				return new \WP_Error( 'noviq_aff_user', __( 'That account is already a representative.', 'noviq-core' ) );
			}

			return __( 'Account attached.', 'noviq-core' );
		}

		$recruiter = Program::recruiter( isset( $_POST['recruiter_id'] ) ? absint( $_POST['recruiter_id'] ) : 0 );
		if ( null === $recruiter ) {
			return new \WP_Error( 'noviq_aff_recruiter', __( 'That recruiter does not exist.', 'noviq-core' ) );
		}
		$user_id = Roles::ensure_user( $email, Roles::RECRUITER, (string) $recruiter['name'] );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		if ( ! Program::attach_recruiter_user( (int) $recruiter['id'], $user_id ) ) {
			return new \WP_Error( 'noviq_aff_user', __( 'That account is already a recruiter.', 'noviq-core' ) );
		}

		return __( 'Account attached.', 'noviq-core' );
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function assign_customer() {
		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$rep_id = isset( $_POST['rep_id'] ) ? absint( $_POST['rep_id'] ) : 0;
		$note   = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		$result = Attribution::assign_by_email( $email, $rep_id, get_current_user_id(), $note );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return __( 'Customer assignment saved. Recorded commissions were not changed.', 'noviq-core' );
	}
}
