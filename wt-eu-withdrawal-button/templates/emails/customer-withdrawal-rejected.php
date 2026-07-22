<?php
/**
 * Customer email: Withdrawal request rejected (HTML).
 *
 * This template can be overridden by copying it to:
 * yourtheme/woocommerce/wbte-eu-withdrawal-button/emails/customer-withdrawal-rejected.php
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 *
 * @var Wbte_Ewb_Request              $request       The withdrawal request.
 * @var WC_Order                     $order         The WooCommerce order.
 * @var string                       $email_heading Email heading.
 * @var bool                         $sent_to_admin Whether this is an admin email.
 * @var bool                         $plain_text    Whether this is plain text.
 * @var Wbte_Ewb_Email_Request_Rejected $email       The email object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header.
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook. ?>

<p>
	<?php
	printf(
		/* translators: %s: customer first name. */
		esc_html__( 'Hi %s,', 'wt-eu-withdrawal-button' ),
		esc_html( $order->get_billing_first_name() )
	);
	?>
</p>

<p>
	<?php
	printf(
		/* translators: %s: order number. */
		esc_html__( 'We have reviewed your withdrawal request for order #%s. Unfortunately, we are unable to approve this request at this time.', 'wt-eu-withdrawal-button' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<h2>
	<?php esc_html_e( 'Request Details', 'wt-eu-withdrawal-button' ); ?>
</h2>

<?php
$wbte_ewb_note_text = '';

if ( ! empty( $reject_note ) ) {
	$wbte_ewb_note_text = $reject_note;
} else {
	$wbte_ewb_meta          = $request->get_meta();
	$wbte_ewb_admin_note    = isset( $wbte_ewb_meta['admin_note'] ) ? $wbte_ewb_meta['admin_note'] : '';
	$wbte_ewb_reject_reason = isset( $wbte_ewb_meta['reject_reason'] ) ? $wbte_ewb_meta['reject_reason'] : '';
	$wbte_ewb_note_text     = ! empty( $wbte_ewb_reject_reason ) ? $wbte_ewb_reject_reason : $wbte_ewb_admin_note;
}
?>

<table cellspacing="0" cellpadding="6" border="1" style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_table_style() ); ?>">
	<?php Wbte_Ewb_Email_Template::render_details_colgroup(); ?>
	<tbody>
		<tr>
			<th scope="row" style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_th_style() ); ?>">
				<?php esc_html_e( 'Order Number', 'wt-eu-withdrawal-button' ); ?>
			</th>
			<td style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_td_style() ); ?>">
				<?php echo esc_html( $order->get_order_number() ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row" style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_th_style() ); ?>">
				<?php esc_html_e( 'Request Type', 'wt-eu-withdrawal-button' ); ?>
			</th>
			<td style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_td_style() ); ?>">
				<?php echo esc_html( $request->get_request_type_label() ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row" style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_th_style() ); ?>">
				<?php esc_html_e( 'Date Submitted', 'wt-eu-withdrawal-button' ); ?>
			</th>
			<td style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_td_style() ); ?>">
				<?php echo esc_html( $request->get_created_at_formatted() ); ?>
			</td>
		</tr>
		<?php
		wc_get_template(
			'emails/parts/withdrawal-verification-code-row.php',
			array( 'request' => $request ),
			'',
			WBTE_EWB_PLUGIN_DIR . 'templates/'
		);
		?>
		<?php if ( $request->get_processed_at_formatted() ) : ?>
		<tr>
			<th scope="row" style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_th_style() ); ?>">
				<?php esc_html_e( 'Date Processed', 'wt-eu-withdrawal-button' ); ?>
			</th>
			<td style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_td_style() ); ?>">
				<?php echo esc_html( $request->get_processed_at_formatted() ); ?>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<th scope="row" style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_th_style() ); ?>">
				<?php esc_html_e( 'Status', 'wt-eu-withdrawal-button' ); ?>
			</th>
			<td style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_td_style() ); ?>">
				<?php esc_html_e( 'Rejected', 'wt-eu-withdrawal-button' ); ?>
			</td>
		</tr>
		<?php if ( ! empty( $wbte_ewb_note_text ) ) : ?>
			<tr>
				<th scope="row" style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_th_style() ); ?>">
					<?php esc_html_e( 'Reason', 'wt-eu-withdrawal-button' ); ?>
				</th>
				<td style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_td_style() ); ?>">
					<?php echo esc_html( $wbte_ewb_note_text ); ?>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<p>
	<?php esc_html_e( 'If you believe this decision was made in error or if you have additional information to provide, please contact us and we will be happy to review your case again.', 'wt-eu-withdrawal-button' ); ?>
</p>

<p>
	<?php
	$wbte_ewb_contact_emails = Wbte_Ewb_Settings::get_customer_contact_emails( $request, $order );
	$wbte_ewb_contact_links  = array();

	foreach ( $wbte_ewb_contact_emails as $wbte_ewb_contact_email ) {
		if ( is_email( $wbte_ewb_contact_email ) ) {
			$wbte_ewb_contact_links[] = '<a href="mailto:' . esc_attr( $wbte_ewb_contact_email ) . '">' . esc_html( $wbte_ewb_contact_email ) . '</a>';
		}
	}

	if ( ! empty( $wbte_ewb_contact_links ) ) {
		printf(
			/* translators: %s: store contact email address or comma-separated list of addresses. */
			esc_html__( 'You can reach us at %s.', 'wt-eu-withdrawal-button' ),
			wp_kses_post( implode( ', ', $wbte_ewb_contact_links ) )
		);
	}
	?>
</p>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer.
 */
do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
