<?php
/**
 * Guest order ineligible for withdrawal email (HTML).
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.1.0
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header.
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
?>

<p>
	<?php
	printf(
		/* translators: %s: order number */
		esc_html__( 'We received your withdrawal request for order #%s. Unfortunately, this order is not currently eligible for withdrawal.', 'wt-eu-withdrawal-button' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<p>
	<?php esc_html_e( 'This may be because:', 'wt-eu-withdrawal-button' ); ?>
</p>

<ul>
	<li><?php esc_html_e( 'The withdrawal period for this order has expired.', 'wt-eu-withdrawal-button' ); ?></li>
	<li><?php esc_html_e( 'A withdrawal has already been processed for this order.', 'wt-eu-withdrawal-button' ); ?></li>
	<li><?php esc_html_e( 'There are no remaining items eligible for withdrawal.', 'wt-eu-withdrawal-button' ); ?></li>
</ul>

<?php
$wbte_ewb_contact_emails = Wbte_Ewb_Settings::get_customer_contact_emails( null, $order );
if ( ! empty( $wbte_ewb_contact_emails ) ) :
?>
<p>
	<?php
	printf(
		/* translators: %s: store contact email */
		esc_html__( 'If you believe this is an error or have questions, please contact us at %s.', 'wt-eu-withdrawal-button' ),
		'<a href="mailto:' . esc_attr( $wbte_ewb_contact_emails[0] ) . '">' . esc_html( $wbte_ewb_contact_emails[0] ) . '</a>'
	);
	?>
</p>
<?php endif; ?>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer.
 */
do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
