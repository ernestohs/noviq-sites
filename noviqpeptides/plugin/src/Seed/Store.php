<?php
/**
 * WooCommerce store configuration.
 *
 * Everything the client would otherwise have to click through: currency, page
 * assignment, permalinks, shipping zone and the free-shipping threshold.
 *
 * @package Noviq\Core\Seed
 */

declare(strict_types=1);

namespace Noviq\Core\Seed;

use Noviq\Core\Claims;

defined( 'ABSPATH' ) || exit;

final class Store {

	public function __construct( private readonly Seeder $seeder ) {}

	public function run(): void {
		$this->seeder->section( 'Store settings' );
		$this->ensure_woocommerce_installed();
		$this->ensure_placeholder_image();
		$this->identity();
		$this->settings();
		$this->pages();
		$this->shipping();
		$this->permalinks();
	}

	/**
	 * Make sure WooCommerce's own tables exist.
	 *
	 * Woo defers the rest of its installer to the first admin request. On a
	 * scripted setup that request never happens, so the lookup and analytics
	 * tables (wc_order_product_lookup, wc_customer_lookup, wc_admin_notes …)
	 * are silently missing and Site Health reports a critical error. Creating
	 * them here keeps `wp noviq seed` a complete setup in any environment.
	 *
	 * The full installer is run, not just create_tables(), because it also
	 * creates the placeholder image attachment — without which a theme that
	 * renders the gallery from an attachment ID (Blocksy does) shows no product
	 * image at all, not even a placeholder.
	 *
	 * install() guards itself with a `wc_installing` transient and returns
	 * early if one was set in the last ten minutes, so the guard is cleared
	 * first. The routine is the same one Woo runs on activation and is safe to
	 * repeat.
	 */
	private function ensure_woocommerce_installed(): void {
		global $wpdb;

		if ( ! class_exists( \WC_Install::class ) ) {
			return;
		}

		// Analytics tables and the placeholder attachment are the two things
		// the deferred installer leaves behind on a scripted setup.
		$sentinel = $wpdb->prefix . 'wc_order_product_lookup';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- schema check, no caching layer applies.
		$has_tables  = $sentinel === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sentinel ) );
		$placeholder = (int) get_option( 'woocommerce_placeholder_image', 0 );
		$has_image   = $placeholder > 0 && wp_get_attachment_url( $placeholder );

		if ( $has_tables && $has_image ) {
			$this->seeder->skipped( 'woocommerce install' );

			return;
		}

		if ( $this->seeder->is_dry_run() ) {
			$this->seeder->created( 'woocommerce install' );

			return;
		}

		delete_transient( 'wc_installing' );
		\WC_Install::install();

		$missing = method_exists( \WC_Install::class, 'verify_base_tables' )
			? \WC_Install::verify_base_tables( true, true )
			: array();

		if ( array() !== $missing ) {
			$this->seeder->warn( sprintf( 'WooCommerce tables still missing: %s', implode( ', ', $missing ) ) );
		}

		$this->seeder->created( 'woocommerce install completed (deferred installer had not run)' );
	}

	/**
	 * Ensure the WooCommerce placeholder image exists as a real attachment.
	 *
	 * Woo only creates it on a genuinely fresh install, and its
	 * create_placeholder_image() is private, so a scripted setup can end up
	 * with the option empty. Themes differ in how they cope: Woo's own template
	 * falls back to a plugin URL, but Blocksy builds the gallery from an
	 * attachment ID and renders *nothing* — no image element at all — which
	 * reads as a broken product page.
	 *
	 * Creating it here is deterministic and does not depend on Woo internals.
	 */
	private function ensure_placeholder_image(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$existing = (int) get_option( 'woocommerce_placeholder_image', 0 );
		if ( $existing > 0 && wp_get_attachment_url( $existing ) ) {
			$this->seeder->skipped( 'placeholder image' );

			return;
		}

		if ( $this->seeder->is_dry_run() ) {
			$this->seeder->created( 'placeholder image' );

			return;
		}

		$source = '';
		foreach ( array( 'placeholder.webp', 'placeholder.png' ) as $file ) {
			$candidate = WC()->plugin_path() . '/assets/images/' . $file;
			if ( is_readable( $candidate ) ) {
				$source = $candidate;
				break;
			}
		}

		if ( '' === $source ) {
			$this->seeder->warn( 'WooCommerce placeholder image not found; product images will be blank.' );

			return;
		}

		$upload = wp_upload_bits( 'woocommerce-placeholder' . '.' . pathinfo( $source, PATHINFO_EXTENSION ), null, (string) file_get_contents( $source ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( ! empty( $upload['error'] ) ) {
			$this->seeder->warn( sprintf( 'Could not write placeholder image: %s', $upload['error'] ) );

			return;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => (string) wp_check_filetype( $upload['file'] )['type'],
				'post_title'     => __( 'Product image placeholder', 'noviq-core' ),
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		if ( is_wp_error( $attachment_id ) || 0 === $attachment_id ) {
			$this->seeder->warn( 'Could not create the placeholder attachment.' );

			return;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

		update_option( 'woocommerce_placeholder_image', $attachment_id );
		$this->seeder->created( 'placeholder image' );
	}

	private function option( string $key, mixed $value ): void {
		if ( get_option( $key ) === $value ) {
			$this->seeder->skipped( $key );

			return;
		}

		if ( ! $this->seeder->is_dry_run() ) {
			update_option( $key, $value );
		}

		$this->seeder->updated( sprintf( 'option %s', $key ) );
	}

	/**
	 * Site title and tagline, from Claims.
	 *
	 * wp-env names the site after the checkout directory, so without this the
	 * storefront is branded "noviq-wpwoo". Reading Claims rather than hard-coding
	 * keeps the brand name in the one place that is audited.
	 */
	private function identity(): void {
		$this->option( 'blogname', (string) Claims::site()['name'] );
		$this->option( 'blogdescription', (string) Claims::site()['tagline'] );
	}

	private function settings(): void {
		$this->option( 'woocommerce_currency', 'USD' );
		$this->option( 'woocommerce_currency_pos', 'left' );
		$this->option( 'woocommerce_price_decimal_sep', '.' );
		$this->option( 'woocommerce_price_thousand_sep', ',' );
		$this->option( 'woocommerce_price_num_decimals', '2' );
		$this->option( 'woocommerce_weight_unit', 'g' );
		$this->option( 'woocommerce_dimension_unit', 'cm' );

		/*
		 * Country is set so shipping zones and the address form work. Street
		 * address, city and postcode are deliberately left blank: the real
		 * registered address is an open question for the client, and a
		 * placeholder address on a storefront becomes a real one the moment
		 * someone copies it onto an invoice.
		 */
		$this->option( 'woocommerce_default_country', 'US:TX' );
		$this->option( 'woocommerce_allowed_countries', 'specific' );
		$this->option( 'woocommerce_specific_allowed_countries', array( 'US' ) );

		// Tax nexus is unresolved — see OPEN-QUESTIONS.md. Calculation stays off
		// rather than shipping a guessed rate.
		$this->option( 'woocommerce_calc_taxes', 'no' );

		// No reviews exist, and invented testimonials are the most common
		// enforcement trigger in this market.
		$this->option( 'woocommerce_enable_reviews', 'no' );

		/*
		 * WooCommerce ships "Coming soon" mode enabled on a fresh install, and
		 * completing the deferred installer turns it on. Left alone it serves a
		 * placeholder page for the whole store and short-circuits the request
		 * before template_include, so the theme's templates never run — the
		 * storefront simply is not there.
		 */
		$this->option( 'woocommerce_coming_soon', 'no' );
		$this->option( 'woocommerce_store_pages_only', 'no' );

		$this->option( 'woocommerce_enable_guest_checkout', 'yes' );
		$this->option( 'woocommerce_terms_page_id', $this->page_id( 'policies/terms' ) );

		$this->email_branding();
	}

	/**
	 * Brand the transactional emails.
	 *
	 * Also fixes a real bug: Woo's deferred installer never wrote the email
	 * colour defaults, so wc_rgb_from_hex() was being handed an empty string and
	 * emitting six "Uninitialized string offset" warnings per call — over a
	 * hundred entries in debug.log on a fresh install. Setting the values both
	 * silences that and makes order emails match the storefront.
	 *
	 * Values are the brand tokens; see the palette in the child theme.
	 */
	private function email_branding(): void {
		$this->option( 'woocommerce_email_base_color', '#0A4DA8' );      // primary
		$this->option( 'woocommerce_email_background_color', '#F0F4FB' ); // tint 50
		$this->option( 'woocommerce_email_body_background_color', '#FFFFFF' );
		$this->option( 'woocommerce_email_text_color', '#151515' );      // ink
		$this->option( 'woocommerce_email_from_name', (string) Claims::site()['name'] );
		$this->option( 'woocommerce_email_from_address', (string) Claims::site()['support_email'] );

		// Every order email carries the RUO footer, not just the storefront.
		$this->option( 'woocommerce_email_footer_text', Claims::ruo_short() );
	}

	/**
	 * Assign the Woo functional pages.
	 *
	 * Cart and checkout are seeded as classic shortcode pages. The block
	 * checkout is supported by the attestation gate too (see
	 * Compliance\Attestation), but the classic checkout is what this pass is
	 * verified against — the choice is recorded in README.md.
	 */
	private function pages(): void {
		$pages = array(
			'shop'      => array( 'Shop', '' ),
			'cart'      => array( 'Cart', '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->' ),
			'checkout'  => array( 'Checkout', '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->' ),
			'myaccount' => array( 'My account', '<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->' ),
		);

		foreach ( $pages as $key => list( $title, $content ) ) {
			$slug    = 'myaccount' === $key ? 'my-account' : $key;
			$page_id = Pages::upsert( $this->seeder, $slug, $title, $content );

			$this->option( 'woocommerce_' . $key . '_page_id', $page_id );
		}
	}

	private function page_id( string $path ): int {
		$page = get_page_by_path( $path );

		return $page instanceof \WP_Post ? $page->ID : 0;
	}

	/**
	 * One US zone: a flat rate, plus free shipping above the threshold in
	 * Claims. The threshold is read from Claims rather than typed here so the
	 * number on the ticker and the number the cart enforces cannot diverge.
	 */
	private function shipping(): void {
		if ( $this->seeder->is_dry_run() ) {
			return;
		}

		$threshold = Claims::get( 'free_shipping_threshold' );
		if ( null === $threshold ) {
			$this->seeder->warn( 'No free-shipping threshold configured; skipping free shipping method.' );

			return;
		}

		$existing = \WC_Shipping_Zones::get_zones();
		foreach ( $existing as $zone_data ) {
			if ( 'United States' === $zone_data['zone_name'] ) {
				$this->seeder->skipped( 'shipping zone' );

				return;
			}
		}

		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( 'United States' );
		$zone->add_location( 'US', 'country' );
		$zone->save();

		$flat_id = $zone->add_shipping_method( 'flat_rate' );
		$flat    = \WC_Shipping_Zones::get_shipping_method( $flat_id );
		if ( $flat instanceof \WC_Shipping_Method ) {
			$flat->init_instance_settings();
			$flat->update_option( 'title', 'Standard shipping' );
			$flat->update_option( 'cost', '12.00' );
		}

		$free_id = $zone->add_shipping_method( 'free_shipping' );
		$free    = \WC_Shipping_Zones::get_shipping_method( $free_id );
		if ( $free instanceof \WC_Shipping_Method ) {
			$free->init_instance_settings();
			$free->update_option( 'title', 'Free shipping' );
			$free->update_option( 'requires', 'min_amount' );
			$free->update_option( 'min_amount', (string) $threshold );
		}

		$this->seeder->created( sprintf( 'shipping zone (free over $%s)', $threshold ) );
	}

	private function permalinks(): void {
		if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
			if ( ! $this->seeder->is_dry_run() ) {
				update_option( 'permalink_structure', '/%postname%/' );
				flush_rewrite_rules();
			}
			$this->seeder->updated( 'permalink structure' );
		} else {
			$this->seeder->skipped( 'permalinks' );
		}

		$permalinks                      = wc_get_permalink_structure();
		$permalinks['product_base']      = '/products';
		$permalinks['category_base']     = 'collections';
		$permalinks['attribute_base']    = '';
		$permalinks['tag_base']          = 'product-tag';

		$this->option( 'woocommerce_permalinks', $permalinks );
	}
}
