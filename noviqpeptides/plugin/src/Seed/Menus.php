<?php
/**
 * Navigation menus.
 *
 * Labels and groupings come from data/navigation.json, generated verbatim from
 * the reference project's site.ts. Items are stored as custom links: the paths
 * are already resolved by the migration script, and a custom link keeps a menu
 * entry from silently vanishing if the object it points at is not in the
 * current seed.
 *
 * @package Noviq\Core\Seed
 */

declare(strict_types=1);

namespace Noviq\Core\Seed;

defined( 'ABSPATH' ) || exit;

final class Menus {

	public function __construct( private readonly Seeder $seeder ) {}

	public function run(): void {
		$this->seeder->section( 'Navigation menus' );

		$navigation = $this->seeder->data( 'navigation' );

		$primary = $this->upsert_menu( 'Primary', $this->flat_items( $navigation['primary'] ) );
		$footer  = $this->upsert_menu( 'Footer', $this->grouped_items( $navigation['footer'] ) );

		if ( $this->seeder->is_dry_run() ) {
			return;
		}

		$this->assign( $primary, $footer );
	}

	/**
	 * Assign the menus to whichever locations the active theme actually
	 * registers.
	 *
	 * Parent themes disagree on location handles — Blocksy's header slot is
	 * `menu_1`, not the conventional `primary` — so every plausible handle is
	 * attempted and only registered ones are written. This is what keeps the
	 * navigation from silently vanishing when the parent theme is swapped.
	 */
	private function assign( int $primary_id, int $footer_id ): void {
		$registered = get_registered_nav_menus();

		$candidates = array(
			$primary_id => array( 'primary', 'menu_1', 'menu_mobile', 'main', 'header' ),
			$footer_id  => array( 'footer', 'footer_menu', 'menu_2', 'secondary' ),
		);

		$locations = get_theme_mod( 'nav_menu_locations' );
		$locations = is_array( $locations ) ? $locations : array();
		$assigned  = array();

		foreach ( $candidates as $menu_id => $handles ) {
			if ( 0 === $menu_id ) {
				continue;
			}

			foreach ( $handles as $handle ) {
				if ( isset( $registered[ $handle ] ) ) {
					$locations[ $handle ] = $menu_id;
					$assigned[]           = $handle;
				}
			}
		}

		set_theme_mod( 'nav_menu_locations', $locations );

		$this->seeder->log(
			sprintf(
				'  menu locations: %s',
				array() === $assigned ? 'none registered by the active theme' : implode( ', ', $assigned )
			)
		);
	}

	/**
	 * @param array<int, array{label: string, path: string}> $links Nav links.
	 * @return array<int, array{label: string, path: ?string, children: array}>
	 */
	private function flat_items( array $links ): array {
		return array_map(
			static fn( array $link ): array => array(
				'label'    => (string) $link['label'],
				'path'     => (string) $link['path'],
				'children' => array(),
			),
			$links
		);
	}

	/**
	 * Footer groups become top-level headings with their links nested beneath.
	 *
	 * @param array<int, array{heading: string, links: array}> $groups Footer groups.
	 * @return array<int, array{label: string, path: ?string, children: array}>
	 */
	private function grouped_items( array $groups ): array {
		return array_map(
			fn( array $group ): array => array(
				'label'    => (string) $group['heading'],
				'path'     => null,
				'children' => $this->flat_items( $group['links'] ),
			),
			$groups
		);
	}

	/**
	 * Create or rebuild a menu.
	 *
	 * Items are rebuilt from scratch on every run rather than diffed: the menu
	 * is seeder-owned, and rebuilding is what makes a re-run converge instead of
	 * appending a second copy of every link.
	 *
	 * @param array<int, array{label: string, path: ?string, children: array}> $items Menu items.
	 */
	private function upsert_menu( string $name, array $items ): int {
		$existing = wp_get_nav_menu_object( $name );

		if ( $this->seeder->is_dry_run() ) {
			$existing ? $this->seeder->skipped( 'menu ' . $name ) : $this->seeder->created( 'menu ' . $name );

			return 0;
		}

		if ( ! $existing ) {
			$menu_id = wp_create_nav_menu( $name );

			if ( is_wp_error( $menu_id ) ) {
				$this->seeder->warn( sprintf( 'Menu %s: %s', $name, $menu_id->get_error_message() ) );

				return 0;
			}

			$this->seeder->created( 'menu ' . $name );
		} else {
			$menu_id = (int) $existing->term_id;

			foreach ( wp_get_nav_menu_items( $menu_id ) ?: array() as $item ) {
				wp_delete_post( (int) $item->ID, true );
			}

			$this->seeder->updated( 'menu ' . $name );
		}

		$menu_id = (int) $menu_id;
		$order   = 0;

		foreach ( $items as $item ) {
			$parent_id = $this->add_item( $menu_id, $item, 0, ++$order );

			foreach ( $item['children'] as $child ) {
				$this->add_item( $menu_id, $child, $parent_id, ++$order );
			}
		}

		return $menu_id;
	}

	/**
	 * @param array{label: string, path: ?string, children?: array} $item Menu item.
	 */
	private function add_item( int $menu_id, array $item, int $parent_id, int $order ): int {
		$path = $item['path'] ?? null;

		$id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => $item['label'],
				'menu-item-url'       => null === $path ? '#' : home_url( $path ),
				'menu-item-type'      => 'custom',
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $parent_id,
				'menu-item-position'  => $order,
				// A group heading is a label, not a destination.
				'menu-item-classes'   => null === $path ? 'noviq-menu-heading' : '',
			)
		);

		return is_wp_error( $id ) ? 0 : (int) $id;
	}
}
