<?php
/**
 * Custom rewrites for hub surfaces.
 *
 * @package NoviqPeptides
 */

declare(strict_types=1);

namespace NoviqPeptides;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rewrites {

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_filter( 'template_include', array( self::class, 'template_include' ) );
	}

	public static function register(): void {
		add_rewrite_rule( '^research-hub/?$', 'index.php?noviq_surface=research-hub', 'top' );
		add_rewrite_rule( '^coa/?$', 'index.php?noviq_surface=coa', 'top' );
		add_rewrite_rule( '^verify/?$', 'index.php?noviq_surface=verify', 'top' );
		add_rewrite_rule( '^quality-standard/?$', 'index.php?noviq_surface=quality-standard', 'top' );
	}

	/**
	 * @param list<string> $vars Vars.
	 * @return list<string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'noviq_surface';
		return $vars;
	}

	public static function template_include( string $template ): string {
		$surface = get_query_var( 'noviq_surface' );
		if ( ! is_string( $surface ) || '' === $surface ) {
			return $template;
		}
		$map = array(
			'research-hub'     => 'page-research-hub.php',
			'coa'              => 'page-coa.php',
			'verify'           => 'page-verify.php',
			'quality-standard' => 'page-quality-standard.php',
		);
		if ( ! isset( $map[ $surface ] ) ) {
			return $template;
		}
		$theme_file = get_stylesheet_directory() . '/' . $map[ $surface ];
		return file_exists( $theme_file ) ? $theme_file : $template;
	}
}
