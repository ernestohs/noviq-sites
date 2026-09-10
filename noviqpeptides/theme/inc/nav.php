<?php
/**
 * Primary menubar fallback from plugin navigation.json.
 *
 * Used when the seeded WP menu has not been rebuilt yet (still flat). Keeps the
 * Figma header groups visible without mutating the database.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

namespace Noviq\Child;

defined( 'ABSPATH' ) || exit;

/**
 * @return array<int, array{label: string, path: string, children: array<int, array{label: string, path: string}>}>
 */
function primary_nav_from_data(): array {
	$path = '';
	if ( defined( 'NOVIQ_CORE_PATH' ) ) {
		$path = NOVIQ_CORE_PATH . 'data/noviq/navigation.json';
	}

	if ( '' === $path || ! is_readable( $path ) ) {
		return array();
	}

	$raw = file_get_contents( $path );
	if ( false === $raw ) {
		return array();
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) || ! isset( $data['primary'] ) || ! is_array( $data['primary'] ) ) {
		return array();
	}

	$out = array();
	foreach ( $data['primary'] as $item ) {
		if ( ! is_array( $item ) || empty( $item['label'] ) || empty( $item['path'] ) ) {
			continue;
		}
		$children = array();
		foreach ( $item['children'] ?? array() as $child ) {
			if ( ! is_array( $child ) || empty( $child['label'] ) || empty( $child['path'] ) ) {
				continue;
			}
			$children[] = array(
				'label' => (string) $child['label'],
				'path'  => (string) $child['path'],
			);
		}
		$out[] = array(
			'label'    => (string) $item['label'],
			'path'     => (string) $item['path'],
			'children' => $children,
		);
	}

	return $out;
}

/**
 * True when the assigned primary menu already has nested items.
 */
function primary_nav_has_children(): bool {
	$locations = get_nav_menu_locations();
	if ( empty( $locations['primary'] ) ) {
		return false;
	}

	$items = wp_get_nav_menu_items( (int) $locations['primary'] );
	if ( ! is_array( $items ) ) {
		return false;
	}

	foreach ( $items as $item ) {
		if ( (int) $item->menu_item_parent > 0 ) {
			return true;
		}
	}

	return false;
}

/**
 * Render the primary menubar list.
 */
function render_primary_menubar( string $menu_class = 'nq-nav__list' ): void {
	if ( primary_nav_has_children() ) {
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => $menu_class,
				'depth'          => 2,
				'fallback_cb'    => '__return_empty_string',
			)
		);

		return;
	}

	$items = primary_nav_from_data();
	if ( array() === $items ) {
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => $menu_class,
				'depth'          => 2,
				'fallback_cb'    => '__return_empty_string',
			)
		);

		return;
	}

	printf( '<ul class="%s">', esc_attr( $menu_class ) );
	foreach ( $items as $item ) {
		$has_kids = array() !== $item['children'];
		printf(
			'<li class="menu-item%s">',
			$has_kids ? ' menu-item-has-children' : ''
		);
		printf(
			'<a href="%s"%s>%s</a>',
			esc_url( home_url( $item['path'] ) ),
			$has_kids ? ' aria-haspopup="true" aria-expanded="false"' : '',
			esc_html( $item['label'] )
		);
		if ( $has_kids ) {
			echo '<ul class="sub-menu">';
			foreach ( $item['children'] as $child ) {
				printf(
					'<li class="menu-item"><a href="%s">%s</a></li>',
					esc_url( home_url( $child['path'] ) ),
					esc_html( $child['label'] )
				);
			}
			echo '</ul>';
		}
		echo '</li>';
	}
	echo '</ul>';
}
