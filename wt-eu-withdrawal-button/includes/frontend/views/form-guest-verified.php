<?php
/**
 * Withdrawal form for verified guest users (after email verification).
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var Wbte_Ewb_Pending_Request $wbte_ewb_verified_pending */
/** @var WC_Order $wbte_ewb_verified_order */

$wbte_ewb_success_message = Wbte_Ewb_Form_Handler::get_success_message();
$errors                 = Wbte_Ewb_Form_Handler::get_errors();
?>

<div class="wbte-ewb-withdrawal-form wbte-ewb-form--guest-verified">

	<div class="wbte-ewb-success-message" role="status">
		<p><?php esc_html_e( 'Your email has been verified. Select the items you wish to withdraw and submit your request below.', 'wt-eu-withdrawal-button' ); ?></p>
	</div>

	<?php if ( ! empty( $wbte_ewb_success_message ) ) : ?>
		<div class="wbte-ewb-success-message" role="alert" aria-live="polite">
			<p><?php echo esc_html( $wbte_ewb_success_message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( is_wp_error( $errors ) && $errors->has_errors() ) : ?>
		<div class="wbte-ewb-error-message" role="alert" aria-live="assertive">
			<ul>
				<?php foreach ( $errors->get_error_messages() as $wbte_ewb_message ) : ?>
					<li><?php echo esc_html( $wbte_ewb_message ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" class="wbte-ewb-form" id="wbte-ewb-withdrawal-form">

		<p class="wbte-ewb-form-row form-row form-row-wide">
			<label for="wbte_ewb_order_number"><?php esc_html_e( 'Order number', 'wt-eu-withdrawal-button' ); ?></label>
			<input type="text" name="order_number" id="wbte_ewb_order_number" class="input-text wbte-ewb-guest-order-number" value="<?php echo esc_attr( $wbte_ewb_verified_pending->order_number ); ?>" readonly />
		</p>

		<p class="wbte-ewb-form-row form-row form-row-wide">
			<label for="wbte_ewb_guest_email"><?php esc_html_e( 'Billing email address', 'wt-eu-withdrawal-button' ); ?></label>
			<input type="email" name="email" id="wbte_ewb_guest_email" class="input-text wbte-ewb-guest-email" value="<?php echo esc_attr( $wbte_ewb_verified_pending->customer_email ); ?>" readonly />
		</p>

		<p class="wbte-ewb-form-row form-row form-row-wide">
			<label for="wbte_ewb_reason"><?php esc_html_e( 'Reason for withdrawal', 'wt-eu-withdrawal-button' ); ?></label>
			<textarea id="wbte_ewb_reason" class="input-text" rows="4" readonly><?php echo esc_textarea( $wbte_ewb_verified_pending->reason ); ?></textarea>
		</p>

		<div class="wbte-ewb-items-container" id="wbte-ewb-items-container">
			<p class="wbte-ewb-form-row form-row form-row-wide">
				<label><?php esc_html_e( 'Items to withdraw', 'wt-eu-withdrawal-button' ); ?></label>
			</p>
			<div class="wbte-ewb-items-list" id="wbte-ewb-items-list"></div>
			<div class="wbte-ewb-items-loading" id="wbte-ewb-items-loading">
				<span class="wbte-ewb-spinner"></span>
				<?php esc_html_e( 'Loading items...', 'wt-eu-withdrawal-button' ); ?>
			</div>
		</div>

		<input type="hidden" name="order_id" id="wbte_ewb_order_id" value="<?php echo esc_attr( $wbte_ewb_verified_order->get_id() ); ?>" />
		<input type="hidden" id="wbte_ewb_billing_first_name" value="<?php echo esc_attr( $wbte_ewb_verified_order->get_billing_first_name() ); ?>" />
		<input type="hidden" id="wbte_ewb_billing_last_name" value="<?php echo esc_attr( $wbte_ewb_verified_order->get_billing_last_name() ); ?>" />
		<input type="hidden" name="request_type" id="wbte_ewb_request_type" value="full" />
		<input type="hidden" name="wbte_ewb_guest_token" id="wbte_ewb_guest_token" value="<?php echo esc_attr( $wbte_ewb_verified_pending->verify_token ); ?>" />
		<input type="hidden" name="wbte_ewb_action" value="submit_withdrawal" />
		<input type="hidden" name="recaptcha_token" id="wbte_ewb_recaptcha_token" value="" />
		<?php wp_nonce_field( 'wbte_ewb_withdrawal_form_nonce', 'wbte_ewb_nonce' ); ?>

		<p class="wbte-ewb-form-row form-row form-row-wide">
			<button type="submit" class="wbte-ewb-btn wbte-ewb-btn--withdrawal button alt disabled" id="wbte-ewb-submit-btn" disabled>
				<?php esc_html_e( 'Submit Withdrawal Request', 'wt-eu-withdrawal-button' ); ?>
			</button>
		</p>

	</form>

</div>
