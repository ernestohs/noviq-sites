<?php
/**
 * Product card.
 *
 * Anatomy matches the Figma landing card: media with optional badges, favorite
 * toggle, title, kicker, dose chips from variation attributes (or a single
 * amount when known), price (sale-aware), Details + Add to cart actions.
 * Purity / View COA only when a lot exists.
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

/**
 * Dose / presentation options for the card chips.
 *
 * @var array<int, array{label: string, variation_id: int, attr_key: string, attr_value: string, price: float, regular: float, on_sale: bool}>
 */
$nq_doses = array();

if ( $nq_is_range ) {
	$attrs = $product->get_variation_attributes();
	foreach ( $attrs as $taxonomy => $options ) {
		if ( ! is_array( $options ) ) {
			continue;
		}

		$attr_key  = 'attribute_' . sanitize_title( $taxonomy );
		$available = $product->get_available_variations();
		foreach ( $options as $option ) {
			$label = $option;
			if ( taxonomy_exists( $taxonomy ) ) {
				$term = get_term_by( 'slug', $option, $taxonomy );
				if ( $term instanceof WP_Term ) {
					$label = $term->name;
				}
			}

			$variation_id = 0;
			$attr_value   = (string) $option;
			$price        = 0.0;
			$regular      = 0.0;
			$on_sale      = false;

			foreach ( $available as $row ) {
				$attrs_row = $row['attributes'] ?? array();
				$match     = isset( $attrs_row[ $attr_key ] ) && (string) $attrs_row[ $attr_key ] === (string) $option;
				if ( ! $match && isset( $attrs_row[ $attr_key ] ) ) {
					// Some stores store the term name instead of the slug.
					$match = (string) $attrs_row[ $attr_key ] === (string) $label;
				}
				if ( ! $match ) {
					continue;
				}
				$variation_id = (int) ( $row['variation_id'] ?? 0 );
				$attr_value   = (string) $attrs_row[ $attr_key ];
				$price        = isset( $row['display_price'] ) ? (float) $row['display_price'] : 0.0;
				$regular      = isset( $row['display_regular_price'] ) ? (float) $row['display_regular_price'] : $price;
				$on_sale      = $regular > $price && $price > 0;
				break;
			}

			$nq_doses[] = array(
				'label'        => (string) $label,
				'variation_id' => $variation_id,
				'attr_key'     => $attr_key,
				'attr_value'   => $attr_value,
				'price'        => $price,
				'regular'      => $regular,
				'on_sale'      => $on_sale,
			);
		}
		break;
	}
} elseif ( class_exists( \Noviq\Core\Meta::class ) ) {
	$amount = get_post_meta( $product->get_id(), \Noviq\Core\Meta::VARIATION_AMOUNT_MG, true );
	if ( is_numeric( $amount ) && (float) $amount > 0 ) {
		$nq_doses[] = array(
			'label'        => rtrim( rtrim( number_format( (float) $amount, 2, '.', '' ), '0' ), '.' ) . 'mg',
			'variation_id' => 0,
			'attr_key'     => '',
			'attr_value'   => '',
			'price'        => (float) $product->get_price(),
			'regular'      => (float) $product->get_regular_price(),
			'on_sale'      => $product->is_on_sale(),
		);
	}
}

$nq_regular  = (float) $product->get_regular_price();
$nq_price    = (float) $product->get_price();
$nq_on_sale  = $product->is_on_sale() && $nq_regular > $nq_price && $nq_price > 0;
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

$nq_selected = $nq_doses[0] ?? null;
if ( null !== $nq_selected && $nq_selected['price'] > 0 ) {
	$nq_price   = $nq_selected['price'];
	$nq_regular = $nq_selected['regular'] > 0 ? $nq_selected['regular'] : $nq_price;
	$nq_on_sale = $nq_selected['on_sale'];
}
?>
<li <?php wc_product_class( 'nq-card', $product ); ?> data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>">
	<div class="nq-card__media">
		<?php if ( $nq_on_sale && $nq_discount > 0 ) : ?>
			<span class="nq-card__badge nq-card__badge--sale">-<?php echo esc_html( (string) $nq_discount ); ?>%</span>
		<?php endif; ?>

		<button
			type="button"
			class="nq-card__fav"
			data-nq-fav
			data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>"
			aria-pressed="false"
			aria-label="<?php esc_attr_e( 'Add to favorites', 'noviq-child' ); ?>"
		>
			<?php \Noviq\Child\the_icon( 'heart', array( 'size' => 22, 'class' => 'nq-card__fav-icon' ) ); ?>
		</button>

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
			<ul class="nq-card__doses" role="listbox" aria-label="<?php esc_attr_e( 'Available sizes', 'noviq-child' ); ?>" data-nq-doses>
				<?php foreach ( $nq_doses as $i => $nq_dose ) : ?>
					<li>
						<button
							type="button"
							class="nq-card__dose noviq-num<?php echo 0 === $i ? ' is-selected' : ''; ?>"
							role="option"
							aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
							data-nq-dose
							data-variation-id="<?php echo esc_attr( (string) $nq_dose['variation_id'] ); ?>"
							data-attr-key="<?php echo esc_attr( $nq_dose['attr_key'] ); ?>"
							data-attr-value="<?php echo esc_attr( $nq_dose['attr_value'] ); ?>"
							data-price="<?php echo esc_attr( (string) $nq_dose['price'] ); ?>"
							data-regular="<?php echo esc_attr( (string) $nq_dose['regular'] ); ?>"
							data-on-sale="<?php echo $nq_dose['on_sale'] ? '1' : '0'; ?>"
							<?php echo 1 === count( $nq_doses ) ? ' disabled' : ''; ?>
						>
							<?php echo esc_html( $nq_dose['label'] ); ?>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<div class="nq-card__foot">
			<p class="nq-card__price noviq-num" data-nq-price>
				<?php if ( $nq_on_sale ) : ?>
					<del><?php echo wp_kses_post( wc_price( $nq_regular ) ); ?></del>
					<ins><?php echo wp_kses_post( wc_price( $nq_price ) ); ?></ins>
				<?php elseif ( $nq_is_range && null === $nq_selected ) : ?>
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
				<?php
				$nq_can_ajax = null !== $nq_selected && $nq_selected['variation_id'] > 0 && '' !== $nq_selected['attr_key'];
				$nq_atc_args = array(
					'add-to-cart'  => $product->get_id(),
					'variation_id' => $nq_selected['variation_id'] ?? 0,
					'quantity'     => 1,
				);
				if ( $nq_can_ajax ) {
					$nq_atc_args[ $nq_selected['attr_key'] ] = $nq_selected['attr_value'];
				}
				$nq_atc_url = $nq_can_ajax
					? add_query_arg( $nq_atc_args, home_url( '/' ) )
					: $product->get_permalink();
				?>
				<a class="nq-card__atc<?php echo $nq_can_ajax ? ' ajax_add_to_cart add_to_cart_button' : ''; ?>"
					href="<?php echo esc_url( $nq_atc_url ); ?>"
					data-nq-atc
					data-parent_id="<?php echo esc_attr( (string) $product->get_id() ); ?>"
					data-product_id="<?php echo esc_attr( (string) ( $nq_can_ajax ? $nq_selected['variation_id'] : $product->get_id() ) ); ?>"
					data-product_sku="<?php echo esc_attr( (string) $product->get_sku() ); ?>"
					data-variation_id="<?php echo esc_attr( (string) ( $nq_selected['variation_id'] ?? 0 ) ); ?>"
					data-attr-key="<?php echo esc_attr( (string) ( $nq_selected['attr_key'] ?? '' ) ); ?>"
					data-attr-value="<?php echo esc_attr( (string) ( $nq_selected['attr_value'] ?? '' ) ); ?>"
					data-quantity="1"
					rel="nofollow">
					<span data-nq-atc-label><?php echo $nq_can_ajax ? esc_html__( 'Add to cart', 'noviq-child' ) : esc_html__( 'Select size', 'noviq-child' ); ?></span>
					<span class="nq-card__atc-icon" data-nq-atc-icon="plus">
						<?php \Noviq\Child\the_icon( 'plus', array( 'size' => 18 ) ); ?>
					</span>
					<span class="nq-card__atc-icon" data-nq-atc-icon="cart" hidden>
						<?php \Noviq\Child\the_icon( 'cart', array( 'size' => 18 ) ); ?>
					</span>
				</a>
			<?php else : ?>
				<a class="nq-card__atc ajax_add_to_cart add_to_cart_button"
					href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
					data-nq-atc
					data-product_id="<?php echo esc_attr( (string) $product->get_id() ); ?>"
					data-product_sku="<?php echo esc_attr( (string) $product->get_sku() ); ?>"
					data-quantity="1"
					rel="nofollow">
					<span data-nq-atc-label><?php esc_html_e( 'Add to cart', 'noviq-child' ); ?></span>
					<span class="nq-card__atc-icon" data-nq-atc-icon="plus">
						<?php \Noviq\Child\the_icon( 'plus', array( 'size' => 18 ) ); ?>
					</span>
					<span class="nq-card__atc-icon" data-nq-atc-icon="cart" hidden>
						<?php \Noviq\Child\the_icon( 'cart', array( 'size' => 18 ) ); ?>
					</span>
				</a>
			<?php endif; ?>
		</div>
	</div>
</li>
