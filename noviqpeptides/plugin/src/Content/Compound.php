<?php
/**
 * Read model for a compound record.
 *
 * Every surface that renders chemistry goes through this class, so the spec
 * table on a product page, the /learn monograph, a /compare column and the
 * JSON-LD all read the same values from the same record.
 *
 * Unknown values return null and render as an em-dash. A missing molecular
 * weight is a fact about our data, not a reason to print a plausible number.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Content;

use Noviq\Core\Meta;
use Noviq\Core\PostTypes;
use Noviq\Core\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class Compound {

	public const EM_DASH = '—';

	private function __construct( private readonly \WP_Post $post ) {}

	public static function from_id( int $post_id ): ?self {
		$post = get_post( $post_id );

		return ( $post instanceof \WP_Post && PostTypes::COMPOUND === $post->post_type )
			? new self( $post )
			: null;
	}

	public static function from_slug( string $slug ): ?self {
		$posts = get_posts(
			array(
				'post_type'      => PostTypes::COMPOUND,
				'name'           => sanitize_title( $slug ),
				'posts_per_page' => 1,
				'post_status'    => 'publish',
			)
		);

		return isset( $posts[0] ) ? new self( $posts[0] ) : null;
	}

	public function id(): int {
		return $this->post->ID;
	}

	public function name(): string {
		return get_the_title( $this->post );
	}

	public function slug(): string {
		return $this->post->post_name;
	}

	public function permalink(): string {
		return (string) get_permalink( $this->post );
	}

	public function precis(): string {
		return (string) get_post_meta( $this->post->ID, 'noviq_precis', true );
	}

	public function narrative(): string {
		return (string) $this->post->post_content;
	}

	/**
	 * A meta value, or null when unset. Empty string counts as unset: the
	 * seeder writes nothing for values the reference data marks null.
	 */
	public function field( string $key ): ?string {
		$value = get_post_meta( $this->post->ID, $key, true );
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$value = trim( (string) $value );

		return '' === $value ? null : $value;
	}

	/**
	 * Display value for a field, falling back to an em-dash.
	 */
	public function display( string $key ): string {
		return $this->field( $key ) ?? self::EM_DASH;
	}

	/**
	 * @return string[]
	 */
	public function synonyms(): array {
		$raw = $this->field( 'noviq_synonyms' );
		if ( null === $raw ) {
			return array();
		}

		return array_values( array_filter( array_map( 'trim', explode( '|', $raw ) ) ) );
	}

	/**
	 * Research area term, or null.
	 */
	public function research_area(): ?\WP_Term {
		$terms = get_the_terms( $this->post->ID, Taxonomies::RESEARCH_AREA );

		return ( is_array( $terms ) && isset( $terms[0] ) ) ? $terms[0] : null;
	}

	/**
	 * The spec rows, in the order the reference project presents them.
	 *
	 * @return array<int, array{label: string, value: string, mono: bool}>
	 */
	public function spec_rows(): array {
		$area = $this->research_area();

		return array(
			array(
				'label' => __( 'CAS number', 'noviq-core' ),
				'value' => $this->display( 'noviq_cas' ),
				'mono'  => true,
			),
			array(
				'label' => __( 'Molecular formula', 'noviq-core' ),
				'value' => $this->display( 'noviq_formula' ),
				'mono'  => true,
			),
			array(
				'label' => __( 'Average molecular weight', 'noviq-core' ),
				'value' => null !== $this->field( 'noviq_mol_weight' )
					? number_format( (float) $this->field( 'noviq_mol_weight' ), 2 ) . ' g/mol'
					: self::EM_DASH,
				'mono'  => true,
			),
			array(
				'label' => __( 'Amino-acid count', 'noviq-core' ),
				'value' => $this->display( 'noviq_aa_count' ),
				'mono'  => true,
			),
			array(
				'label' => __( 'Peptide class', 'noviq-core' ),
				'value' => $this->display( 'noviq_peptide_class' ),
				'mono'  => false,
			),
			array(
				'label' => __( 'Physical form', 'noviq-core' ),
				'value' => $this->display( 'noviq_physical_form' ),
				'mono'  => false,
			),
			array(
				'label' => __( 'Research area', 'noviq-core' ),
				'value' => $area instanceof \WP_Term ? $area->name : self::EM_DASH,
				'mono'  => false,
			),
			array(
				'label' => __( 'Solubility', 'noviq-core' ),
				'value' => $this->display( 'noviq_solubility' ),
				'mono'  => false,
			),
		);
	}

	/**
	 * Primary sequence, which is rendered separately from the spec table
	 * because it wraps.
	 */
	public function sequence(): ?string {
		return $this->field( 'noviq_sequence' );
	}

	/**
	 * Product IDs that contain this compound.
	 *
	 * @return int[]
	 */
	public function product_ids(): array {
		return Meta::products_for_compound( $this->post->ID );
	}

	/**
	 * Render the shared spec table. Numerics carry .noviq-num so they pick up
	 * the tabular monospace face the brand requires.
	 */
	public function render_spec_table(): string {
		$rows = '';
		foreach ( $this->spec_rows() as $row ) {
			$rows .= sprintf(
				'<tr><th scope="row">%1$s</th><td class="%2$s">%3$s</td></tr>',
				esc_html( $row['label'] ),
				$row['mono'] ? 'noviq-num' : '',
				esc_html( $row['value'] )
			);
		}

		$sequence = $this->sequence();
		if ( null !== $sequence ) {
			$rows .= sprintf(
				'<tr><th scope="row">%1$s</th><td class="noviq-num noviq-seq">%2$s</td></tr>',
				esc_html__( 'Primary sequence', 'noviq-core' ),
				esc_html( $sequence )
			);
		}

		return sprintf(
			'<table class="noviq-spec"><caption class="screen-reader-text">%1$s</caption><tbody>%2$s</tbody></table>',
			esc_html(
				sprintf(
					/* translators: %s: compound name. */
					__( 'Analytical specification for %s', 'noviq-core' ),
					$this->name()
				)
			),
			$rows
		);
	}
}
