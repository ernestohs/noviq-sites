<?php
/**
 * Product-detail components.
 *
 * The volume table is priced from the same engine that discounts the cart
 * (`Commerce\VolumeBreaks`), so a displayed per-unit price cannot drift from
 * what the customer is actually charged. Every claim comes from `Claims`, and a
 * claim the client cannot substantiate renders nothing at all.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

namespace Noviq\Child;

defined( 'ABSPATH' ) || exit;

/**
 * The short check-pill list under the product summary.
 *
 * Only substantiated claims appear. `Claims` returning null drops the pill.
 */
function highlight_pills(): string {
	if ( ! class_exists( \Noviq\Core\Claims::class ) ) {
		return '';
	}

	$pills = array();

	if ( \Noviq\Core\Claims::has( 'purity_spec' ) ) {
		$pills[] = sprintf( '≥%s%% purity spec', \Noviq\Core\Claims::get( 'purity_spec' ) );
	}
	if ( \Noviq\Core\Claims::has( 'endotoxin_spec' ) ) {
		$pills[] = sprintf( '≤%s EU/mg endotoxin', \Noviq\Core\Claims::get( 'endotoxin_spec' ) );
	}
	if ( \Noviq\Core\Claims::has( 'sterility_incubation_days' ) ) {
		$pills[] = sprintf( 'USP <71> — %s-day incubation', \Noviq\Core\Claims::get( 'sterility_incubation_days' ) );
	}

	$pills[] = __( 'Lot-matched COA', 'noviq-child' );

	$items = '';
	foreach ( $pills as $pill ) {
		$items .= sprintf( '<li>%s</li>', esc_html( $pill ) );
	}

	return sprintf( '<ul class="nq-pills">%s</ul>', $items );
}

/**
 * One sentence for the documentation guarantee, assembled from substantiated
 * claims only.
 */
function purity_sentence(): string {
	if ( ! class_exists( \Noviq\Core\Claims::class ) ) {
		return '';
	}

	$parts = array();

	if ( \Noviq\Core\Claims::has( 'purity_spec' ) ) {
		$parts[] = sprintf(
			/* translators: %s: purity percentage. */
			__( 'released against a ≥%s%% chromatographic purity specification', 'noviq-child' ),
			\Noviq\Core\Claims::get( 'purity_spec' )
		);
	}

	$parts[] = __( 'identity confirmed by mass spectrometry', 'noviq-child' );
	$parts[] = __( 'with a Certificate of Analysis carrying the vial\'s own lot number', 'noviq-child' );

	return ucfirst( implode( ', ', $parts ) ) . '.';
}

/**
 * The four-item assurance list.
 */
function trust_list(): string {
	if ( ! class_exists( \Noviq\Core\Claims::class ) ) {
		return '';
	}

	$items = array( __( 'Certificate of Analysis published for every released lot', 'noviq-child' ) );

	if ( \Noviq\Core\Claims::has( 'dispatch_cutoff' ) ) {
		$items[] = sprintf(
			/* translators: %s: dispatch cutoff time. */
			__( 'Same-day dispatch on orders before %s', 'noviq-child' ),
			\Noviq\Core\Claims::get( 'dispatch_cutoff' )
		);
	}

	if ( \Noviq\Core\Claims::has( 'free_shipping_threshold' ) ) {
		$items[] = sprintf(
			/* translators: %s: free shipping threshold in dollars. */
			__( 'Free shipping over $%s', 'noviq-child' ),
			\Noviq\Core\Claims::get( 'free_shipping_threshold' )
		);
	}

	$items[] = __( 'Unmarked packaging — no product names outside', 'noviq-child' );

	$html = '';
	foreach ( $items as $item ) {
		$html .= sprintf(
			'<li><svg viewBox="0 0 24 24" width="21" height="21" fill="none" aria-hidden="true"><path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>%s</li>',
			esc_html( $item )
		);
	}

	return sprintf( '<ul class="nq-trust">%s</ul>', $html );
}

/**
 * Volume-pricing table.
 *
 * Shows the actual per-unit price at each break, computed with
 * VolumeBreaks::unit_price_cents() — the same function the cart filter uses.
 * Products with no tier table render nothing.
 */
function volume_box( \WC_Product $product ): string {
	if ( ! class_exists( \Noviq\Core\Commerce\VolumeBreaks::class ) ) {
		return '';
	}

	$tiers = \Noviq\Core\Commerce\VolumeBreaks::tiers_for_product( $product->get_id() );
	if ( array() === $tiers ) {
		return '';
	}

	// Price the ladder off the cheapest purchasable unit, which is what the
	// headline "from" price refers to.
	$base = (float) $product->get_price();
	if ( $base <= 0 ) {
		return '';
	}

	$base_cents = (int) round( $base * 100 );

	$rows = sprintf(
		'<div class="nq-vol__row nq-vol__row--head" role="row"><span role="columnheader">%s</span><span role="columnheader">%s</span><span role="columnheader">%s</span></div>',
		esc_html__( 'Quantity', 'noviq-child' ),
		esc_html__( 'Per unit', 'noviq-child' ),
		esc_html__( 'You save', 'noviq-child' )
	);

	$count = count( $tiers );
	foreach ( $tiers as $index => $tier ) {
		$unit = \Noviq\Core\Commerce\VolumeBreaks::unit_price_cents( $base_cents, $tiers, (int) $tier['min_qty'] );

		$label = ( $index + 1 < $count )
			? sprintf( '%d–%d units', (int) $tier['min_qty'], (int) $tiers[ $index + 1 ]['min_qty'] - 1 )
			: sprintf( '%d+ units', (int) $tier['min_qty'] );

		$rows .= sprintf(
			'<div class="nq-vol__row" role="row"><span role="cell">%1$s</span><span role="cell" class="noviq-num">%2$s</span><span role="cell" class="nq-vol__off noviq-num">−%3$d%%</span></div>',
			esc_html( $label ),
			wp_kses_post( wc_price( $unit / 100 ) ),
			(int) round( $tier['discount'] * 100 )
		);
	}

	return sprintf(
		'<div class="nq-vol"><div class="nq-vol__head"><span class="nq-vol__title">%1$s</span><span class="nq-vol__note">%2$s</span></div><div class="nq-vol__grid" role="table" aria-label="%3$s">%4$s</div></div>',
		esc_html__( 'Volume pricing', 'noviq-child' ),
		esc_html__( 'Applied automatically in the cart', 'noviq-child' ),
		esc_attr__( 'Volume pricing tiers', 'noviq-child' ),
		$rows
	);
}

/**
 * Description on the left, analytical specification on the right.
 *
 * Replaces the WooCommerce tab strip — the reference has no tabs, and a spec
 * table hidden behind a tab is a spec table nobody reads.
 */
function product_detail_split( \WC_Product $product ): string {
	$description = apply_filters( 'the_content', $product->get_description() );

	$spec = '';
	if ( class_exists( \Noviq\Core\Meta::class ) && class_exists( \Noviq\Core\Content\Compound::class ) ) {
		foreach ( \Noviq\Core\Meta::compound_ids( $product->get_id() ) as $compound_id ) {
			$compound = \Noviq\Core\Content\Compound::from_id( $compound_id );
			if ( null === $compound ) {
				continue;
			}

			$spec .= sprintf(
				'<div class="nq-spec"><p class="nq-kicker">%1$s</p>%2$s<p class="nq-spec__link"><a href="%3$s">%4$s</a></p></div>',
				esc_html( $compound->name() ),
				$compound->render_spec_table(),
				esc_url( $compound->permalink() ),
				esc_html__( 'Read the full monograph →', 'noviq-child' )
			);
		}
	}

	if ( '' === trim( wp_strip_all_tags( $description ) ) && '' === $spec ) {
		return '';
	}

	return sprintf(
		'<section class="nq-pdp-detail"><div class="nq-pdp-detail__prose prose-noviq">%1$s</div><div class="nq-pdp-detail__spec">%2$s</div></section>',
		$description,
		$spec
	);
}
