<?php
/**
 * Product quantity inputs.
 *
 * On the product page, cookie noviq_qty_style=dropdown renders a select.
 * Cart and every other screen keep the number input.
 *
 * @package Noviq\Child
 * @version 9.4.0
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$type         = $type ?? 'number';
$classes      = $classes ?? array( 'input-text', 'qty', 'text' );
$placeholder  = $placeholder ?? '';
$inputmode    = $inputmode ?? 'numeric';
$autocomplete = $autocomplete ?? 'on';
$readonly     = $readonly ?? false;
$args         = $args ?? array();

if ( $max_value && $min_value === $max_value ) {
	?>
	<div class="quantity hidden">
		<input
			type="hidden"
			id="<?php echo esc_attr( $input_id ); ?>"
			class="<?php echo esc_attr( implode( ' ', (array) $classes ) ); ?>"
			name="<?php echo esc_attr( $input_name ); ?>"
			value="<?php echo esc_attr( $min_value ); ?>"
		/>
	</div>
	<?php
	return;
}

$label = ! empty( $args['product_name'] )
	? sprintf( esc_html__( '%s quantity', 'woocommerce' ), wp_strip_all_tags( (string) $args['product_name'] ) )
	: esc_html__( 'Quantity', 'woocommerce' );

$use_dropdown = function_exists( 'is_product' )
	&& is_product()
	&& 'dropdown' === \Noviq\Child\quantity_style();
?>
<div class="quantity">
	<?php do_action( 'woocommerce_before_quantity_input_field' ); ?>
	<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>">
		<?php echo esc_html( $label ); ?>
	</label>
	<?php if ( $use_dropdown ) : ?>
		<select
			id="<?php echo esc_attr( $input_id ); ?>"
			class="<?php echo esc_attr( implode( ' ', (array) $classes ) ); ?>"
			name="<?php echo esc_attr( $input_name ); ?>"
		>
			<?php
			$min     = max( 1, (int) $min_value );
			$qty_step = max( 1, (int) $step );
			$cap     = \Noviq\Child\quantity_dropdown_max();
			$max     = (int) $max_value;
			if ( $max > 0 ) {
				$cap = min( $cap, $max );
			}
			$current = (int) $input_value;
			if ( $current > $cap ) {
				$cap = $current;
			}
			for ( $i = $min; $i <= $cap; $i += $qty_step ) {
				printf(
					'<option value="%1$d"%2$s>%1$d</option>',
					$i,
					selected( $current, $i, false )
				);
			}
			?>
		</select>
	<?php else : ?>
		<input
			type="<?php echo esc_attr( $type ); ?>"
			<?php echo ! empty( $readonly ) ? 'readonly="readonly"' : ''; ?>
			id="<?php echo esc_attr( $input_id ); ?>"
			class="<?php echo esc_attr( implode( ' ', (array) $classes ) ); ?>"
			name="<?php echo esc_attr( $input_name ); ?>"
			value="<?php echo esc_attr( $input_value ); ?>"
			aria-label="<?php esc_attr_e( 'Product quantity', 'woocommerce' ); ?>"
			size="4"
			min="<?php echo esc_attr( $min_value ); ?>"
			max="<?php echo esc_attr( 0 < $max_value ? $max_value : '' ); ?>"
			<?php if ( empty( $readonly ) ) : ?>
				step="<?php echo esc_attr( $step ); ?>"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				inputmode="<?php echo esc_attr( $inputmode ); ?>"
				autocomplete="<?php echo esc_attr( isset( $autocomplete ) ? $autocomplete : 'on' ); ?>"
			<?php endif; ?>
		/>
	<?php endif; ?>
	<?php do_action( 'woocommerce_after_quantity_input_field' ); ?>
</div>
