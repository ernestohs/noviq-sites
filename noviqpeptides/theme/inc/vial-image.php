<?php
/**
 * Generated product imagery.
 *
 * A drawn vial seeded from the product slug, so a product looks identical
 * between renders and two products rarely share a cap tone. Ported from the
 * reference project's product-image.tsx.
 *
 * This is placeholder art, not decoration for its own sake: it replaces
 * WooCommerce's grey camera icon, which made every product look broken.
 *
 * TODO: replace with real photography. Set a product's featured image and it
 * wins automatically — nothing else needs to change.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

namespace Noviq\Child\Vial;

defined( 'ABSPATH' ) || exit;

/**
 * Stable hash of a product slug. Same algorithm as the reference so the two
 * builds pick the same cap tone for the same product.
 */
function seed( string $handle ): int {
	$hash = 0;
	$len  = strlen( $handle );

	for ( $i = 0; $i < $len; $i++ ) {
		// >>> 0 in JS: keep it inside 32 unsigned bits.
		$hash = ( $hash * 31 + ord( $handle[ $i ] ) ) & 0xFFFFFFFF;
	}

	return $hash;
}

/**
 * Cap tones stay mutually distinguishable rather than five shades of one blue.
 *
 * @return string[]
 */
function cap_tones(): array {
	return array(
		'var(--nq-blue-600)',
		'var(--nq-blue-500)',
		'var(--nq-navy-cap)',
		'var(--nq-gray-500)',
		'var(--nq-gray-400)',
	);
}

/**
 * Which silhouette a product gets, from its category.
 */
function shape_for( \WC_Product $product ): string {
	$slugs = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'slugs' ) );
	$slugs = is_array( $slugs ) ? $slugs : array();

	if ( array_intersect( $slugs, array( 'supplies', 'apparel' ) ) ) {
		return 'box';
	}
	if ( in_array( 'strips', $slugs, true ) ) {
		return 'strip';
	}
	if ( in_array( 'sprays', $slugs, true ) ) {
		return 'spray';
	}

	return 'vial';
}

/**
 * The SKU shown in the corner of the image panel.
 */
function sku_for( \WC_Product $product ): string {
	$sku = $product->get_sku();

	if ( '' === $sku && $product->is_type( 'variable' ) ) {
		$children = $product->get_children();
		if ( isset( $children[0] ) ) {
			$first = wc_get_product( $children[0] );
			$sku   = $first instanceof \WC_Product ? (string) $first->get_sku() : '';
		}
	}

	return (string) $sku;
}

/**
 * Render the image panel for a product.
 *
 * A real featured image always wins; this only fills the gap while the client
 * has no photography.
 *
 * @param string $size Registered image size used for a real featured image.
 */
function render( \WC_Product $product, string $size = 'woocommerce_thumbnail' ): string {
	if ( $product->get_image_id() ) {
		return sprintf(
			'<div class="nq-media nq-media--photo">%s</div>',
			$product->get_image( $size )
		);
	}

	$handle = $product->get_slug();
	$hash   = seed( $handle );
	$tones  = cap_tones();
	$cap    = $tones[ $hash % count( $tones ) ];
	$shape  = shape_for( $product );
	$fill   = 34 + ( $hash % 5 ) * 6;
	$uid    = 'nqv-' . substr( md5( $handle ), 0, 8 );

	$body   = 'var(--nq-white)';
	$stroke = 'var(--nq-gray-400)';
	$band   = 'var(--nq-paper, #ECEBED)';
	$rule   = 'var(--nq-gray-500)';

	ob_start();
	?>
	<div class="nq-media" aria-hidden="false">
		<svg class="nq-media__grid" aria-hidden="true" focusable="false">
			<defs>
				<pattern id="<?php echo esc_attr( $uid ); ?>" width="16" height="16" patternUnits="userSpaceOnUse">
					<path d="M16 0H0V16" fill="none" stroke="currentColor" stroke-width="0.5" />
				</pattern>
			</defs>
			<rect width="100%" height="100%" fill="url(#<?php echo esc_attr( $uid ); ?>)" />
		</svg>

		<svg class="nq-media__art" viewBox="0 0 120 160" role="img"
			aria-label="<?php echo esc_attr( sprintf( '%s — illustration', $product->get_name() ) ); ?>">
			<?php if ( 'strip' === $shape ) : ?>
				<rect x="22" y="44" width="76" height="72" rx="3" style="fill:<?php echo esc_attr( $body ); ?>;stroke:<?php echo esc_attr( $stroke ); ?>" />
				<rect x="32" y="56" width="56" height="10" fill="<?php echo esc_attr( $cap ); ?>" opacity="0.85" />
				<rect x="32" y="74" width="42" height="4" style="fill:<?php echo esc_attr( $stroke ); ?>" />
				<rect x="32" y="84" width="52" height="4" style="fill:<?php echo esc_attr( $stroke ); ?>" />
				<rect x="32" y="94" width="34" height="4" style="fill:<?php echo esc_attr( $stroke ); ?>" />
			<?php elseif ( 'box' === $shape ) : ?>
				<rect x="26" y="38" width="68" height="84" rx="2" style="fill:<?php echo esc_attr( $body ); ?>;stroke:<?php echo esc_attr( $stroke ); ?>" />
				<rect x="26" y="38" width="68" height="16" fill="<?php echo esc_attr( $cap ); ?>" opacity="0.7" />
				<rect x="38" y="70" width="44" height="3" style="fill:<?php echo esc_attr( $stroke ); ?>" />
				<rect x="38" y="80" width="32" height="3" style="fill:<?php echo esc_attr( $stroke ); ?>" />
			<?php else : ?>
				<path d="M38 52h44v76a8 8 0 0 1-8 8H46a8 8 0 0 1-8-8V52Z"
					style="fill:<?php echo esc_attr( $body ); ?>;stroke:<?php echo esc_attr( $stroke ); ?>" stroke-width="1.5" />
				<path d="M39 <?php echo esc_attr( (string) ( 136 - $fill ) ); ?>h42v<?php echo esc_attr( (string) ( $fill - 8 ) ); ?>a8 8 0 0 1-8 8H47a8 8 0 0 1-8-8Z"
					fill="<?php echo esc_attr( $cap ); ?>" opacity="0.16" />
				<line x1="39" y1="<?php echo esc_attr( (string) ( 136 - $fill ) ); ?>" x2="81" y2="<?php echo esc_attr( (string) ( 136 - $fill ) ); ?>"
					stroke="<?php echo esc_attr( $cap ); ?>" stroke-width="1" opacity="0.6" />
				<rect x="48" y="36" width="24" height="16" style="fill:<?php echo esc_attr( $body ); ?>;stroke:<?php echo esc_attr( $stroke ); ?>" />
				<rect x="44" y="26" width="32" height="12" rx="1.5" fill="<?php echo esc_attr( $cap ); ?>" />
				<?php if ( 'spray' === $shape ) : ?>
					<rect x="54" y="14" width="12" height="12" rx="1" fill="<?php echo esc_attr( $cap ); ?>" opacity="0.8" />
				<?php else : ?>
					<rect x="52" y="20" width="16" height="6" rx="1" fill="<?php echo esc_attr( $cap ); ?>" opacity="0.6" />
				<?php endif; ?>
				<rect x="38" y="82" width="44" height="30" style="fill:<?php echo esc_attr( $band ); ?>;stroke:<?php echo esc_attr( $stroke ); ?>" />
				<rect x="43" y="88" width="26" height="3" fill="<?php echo esc_attr( $cap ); ?>" opacity="0.8" />
				<rect x="43" y="95" width="34" height="2" style="fill:<?php echo esc_attr( $rule ); ?>" />
				<rect x="43" y="100" width="20" height="2" style="fill:<?php echo esc_attr( $rule ); ?>" />
				<rect x="43" y="105" width="30" height="2" style="fill:<?php echo esc_attr( $rule ); ?>" />
			<?php endif; ?>
		</svg>

		<?php $sku = sku_for( $product ); ?>
		<?php if ( '' !== $sku ) : ?>
			<span class="nq-media__sku"><?php echo esc_html( $sku ); ?></span>
		<?php endif; ?>
	</div>
	<?php

	return (string) ob_get_clean();
}
