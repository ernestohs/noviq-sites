<?php
/**
 * Reference library seeding: compounds, comparisons, journal articles and pages.
 *
 * @package Noviq\Core\Seed
 */

declare(strict_types=1);

namespace Noviq\Core\Seed;

use Noviq\Core\Meta;
use Noviq\Core\PostTypes;
use Noviq\Core\Profile;
use Noviq\Core\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class Library {

	/** @var array<string, int> Compound slug → post ID. */
	private array $compound_ids = array();

	public function __construct( private readonly Seeder $seeder ) {}

	/**
	 * Compounds are seeded before products, because products link to them.
	 */
	public function compounds(): void {
		$this->seeder->section( 'Compounds' );

		foreach ( $this->seeder->data( 'compounds' ) as $data ) {
			$this->upsert_compound( $data );
		}
	}

	public function compound_id( string $slug ): int {
		if ( isset( $this->compound_ids[ $slug ] ) ) {
			return $this->compound_ids[ $slug ];
		}

		$post = get_page_by_path( $slug, OBJECT, PostTypes::COMPOUND );

		return $post instanceof \WP_Post ? $post->ID : 0;
	}

	/**
	 * @param array<string, mixed> $data Compound data.
	 */
	private function upsert_compound( array $data ): void {
		$slug     = (string) $data['slug'];
		$existing = get_page_by_path( $slug, OBJECT, PostTypes::COMPOUND );

		$postarr = array(
			'post_type'    => PostTypes::COMPOUND,
			'post_title'   => (string) $data['name'],
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_content' => (string) $data['narrative_html'],
			'post_excerpt' => (string) $data['precis'],
		);

		if ( $this->seeder->is_dry_run() ) {
			$existing instanceof \WP_Post
				? $this->seeder->skipped( 'compound ' . $slug )
				: $this->seeder->created( 'compound ' . $slug );

			return;
		}

		if ( $existing instanceof \WP_Post ) {
			$postarr['ID'] = $existing->ID;
			$post_id       = (int) wp_update_post( $postarr );
			$this->seeder->updated( 'compound ' . $slug );
		} else {
			$post_id = (int) wp_insert_post( $postarr );
			$this->seeder->created( 'compound ' . $slug );
		}

		if ( 0 === $post_id ) {
			return;
		}

		$this->compound_ids[ $slug ] = $post_id;

		/*
		 * A null in the reference data means "we cannot state this with
		 * confidence". It is stored as an absent meta row, not as an empty
		 * string or a zero, so the template renders an em-dash rather than a
		 * figure that looks measured.
		 */
		$fields = array(
			'noviq_cas'           => $data['cas'],
			'noviq_formula'       => $data['formula'],
			'noviq_mol_weight'    => $data['molecular_weight'],
			'noviq_aa_count'      => $data['amino_acid_count'],
			'noviq_sequence'      => $data['sequence'],
			'noviq_peptide_class' => $data['peptide_class'],
			'noviq_physical_form' => $data['physical_form'],
			'noviq_solubility'    => $data['solubility'],
			'noviq_precis'        => $data['precis'],
			'noviq_synonyms'      => array() !== $data['synonyms'] ? implode( ' | ', $data['synonyms'] ) : null,
		);

		foreach ( $fields as $key => $value ) {
			if ( null === $value || '' === $value ) {
				delete_post_meta( $post_id, $key );
				continue;
			}

			update_post_meta( $post_id, $key, $value );
		}

		if ( null !== $data['research_area'] ) {
			wp_set_object_terms( $post_id, array( (string) $data['research_area'] ), Taxonomies::RESEARCH_AREA );
		}
	}

	/**
	 * Everything that does not need to exist before products are created.
	 */
	public function content(): void {
		if ( Profile::feature( 'comparisons' ) ) {
			$this->comparisons();
		}
		if ( Profile::feature( 'articles' ) ) {
			$this->articles();
		}
		$this->policies();
		$this->site_pages();
	}

	private function comparisons(): void {
		$this->seeder->section( 'Comparisons' );

		foreach ( $this->seeder->data( 'comparisons' ) as $data ) {
			$slug     = (string) $data['slug'];
			$existing = get_page_by_path( $slug, OBJECT, PostTypes::COMPARISON );

			$postarr = array(
				'post_type'    => PostTypes::COMPARISON,
				'post_title'   => (string) $data['title'],
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_content' => (string) $data['narrative_html'],
				'post_excerpt' => (string) $data['summary'],
			);

			if ( $this->seeder->is_dry_run() ) {
				$existing instanceof \WP_Post
					? $this->seeder->skipped( 'comparison ' . $slug )
					: $this->seeder->created( 'comparison ' . $slug );
				continue;
			}

			if ( $existing instanceof \WP_Post ) {
				$postarr['ID'] = $existing->ID;
				$post_id       = (int) wp_update_post( $postarr );
				$this->seeder->updated( 'comparison ' . $slug );
			} else {
				$post_id = (int) wp_insert_post( $postarr );
				$this->seeder->created( 'comparison ' . $slug );
			}

			if ( 0 === $post_id ) {
				continue;
			}

			// Rewritten rather than appended, so a re-run does not accumulate
			// duplicate compound references.
			delete_post_meta( $post_id, Meta::COMPARISON_COMPOUND );
			foreach ( $data['compounds'] as $compound_slug ) {
				$compound_id = $this->compound_id( (string) $compound_slug );
				if ( $compound_id > 0 ) {
					add_post_meta( $post_id, Meta::COMPARISON_COMPOUND, $compound_id );
				}
			}
		}
	}

	private function articles(): void {
		$this->seeder->section( 'Journal articles' );

		foreach ( $this->seeder->data( 'articles' ) as $data ) {
			$slug     = (string) $data['slug'];
			$existing = get_page_by_path( $slug, OBJECT, 'post' );

			$postarr = array(
				'post_type'    => 'post',
				'post_title'   => (string) $data['title'],
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_content' => (string) $data['content_html'],
				'post_excerpt' => (string) $data['description'],
			);

			if ( null !== $data['date'] ) {
				$postarr['post_date'] = gmdate( 'Y-m-d H:i:s', (int) strtotime( (string) $data['date'] ) );
			}

			if ( $this->seeder->is_dry_run() ) {
				$existing instanceof \WP_Post
					? $this->seeder->skipped( 'article ' . $slug )
					: $this->seeder->created( 'article ' . $slug );
				continue;
			}

			if ( $existing instanceof \WP_Post ) {
				$postarr['ID'] = $existing->ID;
				$post_id       = (int) wp_update_post( $postarr );
				$this->seeder->updated( 'article ' . $slug );
			} else {
				$post_id = (int) wp_insert_post( $postarr );
				$this->seeder->created( 'article ' . $slug );
			}

			if ( 0 === $post_id ) {
				continue;
			}

			if ( null !== $data['cluster'] ) {
				wp_set_object_terms( $post_id, array( (string) $data['cluster'] ), 'category' );
			}

			if ( array() !== $data['tags'] ) {
				wp_set_object_terms( $post_id, array_map( 'strval', $data['tags'] ), 'post_tag' );
			}
		}
	}

	/**
	 * Policy pages, nested under a /policies parent to match the footer nav in
	 * the reference project.
	 */
	private function policies(): void {
		$this->seeder->section( 'Policies' );

		$parent = Pages::upsert(
			$this->seeder,
			'policies',
			'Policies',
			'<!-- wp:paragraph --><p>Terms, privacy, shipping, cancellation and accessibility.</p><!-- /wp:paragraph -->'
		);

		foreach ( $this->seeder->data( 'pages' ) as $data ) {
			$content = sprintf( '<!-- wp:html -->%s<!-- /wp:html -->', (string) $data['content_html'] );

			if ( null !== $data['updated'] ) {
				$content .= sprintf(
					'<!-- wp:paragraph --><p class="noviq-num noviq-updated">Last updated %s</p><!-- /wp:paragraph -->',
					esc_html( (string) $data['updated'] )
				);
			}

			Pages::upsert(
				$this->seeder,
				'policies/' . (string) $data['slug'],
				(string) $data['title'],
				$content,
				$parent
			);
		}
	}

	/**
	 * Structural pages: the ones whose body is a plugin shortcode plus copy.
	 */
	private function site_pages(): void {
		$this->seeder->section( 'Site pages' );

		foreach ( $this->seeder->data( 'site-pages' ) as $data ) {
			$content = sprintf(
				'<!-- wp:paragraph {"className":"noviq-page__lede"} --><p class="noviq-page__lede">%s</p><!-- /wp:paragraph -->',
				esc_html( (string) $data['lede'] )
			);

			if ( isset( $data['shortcode'] ) ) {
				$content .= sprintf( '<!-- wp:shortcode -->%s<!-- /wp:shortcode -->', (string) $data['shortcode'] );
			}

			if ( isset( $data['body'] ) ) {
				$content .= sprintf( '<!-- wp:html -->%s<!-- /wp:html -->', (string) $data['body'] );
			}

			Pages::upsert( $this->seeder, (string) $data['slug'], (string) $data['title'], $content );
		}

		$this->front_and_blog();
	}

	/**
	 * A static front page and a /blog posts page, both assigned in options so
	 * the client does not have to set them in Settings → Reading.
	 */
	private function front_and_blog(): void {
		$home_title = (string) ( \Noviq\Core\Claims::fact( 'name' ) ?? 'Home' );
		$home_body  = Profile::home_content() ?? $this->home_content();

		$home = Pages::upsert(
			$this->seeder,
			'home',
			$home_title,
			$home_body
		);

		$blog_slug  = Profile::feature( 'articles' ) ? 'blog' : null;
		$blog       = null;

		if ( null !== $blog_slug ) {
			$blog = Pages::upsert( $this->seeder, 'blog', 'Journal', '' );
		}

		if ( $this->seeder->is_dry_run() || 0 === $home ) {
			return;
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home );

		if ( is_int( $blog ) && $blog > 0 ) {
			update_option( 'page_for_posts', $blog );
		}
	}

	/**
	 * Home page copy.
	 *
	 * Claims come from the ticker shortcode, which reads the Claims config —
	 * so a figure the client cannot substantiate cannot reach the home page
	 * even by editing this content.
	 */
	private function home_content(): string {
		// No [noviq_ticker] here: the theme chrome already carries the claims
		// marquee on every page, and two of them is one too many.
		$blocks = array(
			'<!-- wp:heading {"level":1} --><h1>Research-grade peptides, supplied with the analytical package that proves it</h1><!-- /wp:heading -->',
			'<!-- wp:paragraph {"className":"noviq-page__lede"} --><p class="noviq-page__lede">Every lot is released against a written specification and ships with a Certificate of Analysis carrying its own lot number. For laboratory research use only.</p><!-- /wp:paragraph -->',
			'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/shop">Browse the catalog</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/quality-standard">Our testing standard</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
			'<!-- wp:heading --><h2>Featured</h2><!-- /wp:heading -->',
			'<!-- wp:shortcode -->[products limit="4" columns="4" visibility="featured"]<!-- /wp:shortcode -->',
			'<!-- wp:heading --><h2>Reference library</h2><!-- /wp:heading -->',
			'<!-- wp:paragraph --><p>Monographs describing molecular profile, handling and analytics for every compound in the catalog. <a href="/research-hub">Browse by research area</a> or compare compounds <a href="/compare">side by side</a>.</p><!-- /wp:paragraph -->',
		);

		return implode( "\n\n", $blocks );
	}
}
