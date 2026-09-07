<?php
/**
 * Inline SVG icons from theme/assets/img/icons/.
 *
 * Mirrors the Shopify icon-symbol pattern: one helper, file-backed glyphs,
 * explicit outer box size so geometry stays faithful to the Figma export.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

namespace Noviq\Child;

defined( 'ABSPATH' ) || exit;

/**
 * Print an icon. Returns empty string when the file is missing.
 *
 * @param string               $name Icon basename without .svg.
 * @param array<string, mixed> $args Optional size / class overrides.
 */
function icon( string $name, array $args = array() ): string {
	$name = preg_replace( '/[^a-z0-9_-]/', '', strtolower( $name ) ) ?? '';
	if ( '' === $name ) {
		return '';
	}

	$path = get_stylesheet_directory() . '/assets/img/icons/' . $name . '.svg';
	if ( ! is_readable( $path ) ) {
		return '';
	}

	$svg = file_get_contents( $path );
	if ( false === $svg || '' === $svg ) {
		return '';
	}

	$size  = isset( $args['size'] ) ? (int) $args['size'] : 24;
	$class = isset( $args['class'] ) ? (string) $args['class'] : '';
	$label = isset( $args['label'] ) ? (string) $args['label'] : '';

	// Strip XML declaration / doctype if present.
	$svg = preg_replace( '/<\?xml[^>]*\?>/', '', $svg ) ?? $svg;
	$svg = preg_replace( '/<!DOCTYPE[^>]*>/', '', $svg ) ?? $svg;

	$attrs = sprintf(
		' class="nq-icon%s" width="%d" height="%d" focusable="false"',
		'' !== $class ? ' ' . esc_attr( $class ) : '',
		$size,
		$size
	);

	if ( '' === $label ) {
		$attrs .= ' aria-hidden="true"';
	} else {
		$attrs .= ' role="img" aria-label="' . esc_attr( $label ) . '"';
	}

	if ( preg_match( '/<svg\b([^>]*)>/i', $svg, $m ) ) {
		$existing = $m[1];
		// Drop hardcoded width/height so our attrs win; keep viewBox.
		$existing = preg_replace( '/\s(width|height)="[^"]*"/i', '', $existing ) ?? $existing;
		$svg      = preg_replace( '/<svg\b[^>]*>/i', '<svg' . $existing . $attrs . '>', $svg, 1 ) ?? $svg;
	}

	return $svg;
}

/**
 * Echo an icon.
 *
 * @param array<string, mixed> $args Optional size / class overrides.
 */
function the_icon( string $name, array $args = array() ): void {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from theme files.
	echo icon( $name, $args );
}
