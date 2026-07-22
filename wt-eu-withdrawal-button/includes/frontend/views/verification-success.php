<?php
/**
 * Guest withdrawal submission success view.
 *
 * Shown after a verified guest submits their withdrawal request.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.1.0
 *
 * @var string $wbte_ewb_verification_message Success message.
 * @var string $wbte_ewb_verification_title    Result heading.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $wbte_ewb_verification_title ) || '' === $wbte_ewb_verification_title ) {
	$wbte_ewb_verification_title = __( 'Request submitted', 'wt-eu-withdrawal-button' );
}

if ( ! isset( $wbte_ewb_verification_message ) || '' === $wbte_ewb_verification_message ) {
	$wbte_ewb_verification_message = __( 'Your withdrawal request has been submitted successfully. You will receive a confirmation email shortly.', 'wt-eu-withdrawal-button' );
}
?>

<div class="wbte-ewb-verification-result wbte-ewb-verification-result--success" role="status">

	<div class="wbte-ewb-verification-result__icon" aria-hidden="true">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
			<circle cx="12" cy="12" r="10" />
			<polyline points="8 12 11 15 16 9" />
		</svg>
	</div>

	<h2 class="wbte-ewb-verification-result__title">
		<?php echo esc_html( $wbte_ewb_verification_title ); ?>
	</h2>

	<p class="wbte-ewb-verification-result__message">
		<?php echo esc_html( $wbte_ewb_verification_message ); ?>
	</p>

</div>
