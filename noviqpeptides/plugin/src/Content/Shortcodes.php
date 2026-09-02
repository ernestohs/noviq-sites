<?php
/**
 * Shortcodes backing the pages that are not WooCommerce defaults.
 *
 * These are shortcodes rather than bespoke page templates so the pages remain
 * ordinary editable content — the client can add copy above and below each
 * module without touching PHP, and a parent-theme swap cannot strand them.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Content;

use Noviq\Core\Claims;
use Noviq\Core\Meta;
use Noviq\Core\PostTypes;
use Noviq\Core\Profile;
use Noviq\Core\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {

	public static function init(): void {
		add_shortcode( 'noviq_research_hub', array( self::class, 'research_hub' ) );
		add_shortcode( 'noviq_coa_library', array( self::class, 'coa_library' ) );
		add_shortcode( 'noviq_verify', array( self::class, 'verify' ) );
		add_shortcode( 'noviq_compare_index', array( self::class, 'compare_index' ) );
		add_shortcode( 'noviq_ticker', array( self::class, 'ticker' ) );
		add_shortcode( 'noviq_quality_standard', array( self::class, 'quality_standard' ) );
		add_shortcode( 'noviq_contact_details', array( self::class, 'contact_details' ) );
	}

	/**
	 * Contact details from Claims::site(). Null facts are omitted, never
	 * placeholdered — same pattern as quality_standard().
	 */
	public static function contact_details(): string {
		$site = Claims::site();
		$out  = '<div class="noviq-contact">';

		$email_rows = array(
			'support_email'   => __( 'General and order enquiries', 'noviq-core' ),
			'wholesale_email' => __( 'Wholesale and bulk', 'noviq-core' ),
			'partner_email'   => __( 'Partner program', 'noviq-core' ),
		);

		$email_block = '';
		foreach ( $email_rows as $key => $label ) {
			$value = $site[ $key ] ?? null;
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}
			$email_block .= sprintf(
				'<p>%1$s: <a href="mailto:%2$s">%3$s</a></p>',
				esc_html( $label ),
				esc_attr( $value ),
				esc_html( $value )
			);
		}

		if ( '' !== $email_block ) {
			$out .= '<h2>' . esc_html__( 'Email', 'noviq-core' ) . '</h2>' . $email_block;
		}

		$phone = $site['phone'] ?? null;
		if ( is_string( $phone ) && '' !== $phone ) {
			$tel = preg_replace( '/[^\d+]/', '', $phone ) ?? $phone;
			$out .= sprintf(
				'<h2>%1$s</h2><p><a class="noviq-num" href="tel:%2$s">%3$s</a></p>',
				esc_html__( 'Phone', 'noviq-core' ),
				esc_attr( $tel ),
				esc_html( $phone )
			);
		}

		$address = $site['address'] ?? null;
		if ( is_string( $address ) && '' !== $address ) {
			$out .= sprintf(
				'<h2>%1$s</h2><p class="noviq-num">%2$s</p>',
				esc_html__( 'Postal address', 'noviq-core' ),
				esc_html( $address )
			);
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * Index of compounds grouped by research area.
	 */
	public static function research_hub(): string {
		$out = '<div class="noviq-hub">';

		foreach ( Taxonomies::research_areas() as $slug => $area ) {
			$term = get_term_by( 'slug', $slug, Taxonomies::RESEARCH_AREA );
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$compounds = get_posts(
				array(
					'post_type'      => PostTypes::COMPOUND,
					'posts_per_page' => 100,
					'post_status'    => 'publish',
					'orderby'        => 'title',
					'order'          => 'ASC',
					'tax_query'      => array(
						array(
							'taxonomy' => Taxonomies::RESEARCH_AREA,
							'field'    => 'slug',
							'terms'    => $slug,
						),
					),
				)
			);

			if ( array() === $compounds ) {
				continue;
			}

			$out .= sprintf(
				'<section class="noviq-hub__area"><h2 class="noviq-hub__heading">%1$s</h2><p class="noviq-hub__blurb">%2$s</p><ul class="noviq-hub__list">',
				esc_html( $area['label'] ),
				esc_html( $area['blurb'] )
			);

			foreach ( $compounds as $post ) {
				$compound = Compound::from_id( $post->ID );
				if ( null === $compound ) {
					continue;
				}

				$out .= sprintf(
					'<li class="noviq-hub__item"><a href="%1$s"><span class="noviq-hub__name">%2$s</span><span class="noviq-num noviq-hub__mw">%3$s</span></a></li>',
					esc_url( $compound->permalink() ),
					esc_html( $compound->name() ),
					esc_html(
						null !== $compound->field( 'noviq_mol_weight' )
							? number_format( (float) $compound->field( 'noviq_mol_weight' ), 2 )
							: Compound::EM_DASH
					)
				);
			}

			$out .= '</ul></section>';
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * COA & SDS document library.
	 *
	 * Ships functional against an empty lot registry. No lot is invented to
	 * populate it — an empty state here is the honest state until the client
	 * supplies real certificates.
	 */
	public static function coa_library(): string {
		$lots = get_posts(
			array(
				'post_type'      => PostTypes::LOT,
				'posts_per_page' => 200,
				'post_status'    => 'publish',
				'orderby'        => 'meta_value',
				'meta_key'       => 'noviq_lot_release_date',
				'order'          => 'DESC',
			)
		);

		if ( array() === $lots ) {
			return self::empty_state(
				__( 'No certificates published yet', 'noviq-core' ),
				__( 'Certificates of Analysis are published on release, one per lot. Every vial ships with the certificate matching its lot number, and it appears here at the same time.', 'noviq-core' ),
				__( 'Request a certificate', 'noviq-core' )
			);
		}

		$rows = '';
		foreach ( $lots as $lot ) {
			$rows .= self::lot_row( $lot );
		}

		return sprintf(
			'<table class="noviq-lots"><thead><tr><th>%1$s</th><th>%2$s</th><th>%3$s</th><th>%4$s</th><th>%5$s</th></tr></thead><tbody>%6$s</tbody></table>',
			esc_html__( 'Lot', 'noviq-core' ),
			esc_html__( 'Product', 'noviq-core' ),
			esc_html__( 'Released', 'noviq-core' ),
			esc_html__( 'Purity', 'noviq-core' ),
			esc_html__( 'Documents', 'noviq-core' ),
			$rows
		);
	}

	private static function lot_row( \WP_Post $lot ): string {
		$product_id = (int) get_post_meta( $lot->ID, 'noviq_lot_product_id', true );
		$product    = $product_id > 0 ? wc_get_product( $product_id ) : null;
		$company    = (string) get_post_meta( $lot->ID, 'noviq_lot_company', true );
		$analyte    = (string) get_post_meta( $lot->ID, 'noviq_lot_analyte', true );
		$purity     = get_post_meta( $lot->ID, 'noviq_lot_purity', true );
		$coa_id     = (int) get_post_meta( $lot->ID, 'noviq_lot_coa_id', true );
		$sds_id     = (int) get_post_meta( $lot->ID, 'noviq_lot_sds_id', true );

		$subject = Compound::EM_DASH;
		if ( $product instanceof \WC_Product ) {
			$subject = $product->get_name();
		} elseif ( '' !== $company || '' !== $analyte ) {
			$parts = array_filter( array( $company, $analyte ) );
			$subject = implode( ' — ', $parts );
		}

		$docs = array();
		if ( $coa_id > 0 ) {
			$docs[] = sprintf( '<a href="%s">COA</a>', esc_url( (string) wp_get_attachment_url( $coa_id ) ) );
		}
		if ( $sds_id > 0 ) {
			$docs[] = sprintf( '<a href="%s">SDS</a>', esc_url( (string) wp_get_attachment_url( $sds_id ) ) );
		}

		return sprintf(
			'<tr><td class="noviq-num">%1$s</td><td>%2$s</td><td class="noviq-num">%3$s</td><td class="noviq-num">%4$s</td><td>%5$s</td></tr>',
			esc_html( (string) get_post_meta( $lot->ID, 'noviq_lot_number', true ) ),
			esc_html( $subject ),
			esc_html( (string) get_post_meta( $lot->ID, 'noviq_lot_release_date', true ) ),
			esc_html( '' !== (string) $purity ? number_format( (float) $purity, 1 ) . '%' : Compound::EM_DASH ),
			array() === $docs ? esc_html( Compound::EM_DASH ) : implode( ' · ', $docs )
		);
	}

	/**
	 * Accession / lot lookup. Accepts an accession number or a company name.
	 * Functional against an empty registry: a miss returns a correct "not found"
	 * rather than a fabricated certificate.
	 */
	public static function verify(): string {
		$query = isset( $_GET['lot'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['lot'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public lookup.

		$label       = Profile::feature( 'compounds' )
			? __( 'Lot number', 'noviq-core' )
			: __( 'Accession number or company name', 'noviq-core' );
		$placeholder = Profile::feature( 'compounds' )
			? __( 'e.g. NVQ-0000', 'noviq-core' )
			: __( 'e.g. MA-0001 or Acme Research', 'noviq-core' );

		$form = sprintf(
			'<form class="noviq-verify__form" method="get" action="%1$s">
				<label class="noviq-verify__label" for="noviq-lot">%2$s</label>
				<div class="noviq-verify__row">
					<input class="noviq-num" type="text" id="noviq-lot" name="lot" value="%3$s" placeholder="%4$s" autocomplete="off" spellcheck="false" />
					<button type="submit" class="noviq-btn">%5$s</button>
				</div>
			</form>',
			esc_url( get_permalink() ?: home_url( '/verify' ) ),
			esc_html( $label ),
			esc_attr( $query ),
			esc_attr( $placeholder ),
			esc_html__( 'Verify', 'noviq-core' )
		);

		if ( '' === $query ) {
			return '<div class="noviq-verify">' . $form . self::registry_note() . '</div>';
		}

		$matches = get_posts(
			array(
				'post_type'      => PostTypes::LOT,
				'posts_per_page' => 20,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => 'noviq_lot_number',
						'value'   => $query,
						'compare' => '=',
					),
					array(
						'key'     => 'noviq_lot_company',
						'value'   => $query,
						'compare' => 'LIKE',
					),
				),
			)
		);

		if ( array() === $matches ) {
			$result = sprintf(
				'<div class="noviq-verify__result noviq-verify__result--none" role="status"><h2>%1$s</h2><p>%2$s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: the lot number that was searched for. */
						__( 'No certificate found for %s', 'noviq-core' ),
						$query
					)
				),
				esc_html__( 'No matching accession or company was found. Check the number on your report, or contact us and we will trace it.', 'noviq-core' )
			);

			return '<div class="noviq-verify">' . $form . $result . self::registry_note() . '</div>';
		}

		$rows = '';
		foreach ( $matches as $match ) {
			$rows .= self::lot_row( $match );
		}

		$result = sprintf(
			'<div class="noviq-verify__result" role="status"><h2>%1$s</h2><table class="noviq-lots"><tbody>%2$s</tbody></table></div>',
			esc_html__( 'Certificate found', 'noviq-core' ),
			$rows
		);

		return '<div class="noviq-verify">' . $form . $result . '</div>';
	}

	/**
	 * Explains why the registry may be empty without implying documents exist.
	 */
	private static function registry_note(): string {
		$count = (int) wp_count_posts( PostTypes::LOT )->publish;

		if ( $count > 0 ) {
			return '';
		}

		return sprintf(
			'<p class="noviq-verify__note">%s</p>',
			esc_html__( 'The lot registry is empty: no lots have been released yet. Certificates are published here the moment a lot is released.', 'noviq-core' )
		);
	}

	/**
	 * Index of published comparisons.
	 */
	public static function compare_index(): string {
		$comparisons = get_posts(
			array(
				'post_type'      => PostTypes::COMPARISON,
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( array() === $comparisons ) {
			return self::empty_state(
				__( 'No comparisons published yet', 'noviq-core' ),
				__( 'Head-to-head comparisons are published as compound records are added.', 'noviq-core' ),
				null
			);
		}

		$items = '';
		foreach ( $comparisons as $post ) {
			$items .= sprintf(
				'<li class="noviq-compare-index__item"><a href="%1$s"><span>%2$s</span><span class="noviq-compare-index__excerpt">%3$s</span></a></li>',
				esc_url( (string) get_permalink( $post ) ),
				esc_html( get_the_title( $post ) ),
				esc_html( wp_strip_all_tags( get_the_excerpt( $post ) ) )
			);
		}

		return '<ul class="noviq-compare-index">' . $items . '</ul>';
	}

	/**
	 * Rotating claims strip. Reads Claims so a null value drops the line
	 * entirely rather than printing an empty figure.
	 */
	public static function ticker(): string {
		$items = '';
		foreach ( Claims::ticker_items() as $item ) {
			$items .= sprintf( '<li class="noviq-ticker__item">%s</li>', esc_html( $item ) );
		}

		return sprintf( '<ul class="noviq-ticker" aria-label="%s">%s</ul>', esc_attr__( 'Product claims', 'noviq-core' ), $items );
	}

	/**
	 * The testing standard, rendered from Claims so no figure is hard-coded in
	 * page content. A null claim renders no row at all.
	 */
	public static function quality_standard(): string {
		$rows  = array();
		$specs = array(
			'purity_spec'               => array( __( 'Chromatographic purity', 'noviq-core' ), '≥%s%%' ),
			'endotoxin_spec'            => array( __( 'Endotoxin', 'noviq-core' ), '≤%s EU/mg' ),
			'sterility_incubation_days' => array( __( 'USP <71> sterility incubation', 'noviq-core' ), '%s days' ),
			'average_purity'            => array( __( 'Measured average purity', 'noviq-core' ), '%s%%' ),
		);

		foreach ( $specs as $key => $spec ) {
			if ( ! Claims::has( $key ) ) {
				continue;
			}
			$rows[] = sprintf(
				'<tr><th scope="row">%1$s</th><td class="noviq-num">%2$s</td></tr>',
				esc_html( $spec[0] ),
				Claims::render( $key, $spec[1] )
			);
		}

		if ( array() === $rows ) {
			return '';
		}

		return sprintf(
			'<table class="noviq-spec noviq-standard"><tbody>%s</tbody></table>',
			implode( '', $rows )
		);
	}

	/**
	 * Shared empty state. Deliberate, explanatory, and never implies a document
	 * exists that does not.
	 */
	private static function empty_state( string $heading, string $body, ?string $cta_label ): string {
		$cta = '';
		if ( null !== $cta_label ) {
			$contact = get_page_by_path( 'contact' );
			$url     = $contact instanceof \WP_Post ? (string) get_permalink( $contact ) : home_url( '/contact' );
			$cta     = sprintf( '<p><a class="noviq-btn" href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html( $cta_label ) );
		}

		return sprintf(
			'<div class="noviq-empty" role="status"><h2 class="noviq-empty__heading">%1$s</h2><p class="noviq-empty__body">%2$s</p>%3$s</div>',
			esc_html( $heading ),
			esc_html( $body ),
			$cta
		);
	}
}
