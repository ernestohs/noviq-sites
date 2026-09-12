<?php
/**
 * Import Certificates of Analysis into noviq_lot records.
 *
 * Production-only path. Reads coas.json (lot metadata transcribed from the
 * analysing lab) and PDFs from NOVIQ_COA_DIR. Never invents lot numbers or
 * purity figures. Idempotent on lot_number and PDF filename.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Seed;

use Noviq\Core\PostTypes;

defined( 'ABSPATH' ) || exit;

final class ImportCoas {

	private const COA_FILE_META = '_noviq_coa_file';

	public function __construct( private readonly Seeder $seeder ) {}

	public function run(): void {
		$this->seeder->section( 'COA import' );

		$dir = $this->coa_dir();
		if ( ! is_dir( $dir ) ) {
			$this->seeder->error( sprintf( 'COA directory not found: %s (set NOVIQ_COA_DIR)', $dir ) );
		}

		$rows = $this->seeder->data( 'coas' );
		$this->seeder->log( sprintf( 'Manifest: %d row(s); PDF dir: %s', count( $rows ), $dir ) );

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				$this->seeder->warn( sprintf( 'Row %d is not an object — skipped.', $index ) );
				continue;
			}

			$this->import_row( $row, $dir );
		}
	}

	/**
	 * @param array<string, mixed> $row Manifest row.
	 */
	private function import_row( array $row, string $dir ): void {
		$file = isset( $row['file'] ) ? (string) $row['file'] : '';
		$label = '' !== $file ? $file : '(unnamed)';

		if ( ! empty( $row['skip'] ) ) {
			$notes = isset( $row['notes'] ) ? (string) $row['notes'] : 'skipped';
			$this->seeder->log( sprintf( '  - %s (%s)', $label, $notes ) );
			$this->seeder->skipped( $label );

			return;
		}

		$lot_number   = isset( $row['lot_number'] ) ? trim( (string) $row['lot_number'] ) : '';
		$variant_sku  = isset( $row['variant_sku'] ) ? trim( (string) $row['variant_sku'] ) : '';
		$release_date = isset( $row['release_date'] ) ? trim( (string) $row['release_date'] ) : '';
		$purity       = isset( $row['purity'] ) ? $row['purity'] : null;

		if ( '' === $file || '' === $lot_number || '' === $variant_sku || '' === $release_date || ! is_numeric( $purity ) ) {
			$this->seeder->warn(
				sprintf(
					'%s: missing required fields (file, variant_sku, lot_number, purity, release_date) — skipped.',
					$label
				)
			);
			$this->seeder->skipped( $label );

			return;
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $release_date ) ) {
			$this->seeder->warn( sprintf( '%s: release_date must be YYYY-MM-DD — skipped.', $label ) );
			$this->seeder->skipped( $label );

			return;
		}

		$path = $dir . '/' . $file;
		if ( ! is_readable( $path ) ) {
			$this->seeder->warn( sprintf( '%s: PDF not readable at %s — skipped.', $label, $path ) );
			$this->seeder->skipped( $label );

			return;
		}

		$variation_id = (int) wc_get_product_id_by_sku( $variant_sku );
		if ( $variation_id <= 0 ) {
			$this->seeder->warn( sprintf( '%s: SKU %s not found — skipped.', $label, $variant_sku ) );
			$this->seeder->skipped( $label );

			return;
		}

		$variation = wc_get_product( $variation_id );
		if ( ! $variation instanceof \WC_Product_Variation ) {
			$this->seeder->warn( sprintf( '%s: SKU %s is not a variation — skipped.', $label, $variant_sku ) );
			$this->seeder->skipped( $label );

			return;
		}

		$product_id = (int) $variation->get_parent_id();
		if ( $product_id <= 0 ) {
			$this->seeder->warn( sprintf( '%s: variation %s has no parent — skipped.', $label, $variant_sku ) );
			$this->seeder->skipped( $label );

			return;
		}

		$purity_f = (float) $purity;
		$lot_id   = $this->find_lot_by_number( $lot_number );

		if ( $this->seeder->is_dry_run() ) {
			if ( $lot_id > 0 ) {
				$this->seeder->updated( sprintf( 'lot %s → %s', $lot_number, $label ) );
			} else {
				$this->seeder->created( sprintf( 'lot %s → %s', $lot_number, $label ) );
			}

			return;
		}

		$coa_id = $this->upsert_attachment( $path, $file );
		if ( $coa_id <= 0 ) {
			$this->seeder->warn( sprintf( '%s: could not sideload PDF — skipped.', $label ) );
			$this->seeder->skipped( $label );

			return;
		}

		if ( $lot_id > 0 ) {
			if ( $this->lot_matches( $lot_id, $lot_number, $product_id, $variation_id, $release_date, $purity_f, $coa_id ) ) {
				$this->seeder->skipped( sprintf( 'lot %s', $lot_number ) );

				return;
			}

			$this->write_lot( $lot_id, $lot_number, $product_id, $variation_id, $release_date, $purity_f, $coa_id );
			$this->seeder->updated( sprintf( 'lot %s → %s', $lot_number, $label ) );

			return;
		}

		$lot_id = wp_insert_post(
			array(
				'post_type'   => PostTypes::LOT,
				'post_status' => 'publish',
				'post_title'  => $lot_number,
			),
			true
		);

		if ( is_wp_error( $lot_id ) || (int) $lot_id <= 0 ) {
			$this->seeder->warn(
				sprintf(
					'%s: could not create lot %s — %s',
					$label,
					$lot_number,
					is_wp_error( $lot_id ) ? $lot_id->get_error_message() : 'unknown error'
				)
			);

			return;
		}

		$this->write_lot( (int) $lot_id, $lot_number, $product_id, $variation_id, $release_date, $purity_f, $coa_id );
		$this->seeder->created( sprintf( 'lot %s → %s', $lot_number, $label ) );
	}

	private function coa_dir(): string {
		$env = getenv( 'NOVIQ_COA_DIR' );
		if ( is_string( $env ) && '' !== $env ) {
			return rtrim( $env, '/' );
		}

		return '/var/www/noviq-coa-pdfs';
	}

	private function find_lot_by_number( string $lot_number ): int {
		$found = get_posts(
			array(
				'post_type'      => PostTypes::LOT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'noviq_lot_number',
				'meta_value'     => $lot_number,
			)
		);

		return array() !== $found ? (int) $found[0] : 0;
	}

	private function lot_matches(
		int $lot_id,
		string $lot_number,
		int $product_id,
		int $variation_id,
		string $release_date,
		float $purity,
		int $coa_id
	): bool {
		return $lot_number === (string) get_post_meta( $lot_id, 'noviq_lot_number', true )
			&& $product_id === (int) get_post_meta( $lot_id, 'noviq_lot_product_id', true )
			&& $variation_id === (int) get_post_meta( $lot_id, 'noviq_lot_variation_id', true )
			&& $release_date === (string) get_post_meta( $lot_id, 'noviq_lot_release_date', true )
			&& abs( (float) get_post_meta( $lot_id, 'noviq_lot_purity', true ) - $purity ) < 0.0001
			&& $coa_id === (int) get_post_meta( $lot_id, 'noviq_lot_coa_id', true )
			&& 'publish' === get_post_status( $lot_id );
	}

	private function write_lot(
		int $lot_id,
		string $lot_number,
		int $product_id,
		int $variation_id,
		string $release_date,
		float $purity,
		int $coa_id
	): void {
		wp_update_post(
			array(
				'ID'          => $lot_id,
				'post_title'  => $lot_number,
				'post_status' => 'publish',
			)
		);

		update_post_meta( $lot_id, 'noviq_lot_number', $lot_number );
		update_post_meta( $lot_id, 'noviq_lot_product_id', (string) $product_id );
		update_post_meta( $lot_id, 'noviq_lot_variation_id', (string) $variation_id );
		update_post_meta( $lot_id, 'noviq_lot_release_date', $release_date );
		// Numeric meta must be a string — see Meta::sanitizer() recursion note.
		update_post_meta( $lot_id, 'noviq_lot_purity', $this->float_to_string( $purity ) );
		update_post_meta( $lot_id, 'noviq_lot_coa_id', (string) $coa_id );
	}

	private function float_to_string( float $value ): string {
		$string = wp_strip_all_tags( sprintf( '%.10F', $value ) );
		$string = preg_replace( '/\.?0+$/', '', $string );

		return is_string( $string ) && '' !== $string ? $string : '0';
	}

	private function upsert_attachment( string $path, string $filename ): int {
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::COA_FILE_META,
				'meta_value'     => $filename,
			)
		);

		if ( array() !== $found ) {
			$existing_id = (int) $found[0];
			$attached    = get_attached_file( $existing_id );
			if ( is_string( $attached ) && is_readable( $attached ) && md5_file( $path ) === md5_file( $attached ) ) {
				return $existing_id;
			}

			// Replace bytes in place when the PDF changed.
			$dest = $attached;
			if ( is_string( $dest ) && '' !== $dest ) {
				if ( ! copy( $path, $dest ) ) {
					return 0;
				}
				wp_update_attachment_metadata( $existing_id, wp_generate_attachment_metadata( $existing_id, $dest ) );

				return $existing_id;
			}
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$safe_name = sanitize_file_name( $filename );
		$tmp       = wp_tempnam( $safe_name );
		if ( ! is_string( $tmp ) || ! copy( $path, $tmp ) ) {
			return 0;
		}

		$file_array = array(
			'name'     => $safe_name,
			'tmp_name' => $tmp,
		);

		$id = media_handle_sideload( $file_array, 0, pathinfo( $filename, PATHINFO_FILENAME ) );
		if ( is_wp_error( $id ) ) {
			if ( file_exists( $tmp ) ) {
				unlink( $tmp );
			}
			$this->seeder->warn( $id->get_error_message() );

			return 0;
		}

		update_post_meta( (int) $id, self::COA_FILE_META, $filename );

		return (int) $id;
	}
}
