<?php
/**
 * Email table row: withdrawal receipt hash.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.4
 *
 * @var Wbte_Ewb_Request $request Withdrawal request.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $request ) || ! $request instanceof Wbte_Ewb_Request ) {
	return;
}

$wbte_ewb_verification_code = $request->get_verification_code();

if ( '' === $wbte_ewb_verification_code ) {
	return;
}
?>
<tr>
	<th scope="row" style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_th_style() ); ?>">
		<?php esc_html_e( 'Receipt hash', 'wt-eu-withdrawal-button' ); ?>
	</th>
	<td style="<?php echo esc_attr( Wbte_Ewb_Email_Template::details_td_style() ); ?>">
		<span style="<?php echo esc_attr( Wbte_Ewb_Email_Template::verification_code_style() ); ?>">
			<?php echo esc_html( $wbte_ewb_verification_code ); ?>
		</span>
	</td>
</tr>
