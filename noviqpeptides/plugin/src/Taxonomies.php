<?php
/**
 * Taxonomies.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core;

defined( 'ABSPATH' ) || exit;

final class Taxonomies {

	public const RESEARCH_AREA = 'noviq_research_area';

	/**
	 * Research areas cut across the nine product categories, so they are a
	 * separate taxonomy attached to both compounds and products rather than a
	 * second product-category tree.
	 *
	 * Labels and blurbs are taken from noviq/src/data/compounds.ts.
	 *
	 * @return array<string, array{label: string, blurb: string}>
	 */
	public static function research_areas(): array {
		return array(
			'metabolic'    => array(
				'label' => 'Metabolic research',
				'blurb' => 'Incretin analogues and mitochondrial-derived sequences studied in glucose handling and energy-substrate models.',
			),
			'growth'       => array(
				'label' => 'Somatotropic research',
				'blurb' => 'Releasing-hormone analogues and secretagogues examined in endocrine signalling assays.',
			),
			'tissue'       => array(
				'label' => 'Tissue & matrix research',
				'blurb' => 'Short sequences investigated in cell migration, matrix remodelling and wound-model systems.',
			),
			'neuro'        => array(
				'label' => 'Neurological research',
				'blurb' => 'Regulatory peptides studied in neurotrophic signalling and central-nervous-system models.',
			),
			'longevity'    => array(
				'label' => 'Longevity & mitochondrial research',
				'blurb' => 'Compounds explored in cellular senescence, mitochondrial function and oxidative-stress models.',
			),
			'bioregulator' => array(
				'label' => 'Peptide bioregulators',
				'blurb' => 'Short di- to tetrapeptides from the Khavinson bioregulator literature, studied in gene-expression models.',
			),
			'cosmetic'     => array(
				'label' => 'Dermal & cosmetic research',
				'blurb' => 'Sequences studied topically in dermal matrix, pigmentation and in-vitro skin models.',
			),
			'hormone'      => array(
				'label' => 'Endocrine & receptor research',
				'blurb' => 'Receptor-active peptides used as reference standards in binding and signalling assays.',
			),
			'immune'       => array(
				'label' => 'Immunological research',
				'blurb' => 'Peptides examined in cytokine signalling, inflammation and immune-modulation models.',
			),
		);
	}

	public static function register(): void {
		if ( ! Profile::feature( 'research_areas' ) ) {
			return;
		}

		$object_types = array( 'product' );
		if ( Profile::feature( 'compounds' ) ) {
			array_unshift( $object_types, PostTypes::COMPOUND );
		}

		register_taxonomy(
			self::RESEARCH_AREA,
			$object_types,
			array(
				'labels'            => array(
					'name'          => __( 'Research areas', 'noviq-core' ),
					'singular_name' => __( 'Research area', 'noviq-core' ),
					'menu_name'     => __( 'Research areas', 'noviq-core' ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'rest_base'         => 'research-areas',
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'research-area',
					'with_front' => false,
				),
			)
		);
	}
}
