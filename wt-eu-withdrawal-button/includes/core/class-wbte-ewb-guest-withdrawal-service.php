<?php
/**
 * Guest withdrawal queue and email verification service.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Guest_Withdrawal_Service
 *
 * @since 1.1.0
 */
class Wbte_Ewb_Guest_Withdrawal_Service {

	/**
	 * Pending request repository.
	 *
	 * @var Wbte_Ewb_Pending_Request_Repository
	 */
	private $pending_repository;

	/**
	 * Withdrawal request repository.
	 *
	 * @var Wbte_Ewb_Request_Repository
	 */
	private $request_repository;

	/**
	 * Constructor.
	 *
	 * @param Wbte_Ewb_Pending_Request_Repository $pending_repository Pending repository.
	 * @param Wbte_Ewb_Request_Repository         $request_repository Request repository.
	 */
	public function __construct( Wbte_Ewb_Pending_Request_Repository $pending_repository, Wbte_Ewb_Request_Repository $request_repository ) {
		$this->pending_repository = $pending_repository;
		$this->request_repository = $request_repository;
	}

	/**
	 * Queue a guest withdrawal request and send a verification email when details match.
	 *
	 * Order number and billing email are validated against store data before
	 * queueing. Duplicate requests for the same order/customer are blocked.
	 * A generic success response is always returned when input is valid so
	 * mismatches and duplicates are not revealed to the submitter.
	 *
	 * @param array<string, mixed> $data Guest submission data.
	 * @return true|WP_Error
	 */
	public function queue_request( $data ) {
		$order_number = isset( $data['order_number'] ) ? sanitize_text_field( $data['order_number'] ) : '';
		$email        = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$reason       = isset( $data['reason'] ) ? sanitize_textarea_field( $data['reason'] ) : '';

		if ( '' === $order_number ) {
			return new WP_Error(
				'wbte_ewb_order_required',
				__( 'Please enter your order number.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error(
				'wbte_ewb_email_required',
				__( 'Please enter a valid email address.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( 'yes' === Wbte_Ewb_Settings::get( 'reason_required', 'no' ) && '' === $reason ) {
			return new WP_Error(
				'wbte_ewb_reason_required',
				__( 'Please provide a reason for your withdrawal request.', 'wt-eu-withdrawal-button' )
			);
		}

		$queue_check = $this->check_guest_queue_eligibility( $order_number, $email );

		if ( 'ineligible' === $queue_check ) {
			// Send ineligibility email once per order+email (throttled to one per 24h).
			$throttle_key = 'wbte_ewb_ineligible_' . md5( $order_number . '|' . strtolower( $email ) );
			if ( ! get_transient( $throttle_key ) ) {
				$order = $this->resolve_order_by_number( $order_number );
				if ( $order instanceof WC_Order ) {
					/**
					 * Fires when a guest submits a withdrawal for a valid but ineligible order.
					 *
					 * @since 1.1.0
					 *
					 * @param WC_Order $order        The ineligible order.
					 * @param string   $email        Guest billing email.
					 * @param string   $order_number Customer-facing order number.
					 */
					do_action( 'wbte_ewb_guest_order_ineligible_notification', $order, $email, $order_number );
					set_transient( $throttle_key, 1, DAY_IN_SECONDS );
				}
			}
			return true;
		}

		if ( ! $queue_check ) {
			return true;
		}

		$this->pending_repository->delete_expired();

		$expiry_hours = absint( Wbte_Ewb_Settings::get( 'guest_verification_expiry_hours', 48 ) );
		if ( $expiry_hours < 1 ) {
			$expiry_hours = 48;
		}

		$token = $this->generate_verify_token();

		$pending = $this->pending_repository->create(
			array(
				'order_number'   => $order_number,
				'customer_email' => $email,
				'reason'         => $reason,
				'verify_token'   => $token,
				'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + ( $expiry_hours * HOUR_IN_SECONDS ) ),
			)
		);

		if ( ! $pending ) {
			return new WP_Error(
				'wbte_ewb_queue_failed',
				__( 'Unable to queue your withdrawal request. Please try again.', 'wt-eu-withdrawal-button' )
			);
		}

		$this->send_verification_email( $pending );

		/**
		 * Fires after a guest withdrawal request is queued for email verification.
		 *
		 * @since 1.1.0
		 *
		 * @param Wbte_Ewb_Pending_Request $pending Queued pending request.
		 */
		do_action( 'wbte_ewb_guest_withdrawal_queued', $pending );

		return true;
	}

	/**
	 * Find a pending request by verification token.
	 *
	 * @param string $token Verification token.
	 * @return Wbte_Ewb_Pending_Request|null
	 */
	public function find_pending_by_token( $token ) {
		return $this->pending_repository->find_by_token( $token );
	}

	/**
	 * Verify a guest email address without creating the withdrawal request.
	 *
	 * @since 1.0.3
	 *
	 * @param string $token Verification token.
	 * @return Wbte_Ewb_Pending_Request|WP_Error
	 */
	public function verify_email( $token ) {
		$pending = $this->get_valid_pending_for_verification( $token );

		if ( is_wp_error( $pending ) ) {
			return $pending;
		}

		$order = $this->resolve_order_by_number( $pending->order_number );

		if ( ! $order ) {
			$this->mark_failed( $pending, __( 'No order found matching the provided order number.', 'wt-eu-withdrawal-button' ) );

			return new WP_Error(
				'wbte_ewb_order_not_found',
				__( 'No order found matching the provided order number.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( strtolower( $pending->customer_email ) !== strtolower( $order->get_billing_email() ) ) {
			$this->mark_failed( $pending, __( 'The email address does not match the order.', 'wt-eu-withdrawal-button' ) );

			return new WP_Error(
				'wbte_ewb_email_mismatch',
				__( 'The email address does not match the billing email on this order.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( ! $this->can_guest_submit_withdrawal( $order, $pending->customer_email ) ) {
			$this->mark_failed( $pending, __( 'This order is not eligible for withdrawal.', 'wt-eu-withdrawal-button' ) );

			return new WP_Error(
				'wbte_ewb_not_eligible',
				__( 'This order is not eligible for withdrawal.', 'wt-eu-withdrawal-button' )
			);
		}

		$this->pending_repository->update(
			$pending->id,
			array(
				'status'      => 'verified',
				'verified_at' => current_time( 'mysql', true ),
			)
		);

		$pending->status      = 'verified';
		$pending->verified_at = current_time( 'mysql', true );

		/**
		 * Fires after a guest email address is verified for a withdrawal request.
		 *
		 * @since 1.0.3
		 *
		 * @param Wbte_Ewb_Pending_Request $pending Verified pending request.
		 * @param WC_Order                 $order   WooCommerce order.
		 */
		do_action( 'wbte_ewb_guest_email_verified', $pending, $order );

		return $pending;
	}

	/**
	 * Submit a withdrawal request after guest email verification.
	 *
	 * @since 1.0.3
	 *
	 * @param string               $token Verification token.
	 * @param array<string, mixed> $data  Submission data (request_type, items).
	 * @return Wbte_Ewb_Request|WP_Error
	 */
	public function submit_verified_request( $token, $data ) {
		$pending = $this->pending_repository->find_by_token( $token );

		if ( ! $pending ) {
			return new WP_Error(
				'wbte_ewb_invalid_token',
				__( 'This verification link is invalid or has already been used.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( 'verified' !== $pending->status ) {
			return new WP_Error(
				'wbte_ewb_invalid_token',
				__( 'This verification link is invalid or has already been used.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( $pending->withdrawal_id > 0 ) {
			return new WP_Error(
				'wbte_ewb_already_submitted',
				__( 'This withdrawal request has already been submitted.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( strtotime( $pending->expires_at . ' UTC' ) < time() ) {
			$this->pending_repository->update(
				$pending->id,
				array(
					'status'         => 'expired',
					'failure_reason' => __( 'Verification link expired.', 'wt-eu-withdrawal-button' ),
				)
			);

			return new WP_Error(
				'wbte_ewb_token_expired',
				__( 'This verification link has expired. Please submit a new withdrawal request.', 'wt-eu-withdrawal-button' )
			);
		}

		$order = $this->resolve_order_by_number( $pending->order_number );

		if ( ! $order ) {
			return new WP_Error(
				'wbte_ewb_order_not_found',
				__( 'No order found matching the provided order number.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( strtolower( $pending->customer_email ) !== strtolower( $order->get_billing_email() ) ) {
			return new WP_Error(
				'wbte_ewb_email_mismatch',
				__( 'The email address does not match the billing email on this order.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( ! $this->can_guest_submit_withdrawal( $order, $pending->customer_email ) ) {
			return new WP_Error(
				'wbte_ewb_not_eligible',
				__( 'This order is not eligible for withdrawal.', 'wt-eu-withdrawal-button' )
			);
		}

		$request_type = isset( $data['request_type'] ) ? sanitize_text_field( $data['request_type'] ) : 'full';
		$items        = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();

		if ( ! in_array( $request_type, array( 'full', 'partial' ), true ) ) {
			$request_type = 'full';
		}

		if ( 'partial' === $request_type && empty( $items ) ) {
			return new WP_Error(
				'wbte_ewb_items_required',
				__( 'Please select at least one item to withdraw.', 'wt-eu-withdrawal-button' )
			);
		}

		$service = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'service' ) : null;

		if ( ! $service ) {
			return new WP_Error(
				'wbte_ewb_service_unavailable',
				__( 'The withdrawal service is temporarily unavailable.', 'wt-eu-withdrawal-button' )
			);
		}

		$submit_data = array(
			'order_id'     => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'email'        => $pending->customer_email,
			'request_type' => $request_type,
			'reason'       => $pending->reason,
			'user_id'      => 0,
		);

		if ( 'partial' === $request_type ) {
			$submit_data['items'] = $items;
		}

		$result = $service->submit( $submit_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->pending_repository->update(
			$pending->id,
			array(
				'withdrawal_id' => $result->id,
			)
		);

		/**
		 * Fires after a verified guest withdrawal request is submitted.
		 *
		 * @since 1.1.0
		 *
		 * @param Wbte_Ewb_Pending_Request $pending Queued request.
		 * @param Wbte_Ewb_Request         $request Created withdrawal request.
		 * @param WC_Order                 $order   WooCommerce order.
		 */
		do_action( 'wbte_ewb_guest_withdrawal_verified', $pending, $result, $order );

		return $result;
	}

	/**
	 * Verify a queued guest request and create the real withdrawal request.
	 *
	 * @deprecated 1.0.3 Use verify_email() and submit_verified_request() instead.
	 *
	 * @param string $token Verification token.
	 * @return Wbte_Ewb_Request|WP_Error
	 */
	public function verify_and_process( $token ) {
		$pending = $this->verify_email( $token );

		if ( is_wp_error( $pending ) ) {
			return $pending;
		}

		return $this->submit_verified_request(
			$token,
			array(
				'request_type' => 'full',
			)
		);
	}

	/**
	 * Resolve an order number to a WC_Order object.
	 *
	 * @since 1.0.3
	 *
	 * @param string $order_number Order number.
	 * @return WC_Order|false
	 */
	public function resolve_order_by_number( $order_number ) {
		if ( class_exists( 'Wbte_Ewb_Sequential_Orders' ) ) {
			$seq   = new Wbte_Ewb_Sequential_Orders();
			$order = $seq->find_order_by_number( $order_number );
			if ( $order ) {
				return $order;
			}
		}

		$order = wc_get_order( absint( $order_number ) );

		return ( $order && $order->get_id() ) ? $order : false;
	}

	/**
	 * Determine whether a guest may queue or submit a withdrawal for an order.
	 *
	 * @since 1.0.3
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $email Customer billing email.
	 * @return bool
	 */
	public function can_guest_submit_withdrawal( $order, $email ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$email = sanitize_email( $email );

		if ( '' === $email || strtolower( $email ) !== strtolower( $order->get_billing_email() ) ) {
			return false;
		}

		$eligibility = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'eligibility' ) : new Wbte_Ewb_Eligibility();

		if ( ! $eligibility instanceof Wbte_Ewb_Eligibility ) {
			return false;
		}

		$customer_requests = $this->request_repository->find_active_by_order_and_email( $order->get_id(), $email );

		foreach ( $customer_requests as $request ) {
			if ( 'full' === $request->request_type ) {
				return false;
			}
		}

		$allow_partial = 'yes' === Wbte_Ewb_Settings::get( 'allow_partial_withdrawals', 'yes' );

		if ( ! $allow_partial && ! empty( $customer_requests ) ) {
			return false;
		}

		if ( ! $eligibility->is_order_eligible( $order ) ) {
			return false;
		}

		if ( $allow_partial && ! empty( $customer_requests ) ) {
			return $eligibility->has_remaining_withdrawable_quantity( $order );
		}

		return true;
	}

	/**
	 * Build the email verification URL for a pending request.
	 *
	 * @param Wbte_Ewb_Pending_Request $pending Pending request.
	 * @return string
	 */
	public function get_verification_url( $pending ) {
		$base = Wbte_Ewb_Settings::get_withdrawal_page_url();

		if ( ! $base ) {
			$base = home_url( '/' );
		}

		// Get the translated URL for the order's language (WPML/Polylang/TranslatePress).
		$order = $this->resolve_order_by_number( $pending->order_number );
		if ( $order instanceof WC_Order ) {
			$order_lang = Wbte_Ewb_Multilingual::get_order_language( $order );
			if ( $order_lang ) {
				$base = apply_filters( 'wpml_permalink', $base, $order_lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			}
		}

		return add_query_arg(
			array(
				'wbte_ewb_verify' => rawurlencode( $pending->verify_token ),
			),
			$base
		);
	}

	/**
	 * Build the withdrawal form URL for a verified guest session.
	 *
	 * @since 1.0.3
	 *
	 * @param Wbte_Ewb_Pending_Request $pending Verified pending request.
	 * @return string
	 */
	public function get_verified_form_url( $pending ) {
		$base = Wbte_Ewb_Settings::get_withdrawal_page_url();

		if ( ! $base ) {
			$base = home_url( '/' );
		}

		return add_query_arg(
			array(
				'wbte_ewb_guest_token' => rawurlencode( $pending->verify_token ),
			),
			$base
		);
	}

	/**
	 * Load a verified pending request that is ready for item selection.
	 *
	 * @since 1.0.3
	 *
	 * @param string $token Verification token.
	 * @return Wbte_Ewb_Pending_Request|WP_Error
	 */
	public function get_verified_pending_for_form( $token ) {
		$pending = $this->pending_repository->find_by_token( $token );

		if ( ! $pending || 'verified' !== $pending->status ) {
			return new WP_Error(
				'wbte_ewb_invalid_token',
				__( 'This verification session is invalid or has expired.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( $pending->withdrawal_id > 0 ) {
			return new WP_Error(
				'wbte_ewb_already_submitted',
				__( 'This withdrawal request has already been submitted.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( strtotime( $pending->expires_at . ' UTC' ) < time() ) {
			return new WP_Error(
				'wbte_ewb_token_expired',
				__( 'This verification link has expired. Please submit a new withdrawal request.', 'wt-eu-withdrawal-button' )
			);
		}

		$order = $this->resolve_order_by_number( $pending->order_number );

		if ( ! $order || ! $this->can_guest_submit_withdrawal( $order, $pending->customer_email ) ) {
			return new WP_Error(
				'wbte_ewb_not_eligible',
				__( 'This order is not eligible for withdrawal.', 'wt-eu-withdrawal-button' )
			);
		}

		return $pending;
	}

	/**
	 * Send the verification email for a pending request.
	 *
	 * @param Wbte_Ewb_Pending_Request $pending Pending request.
	 * @return void
	 */
	private function send_verification_email( $pending ) {
		if ( function_exists( 'WC' ) ) {
			WC()->mailer();
			do_action( 'wbte_ewb_guest_verification_notification', $pending );
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Verify your withdrawal request', 'wt-eu-withdrawal-button' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$message = sprintf(
			/* translators: 1: verification URL, 2: order number */
			__( "Please verify your withdrawal request by clicking the link below:\n\n%1\$s\n\nOrder: %2\$s", 'wt-eu-withdrawal-button' ),
			esc_url( $this->get_verification_url( $pending ) ),
			$pending->order_number
		);

		wp_mail( $pending->customer_email, $subject, $message );
	}

	/**
	 * Mark a pending request as failed.
	 *
	 * @param Wbte_Ewb_Pending_Request $pending Pending request.
	 * @param string                   $reason  Failure reason.
	 * @return void
	 */
	private function mark_failed( $pending, $reason ) {
		$this->pending_repository->update(
			$pending->id,
			array(
				'status'         => 'failed',
				'failure_reason' => sanitize_text_field( $reason ),
			)
		);
	}

	/**
	 * Determine whether a guest request should be queued and emailed.
	 *
	 * @param string $order_number Order number.
	 * @param string $email        Billing email address.
	 * @return bool
	 */
	private function can_queue_guest_request( $order_number, $email ) {
		$check = $this->check_guest_queue_eligibility( $order_number, $email );
		return true === $check;
	}

	/**
	 * Check guest queue eligibility with reason.
	 *
	 * @since 1.1.0
	 *
	 * @param string $order_number Order number.
	 * @param string $email        Billing email.
	 * @return true|string|false True if eligible, 'ineligible' if order matched but not eligible, false otherwise.
	 */
	private function check_guest_queue_eligibility( $order_number, $email ) {
		$order = $this->resolve_order_by_number( $order_number );

		if ( ! $order || ! ( $order instanceof WC_Order ) || $order->get_type() === 'shop_order_refund' ) {
			return false;
		}

		$email = sanitize_email( $email );

		// Email doesn't match — don't reveal anything.
		if ( '' === $email || strtolower( $email ) !== strtolower( $order->get_billing_email() ) ) {
			return false;
		}

		// Email matches. Check if order is eligible for withdrawal.
		if ( ! $this->can_guest_submit_withdrawal( $order, $email ) ) {
			return 'ineligible';
		}

		// Already has an open verification session.
		if ( $this->pending_repository->has_open_guest_session( $order_number, $email ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate a pending request before first-time email verification.
	 *
	 * @param string $token Verification token.
	 * @return Wbte_Ewb_Pending_Request|WP_Error
	 */
	private function get_valid_pending_for_verification( $token ) {
		$pending = $this->pending_repository->find_by_token( $token );

		if ( ! $pending ) {
			return new WP_Error(
				'wbte_ewb_invalid_token',
				__( 'This verification link is invalid or has already been used.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( 'pending_verification' !== $pending->status ) {
			return new WP_Error(
				'wbte_ewb_invalid_token',
				__( 'This verification link is invalid or has already been used.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( strtotime( $pending->expires_at . ' UTC' ) < time() ) {
			$this->pending_repository->update(
				$pending->id,
				array(
					'status'         => 'expired',
					'failure_reason' => __( 'Verification link expired.', 'wt-eu-withdrawal-button' ),
				)
			);

			return new WP_Error(
				'wbte_ewb_token_expired',
				__( 'This verification link has expired. Please submit a new withdrawal request.', 'wt-eu-withdrawal-button' )
			);
		}

		return $pending;
	}

	/**
	 * Generate a unique verification token.
	 *
	 * @return string
	 */
	private function generate_verify_token() {
		return wp_generate_password( 48, false, false );
	}
}
