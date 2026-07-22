<?php
/**
 * Withdrawal form template for guest users.
 *
 * Guest requests are queued for email verification before a
 * withdrawal request is created in the dashboard.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wbte_ewb_success_message = Wbte_Ewb_Form_Handler::get_success_message();
$errors                   = Wbte_Ewb_Form_Handler::get_errors();
$wbte_ewb_reason_required = 'yes' === Wbte_Ewb_Settings::get( 'reason_required', 'no' );

if ( ! isset( $wbte_ewb_notice_message ) ) {
	$wbte_ewb_notice_message = class_exists( 'Wbte_Ewb_Guest_Verification' ) ? Wbte_Ewb_Guest_Verification::get_notice_message() : '';
}

if ( ! isset( $wbte_ewb_notice_is_error ) ) {
	$wbte_ewb_notice_is_error = class_exists( 'Wbte_Ewb_Guest_Verification' ) ? Wbte_Ewb_Guest_Verification::notice_is_error() : false;
}

// Preserve previously submitted values on validation failure.
$wbte_ewb_posted_order_number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
$wbte_ewb_posted_email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
$wbte_ewb_posted_reason       = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
?>

<div class="wbte-ewb-withdrawal-form wbte-ewb-form--guest">

	<?php if ( ! empty( $wbte_ewb_notice_message ) ) : ?>
		<div class="<?php echo $wbte_ewb_notice_is_error ? 'wbte-ewb-error-message' : 'wbte-ewb-success-message'; ?>" role="alert">
			<p><?php echo esc_html( $wbte_ewb_notice_message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $wbte_ewb_success_message ) ) : ?>
		<div class="wbte-ewb-success-message" role="alert">
			<p><?php echo esc_html( $wbte_ewb_success_message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( is_wp_error( $errors ) && $errors->has_errors() ) : ?>
		<div class="wbte-ewb-error-message" role="alert">
			<ul>
				<?php foreach ( $errors->get_error_messages() as $wbte_ewb_message ) : ?>
					<li><?php echo esc_html( $wbte_ewb_message ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" class="wbte-ewb-form" id="wbte-ewb-withdrawal-form">

		<!-- Order number -->
		<p class="wbte-ewb-form-row form-row form-row-wide">
			<label for="wbte_ewb_order_number"><?php esc_html_e( 'Order number', 'wt-eu-withdrawal-button' ); ?>&nbsp;<abbr class="required" title="<?php esc_attr_e( 'required', 'wt-eu-withdrawal-button' ); ?>">*</abbr></label>
			<input type="text" name="order_number" id="wbte_ewb_order_number" class="input-text wbte-ewb-guest-order-number" value="<?php echo esc_attr( $wbte_ewb_posted_order_number ); ?>" required />
		</p>

		<!-- Billing email -->
		<p class="wbte-ewb-form-row form-row form-row-wide">
			<label for="wbte_ewb_guest_email"><?php esc_html_e( 'Billing email address', 'wt-eu-withdrawal-button' ); ?>&nbsp;<abbr class="required" title="<?php esc_attr_e( 'required', 'wt-eu-withdrawal-button' ); ?>">*</abbr></label>
			<input type="email" name="email" id="wbte_ewb_guest_email" class="input-text wbte-ewb-guest-email" value="<?php echo esc_attr( $wbte_ewb_posted_email ); ?>" required />
		</p>

		<!-- Reason -->
		<p class="wbte-ewb-form-row form-row form-row-wide">
			<label for="wbte_ewb_reason">
				<?php esc_html_e( 'Reason for withdrawal', 'wt-eu-withdrawal-button' ); ?>
				<?php if ( $wbte_ewb_reason_required ) : ?>
					&nbsp;<abbr class="required" title="<?php esc_attr_e( 'required', 'wt-eu-withdrawal-button' ); ?>">*</abbr>
				<?php endif; ?>
			</label>
			<textarea name="reason" id="wbte_ewb_reason" class="input-text" rows="4" <?php echo $wbte_ewb_reason_required ? 'required' : ''; ?>><?php echo esc_textarea( $wbte_ewb_posted_reason ); ?></textarea>
		</p>

		<p class="wbte-ewb-form-description">
			<?php esc_html_e( 'Enter your order number and billing email to start. If your details match our records, you will receive a verification email. After verifying your email, you can choose which items to withdraw.', 'wt-eu-withdrawal-button' ); ?>
		</p>

		<!-- Hidden fields -->
		<input type="hidden" name="wbte_ewb_action" value="submit_withdrawal" />
		<?php wp_nonce_field( 'wbte_ewb_withdrawal_form_nonce', 'wbte_ewb_nonce' ); ?>

		<p class="wbte-ewb-form-row form-row form-row-wide">
			<button type="submit" class="wbte-ewb-btn wbte-ewb-btn--withdrawal button alt" id="wbte-ewb-submit-btn">
				<?php esc_html_e( 'Submit Withdrawal Request', 'wt-eu-withdrawal-button' ); ?>
			</button>
		</p>

	</form>

</div>
