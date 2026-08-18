<?php
/**
 * Product-page panels driven by the compound record.
 *
 * The spec table, documentation panel and bundle contents are rendered from the
 * linked noviq_compound records — chemistry is never copied into product meta,
 * so correcting a molecular weight in one place corrects it on the product
 * page, the monograph, every comparison and the JSON-LD at once.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Content;

use Noviq\Core\Meta;
use Noviq\Core\PostTypes;

defined( 'ABSPATH' ) || exit;

final class ProductPanels {

	public static function init(): void {
		add_filter( 'woocommerce_product_tabs', array( self::class, 'tabs' ) );
		add_action( 'woocommerce_single_product_summary', array( self::class, 'bundle_contents' ), 27 );
	}

	/**
	 * @param array<string, array{title: string, priority: int, callback: callable}> $tabs Product tabs.
	 * @return array<string, array{title: string, priority: int, callback: callable}>
	 */
	public static function tabs( array $tabs ): array {
		global $product;

		if ( $product instanceof \WC_Product && array() !== Meta::compound_ids( $product->get_id() ) ) {
			$tabs['noviq_specification'] = array(
				'title'    => __( 'Specification', 'noviq-core' ),
				'priority' => 15,
				'callback' => array( self::class, 'render_specification' ),
			);
		}

		$tabs['noviq_documentation'] = array(
			'title'    => __( 'Documentation', 'noviq-core' ),
			'priority' => 25,
			'callback' => array( self::class, 'render_documentation' ),
		);

		return $tabs;
	}

	public static function render_specification(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		foreach ( Meta::compound_ids( $product->get_id() ) as $compound_id ) {
			$compound = Compound::from_id( $compound_id );
			if ( null === $compound ) {
				continue;
			}

			printf(
				'<section class="noviq-product-spec"><h3 class="noviq-product-spec__heading">%1$s</h3>%2$s<p class="noviq-product-spec__precis">%3$s</p><p><a href="%4$s">%5$s</a></p></section>',
				esc_html( $compound->name() ),
				$compound->render_spec_table(),
				esc_html( $compound->precis() ),
				esc_url( $compound->permalink() ),
				esc_html(
					sprintf(
						/* translators: %s: compound name. */
						__( 'Read the %s monograph', 'noviq-core' ),
						$compound->name()
					)
				)
			);
		}
	}

	/**
	 * Documentation panel.
	 *
	 * With an empty lot registry this renders a correct empty state. It never
	 * claims a certificate is attached to this product when none has been
	 * released.
	 */
	public static function render_documentation(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$lots = get_posts(
			array(
				'post_type'      => PostTypes::LOT,
				'posts_per_page' => 20,
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'   => 'noviq_lot_product_id',
						'value' => $product->get_id(),
					),
				),
			)
		);

		if ( array() === $lots ) {
			$verify = get_page_by_path( 'verify' );
			printf(
				'<div class="noviq-empty" role="status"><h3 class="noviq-empty__heading">%1$s</h3><p class="noviq-empty__body">%2$s</p><p><a href="%3$s">%4$s</a></p></div>',
				esc_html__( 'Certificates published on release', 'noviq-core' ),
				esc_html__( 'Every vial ships with the Certificate of Analysis matching its lot number. Released lots are listed here and in the COA library; none have been published for this product yet.', 'noviq-core' ),
				esc_url( $verify instanceof \WP_Post ? (string) get_permalink( $verify ) : home_url( '/verify' ) ),
				esc_html__( 'Verify a lot number', 'noviq-core' )
			);

			return;
		}

		echo do_shortcode( '[noviq_coa_library]' );
	}

	/**
	 * Bundle contents, display-only.
	 *
	 * A real bundle product type is a follow-up; this lists what the set
	 * contains without pretending the components are separately configurable.
	 */
	public static function bundle_contents(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$raw = get_post_meta( $product->get_id(), Meta::PRODUCT_BUNDLE_OF, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return;
		}

		$items = json_decode( $raw, true );
		if ( ! is_array( $items ) || array() === $items ) {
			return;
		}

		echo '<div class="noviq-bundle"><h3 class="noviq-bundle__heading">' . esc_html__( 'This set contains', 'noviq-core' ) . '</h3><ul class="noviq-bundle__list">';

		foreach ( $items as $item ) {
			$label  = isset( $item['label'] ) ? (string) $item['label'] : '';
			$handle = isset( $item['handle'] ) ? (string) $item['handle'] : '';
			if ( '' === $label ) {
				continue;
			}

			$linked = '' !== $handle ? get_page_by_path( $handle, OBJECT, 'product' ) : null;

			printf(
				'<li>%s</li>',
				$linked instanceof \WP_Post
					? sprintf( '<a href="%1$s">%2$s</a>', esc_url( (string) get_permalink( $linked ) ), esc_html( $label ) )
					: esc_html( $label )
			);
		}

		echo '</ul></div>';
	}
}
