<?php
/**
 * Product card.
 *
 * Anatomy matches the Figma landing card: media with optional badges, title,
 * kicker, dose chips from the variation attribute, price (sale-aware), Details
 * + Add to cart actions. No wishlist. Purity / View COA only when a lot exists.
 *
 * @package Noviq\Child
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
	return;
}

$nq_kicker = class_exists( \Noviq\Core\Meta::class )
	? (string) get_post_meta( $product->get_id(), \Noviq\Core\Meta::PRODUCT_KICKER, true )
	: '';

$nq_is_range = $product->is_type( 'variable' );
$nq_doses    = array();

if ( $nq_is_range ) {
	$attrs = $product->get_variation_attributes();
	foreach ( $attrs as $taxonomy => $options ) {
		if ( ! is_array( $options ) ) {
			continue;
		}
		foreach ( $options as $option ) {
			$label = $option;
			if ( taxonomy_exists( $taxonomy ) ) {
				$term = get_term_by( 'slug', $option, $taxonomy );
				if ( $term instanceof WP_Term ) {
					$label = $term->name;
				}
			}
			$nq_doses[] = $label;
		}
		break;
	}
}

$nq_regular = (float) $product->get_regular_price();
$nq_price   = (float) $product->get_price();
$nq_on_sale = $product->is_on_sale() && $nq_regular > $nq_price && $nq_price > 0;
$nq_discount = $nq_on_sale ? (int) round( ( 1 - ( $nq_price / $nq_regular ) ) * 100 ) : 0;

$nq_best_seller = (int) $product->get_total_sales() >= 5;

$nq_lot_purity = '';
$nq_coa_url    = '';
if ( class_exists( \Noviq\Core\PostTypes::class ) ) {
	$nq_lots = get_posts(
		array(
			'post_type'      => \Noviq\Core\PostTypes::LOT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => 'noviq_lot_product_id',
					'value' => $product->get_id(),
				),
			),
		)
	);
	if ( array() !== $nq_lots ) {
		$lot           = $nq_lots[0];
		$purity        = get_post_meta( $lot->ID, 'noviq_lot_purity', true );
		$nq_lot_purity = is_numeric( $purity ) ? (string) $purity : '';
		$coa_id        = (int) get_post_meta( $lot->ID, 'noviq_lot_coa_id', true );
		$nq_coa_url    = $coa_id > 0 ? (string) wp_get_attachment_url( $coa_id ) : home_url( '/coa/' );
	}
}
?>
<li <?php wc_product_class( 'nq-card', $product ); ?>>
	<div class="nq-card__media">
		<?php if ( $nq_on_sale && $nq_discount > 0 ) : ?>
			<span class="nq-card__badge nq-card__badge--sale">-<?php echo esc_html( (string) $nq_discount ); ?>%</span>
		<?php endif; ?>

		<?php if ( $nq_best_seller ) : ?>
			<span class="nq-card__badge nq-card__badge--best">
				<?php esc_html_e( 'BEST SELLER', 'noviq-child' ); ?>
				<?php \Noviq\Child\the_icon( 'star-filled', array( 'size' => 10 ) ); ?>
			</span>
		<?php endif; ?>

		<?php if ( '' !== $nq_lot_purity ) : ?>
			<a class="nq-card__coa" href="<?php echo esc_url( $nq_coa_url ); ?>">
				<?php \Noviq\Child\the_icon( 'verified-check', array( 'size' => 10 ) ); ?>
				<span class="noviq-num"><?php echo esc_html( $nq_lot_purity ); ?>%</span>
				<span class="nq-card__coa__label"><?php esc_html_e( 'View COA', 'noviq-child' ); ?></span>
				<?php \Noviq\Child\the_icon( 'chevron-right', array( 'size' => 10 ) ); ?>
			</a>
		<?php endif; ?>

		<a class="nq-card__media-link" href="<?php echo esc_url( $product->get_permalink() ); ?>">
			<?php
			echo \Noviq\Child\Vial\render( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</a>
	</div>

	<div class="nq-card__body">
		<a class="nq-card__link" href="<?php echo esc_url( $product->get_permalink() ); ?>">
			<h3 class="nq-card__title"><?php echo esc_html( $product->get_name() ); ?></h3>
		</a>

		<?php if ( '' !== $nq_kicker ) : ?>
			<p class="nq-card__sub"><?php echo esc_html( $nq_kicker ); ?></p>
		<?php endif; ?>

		<?php if ( array() !== $nq_doses ) : ?>
			<ul class="nq-card__doses" aria-label="<?php esc_attr_e( 'Available sizes', 'noviq-child' ); ?>">
				<?php foreach ( $nq_doses as $nq_dose ) : ?>
					<li class="noviq-num"><?php echo esc_html( $nq_dose ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<div class="nq-card__foot">
			<p class="nq-card__price noviq-num">
				<?php if ( $nq_on_sale ) : ?>
					<del><?php echo wp_kses_post( wc_price( $nq_regular ) ); ?></del>
					<ins><?php echo wp_kses_post( wc_price( $nq_price ) ); ?></ins>
				<?php elseif ( $nq_is_range ) : ?>
					<small><?php esc_html_e( 'from', 'noviq-child' ); ?></small>
					<?php echo wp_kses_post( wc_price( $nq_price ) ); ?>
				<?php else : ?>
					<?php echo wp_kses_post( wc_price( $nq_price ) ); ?>
				<?php endif; ?>
			</p>
		</div>

		<div class="nq-card__actions">
			<a class="nq-card__details" href="<?php echo esc_url( $product->get_permalink() ); ?>">
				<?php esc_html_e( 'Details', 'noviq-child' ); ?>
			</a>

			<?php if ( $nq_is_range ) : ?>
				<a class="nq-card__atc" href="<?php echo esc_url( $product->get_permalink() ); ?>">
					<span><?php esc_html_e( 'Select size', 'noviq-child' ); ?></span>
					<?php \Noviq\Child\the_icon( 'plus', array( 'size' => 18 ) ); ?>
				</a>
			<?php else : ?>
				<a class="nq-card__atc ajax_add_to_cart add_to_cart_button"
					href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
					data-product_id="<?php echo esc_attr( (string) $product->get_id() ); ?>"
					data-product_sku="<?php echo esc_attr( (string) $product->get_sku() ); ?>"
					data-quantity="1"
					rel="nofollow">
					<span><?php esc_html_e( 'Add to cart', 'noviq-child' ); ?></span>
					<?php \Noviq\Child\the_icon( 'plus', array( 'size' => 18 ) ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</li>
