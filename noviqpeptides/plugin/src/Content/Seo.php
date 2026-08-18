<?php
/**
 * JSON-LD structured data.
 *
 * Emitted from the compound record and WooCommerce product data, so structured
 * data cannot drift from what the page actually shows. Nothing here asserts a
 * therapeutic use, an efficacy claim, or an aggregate rating we do not hold.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Content;

use Noviq\Core\Claims;
use Noviq\Core\Meta;
use Noviq\Core\PostTypes;

defined( 'ABSPATH' ) || exit;

final class Seo {

	public static function init(): void {
		add_action( 'wp_head', array( self::class, 'emit' ), 20 );

		// WooCommerce advertises an aggregateRating by default; with zero real
		// reviews that would be an invented figure, so it is stripped.
		add_filter( 'woocommerce_structured_data_product', array( self::class, 'strip_ratings' ) );
	}

	/**
	 * @param array<string, mixed> $markup Structured data.
	 * @return array<string, mixed>
	 */
	public static function strip_ratings( array $markup ): array {
		if ( ! Claims::has( 'review_score' ) ) {
			unset( $markup['aggregateRating'], $markup['review'] );
		}

		return $markup;
	}

	public static function emit(): void {
		$graph = array();

		if ( is_singular( PostTypes::COMPOUND ) ) {
			$compound = Compound::from_id( (int) get_queried_object_id() );
			if ( null !== $compound ) {
				$graph[] = self::chemical_substance( $compound );
			}
		}

		if ( is_singular( 'product' ) ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product instanceof \WC_Product ) {
				foreach ( Meta::compound_ids( $product->get_id() ) as $compound_id ) {
					$compound = Compound::from_id( $compound_id );
					if ( null !== $compound ) {
						$graph[] = self::chemical_substance( $compound );
					}
				}
			}
		}

		if ( array() === $graph ) {
			return;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);
	}

	/**
	 * ChemicalSubstance node. Fields the record does not hold are omitted
	 * entirely rather than emitted empty.
	 *
	 * @return array<string, mixed>
	 */
	private static function chemical_substance( Compound $compound ): array {
		$node = array(
			'@type' => 'ChemicalSubstance',
			'name'  => $compound->name(),
			'url'   => $compound->permalink(),
		);

		$map = array(
			'noviq_formula'    => 'molecularFormula',
			'noviq_mol_weight' => 'molecularWeight',
			'noviq_precis'     => 'description',
		);

		foreach ( $map as $meta_key => $schema_key ) {
			$value = $compound->field( $meta_key );
			if ( null !== $value ) {
				$node[ $schema_key ] = $value;
			}
		}

		$cas = $compound->field( 'noviq_cas' );
		if ( null !== $cas ) {
			$node['identifier'] = array(
				'@type'         => 'PropertyValue',
				'propertyID'    => 'CAS',
				'value'         => $cas,
			);
		}

		$synonyms = $compound->synonyms();
		if ( array() !== $synonyms ) {
			$node['alternateName'] = $synonyms;
		}

		return $node;
	}
}
