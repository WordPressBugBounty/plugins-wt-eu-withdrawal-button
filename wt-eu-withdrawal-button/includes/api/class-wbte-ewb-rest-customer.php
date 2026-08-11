<?php
/**
 * REST controller for customer-facing withdrawal endpoints.
 *
 * Handles submission of new withdrawal requests and retrieval
 * of eligible order items.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_REST_Customer
 *
 * Registers customer-facing REST routes for submitting withdrawal
 * requests and querying eligible items on an order.
 *
 * @since 1.0.0
 */
class Wbte_Ewb_REST_Customer extends Wbte_Ewb_REST_Controller {

	/**
	 * Route base.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $rest_base = 'customer';

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		// POST /customer/guest-pending-requests — queue a guest withdrawal for email verification.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/guest-pending-requests',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_guest_pending_request' ),
					'permission_callback' => array( $this, 'guest_queue_permission_check' ),
					'args'                => array(
						'order_number' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'email'        => array(
							'type'              => 'string',
							'format'            => 'email',
							'required'          => true,
							'sanitize_callback' => 'sanitize_email',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'reason'       => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// POST /customer/guest-verified-requests — submit after email verification.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/guest-verified-requests',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_guest_verified_request' ),
					'permission_callback' => array( $this, 'guest_queue_permission_check' ),
					'args'                => array(
						'verify_token' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'request_type' => array(
							'type'              => 'string',
							'required'          => true,
							'enum'              => array( 'full', 'partial' ),
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'items'        => array(
							'type'              => 'array',
							'required'          => false,
							'items'             => array(
								'type'       => 'object',
								'properties' => array(
									'line_item_id' => array(
										'type'     => 'integer',
										'required' => true,
									),
									'product_id'   => array(
										'type'     => 'integer',
										'required' => true,
									),
									'qty'          => array(
										'type'     => 'integer',
										'required' => true,
										'minimum'  => 1,
									),
								),
							),
							'sanitize_callback' => array( $this, 'sanitize_items' ),
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// POST /customer/requests — submit a new withdrawal request.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/requests',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_request' ),
					'permission_callback' => array( $this, 'customer_permission_check' ),
					'args'                => apply_filters( 'wbte_ewb_create_request_args', $this->get_create_request_args() ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// GET /customer/orders/{order_id}/eligible-items.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/orders/(?P<order_id>[\d]+)/eligible-items',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_eligible_items' ),
					'permission_callback' => array( $this, 'customer_permission_check' ),
					'args'                => array(
						'order_id'     => array(
							'description'       => __( 'WooCommerce order ID.', 'wt-eu-withdrawal-button' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'order_number' => array(
							'description'       => __( 'Order number (required for guest access).', 'wt-eu-withdrawal-button' ),
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'email'        => array(
							'description'       => __( 'Customer email (required for guest access).', 'wt-eu-withdrawal-button' ),
							'type'              => 'string',
							'format'            => 'email',
							'required'          => false,
							'sanitize_callback' => 'sanitize_email',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
	}

	/**
	 * Get argument definitions for the create request endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array> Argument definitions.
	 */
	protected function get_create_request_args() {
		return array(
			'order_id'     => array(
				'description'       => __( 'WooCommerce order ID. Provide this or order_number.', 'wt-eu-withdrawal-button' ),
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'order_number' => array(
				'description'       => __( 'Customer-facing order number. Provide this or order_id.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'email'        => array(
				'description'       => __( 'Customer email address (required for guests).', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'format'            => 'email',
				'required'          => false,
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'request_type' => array(
				'description'       => __( 'Whether this is a full or partial withdrawal.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => true,
				'enum'              => array( 'full', 'partial' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'items'        => array(
				'description'       => __( 'Line items for partial withdrawal. Required if request_type is partial.', 'wt-eu-withdrawal-button' ),
				'type'              => 'array',
				'required'          => false,
				'items'             => array(
					'type'       => 'object',
					'properties' => array(
						'line_item_id' => array(
							'description' => __( 'WooCommerce line item ID.', 'wt-eu-withdrawal-button' ),
							'type'        => 'integer',
							'required'    => true,
						),
						'product_id'   => array(
							'description' => __( 'WooCommerce product ID.', 'wt-eu-withdrawal-button' ),
							'type'        => 'integer',
							'required'    => true,
						),
						'qty'          => array(
							'description' => __( 'Quantity to withdraw.', 'wt-eu-withdrawal-button' ),
							'type'        => 'integer',
							'required'    => true,
							'minimum'     => 1,
						),
					),
				),
				'sanitize_callback' => array( $this, 'sanitize_items' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
			'reason'       => array(
				'description'       => __( 'Customer reason for the withdrawal.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	/**
	 * Sanitize the items array.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $items Raw items input.
	 * @return array Sanitized items array.
	 */
	public function sanitize_items( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$sanitized[] = array(
				'line_item_id' => isset( $item['line_item_id'] ) ? absint( $item['line_item_id'] ) : 0,
				'product_id'   => isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0,
				'qty'          => isset( $item['qty'] ) ? absint( $item['qty'] ) : 0,
			);
		}

		return $sanitized;
	}

	/**
	 * Submit a new withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, WP_Error on failure.
	 */
	public function create_guest_pending_request( $request ) {
		$guest_service = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'guest_withdrawal' ) : null;

		if ( ! $guest_service ) {
			return $this->error_response(
				'wbte_ewb_missing_dependency',
				__( 'Guest withdrawal service is not available.', 'wt-eu-withdrawal-button' ),
				500
			);
		}

		$order_number = $request->get_param( 'order_number' );

		$result = $guest_service->queue_request(
			array(
				'order_number' => $order_number,
				'email'        => $request->get_param( 'email' ),
				'reason'       => $request->get_param( 'reason' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = isset( $error_data['status'] ) ? (int) $error_data['status'] : 400;

			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				$status
			);
		}

		// Switch WPML language to the order's language for the response message.
		$order = $guest_service->resolve_order_by_number( $order_number );
		if ( $order instanceof WC_Order ) {
			$order_lang = $order->get_meta( 'wpml_language' );
			if ( $order_lang ) {
				do_action( 'wpml_switch_language', $order_lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			}
		}

		$response = $this->success_response(
			array(),
			Wbte_Ewb_Guest_Verification::get_queue_success_message(),
			201
		);

		if ( ! empty( $order_lang ) ) {
			do_action( 'wpml_switch_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		return $response;
	}

	/**
	 * Submit a withdrawal request after guest email verification.
	 *
	 * @since 1.0.3
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, WP_Error on failure.
	 */
	public function create_guest_verified_request( $request ) {
		$guest_service = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'guest_withdrawal' ) : null;

		if ( ! $guest_service ) {
			return $this->error_response(
				'wbte_ewb_missing_dependency',
				__( 'Guest withdrawal service is not available.', 'wt-eu-withdrawal-button' ),
				500
			);
		}

		$request_type = $request->get_param( 'request_type' );
		$items        = $request->get_param( 'items' );

		if ( 'partial' === $request_type && ( empty( $items ) || ! is_array( $items ) ) ) {
			return $this->error_response(
				'wbte_ewb_items_required',
				__( 'Please select at least one item to withdraw.', 'wt-eu-withdrawal-button' ),
				400
			);
		}

		$result = $guest_service->submit_verified_request(
			$request->get_param( 'verify_token' ),
			array(
				'request_type' => $request_type,
				'items'        => $items,
			)
		);

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = isset( $error_data['status'] ) ? (int) $error_data['status'] : 400;

			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				$status
			);
		}

		$data = is_object( $result ) && method_exists( $result, 'to_array' ) ? $result->to_array() : (array) $result;

		return $this->success_response(
			$data,
			__( 'Withdrawal request submitted successfully.', 'wt-eu-withdrawal-button' ),
			201
		);
	}

	/**
	 * Submit a new withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, WP_Error on failure.
	 */
	public function create_request( $request ) {
		if ( ! is_user_logged_in() ) {
			return $this->error_response(
				'wbte_ewb_guest_verification_required',
				__( 'Guest withdrawal requests must be verified by email before they are created.', 'wt-eu-withdrawal-button' ),
				403
			);
		}

		$order_id     = $request->get_param( 'order_id' );
		$order_number = $request->get_param( 'order_number' );
		$email        = $request->get_param( 'email' );
		$request_type = $request->get_param( 'request_type' );
		$items        = $request->get_param( 'items' );
		$reason       = $request->get_param( 'reason' );

		// Must provide at least one order identifier.
		if ( empty( $order_id ) && empty( $order_number ) ) {
			return $this->error_response(
				'wbte_ewb_missing_order',
				__( 'Either order_id or order_number is required.', 'wt-eu-withdrawal-button' ),
				400
			);
		}

		// Partial withdrawals require items.
		if ( 'partial' === $request_type && ( empty( $items ) || ! is_array( $items ) ) ) {
			return $this->error_response(
				'wbte_ewb_items_required',
				__( 'Items are required for partial withdrawal requests.', 'wt-eu-withdrawal-button' ),
				400
			);
		}

		// Resolve the order.
		$order = null;

		if ( ! empty( $order_id ) ) {
			$order = wc_get_order( absint( $order_id ) );
		}

		if ( ! $order && ! empty( $order_number ) ) {
			$order = $this->resolve_order_by_number( sanitize_text_field( $order_number ) );
		}

		if ( ! $order ) {
			return $this->error_response(
				'wbte_ewb_order_not_found',
				__( 'The specified order could not be found.', 'wt-eu-withdrawal-button' ),
				404
			);
		}

		// Ownership validation.
		$validation = $this->validate_order_ownership( $order, $request );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Check for required reason setting.
		if ( class_exists( 'Wbte_Ewb_Settings' ) ) {
			$reason_required = Wbte_Ewb_Settings::get( 'reason_required', 'no' );
			if ( 'yes' === $reason_required && empty( $reason ) ) {
				return $this->error_response(
					'wbte_ewb_reason_required',
					__( 'A reason is required for withdrawal requests.', 'wt-eu-withdrawal-button' ),
					400
				);
			}
		}

		// Build submission data.
		$submit_data = array(
			'order_id'     => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'email'        => is_user_logged_in() ? $order->get_billing_email() : sanitize_email( $email ),
			'user_id'      => get_current_user_id(),
			'request_type' => $request_type,
			'items'        => $items,
			'reason'       => $reason,
		);

		/**
		 * Filters the withdrawal request data before submission.
		 *
		 * @since 1.0.0
		 *
		 * @param array           $submit_data The data to be submitted.
		 * @param WC_Order        $order       The WooCommerce order.
		 * @param WP_REST_Request $request     The current REST request.
		 */
		$submit_data = apply_filters( 'wbte_ewb_before_request_submit', $submit_data, $order, $request );

		if ( is_wp_error( $submit_data ) ) {
			$error_data = $submit_data->get_error_data();
			$status     = isset( $error_data['status'] ) ? (int) $error_data['status'] : 400;
			return $this->error_response(
				$submit_data->get_error_code(),
				$submit_data->get_error_message(),
				$status
			);
		}

		$service = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'service' ) : null;

		if ( ! $service ) {
			return $this->error_response(
				'wbte_ewb_missing_dependency',
				__( 'Request service is not available.', 'wt-eu-withdrawal-button' ),
				500
			);
		}

		$result = $service->submit( $submit_data );

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = isset( $error_data['status'] ) ? (int) $error_data['status'] : 400;
			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				$status
			);
		}

		$data = is_object( $result ) && method_exists( $result, 'to_array' ) ? $result->to_array() : (array) $result;

		return $this->success_response(
			$data,
			__( 'Withdrawal request submitted successfully.', 'wt-eu-withdrawal-button' ),
			201
		);
	}

	/**
	 * Validate that the current user or guest owns the order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order        $order   The WooCommerce order.
	 * @param WP_REST_Request $request The current REST request.
	 * @return true|WP_Error True if validated, WP_Error otherwise.
	 */
	protected function validate_order_ownership( $order, $request ) {
		// Admins can access any order.
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		if ( is_user_logged_in() ) {
			$current_user_id   = get_current_user_id();
			$order_customer_id = $order->get_customer_id();

			if ( (int) $order_customer_id !== (int) $current_user_id ) {
				return $this->error_response(
					'wbte_ewb_order_not_yours',
					__( 'This order does not belong to your account.', 'wt-eu-withdrawal-button' ),
					403
				);
			}

			return true;
		}

		// Guest: validate order_number + email match.
		$order_number = sanitize_text_field( (string) $request->get_param( 'order_number' ) );
		$email        = sanitize_email( $request->get_param( 'email' ) );
		$order_email  = $order->get_billing_email();

		if ( empty( $order_number ) ) {
			return $this->error_response(
				'wbte_ewb_order_number_required',
				__( 'Order number is required for guest withdrawal requests.', 'wt-eu-withdrawal-button' ),
				400
			);
		}

		if ( empty( $email ) ) {
			return $this->error_response(
				'wbte_ewb_email_required',
				__( 'Email is required for guest withdrawal requests.', 'wt-eu-withdrawal-button' ),
				400
			);
		}

		if ( ! $this->order_number_matches_order( $order, $order_number ) ) {
			return $this->error_response(
				'wbte_ewb_order_number_mismatch',
				__( 'The order number does not match this order.', 'wt-eu-withdrawal-button' ),
				403
			);
		}

		if ( strtolower( $email ) !== strtolower( $order_email ) ) {
			return $this->error_response(
				'wbte_ewb_email_mismatch',
				__( 'The email address does not match the order.', 'wt-eu-withdrawal-button' ),
				403
			);
		}

		return true;
	}

	/**
	 * Get eligible items for a given order.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, WP_Error on failure.
	 */
	public function get_eligible_items( $request ) {
		$order_id = absint( $request->get_param( 'order_id' ) );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return $this->error_response(
				'wbte_ewb_order_not_found',
				__( 'The specified order could not be found.', 'wt-eu-withdrawal-button' ),
				404
			);
		}

		// Validate ownership.
		$validation = $this->validate_order_ownership( $order, $request );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$eligibility = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'eligibility' ) : null;

		if ( ! $eligibility ) {
			return $this->error_response(
				'wbte_ewb_missing_dependency',
				__( 'Eligibility service is not available.', 'wt-eu-withdrawal-button' ),
				500
			);
		}

		$eligible_items = $eligibility->get_eligible_items( $order );

		if ( is_wp_error( $eligible_items ) ) {
			return $eligible_items;
		}

		// Build a set of eligible item IDs for quick lookup.
		$eligible_ids = array();
		foreach ( $eligible_items as $eid => $eitem ) {
			$eligible_ids[] = absint( $eid );
		}

		$remaining_qtys = $eligibility->get_remaining_quantities( $order );
		$data           = array();

		// Collect bundled child items indexed by parent bundle cart key.
		$bundled_children = array();
		$bundle_key_to_item_id = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$bundled_by = $item->get_meta( '_bundled_by', true );
			if ( $bundled_by ) {
				$bundled_children[ $bundled_by ][] = $item;
				continue;
			}
			$bundle_key = $item->get_meta( '_bundle_cart_key', true );
			if ( $bundle_key ) {
				$bundle_key_to_item_id[ $bundle_key ] = absint( $item_id );
			}
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			// Skip bundled child items — they are nested under their parent below.
			if ( $item->get_meta( '_bundled_by', true ) ) {
				continue;
			}

			$product       = $item->get_product();
			$thumbnail_url = '';
			$withdrawable  = in_array( absint( $item_id ), $eligible_ids, true );
			$reason        = '';
			$ordered_qty   = $item instanceof \WC_Order_Item_Product ? absint( $item->get_quantity() ) : 0;
			$display_qty   = $ordered_qty;

			if ( $product ) {
				$image_id      = $product->get_image_id();
				$thumbnail_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );

				// Determine exclusion reason.
				if ( ! $withdrawable ) {
					$reason = $eligibility->get_product_exclusion_reason( $product );

					if ( '' === $reason ) {
						$reason = __( 'This item is not eligible for withdrawal.', 'wt-eu-withdrawal-button' );
					}
				}
			}

			if ( $withdrawable ) {
				$remaining_qty = isset( $remaining_qtys[ $item_id ] ) ? absint( $remaining_qtys[ $item_id ] ) : 0;

				if ( $remaining_qty < 1 ) {
					$withdrawable = false;
					$reason       = __( 'This item is already included in a pending or approved withdrawal request.', 'wt-eu-withdrawal-button' );
				} else {
					$display_qty = $remaining_qty;
				}
			}

			$line_total = Wbte_Ewb_Request::get_line_item_total_incl_tax( $item );

			// Collect bundled child names for this parent and include
			// individually-priced child totals in the parent line total.
			$children = array();
			$bundle_key = $item->get_meta( '_bundle_cart_key', true );
			if ( $bundle_key && ! empty( $bundled_children[ $bundle_key ] ) ) {
				foreach ( $bundled_children[ $bundle_key ] as $child_item ) {
					$child_qty = $child_item instanceof \WC_Order_Item_Product ? absint( $child_item->get_quantity() ) : 1;
					$children[] = array(
						'name'     => $child_item->get_name(),
						'quantity' => $child_qty,
					);

					// Add individually-priced child item totals to the parent.
					$child_total = Wbte_Ewb_Request::get_line_item_total_incl_tax( $child_item );
					if ( is_numeric( $child_total ) && (float) $child_total > 0 ) {
						$line_total = (float) $line_total + (float) $child_total;
					}
				}
				$line_total = wc_format_decimal( $line_total, '' );
			}

			// Calculate proportional total for the withdrawable quantity.
			if ( $display_qty < $ordered_qty && $ordered_qty > 0 && is_numeric( $line_total ) ) {
				$line_total = round( ( (float) $line_total / $ordered_qty ) * $display_qty, 2 );
			}

			if ( $item instanceof \WC_Order_Item_Product ) {
				/**
				 * Filters the line total for order items on the customer withdrawal form.
				 *
				 * @since 1.0.3
				 *
				 * @param float|string           $line_total Line total (incl. tax by default when present).
				 * @param \WC_Order_Item_Product $line_item  Order line item.
				 * @param \WC_Order              $order      Order object.
				 * @param WP_REST_Request        $request    REST request.
				 */
				$line_total = apply_filters( 'wbte_ewb_frontend_item_line_total', $line_total, $item, $order, $request );
			}

			$item_data = array(
				'line_item_id'     => absint( $item->get_id() ),
				'product_id'       => absint( $item->get_product_id() ),
				'name'             => $item->get_name(),
				'quantity'         => $display_qty,
				'ordered_quantity' => $ordered_qty,
				'total'            => $line_total,
				'thumbnail_url'    => esc_url( $thumbnail_url ),
				'withdrawable'     => $withdrawable,
				'reason'           => $reason,
			);

			if ( ! empty( $children ) ) {
				$item_data['bundled_items'] = $children;
			}

			$data[] = $item_data;
		}

		/**
		 * Filters the eligible items returned to the customer via REST.
		 *
		 * @since 1.0.0
		 *
		 * @param array           $data    Array of eligible item data.
		 * @param WC_Order        $order   The WooCommerce order.
		 * @param WP_REST_Request $request The current REST request.
		 */
		$data = apply_filters( 'wbte_ewb_rest_eligible_items', $data, $order, $request );

		$response = $this->success_response( $data );

		/**
		 * Filters the eligible items REST response before it is returned.
		 *
		 * Addons can use this to attach additional data (e.g. payment method info)
		 * to the response envelope without modifying the items array.
		 *
		 * @since 1.1.0
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WC_Order         $order    The WooCommerce order.
		 * @param WP_REST_Request  $request  The current REST request.
		 */
		return apply_filters( 'wbte_ewb_rest_eligible_items_response', $response, $order, $request );
	}

	/**
	 * Get the withdrawal request schema for REST responses.
	 *
	 * @since 1.0.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wbte-ewb-customer-request',
			'type'       => 'object',
			'properties' => array(
				'id'             => array(
					'description' => __( 'Unique identifier for the withdrawal request.', 'wt-eu-withdrawal-button' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'order_id'       => array(
					'description' => __( 'WooCommerce order ID.', 'wt-eu-withdrawal-button' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'order_number'   => array(
					'description' => __( 'Customer-facing order number.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'status'         => array(
					'description' => __( 'Status of the withdrawal request.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'enum'        => array( 'pending', 'approved', 'rejected' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'request_type'   => array(
					'description' => __( 'Full or partial withdrawal.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'enum'        => array( 'full', 'partial' ),
					'context'     => array( 'view', 'edit' ),
				),
				'items'          => array(
					'description' => __( 'Line items included in the withdrawal.', 'wt-eu-withdrawal-button' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'line_item_id' => array(
								'type' => 'integer',
							),
							'product_id'   => array(
								'type' => 'integer',
							),
							'qty'          => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
						),
					),
				),
				'reason'         => array(
					'description' => __( 'Customer reason for the withdrawal.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'created_at'     => array(
					'description' => __( 'Date the request was created.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
