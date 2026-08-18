<?php
/**
 * Seed orchestrator.
 *
 * Idempotency is a property of every step, not of the runner: each record is
 * matched on a stable natural key (product slug, variation SKU, term slug, page
 * path) and updated in place. Re-running is a no-op plus any data that actually
 * changed.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Seed;

use Noviq\Core\PostTypes;
use Noviq\Core\Profile;

defined( 'ABSPATH' ) || exit;

final class Seeder {

	private int $created = 0;
	private int $updated = 0;
	private int $skipped = 0;

	public function __construct(
		private readonly bool $dry_run = false,
		private readonly bool $skip_store = false,
	) {}

	public function run(): void {
		$this->log(
			sprintf(
				'%s — profile %s.',
				$this->dry_run ? 'Dry run — nothing will be written' : 'Seeding storefront',
				Profile::id()
			)
		);

		if ( ! $this->skip_store ) {
			( new Store( $this ) )->run();
		}

		$library = new Library( $this );
		$catalog = new Catalog( $this, $library );

		// Compounds first: products link to them, and chemistry is never
		// duplicated onto the product.
		if ( Profile::feature( 'compounds' ) ) {
			$library->compounds();
		}
		$catalog->run();
		$catalog->link();
		$library->content();

		// Menus last: every page they link to now exists.
		( new Menus( $this ) )->run();

		$this->flush_rewrites();
		$this->assert_empty_lot_registry();

		$this->log( '' );
		$this->success(
			sprintf(
				'Seed complete — %d created, %d updated, %d unchanged.',
				$this->created,
				$this->updated,
				$this->skipped
			)
		);
	}

	/**
	 * Rebuild rewrite rules once, at the end.
	 *
	 * The seeder changes three things that all feed the rewrite table: the
	 * permalink structure, WooCommerce's product and category bases, and the
	 * compound/comparison post types. Flushing only when WordPress' own
	 * permalink_structure changed left /products/{slug} and /collections/{slug}
	 * returning 404 whenever the Woo bases were what actually changed — which
	 * is exactly what happens on a fresh install where the structure was
	 * already set before seeding.
	 *
	 * Doing it unconditionally here is cheap: this is a CLI command, not a
	 * request path.
	 */
	private function flush_rewrites(): void {
		if ( $this->dry_run ) {
			return;
		}

		PostTypes::register();
		flush_rewrite_rules( false );

		$this->log( '' );
		$this->log( 'Rewrite rules rebuilt.' );
	}

	/**
	 * The lot registry must be empty.
	 *
	 * Seeding a lot means publishing a laboratory record — a lot number, a
	 * measured purity, a certificate. Inventing one is fabricating an analytical
	 * document, so the seeder creates none and asserts that it created none.
	 * If this ever fails, someone added fake COA data.
	 */
	private function assert_empty_lot_registry(): void {
		if ( ! Profile::feature( 'lots' ) ) {
			return;
		}

		$count = (int) wp_count_posts( PostTypes::LOT )->publish;

		if ( 0 === $count ) {
			$this->log( '' );
			$this->log( 'Lot registry: 0 rows (by design — /coa and /verify ship a real empty state).' );

			return;
		}

		$this->warn(
			sprintf(
				'Lot registry contains %d published lot(s). The seeder never creates lots — confirm every row came from the analysing laboratory.',
				$count
			)
		);
	}

	// ------------------------------------------------------------- helpers

	public function is_dry_run(): bool {
		return $this->dry_run;
	}

	/**
	 * Load a data file. Missing files are a hard error: a silent empty seed is
	 * worse than a loud failure.
	 *
	 * @return array<int|string, mixed>
	 */
	public function data( string $name ): array {
		$path = NOVIQ_CORE_PATH . 'data/' . Profile::id() . '/' . $name . '.json';

		if ( ! is_readable( $path ) ) {
			$this->error( sprintf( 'Missing data file %s. Run: node bin/build-seed-data.mjs', $path ) );
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true );

		if ( ! is_array( $decoded ) ) {
			$this->error( sprintf( 'Could not parse %s.', $path ) );
		}

		return $decoded;
	}

	public function created( string $what ): void {
		++$this->created;
		$this->log( sprintf( '  + %s', $what ) );
	}

	public function updated( string $what ): void {
		++$this->updated;
		$this->log( sprintf( '  ~ %s', $what ) );
	}

	public function skipped( string $what ): void {
		++$this->skipped;
	}

	public function log( string $message ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::log( $message );
		}

		// STDOUT is block-buffered when piped, which hides progress if a step
		// stalls. NOVIQ_SEED_TRACE=1 mirrors each line to unbuffered STDERR.
		if ( false !== getenv( 'NOVIQ_SEED_TRACE' ) && defined( 'STDERR' ) ) {
			@fwrite( STDERR, $message . "\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Fine-grained progress, written unbuffered to STDERR only when
	 * NOVIQ_SEED_TRACE is set. Free when it is not — a full-catalog migration
	 * is long enough that being able to see which record stalled matters.
	 */
	public function trace( string $message ): void {
		if ( false === getenv( 'NOVIQ_SEED_TRACE' ) || ! defined( 'STDERR' ) ) {
			return;
		}

		// Suppressed: when the caller pipes output into something that exits
		// early (`| head`), STDERR is a broken pipe and fwrite would fill the
		// debug log with notices about a diagnostic aid.
		@fwrite( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			STDERR,
			sprintf( "    · [%.1f MB] %s\n", memory_get_usage( true ) / 1048576, $message )
		);
	}

	public function section( string $message ): void {
		$this->log( '' );
		$this->log( $message );
	}

	public function warn( string $message ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::warning( $message );
		}
	}

	public function success( string $message ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::success( $message );
		}
	}

	public function error( string $message ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::error( $message );
		}

		throw new \RuntimeException( esc_html( $message ) );
	}
}
