<?php
/**
 * Guest withdrawal email verification handler.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Guest_Verification
 *
 * @since 1.1.0
 */
class Wbte_Ewb_Guest_Verification {

	/**
	 * Guest withdrawal service.
	 *
	 * @var Wbte_Ewb_Guest_Withdrawal_Service
	 */
	private $guest_service;

	/**
	 * Constructor.
	 *
	 * @param Wbte_Ewb_Guest_Withdrawal_Service $guest_service Guest service.
	 */
	public function __construct( Wbte_Ewb_Guest_Withdrawal_Service $guest_service ) {
		$this->guest_service = $guest_service;

		add_action( 'template_redirect', array( $this, 'handle_verification_link' ), 5 );
	}

	/**
	 * Process email verification links on the frontend.
	 *
	 * @return void
	 */
	public function handle_verification_link() {
		if ( empty( $_GET['wbte_ewb_verify'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['wbte_ewb_verify'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$target = Wbte_Ewb_Settings::get_withdrawal_page_url();

		if ( ! $target ) {
			$target = wp_get_referer();
		}

		if ( ! $target ) {
			$target = home_url( '/' );
		}

		$target = remove_query_arg( 'wbte_ewb_verify', $target );

		$pending = $this->guest_service->find_pending_by_token( $token );

		// Resolve order language and translate the target URL for WPML.
		$order_lang = '';
		if ( $pending && ! empty( $pending->order_number ) ) {
			$order = $this->guest_service->resolve_order_by_number( $pending->order_number );
			if ( $order instanceof WC_Order ) {
				$order_lang = $order->get_meta( 'wpml_language' );
				if ( $order_lang ) {
					$target = apply_filters( 'wpml_permalink', $target, $order_lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
					do_action( 'wpml_switch_language', $order_lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				}
			}
		}

		if ( $pending && 'verified' === $pending->status ) {
			if ( $pending->withdrawal_id > 0 ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'wbte_ewb_guest_submitted' => '1',
						),
						$target
					)
				);
				exit;
			}

			$form_url = $this->guest_service->get_verified_form_url( $pending );
			if ( $order_lang ) {
				$form_url = apply_filters( 'wpml_permalink', $form_url, $order_lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			}
			wp_safe_redirect( $form_url );
			exit;
		}

		$result = $this->guest_service->verify_email( $token );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'wbte_ewb_verify_error' => rawurlencode( $result->get_error_code() ),
					),
					$target
				)
			);
			exit;
		}

		$form_url = $this->guest_service->get_verified_form_url( $result );
		if ( $order_lang ) {
			$form_url = apply_filters( 'wpml_permalink', $form_url, $order_lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}
		wp_safe_redirect( $form_url );
		exit;
	}

	/**
	 * Whether the current request should show the verified guest withdrawal form.
	 *
	 * @since 1.0.3
	 *
	 * @return bool
	 */
	public static function is_verified_form_view() {
		if ( is_user_logged_in() ) {
			return false;
		}

		if ( empty( $_GET['wbte_ewb_guest_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['wbte_ewb_guest_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$guest = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'guest_withdrawal' ) : null;

		if ( ! $guest instanceof Wbte_Ewb_Guest_Withdrawal_Service ) {
			return false;
		}

		$pending = $guest->find_pending_by_token( $token );

		return $pending && 'verified' === $pending->status && $pending->withdrawal_id < 1;
	}

	/**
	 * Get the verified guest pending request for the current page view.
	 *
	 * @since 1.0.3
	 *
	 * @return Wbte_Ewb_Pending_Request|WP_Error|null Null when not on the verified form view.
	 */
	public static function get_verified_pending_request() {
		if ( ! self::is_verified_form_view() ) {
			return null;
		}

		if ( empty( $_GET['wbte_ewb_guest_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public guest verification link.
			return null;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['wbte_ewb_guest_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$guest = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'guest_withdrawal' ) : null;

		if ( ! $guest instanceof Wbte_Ewb_Guest_Withdrawal_Service ) {
			return new WP_Error(
				'wbte_ewb_service_unavailable',
				__( 'The withdrawal service is temporarily unavailable.', 'wt-eu-withdrawal-button' )
			);
		}

		return $guest->get_verified_pending_for_form( $token );
	}

	/**
	 * Whether the current request should show the verified success view.
	 *
	 * @return bool
	 */
	public static function is_verified_view() {
		return isset( $_GET['wbte_ewb_guest_submitted'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['wbte_ewb_guest_submitted'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Render the submission success view for verified guest withdrawals.
	 *
	 * @return string|null HTML or null when the form should render normally.
	 */
	public static function render_verified_view() {
		if ( ! self::is_verified_view() ) {
			return null;
		}

		$wbte_ewb_already_verified   = false;
		$wbte_ewb_verification_title = __( 'Request submitted', 'wt-eu-withdrawal-button' );
		$wbte_ewb_verification_message = __( 'Your withdrawal request has been submitted successfully. You will receive a confirmation email shortly.', 'wt-eu-withdrawal-button' );

		ob_start();

		$template = WBTE_EWB_PLUGIN_DIR . 'includes/frontend/views/verification-success.php';

		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<div class="wbte-ewb-withdrawal-form wbte-ewb-form--guest-verified">';
			echo '<div class="wbte-ewb-success-message" role="alert"><p>' . esc_html( $wbte_ewb_verification_message ) . '</p></div>';
			echo '</div>';
		}

		return ob_get_clean();
	}

	/**
	 * Generic success message shown after a guest queues a withdrawal request.
	 *
	 * @return string
	 */
	public static function get_queue_success_message() {
		return __( 'Thank you. If the details you provided are correct, you will receive a verification email to complete your withdrawal request.', 'wt-eu-withdrawal-button' );
	}

	/**
	 * Return a user-facing verification notice for the withdrawal form.
	 *
	 * @return string
	 */
	public static function get_notice_message() {
		if ( self::is_verified_view() || self::is_verified_form_view() ) {
			return '';
		}

		if ( ! empty( $_GET['wbte_ewb_verify_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$code = sanitize_key( wp_unslash( $_GET['wbte_ewb_verify_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$messages = array(
				'wbte_ewb_invalid_token'   => __( 'This verification link is invalid or has already been used.', 'wt-eu-withdrawal-button' ),
				'wbte_ewb_token_expired'   => __( 'This verification link has expired. Please submit a new withdrawal request.', 'wt-eu-withdrawal-button' ),
				'wbte_ewb_order_not_found' => __( 'No order found matching the provided order number.', 'wt-eu-withdrawal-button' ),
				'wbte_ewb_email_mismatch'  => __( 'The email address does not match the billing email on this order.', 'wt-eu-withdrawal-button' ),
				'wbte_ewb_not_eligible'    => __( 'This order is not eligible for withdrawal.', 'wt-eu-withdrawal-button' ),
				'wbte_ewb_already_submitted' => __( 'This withdrawal request has already been submitted.', 'wt-eu-withdrawal-button' ),
			);

			if ( isset( $messages[ $code ] ) ) {
				return $messages[ $code ];
			}

			return __( 'We could not verify your withdrawal request. Please try again.', 'wt-eu-withdrawal-button' );
		}

		if ( isset( $_GET['wbte_ewb_guest_queued'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['wbte_ewb_guest_queued'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return self::get_queue_success_message();
		}

		return '';
	}

	/**
	 * Whether the current notice is an error.
	 *
	 * @return bool
	 */
	public static function notice_is_error() {
		return ! empty( $_GET['wbte_ewb_verify_error'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
