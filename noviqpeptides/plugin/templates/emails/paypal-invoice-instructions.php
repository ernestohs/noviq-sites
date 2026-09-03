<?php
/**
 * PayPal invoice instructions email (HTML).
 *
 * @package Noviq\Core
 * @var \WC_Order $order
 * @var string    $email_heading
 * @var string    $additional_content
 * @var bool      $sent_to_admin
 * @var bool      $plain_text
 * @var \WC_Email $email
 * @var string    $paypal_url
 * @var string    $instructions
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
	<?php
	printf(
		/* translators: %s: customer first name */
		esc_html__( 'Hi %s,', 'noviq-core' ),
		esc_html( $order->get_billing_first_name() )
	);
	?>
</p>

<p><?php esc_html_e( 'Thank you for your order. Please complete payment via PayPal using the link below.', 'noviq-core' ); ?></p>

<?php if ( '' !== $instructions ) : ?>
	<?php echo wp_kses_post( wpautop( wptexturize( $instructions ) ) ); ?>
<?php endif; ?>

<?php if ( '' !== $paypal_url ) : ?>
	<p style="margin: 24px 0;">
		<a href="<?php echo esc_url( $paypal_url ); ?>" style="display:inline-block;padding:12px 24px;background:#0A4DA8;color:#ffffff;text-decoration:none;border-radius:4px;">
			<?php esc_html_e( 'Pay with PayPal', 'noviq-core' ); ?>
		</a>
	</p>
	<p><a href="<?php echo esc_url( $paypal_url ); ?>"><?php echo esc_html( $paypal_url ); ?></a></p>
<?php endif; ?>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

if ( '' !== $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
