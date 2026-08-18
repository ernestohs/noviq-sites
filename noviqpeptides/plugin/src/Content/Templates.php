<?php
/**
 * Template routing for plugin-owned post types.
 *
 * The plugin ships fallback templates so /learn/{slug} and /compare/{slug} work
 * with any parent theme. A theme may override either by dropping
 * single-noviq_compound.php or single-noviq_comparison.php into its own root —
 * locate_template() is checked first, so a parent-theme swap does not strand
 * these routes.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Content;

use Noviq\Core\PostTypes;

defined( 'ABSPATH' ) || exit;

final class Templates {

	public static function init(): void {
		add_filter( 'template_include', array( self::class, 'route' ) );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
	}

	public static function route( string $template ): string {
		$candidates = array();

		if ( is_singular( PostTypes::COMPOUND ) ) {
			$candidates = array( 'single-' . PostTypes::COMPOUND . '.php' );
		} elseif ( is_post_type_archive( PostTypes::COMPOUND ) ) {
			$candidates = array( 'archive-' . PostTypes::COMPOUND . '.php' );
		} elseif ( is_singular( PostTypes::COMPARISON ) ) {
			$candidates = array( 'single-' . PostTypes::COMPARISON . '.php' );
		} elseif ( is_post_type_archive( PostTypes::COMPARISON ) ) {
			$candidates = array( 'archive-' . PostTypes::COMPARISON . '.php' );
		}

		if ( array() === $candidates ) {
			return $template;
		}

		$from_theme = locate_template( $candidates );
		if ( '' !== $from_theme ) {
			return $from_theme;
		}

		$fallback = NOVIQ_CORE_PATH . 'templates/' . $candidates[0];

		return is_readable( $fallback ) ? $fallback : $template;
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( array $classes ): array {
		if ( is_singular( PostTypes::COMPOUND ) || is_post_type_archive( PostTypes::COMPOUND ) ) {
			$classes[] = 'noviq-monograph';
		}
		if ( is_singular( PostTypes::COMPARISON ) || is_post_type_archive( PostTypes::COMPARISON ) ) {
			$classes[] = 'noviq-comparison';
		}

		return $classes;
	}
}
