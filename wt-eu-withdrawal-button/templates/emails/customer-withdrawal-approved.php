<?php
/**
 * Customer email: Withdrawal request approved (HTML).
 *
 * This template can be overridden by copying it to:
 * yourtheme/woocommerce/wbte-eu-withdrawal-button/emails/customer-withdrawal-approved.php
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 *
 * @var Wbte_Ewb_Request             $request       The withdrawal request.
 * @var WC_Order                    $order         The WooCommerce order.
 * @var string                      $email_heading Email heading.
 * @var bool                        $sent_to_admin Whether this is an admin email.
 * @var bool                        $plain_text    Whether this is plain text.
 * @var Wbte_Ewb_Email_Request_Approved $email      The email object.
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
		esc_html__( 'Great news! Your withdrawal request for order #%s has been approved.', 'wt-eu-withdrawal-button' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<h2>
	<?php esc_html_e( 'Request Details', 'wt-eu-withdrawal-button' ); ?>
</h2>

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
				<?php esc_html_e( 'Approved', 'wt-eu-withdrawal-button' ); ?>
			</td>
		</tr>
	</tbody>
</table>

<?php
// Show items.
$wbte_ewb_request_items = $request->get_items();
$wbte_ewb_display_items = array();

if ( ! empty( $wbte_ewb_request_items ) ) {
	$wbte_ewb_display_items = $wbte_ewb_request_items;
} else {
	foreach ( $order->get_items() as $wbte_ewb_line_item ) {
		$wbte_ewb_display_items[] = array(
			'name' => $wbte_ewb_line_item->get_name(),
			'qty'  => $wbte_ewb_line_item->get_quantity(),
		);
	}
}

if ( ! empty( $wbte_ewb_display_items ) ) :
	?>
	<h2><?php esc_html_e( 'Items', 'wt-eu-withdrawal-button' ); ?></h2>
	<table cellspacing="0" cellpadding="6" border="1" style="<?php echo esc_attr( Wbte_Ewb_Email_Template::items_table_style() ); ?>">
		<thead>
			<tr>
				<th style="<?php echo esc_attr( Wbte_Ewb_Email_Template::items_th_style() ); ?>"><?php esc_html_e( 'Product', 'wt-eu-withdrawal-button' ); ?></th>
				<th style="<?php echo esc_attr( Wbte_Ewb_Email_Template::items_th_style() ); ?>"><?php esc_html_e( 'Quantity', 'wt-eu-withdrawal-button' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $wbte_ewb_display_items as $wbte_ewb_item ) : ?>
				<tr>
					<td style="<?php echo esc_attr( Wbte_Ewb_Email_Template::items_td_style() ); ?>"><?php echo esc_html( isset( $wbte_ewb_item['name'] ) ? $wbte_ewb_item['name'] : __( 'Unknown item', 'wt-eu-withdrawal-button' ) ); ?></td>
					<td style="<?php echo esc_attr( Wbte_Ewb_Email_Template::items_td_style() ); ?>"><?php echo esc_html( isset( $wbte_ewb_item['qty'] ) ? $wbte_ewb_item['qty'] : '1' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<h2>
	<?php esc_html_e( 'Next Steps', 'wt-eu-withdrawal-button' ); ?>
</h2>

<p>
	<?php esc_html_e( 'Your withdrawal has been processed. Depending on the type of request, the following may apply:', 'wt-eu-withdrawal-button' ); ?>
</p>

<ul>
	<li><?php esc_html_e( 'If a refund is due, it will be processed to your original payment method. Please allow a few business days for it to appear.', 'wt-eu-withdrawal-button' ); ?></li>
	<li><?php esc_html_e( 'If you need to return any items, you will receive separate return instructions.', 'wt-eu-withdrawal-button' ); ?></li>
</ul>

<p>
	<?php esc_html_e( 'If you have any questions, please do not hesitate to contact us.', 'wt-eu-withdrawal-button' ); ?>
</p>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer.
 */
do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
