<?php
/**
 * Guest withdrawal verification email (HTML).
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.1.0
 *
 * @var Wbte_Ewb_Pending_Request $pending
 * @var string                   $verification_url
 * @var string                   $email_heading
 * @var bool                     $sent_to_admin
 * @var bool                     $plain_text
 * @var WC_Email                 $email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header.
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
?>

<p>
	<?php esc_html_e( 'We received your withdrawal request. To complete it, please verify your billing email address by clicking the button below.', 'wt-eu-withdrawal-button' ); ?>
</p>

<p>
	<strong><?php esc_html_e( 'Order number:', 'wt-eu-withdrawal-button' ); ?></strong>
	<?php echo esc_html( $pending->order_number ); ?>
</p>

<?php if ( ! empty( $pending->reason ) ) : ?>
	<p>
		<strong><?php esc_html_e( 'Reason:', 'wt-eu-withdrawal-button' ); ?></strong>
		<?php echo esc_html( $pending->reason ); ?>
	</p>
<?php endif; ?>

<?php
if ( empty( $verification_url ) && ! empty( $pending->verify_token ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- wc_get_template variable.
	$verification_url            = add_query_arg( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- wc_get_template variable.
		array(
			'wbte_ewb_verify' => rawurlencode( $pending->verify_token ),
		),
		Wbte_Ewb_Settings::get_withdrawal_page_url() ?: home_url( '/' )
	);
}

$wbte_ewb_base_color   = get_option( 'woocommerce_email_base_color', '#96588a' );
$wbte_ewb_button_style = sprintf(
	'background-color:%1$s;border:1px solid %1$s;color:#ffffff;display:inline-block;font-size:16px;font-weight:bold;line-height:1;padding:12px 24px;text-decoration:none;border-radius:3px;',
	esc_attr( $wbte_ewb_base_color )
);
?>

<?php if ( ! empty( $verification_url ) ) : ?>
	<p style="margin: 24px 0; text-align: center;">
		<a class="button" href="<?php echo esc_url( $verification_url ); ?>" style="<?php echo esc_attr( $wbte_ewb_button_style ); ?>">
			<?php esc_html_e( 'Verify withdrawal request', 'wt-eu-withdrawal-button' ); ?>
		</a>
	</p>
<?php endif; ?>

<p>
	<?php esc_html_e( 'If you did not request a withdrawal, you can ignore this email.', 'wt-eu-withdrawal-button' ); ?>
</p>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer.
 */
do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
