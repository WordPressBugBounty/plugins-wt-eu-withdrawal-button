<?php
/**
 * Guest withdrawal verification email (plain text).
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

esc_html_e( 'We received your withdrawal request. To complete it, please verify your billing email address using the link below.', 'wt-eu-withdrawal-button' );
echo "\n\n";

/* translators: %s: order number */
printf( esc_html__( 'Order number: %s', 'wt-eu-withdrawal-button' ), esc_html( $pending->order_number ) );
echo "\n";

if ( ! empty( $pending->reason ) ) {
	/* translators: %s: reason */
	printf( esc_html__( 'Reason: %s', 'wt-eu-withdrawal-button' ), esc_html( $pending->reason ) );
	echo "\n";
}

echo "\n" . esc_url( $verification_url ) . "\n\n";

esc_html_e( 'If you did not request a withdrawal, you can ignore this email.', 'wt-eu-withdrawal-button' );
echo "\n";
