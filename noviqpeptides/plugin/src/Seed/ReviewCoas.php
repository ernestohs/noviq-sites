<?php
/**
 * Local-only COA sample seeding for payment processor review.
 *
 * Sample documents are media attachments on a clearly labelled local page.
 * They are deliberately not lot records, so they cannot populate /coa or
 * /verify and cannot be mistaken for released analytical records.
 *
 * @package Noviq\Core\Seed
 */

declare(strict_types=1);

namespace Noviq\Core\Seed;

defined( 'ABSPATH' ) || exit;

final class ReviewCoas {

	private const FILE_META = '_noviq_review_coa_file';
	private const PAGE_PATH  = 'coa-review-samples';

	public function __construct( private readonly Seeder $seeder ) {}

	public static function enabled(): bool {
		return '1' === getenv( 'NOVIQ_REVIEW_COAS' );
	}

	/**
	 * Review-only PDF attachments, never lot records.
	 *
	 * @return \WP_Post[]
	 */
	public static function attachments(): array {
		if ( ! self::enabled() ) {
			return array();
		}

		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_key'       => self::FILE_META,
				'meta_compare'   => 'EXISTS',
			)
		);

		return is_array( $attachments ) ? $attachments : array();
	}

	public static function sample_name( \WP_Post $attachment ): string {
		$file = (string) get_post_meta( $attachment->ID, self::FILE_META, true );

		return '' === $file
			? (string) $attachment->post_title
			: (string) pathinfo( $file, PATHINFO_FILENAME );
	}

	public static function sample_url( \WP_Post $attachment ): string {
		return (string) ( wp_get_attachment_url( $attachment->ID ) ?: '' );
	}

	/**
	 * Match a sample by its displayed filename or full filename.
	 *
	 * @return \WP_Post[]
	 */
	public static function matches( string $query ): array {
		$needle  = strtolower( trim( $query ) );
		$matches = array();

		if ( '' === $needle ) {
			return $matches;
		}

		foreach ( self::attachments() as $attachment ) {
			$name = strtolower( self::sample_name( $attachment ) );
			$file = strtolower( (string) get_post_meta( $attachment->ID, self::FILE_META, true ) );

			if ( $needle === $name || $needle === $file ) {
				$matches[] = $attachment;
			}
		}

		return $matches;
	}

	public function run(): void {
		$directory = getenv( 'NOVIQ_SEED_COA' );

		if ( ! is_string( $directory ) || '' === $directory || ! is_dir( $directory ) ) {
			$this->seeder->log( 'Review COA samples: skipped (NOVIQ_SEED_COA is not configured).' );

			return;
		}

		$files = glob( rtrim( $directory, '/' ) . '/*.pdf' );
		$files = is_array( $files ) ? $files : array();
		sort( $files, SORT_NATURAL | SORT_FLAG_CASE );

		if ( array() === $files ) {
			$this->seeder->log( 'Review COA samples: skipped (no PDF files found).' );

			return;
		}

		$links = array();
		foreach ( $files as $file ) {
			$attachment_id = $this->sideload( $file );

			if ( $attachment_id <= 0 ) {
				continue;
			}

			$url = wp_get_attachment_url( $attachment_id );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$links[] = sprintf(
				'<li><a href="%1$s" target="_blank" rel="noopener">%2$s</a></li>',
				esc_url( $url ),
				esc_html( pathinfo( basename( $file ), PATHINFO_FILENAME ) )
			);
		}

		if ( array() === $links ) {
			$this->seeder->warn( 'Review COA samples: no readable attachments were imported.' );

			return;
		}

		$content = implode(
			"\n",
			array(
				'<!-- wp:paragraph --><p><strong>Review samples only.</strong> These documents are supplied only for payment processor review. They are not production release records, are not attached to products, and are not used by the public lot registry or lot verification.</p><!-- /wp:paragraph -->',
				'<!-- wp:paragraph --><p>Remove this page and its attachments before launch. Real certificates should be added as verified lot records only after the analysing laboratory confirms the release data.</p><!-- /wp:paragraph -->',
				'<!-- wp:list --><ul>' . implode( '', $links ) . '</ul><!-- /wp:list -->',
			)
		);

		Pages::upsert(
			$this->seeder,
			self::PAGE_PATH,
			'COA samples for processor review',
			$content
		);
		$this->seeder->log( sprintf( 'Review COA samples: %d PDF(s) available at /%s/.', count( $links ), self::PAGE_PATH ) );
	}

	private function sideload( string $path ): int {
		$filename = basename( $path );
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::FILE_META,
				'meta_value'     => $filename,
			)
		);

		if ( array() !== $existing ) {
			$this->seeder->skipped( 'review COA ' . $filename );

			return (int) $existing[0];
		}

		if ( ! is_readable( $path ) ) {
			$this->seeder->warn( 'Review COA is not readable: ' . $path );

			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( $filename );
		if ( ! is_string( $tmp ) || ! copy( $path, $tmp ) ) {
			$this->seeder->warn( 'Could not copy review COA: ' . $filename );

			return 0;
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => sanitize_file_name( $filename ),
				'tmp_name' => $tmp,
			),
			0,
			pathinfo( $filename, PATHINFO_FILENAME )
		);

		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $tmp ) ) {
				unlink( $tmp );
			}
			$this->seeder->warn( $attachment_id->get_error_message() );

			return 0;
		}

		update_post_meta( (int) $attachment_id, self::FILE_META, $filename );
		update_post_meta( (int) $attachment_id, '_noviq_review_only', '1' );
		$this->seeder->created( 'review COA ' . $filename );

		return (int) $attachment_id;
	}
}
