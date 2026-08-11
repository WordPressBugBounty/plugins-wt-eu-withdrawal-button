<?php
/**
 * Guest order ineligible for withdrawal email (plain text).
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

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

printf(
	/* translators: %s: order number */
	esc_html__( 'We received your withdrawal request for order #%s. Unfortunately, this order is not currently eligible for withdrawal.', 'wt-eu-withdrawal-button' ),
	esc_html( $order->get_order_number() )
);
echo "\n\n";

echo esc_html__( 'This may be because:', 'wt-eu-withdrawal-button' ) . "\n";
echo '- ' . esc_html__( 'The withdrawal period for this order has expired.', 'wt-eu-withdrawal-button' ) . "\n";
echo '- ' . esc_html__( 'A withdrawal has already been processed for this order.', 'wt-eu-withdrawal-button' ) . "\n";
echo '- ' . esc_html__( 'There are no remaining items eligible for withdrawal.', 'wt-eu-withdrawal-button' ) . "\n\n";

$wbte_ewb_contact_emails = Wbte_Ewb_Settings::get_customer_contact_emails( null, $order );
if ( ! empty( $wbte_ewb_contact_emails ) ) {
	printf(
		/* translators: %s: store contact email */
		esc_html__( 'If you believe this is an error or have questions, please contact us at %s.', 'wt-eu-withdrawal-button' ),
		esc_html( $wbte_ewb_contact_emails[0] )
	);
	echo "\n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
