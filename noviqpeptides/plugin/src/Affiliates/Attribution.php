<?php
/**
 * Persistent customer attribution.
 *
 * The first paid order that carries a valid rep code owns the customer.
 * Later codes do not move that relationship. Administrators can. Guest
 * checkout is disabled on this store; email hash is still recorded so an
 * order that only has a billing email (admin-created, or a future guest path)
 * resolves to the same owner. The hash is sha256 of the normalized address,
 * not the address itself.
 *
 * Rep id and recruiter id are copied onto the order. Reassignment does not
 * rewrite those snapshots.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates;

defined( 'ABSPATH' ) || exit;

final class Attribution {

	public const META_REP       = '_noviq_aff_rep_id';
	public const META_RECRUITER = '_noviq_aff_recruiter_id';
	public const META_SOURCE    = '_noviq_aff_source';
	public const META_CODE      = '_noviq_aff_code';
	public const META_DISCOUNT  = '_noviq_aff_discount_applied';
	public const META_IGNORED   = '_noviq_aff_ignored_code';

	public const BLOCK_FIELD = 'noviq/referral_code';

	private const SESSION_KEY = 'noviq_aff_referral';
	private const COOKIE      = 'noviq_aff_referral';

	public static function init(): void {
		add_action( 'noviq_referral_code_stashed', array( self::class, 'remember_code' ) );
		add_action( 'woocommerce_after_order_notes', array( self::class, 'render_field' ) );
		add_action( 'woocommerce_checkout_process', array( self::class, 'maybe_notice' ) );
		add_action( 'woocommerce_checkout_order_created', array( self::class, 'capture_created_order' ) );
		add_action( 'woocommerce_init', array( self::class, 'register_block_field' ) );
		add_action( 'woocommerce_set_additional_field_value', array( self::class, 'store_block_field' ), 10, 4 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( self::class, 'capture_created_order' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( self::class, 'render_admin' ) );
	}

	public static function register_block_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => self::BLOCK_FIELD,
				'label'    => __( 'Referral code', 'noviq-core' ),
				'location' => 'order',
				'type'     => 'text',
				'required' => false,
			)
		);
	}

	public static function remember_code( string $code ): void {
		$code = Program::format_code( $code );
		if ( ! Codes::is_affiliate_code( $code ) ) {
			return;
		}

		self::ensure_session();
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_KEY, $code );
		}

		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE,
			$code,
			array(
				'expires'  => time() + WEEK_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $code;
	}

	public static function pending_code(): string {
		$code = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$from_session = WC()->session->get( self::SESSION_KEY );
			if ( is_string( $from_session ) && '' !== $from_session ) {
				$code = $from_session;
			}
		}
		if ( '' === $code && isset( $_COOKIE[ self::COOKIE ] ) ) {
			$code = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		}

		$code = Program::format_code( $code );

		return Codes::is_affiliate_code( $code ) ? $code : '';
	}

	/**
	 * @param mixed $value     Field value.
	 * @param mixed $group     Field group.
	 * @param mixed $wc_object Order or customer.
	 */
	public static function store_block_field( string $key, $value, $group, $wc_object ): void {
		unset( $group );
		if ( self::BLOCK_FIELD !== $key || ! $wc_object instanceof \WC_Order ) {
			return;
		}
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return;
		}

		$wc_object->update_meta_data( self::META_CODE, Program::format_code( $value ) );
	}

	public static function capture_created_order( \WC_Order $order ): void {
		self::capture_order( $order );
	}

	/**
	 * Snapshot rep and recruiter onto the order. Does nothing once an earn
	 * row exists, so payment retries and later edits cannot move history.
	 */
	public static function capture_order( \WC_Order $order, string $preferred_code = '' ): void {
		if ( $order instanceof \WC_Order_Refund || self::has_earn( $order->get_id() ) ) {
			return;
		}

		$entered     = self::resolve_entered_code( $order, $preferred_code );
		$customer_id = (int) $order->get_customer_id();
		$email       = (string) $order->get_billing_email();
		$owner_id    = self::owner_rep_id( $customer_id, $email );
		$code_rep    = '' !== $entered ? Codes::rep_for_code( $entered ) : null;

		if ( null !== $owner_id ) {
			$rep = Program::rep( $owner_id );
			if ( null === $rep || 'active' !== (string) $rep['status'] ) {
				return;
			}
			$ignored = ( null !== $code_rep && (int) $code_rep['id'] !== (int) $rep['id'] ) ? $entered : '';
			self::write_snapshot( $order, $rep, 'owner', $entered, $ignored );

			return;
		}

		if ( null === $code_rep || 'active' !== (string) $code_rep['status'] ) {
			return;
		}

		self::write_snapshot( $order, $code_rep, 'code', $entered, '' );
	}

	/**
	 * First paid referral creates ownership. A concurrent order that loses the
	 * unique-key race keeps the winner's rep.
	 *
	 * @return array<string, mixed>|null Snapshot used for commission.
	 */
	public static function lock_in_owner( \WC_Order $order ): ?array {
		$snapshot = self::order_row( $order->get_id() );
		if ( null === $snapshot ) {
			self::capture_order( $order );
			$snapshot = self::order_row( $order->get_id() );
		}
		if ( null === $snapshot ) {
			return null;
		}

		$rep_id = (int) $snapshot['rep_id'];
		if ( 'code' === (string) $snapshot['source'] ) {
			self::sync_owner( $order, $rep_id, true );
		} else {
			self::sync_owner( $order, $rep_id, false );
		}

		$owner_id = self::owner_rep_id( (int) $order->get_customer_id(), (string) $order->get_billing_email() );
		if ( null !== $owner_id && $owner_id !== $rep_id && ! self::has_earn( $order->get_id() ) ) {
			$rep = Program::rep( $owner_id );
			if ( null !== $rep && 'active' === (string) $rep['status'] ) {
				self::write_snapshot( $order, $rep, 'owner', (string) $snapshot['code_entered'], (string) $snapshot['code_entered'] );
				$snapshot = self::order_row( $order->get_id() );
			}
		}

		return $snapshot;
	}

	public static function maybe_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before this hook.
		$email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
		$code  = self::resolve_entered_code_from_request();
		self::add_foreign_code_notice( get_current_user_id(), $email, $code );
	}

	public static function render_field(): void {
		$value = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Repopulates the field after a failed checkout POST.
		if ( isset( $_POST['noviq_aff_referral'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST['noviq_aff_referral'] ) );
		} elseif ( '' !== self::pending_code() ) {
			$value = self::pending_code();
		}

		if ( function_exists( 'woocommerce_form_field' ) ) {
			woocommerce_form_field(
				'noviq_aff_referral',
				array(
					'type'        => 'text',
					'label'       => __( 'Referral code', 'noviq-core' ),
					'placeholder' => __( 'Referral code', 'noviq-core' ),
					'required'    => false,
					'class'       => array( 'form-row-wide' ),
				),
				$value
			);

			return;
		}

		printf(
			'<p class="form-row form-row-wide"><label for="noviq_aff_referral">%1$s</label><input type="text" class="input-text" name="noviq_aff_referral" id="noviq_aff_referral" value="%2$s" /></p>',
			esc_html__( 'Referral code', 'noviq-core' ),
			esc_attr( $value )
		);
	}

	public static function render_admin( \WC_Order $order ): void {
		$row = self::order_row( $order->get_id() );
		if ( null === $row ) {
			return;
		}

		$rep = Program::rep( (int) $row['rep_id'] );
		$rec = ! empty( $row['recruiter_id'] ) ? Program::recruiter( (int) $row['recruiter_id'] ) : null;

		echo '<div class="noviq-aff-order"><h3>' . esc_html__( 'Affiliate attribution', 'noviq-core' ) . '</h3>';
		echo '<p>' . esc_html(
			sprintf(
				/* translators: 1: rep name, 2: code, 3: source */
				__( 'Representative: %1$s (%2$s). Source: %3$s.', 'noviq-core' ),
				null !== $rep ? (string) $rep['name'] : (string) $row['rep_id'],
				null !== $rep ? (string) $rep['code'] : '',
				(string) $row['source']
			)
		) . '</p>';
		if ( null !== $rec ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: %s: recruiter name */
					__( 'Recruiter at the time of the order: %s.', 'noviq-core' ),
					(string) $rec['name']
				)
			) . '</p>';
		}
		echo '</div>';
	}

	public static function email_hash( string $email ): string {
		$email = strtolower( trim( $email ) );
		if ( ! is_email( $email ) ) {
			return '';
		}

		return hash( 'sha256', $email );
	}

	public static function owner_rep_id( int $customer_id, string $email ): ?int {
		if ( $customer_id > 0 ) {
			$row = self::customer_by_user( $customer_id );
			if ( null !== $row ) {
				return (int) $row['rep_id'];
			}
		}

		$hash = self::email_hash( $email );
		if ( '' === $hash ) {
			return null;
		}
		$row = self::customer_by_hash( $hash );

		return null !== $row ? (int) $row['rep_id'] : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function order_row( int $order_id ): ?array {
		if ( $order_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'orders' ) . ' WHERE order_id = %d', $order_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	public static function update_base( int $order_id, int $base_cents, bool $discount_applied ): void {
		global $wpdb;
		$wpdb->update(
			Schema::table( 'orders' ),
			array(
				'base_cents'       => $base_cents,
				'discount_applied' => $discount_applied ? 1 : 0,
			),
			array( 'order_id' => $order_id ),
			array( '%d', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function customer_by_email( string $email ): ?array {
		$hash = self::email_hash( $email );
		$row  = '' !== $hash ? self::customer_by_hash( $hash ) : null;
		if ( null !== $row ) {
			return $row;
		}

		$user = get_user_by( 'email', $email );
		if ( $user instanceof \WP_User ) {
			return self::customer_by_user( (int) $user->ID );
		}

		return null;
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function assign_by_email( string $email, int $rep_id, int $changed_by, string $note ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'noviq_aff_email', __( 'Enter a valid email address.', 'noviq-core' ) );
		}
		if ( null === Program::rep( $rep_id ) ) {
			return new \WP_Error( 'noviq_aff_rep', __( 'That representative does not exist.', 'noviq-core' ) );
		}

		$row = self::customer_by_email( $email );
		if ( null !== $row ) {
			return self::reassign_customer( (int) $row['id'], $rep_id, $changed_by, $note );
		}

		$user        = get_user_by( 'email', $email );
		$customer_id = $user instanceof \WP_User ? (int) $user->ID : 0;
		$row_id      = Schema::insert(
			Schema::table( 'customers' ),
			array(
				'customer_id'    => $customer_id > 0 ? $customer_id : null,
				'email_hash'     => self::email_hash( $email ),
				'rep_id'         => $rep_id,
				'source_order_id'=> 0,
				'method'         => 'admin',
				'assigned_at'    => Schema::now(),
				'assigned_by'    => $changed_by,
			)
		);
		self::write_history( $row_id, 0, $rep_id, $changed_by, $note );

		return true;
	}

	/**
	 * Future orders follow the new rep. Ledger rows are not rewritten.
	 *
	 * @return true|\WP_Error
	 */
	public static function reassign_customer( int $row_id, int $rep_id, int $changed_by, string $note ) {
		$row = self::customer_row( $row_id );
		if ( null === $row ) {
			return new \WP_Error( 'noviq_aff_customer', __( 'That customer assignment does not exist.', 'noviq-core' ) );
		}
		if ( null === Program::rep( $rep_id ) ) {
			return new \WP_Error( 'noviq_aff_rep', __( 'That representative does not exist.', 'noviq-core' ) );
		}
		if ( (int) $row['rep_id'] === $rep_id ) {
			return true;
		}

		global $wpdb;
		$wpdb->update(
			Schema::table( 'customers' ),
			array(
				'rep_id'      => $rep_id,
				'method'      => 'admin',
				'assigned_at' => Schema::now(),
				'assigned_by' => $changed_by,
			),
			array( 'id' => $row_id ),
			array( '%d', '%s', '%s', '%d' ),
			array( '%d' )
		);
		self::write_history( $row_id, (int) $row['rep_id'], $rep_id, $changed_by, $note );

		return true;
	}

	/**
	 * @param array<string, mixed> $rep Rep row.
	 */
	private static function write_snapshot( \WC_Order $order, array $rep, string $source, string $entered, string $ignored ): void {
		$rep_id       = (int) $rep['id'];
		$recruiter_id = Program::commissionable_recruiter_id( $rep_id );
		$discount     = self::discount_applied( $order, (string) $rep['code'] );
		$base         = self::line_base_cents( $order );

		$order->update_meta_data( self::META_REP, $rep_id );
		$order->update_meta_data( self::META_RECRUITER, $recruiter_id ?? 0 );
		$order->update_meta_data( self::META_SOURCE, $source );
		$order->update_meta_data( self::META_CODE, $entered );
		$order->update_meta_data( self::META_DISCOUNT, $discount ? 'yes' : 'no' );
		if ( '' !== $ignored ) {
			$order->update_meta_data( self::META_IGNORED, $ignored );
		}
		$order->save();

		self::upsert_order_row( $order->get_id(), $rep_id, $recruiter_id, $source, $entered, $discount, $base );
	}

	private static function upsert_order_row( int $order_id, int $rep_id, ?int $recruiter_id, string $source, string $entered, bool $discount, int $base ): void {
		global $wpdb;

		$table = Schema::table( 'orders' );
		$now   = Schema::now();
		$sql   = "INSERT INTO {$table}
			(order_id, rep_id, recruiter_id, source, code_entered, discount_applied, base_cents, attributed_at)
			VALUES (%d, %d, " . ( null === $recruiter_id ? 'NULL' : '%d' ) . ", %s, %s, %d, %d, %s)
			ON DUPLICATE KEY UPDATE
				rep_id = VALUES(rep_id),
				recruiter_id = VALUES(recruiter_id),
				source = VALUES(source),
				code_entered = VALUES(code_entered),
				discount_applied = VALUES(discount_applied),
				base_cents = VALUES(base_cents),
				attributed_at = VALUES(attributed_at)";

		$args = array( $order_id, $rep_id );
		if ( null !== $recruiter_id ) {
			$args[] = $recruiter_id;
		}
		$args[] = $source;
		$args[] = $entered;
		$args[] = $discount ? 1 : 0;
		$args[] = $base;
		$args[] = $now;

		$prepared = $wpdb->prepare( $sql, $args );
		if ( is_string( $prepared ) ) {
			$wpdb->query( $prepared );
		}
	}

	private static function sync_owner( \WC_Order $order, int $rep_id, bool $create ): void {
		$customer_id = (int) $order->get_customer_id();
		$hash        = self::email_hash( (string) $order->get_billing_email() );
		$by_customer = $customer_id > 0 ? self::customer_by_user( $customer_id ) : null;
		$by_email    = '' !== $hash ? self::customer_by_hash( $hash ) : null;

		if ( null !== $by_customer ) {
			if ( ( empty( $by_customer['email_hash'] ) ) && '' !== $hash && null === $by_email ) {
				self::set_email_hash( (int) $by_customer['id'], $hash );
			}

			return;
		}

		if ( null !== $by_email ) {
			if ( $customer_id > 0 && empty( $by_email['customer_id'] ) && null === self::customer_by_user( $customer_id ) ) {
				self::set_customer_id( (int) $by_email['id'], $customer_id );
			}

			return;
		}

		if ( ! $create || $rep_id <= 0 || ( '' === $hash && $customer_id <= 0 ) ) {
			return;
		}

		Schema::insert(
			Schema::table( 'customers' ),
			array(
				'customer_id'     => $customer_id > 0 ? $customer_id : null,
				'email_hash'      => '' !== $hash ? $hash : null,
				'rep_id'          => $rep_id,
				'source_order_id' => $order->get_id(),
				'method'          => 'first_order',
				'assigned_at'     => Schema::now(),
				'assigned_by'     => 0,
			)
		);
	}

	public static function line_base_cents( \WC_Order $order ): int {
		$cents = 0;
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$cents += Money::to_cents( $item->get_total( 'edit' ) );
		}

		return $cents;
	}

	private static function discount_applied( \WC_Order $order, string $rep_code ): bool {
		$rep_code = Program::format_code( $rep_code );
		foreach ( $order->get_items( 'coupon' ) as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Coupon ) {
				continue;
			}
			if ( Program::format_code( $item->get_code() ) === $rep_code ) {
				return true;
			}
		}

		return false;
	}

	private static function resolve_entered_code( \WC_Order $order, string $preferred ): string {
		$candidates = array();
		foreach ( $order->get_items( 'coupon' ) as $item ) {
			if ( $item instanceof \WC_Order_Item_Coupon ) {
				$candidates[] = $item->get_code();
			}
		}
		if ( '' !== $preferred ) {
			$candidates[] = $preferred;
		}
		$candidates[] = self::posted_code();
		$candidates[] = (string) $order->get_meta( self::META_CODE );
		$candidates[] = self::pending_code();

		foreach ( $candidates as $code ) {
			$code = Program::format_code( (string) $code );
			if ( Codes::is_affiliate_code( $code ) ) {
				return $code;
			}
		}

		return '';
	}

	private static function resolve_entered_code_from_request(): string {
		$code = self::posted_code();
		if ( '' !== $code && Codes::is_affiliate_code( $code ) ) {
			return $code;
		}
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_applied_coupons() as $applied ) {
				$applied = Program::format_code( (string) $applied );
				if ( Codes::is_affiliate_code( $applied ) ) {
					return $applied;
				}
			}
		}

		return self::pending_code();
	}

	private static function posted_code(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checkout nonce is verified by WooCommerce before checkout hooks.
		if ( ! isset( $_POST['noviq_aff_referral'] ) ) {
			return '';
		}

		return Program::format_code( sanitize_text_field( wp_unslash( $_POST['noviq_aff_referral'] ) ) );
	}

	private static function add_foreign_code_notice( int $customer_id, string $email, string $code ): void {
		if ( '' === $code || ! Codes::is_active_code( $code ) ) {
			return;
		}
		$owner = self::owner_rep_id( $customer_id, $email );
		$rep   = Codes::rep_for_code( $code );
		if ( null === $owner || null === $rep || (int) $rep['id'] === $owner ) {
			return;
		}
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice(
				__( 'This account already has a representative. The code you entered will not change that.', 'noviq-core' ),
				'notice'
			);
		}
	}

	private static function has_earn( int $order_id ): bool {
		global $wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . Schema::table( 'ledger' ) . ' WHERE order_id = %d AND entry_type = %s LIMIT 1',
				$order_id,
				'earn'
			)
		);

		return null !== $found && '' !== $found;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function customer_by_user( int $customer_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'customers' ) . ' WHERE customer_id = %d', $customer_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function customer_by_hash( string $hash ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'customers' ) . ' WHERE email_hash = %s', $hash ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function customer_row( int $row_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'customers' ) . ' WHERE id = %d', $row_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	private static function set_email_hash( int $row_id, string $hash ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::table( 'customers' ) . ' SET email_hash = %s WHERE id = %d AND email_hash IS NULL',
				$hash,
				$row_id
			)
		);
	}

	private static function set_customer_id( int $row_id, int $customer_id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::table( 'customers' ) . ' SET customer_id = %d WHERE id = %d AND customer_id IS NULL',
				$customer_id,
				$row_id
			)
		);
	}

	private static function write_history( int $row_id, int $from_rep, int $to_rep, int $changed_by, string $note ): void {
		Schema::insert(
			Schema::table( 'customer_history' ),
			array(
				'customer_row_id' => $row_id,
				'from_rep_id'     => $from_rep,
				'to_rep_id'       => $to_rep,
				'changed_by'      => $changed_by,
				'note'            => substr( sanitize_text_field( $note ), 0, 255 ),
				'created_at'      => Schema::now(),
			)
		);
	}

	private static function ensure_session(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}
}
