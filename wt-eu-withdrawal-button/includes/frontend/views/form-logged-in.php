<?php
/**
 * Withdrawal form template for logged-in users.
 *
 * This template is loaded by the withdrawal-form.php wrapper
 * when the current visitor is authenticated.
 *
 * Available variables:
 *   $wbte_ewb_eligible_orders  array  List of WC_Order objects eligible for withdrawal.
 *   $wbte_ewb_current_user     WP_User  The logged-in user object.
 *   $wbte_ewb_preselected_order_id  int  Pre-selected order ID from query string (0 if none).
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wbte_ewb_success_message = Wbte_Ewb_Form_Handler::get_success_message();
$errors          = Wbte_Ewb_Form_Handler::get_errors();
$wbte_ewb_reason_required = 'yes' === Wbte_Ewb_Settings::get( 'reason_required', 'no' );
?>

<div class="wbte-ewb-withdrawal-form wbte-ewb-form--logged-in">

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

	<?php if ( empty( $wbte_ewb_eligible_orders ) ) : ?>
		<p><?php esc_html_e( 'You currently have no orders eligible for withdrawal.', 'wt-eu-withdrawal-button' ); ?></p>
	<?php else : ?>

		<form method="post" class="wbte-ewb-form" id="wbte-ewb-withdrawal-form">

			<!-- Order selection -->
			<p class="wbte-ewb-form-row form-row form-row-wide">
				<label for="wbte_ewb_order_id"><?php esc_html_e( 'Order', 'wt-eu-withdrawal-button' ); ?>&nbsp;<abbr class="required" title="<?php esc_attr_e( 'required', 'wt-eu-withdrawal-button' ); ?>">*</abbr></label>
				<select name="order_id" id="wbte_ewb_order_id" class="wbte-ewb-order-select" required aria-required="true">
					<option value=""><?php esc_html_e( '-- Select an order --', 'wt-eu-withdrawal-button' ); ?></option>
					<?php foreach ( $wbte_ewb_eligible_orders as $order ) : ?>
						<option value="<?php echo esc_attr( $order->get_id() ); ?>"<?php selected( $wbte_ewb_preselected_order_id, $order->get_id() ); ?>>
							<?php
							printf(
								/* translators: 1: order number, 2: order date */
								esc_html__( '#%1$s — %2$s', 'wt-eu-withdrawal-button' ),
								esc_html( $order->get_order_number() ),
								esc_html( date_i18n( wc_date_format(), $order->get_date_created()->getTimestamp() ) )
							);
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<!-- Items container (populated via JS after order selection) -->
			<div class="wbte-ewb-items-container" id="wbte-ewb-items-container" style="display:none;">
				<p class="wbte-ewb-form-row form-row form-row-wide">
					<label><?php esc_html_e( 'Items to withdraw', 'wt-eu-withdrawal-button' ); ?></label>
				</p>
				<div class="wbte-ewb-items-list" id="wbte-ewb-items-list">
					<!-- Items loaded dynamically -->
				</div>
				<div class="wbte-ewb-items-loading" id="wbte-ewb-items-loading" role="status" aria-live="polite" style="display:none;">
					<span class="wbte-ewb-spinner" aria-hidden="true"></span>
					<?php esc_html_e( 'Loading items...', 'wt-eu-withdrawal-button' ); ?>
				</div>
			</div>

			<!-- Pre-filled read-only email -->
			<p class="wbte-ewb-form-row form-row form-row-wide">
				<label for="wbte_ewb_email"><?php esc_html_e( 'Email', 'wt-eu-withdrawal-button' ); ?></label>
				<input type="email" name="email" id="wbte_ewb_email" class="input-text" value="<?php echo esc_attr( $wbte_ewb_current_user->user_email ); ?>" readonly aria-readonly="true" />
			</p>

			<!-- Pre-filled read-only name -->
			<div class="wbte-ewb-name-row">
				<p class="wbte-ewb-form-row form-row">
					<label for="wbte_ewb_first_name"><?php esc_html_e( 'First name', 'wt-eu-withdrawal-button' ); ?></label>
					<input type="text" name="first_name" id="wbte_ewb_first_name" class="input-text" value="<?php echo esc_attr( $wbte_ewb_current_user->first_name ); ?>" readonly aria-readonly="true" />
				</p>

				<p class="wbte-ewb-form-row form-row">
					<label for="wbte_ewb_last_name"><?php esc_html_e( 'Last name', 'wt-eu-withdrawal-button' ); ?></label>
					<input type="text" name="last_name" id="wbte_ewb_last_name" class="input-text" value="<?php echo esc_attr( $wbte_ewb_current_user->last_name ); ?>" readonly aria-readonly="true" />
				</p>
			</div>

			<!-- Reason -->
			<p class="wbte-ewb-form-row form-row form-row-wide">
				<label for="wbte_ewb_reason">
					<?php esc_html_e( 'Reason for withdrawal', 'wt-eu-withdrawal-button' ); ?>
					<?php if ( $wbte_ewb_reason_required ) : ?>
						&nbsp;<abbr class="required" title="<?php esc_attr_e( 'required', 'wt-eu-withdrawal-button' ); ?>">*</abbr>
					<?php endif; ?>
				</label>
				<textarea name="reason" id="wbte_ewb_reason" class="input-text" rows="4" <?php echo $wbte_ewb_reason_required ? 'required aria-required="true"' : ''; ?>></textarea>
			</p>

			<?php
			// T&C checkbox (provided by pro/marketplace addon).
			$wbte_ewb_show_terms = 'no';
			$wbte_ewb_terms_text_val = '';
			if ( class_exists( 'Wbte_Ewb_Advanced_Settings' ) ) {
				$wbte_ewb_show_terms = Wbte_Ewb_Advanced_Settings::get( 'show_terms_checkbox', 'no' );
				$wbte_ewb_terms_text_val = Wbte_Ewb_Advanced_Settings::get( 'terms_link_text', '' );
			} elseif ( class_exists( 'Wbte_Ewb_Pro_Settings' ) ) {
				$wbte_ewb_show_terms = Wbte_Ewb_Pro_Settings::get( 'show_terms_checkbox', 'no' );
				$wbte_ewb_terms_text_val = Wbte_Ewb_Pro_Settings::get( 'terms_link_text', '' );
			}
			if (
				'yes' === $wbte_ewb_show_terms
				&& class_exists( 'Wbte_Ewb_Terms_Page' )
			) :
				$wbte_ewb_terms_url = Wbte_Ewb_Terms_Page::get_page_url();
				if ( '' === $wbte_ewb_terms_text_val ) {
					$wbte_ewb_terms_text_val = __( 'I have read and agree to the withdrawal terms and conditions', 'wt-eu-withdrawal-button' );
				}
				if ( $wbte_ewb_terms_url ) :
			?>
			<p class="wbte-ewb-form-row form-row form-row-wide wbte-ewb-terms-row">
				<a href="<?php echo esc_url( $wbte_ewb_terms_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $wbte_ewb_terms_text_val ); ?></a>
			</p>
			<?php endif; endif; ?>

			<!-- Hidden fields -->
			<input type="hidden" name="wbte_ewb_action" value="submit_withdrawal" />
			<input type="hidden" name="request_type" id="wbte_ewb_request_type" value="full" />
			<input type="hidden" name="recaptcha_token" id="wbte_ewb_recaptcha_token" value="" />
			<?php wp_nonce_field( 'wbte_ewb_withdrawal_form_nonce', 'wbte_ewb_nonce' ); ?>

			<p class="wbte-ewb-form-row form-row form-row-wide">
				<button type="submit" class="wbte-ewb-btn wbte-ewb-btn--withdrawal button alt" id="wbte-ewb-submit-btn">
					<?php esc_html_e( 'Submit Withdrawal Request', 'wt-eu-withdrawal-button' ); ?>
				</button>
			</p>

		</form>

	<?php endif; ?>

</div>
