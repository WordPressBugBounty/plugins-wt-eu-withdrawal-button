<?php
/**
 * REST controller for withdrawal requests (admin).
 *
 * Handles listing, viewing, approving, and rejecting
 * withdrawal requests.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_REST_Requests
 *
 * Registers admin-facing REST routes for withdrawal request
 * management: list, detail, approve, and reject.
 *
 * @since 1.0.0
 */
class Wbte_Ewb_REST_Requests extends Wbte_Ewb_REST_Controller {

	/**
	 * Route base.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $rest_base = 'requests';

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		// GET /requests — paginated list.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// GET /requests/stats — dashboard statistics.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
				),
			)
		);

		// GET /requests/{id} — single request with logs.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(
						'id' => array(
							'description'       => __( 'Unique identifier for the withdrawal request.', 'wt-eu-withdrawal-button' ),
							'type'              => 'integer',
							'required'          => true,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// POST /requests/{id}/approve.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/approve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve_item' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(
						'id'   => array(
							'description'       => __( 'Unique identifier for the withdrawal request.', 'wt-eu-withdrawal-button' ),
							'type'              => 'integer',
							'required'          => true,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
						),
						'note' => array(
							'description'       => __( 'Optional note for the approval.', 'wt-eu-withdrawal-button' ),
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// POST /requests/{id}/reject.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/reject',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reject_item' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(
						'id'   => array(
							'description'       => __( 'Unique identifier for the withdrawal request.', 'wt-eu-withdrawal-button' ),
							'type'              => 'integer',
							'required'          => true,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
						),
						'note' => array(
							'description'       => __( 'Reason for rejecting the withdrawal request.', 'wt-eu-withdrawal-button' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
							'validate_callback' => function ( $value ) {
								$value = sanitize_textarea_field( $value );
								if ( empty( $value ) ) {
									return new WP_Error(
										'wbte_ewb_note_required',
										__( 'A note is required when rejecting a request.', 'wt-eu-withdrawal-button' )
									);
								}
								return true;
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Get the query params for collections.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array> Collection parameters.
	 */
	public function get_collection_params() {
		return array(
			'status'       => array(
				'description'       => __( 'Filter by request status.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( 'pending', 'approved', 'rejected' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'date_from'    => array(
				'description'       => __( 'Filter requests created on or after this date (YYYY-MM-DD).', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'format'            => 'date',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'date_to'      => array(
				'description'       => __( 'Filter requests created on or before this date (YYYY-MM-DD).', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'format'            => 'date',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'customer'     => array(
				'description'       => __( 'Filter by customer email or name.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'search' => array(
				'description'       => __( 'Search by request ID, order number or receipt hash.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'order_number' => array(
				'description'       => __( 'Filter by order number.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'verification_code' => array(
				'description'       => __( 'Filter by receipt hash (full or partial match).', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page'     => array(
				'description'       => __( 'Maximum number of items to be returned per page.', 'wt-eu-withdrawal-button' ),
				'type'              => 'integer',
				'required'          => false,
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'page'         => array(
				'description'       => __( 'Current page of the collection.', 'wt-eu-withdrawal-button' ),
				'type'              => 'integer',
				'required'          => false,
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	/**
	 * Retrieve a paginated list of withdrawal requests.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, WP_Error on failure.
	 */
	public function get_items( $request ) {
		$args = array(
			'status'       => $request->get_param( 'status' ),
			'date_from'    => $request->get_param( 'date_from' ),
			'date_to'      => $request->get_param( 'date_to' ),
			'customer'     => $request->get_param( 'customer' ),
			'search'            => $request->get_param( 'search' ),
			'order_number'      => $request->get_param( 'order_number' ),
			'verification_code' => $request->get_param( 'verification_code' ),
			'per_page'     => $request->get_param( 'per_page' ),
			'page'         => $request->get_param( 'page' ),
		);

		// Remove null values so the repository uses its own defaults.
		$args = array_filter(
			$args,
			function ( $value ) {
				return null !== $value;
			}
		);

		$repository = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'repository' ) : null;

		if ( ! $repository ) {
			return $this->error_response(
				'wbte_ewb_missing_dependency',
				__( 'Request repository is not available.', 'wt-eu-withdrawal-button' ),
				500
			);
		}

		$result = $repository->query( $args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$items      = isset( $result['items'] ) ? $result['items'] : array();
		$total      = isset( $result['total'] ) ? (int) $result['total'] : 0;
		$per_page   = isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;
		$total_pages = ( $per_page > 0 ) ? (int) ceil( $total / $per_page ) : 1;

		$data = array();
		foreach ( $items as $item ) {
			$data[] = is_object( $item ) && method_exists( $item, 'to_array' ) ? $item->to_array() : (array) $item;
		}

		/**
		 * Filters the collection of requests before returning via REST.
		 *
		 * @since 1.0.0
		 *
		 * @param array           $data    Array of request data arrays.
		 * @param WP_REST_Request $request The current REST request.
		 */
		$data = apply_filters( 'wbte_ewb_rest_requests_collection', $data, $request );

		$response = $this->success_response( $data );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}

	/**
	 * Retrieve a single withdrawal request with its activity logs.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, WP_Error on failure.
	 */
	public function get_item( $request ) {
		$id = absint( $request->get_param( 'id' ) );

		$repository = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'repository' ) : null;

		if ( ! $repository ) {
			return $this->error_response(
				'wbte_ewb_missing_dependency',
				__( 'Request repository is not available.', 'wt-eu-withdrawal-button' ),
				500
			);
		}

		$item = $repository->find( $id );

		if ( ! $item ) {
			return $this->error_response(
				'wbte_ewb_request_not_found',
				__( 'Withdrawal request not found.', 'wt-eu-withdrawal-button' ),
				404
			);
		}

		$data = is_object( $item ) && method_exists( $item, 'to_array' ) ? $item->to_array() : (array) $item;

		// Attach activity logs with resolved actor names.
		$logs = $repository->get_logs( $id );

		$user_cache = array();
		foreach ( $logs as &$log ) {
			$actor_id = isset( $log->actor_id ) ? absint( $log->actor_id ) : 0;
			if ( $actor_id && ! isset( $user_cache[ $actor_id ] ) ) {
				$user = get_userdata( $actor_id );
				$user_cache[ $actor_id ] = $user ? $user->display_name : '';
			}
			if ( 'customer' === $log->actor_type && 0 === $actor_id ) {
				$log->actor_name = __( 'Guest user', 'wt-eu-withdrawal-button' );
			} elseif ( $actor_id && ! empty( $user_cache[ $actor_id ] ) ) {
				$log->actor_name = $user_cache[ $actor_id ];
			} else {
				$log->actor_name = '';
			}

			$log->created_at_formatted = Wbte_Ewb_Request::format_datetime( $log->created_at );
		}
		unset( $log );

		$data['logs'] = $logs;

		/**
		 * Filters a single request before returning via REST.
		 *
		 * @since 1.0.0
		 *
		 * @param array           $data    Request data array including logs.
		 * @param WP_REST_Request $request The current REST request.
		 */
		$data = apply_filters( 'wbte_ewb_rest_request_data', $data, $request );

		return $this->success_response( $data );
	}

	/**
	 * Approve a withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, WP_Error on failure.
	 */
	public function approve_item( $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$note = $request->get_param( 'note' );

		$service = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'service' ) : null;

		if ( ! $service ) {
			return $this->error_response(
				'wbte_ewb_missing_dependency',
				__( 'Request service is not available.', 'wt-eu-withdrawal-button' ),
				500
			);
		}

		$result = $service->approve( $id, get_current_user_id(), $note );

		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_data();
			$status = isset( $status['status'] ) ? (int) $status['status'] : 400;
			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				$status
			);
		}

		$data = is_object( $result ) && method_exists( $result, 'to_array' ) ? $result->to_array() : (array) $result;

		return $this->success_response(
			$data,
			__( 'Withdrawal request approved successfully.', 'wt-eu-withdrawal-button' )
		);
	}

	/**
	 * Reject a withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, WP_Error on failure.
	 */
	public function reject_item( $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$note = $request->get_param( 'note' );

		$service = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'service' ) : null;

		if ( ! $service ) {
			return $this->error_response(
				'wbte_ewb_missing_dependency',
				__( 'Request service is not available.', 'wt-eu-withdrawal-button' ),
				500
			);
		}

		$result = $service->reject( $id, get_current_user_id(), $note );

		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_data();
			$status = isset( $status['status'] ) ? (int) $status['status'] : 400;
			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				$status
			);
		}

		$data = is_object( $result ) && method_exists( $result, 'to_array' ) ? $result->to_array() : (array) $result;

		return $this->success_response(
			$data,
			__( 'Withdrawal request rejected.', 'wt-eu-withdrawal-button' )
		);
	}

	/**
	 * Retrieve dashboard statistics for withdrawal requests.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, WP_Error on failure.
	 */
	public function get_stats( $request ) {
		$repository = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'repository' ) : null;

		if ( ! $repository ) {
			return $this->error_response(
				'wbte_ewb_missing_dependency',
				__( 'Request repository is not available.', 'wt-eu-withdrawal-button' ),
				500
			);
		}

		$stats = $repository->get_stats();

		return $this->success_response( $stats );
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
			'title'      => 'wbte-ewb-request',
			'type'       => 'object',
			'properties' => array(
				'id'              => array(
					'description' => __( 'Unique identifier for the withdrawal request.', 'wt-eu-withdrawal-button' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'order_id'        => array(
					'description' => __( 'Associated WooCommerce order ID.', 'wt-eu-withdrawal-button' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'order_number'    => array(
					'description' => __( 'Customer-facing order number.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'customer_email'  => array(
					'description' => __( 'Customer email address.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => array( 'view', 'edit' ),
				),
				'customer_user_id' => array(
					'description' => __( 'Customer user ID (0 or null for guests).', 'wt-eu-withdrawal-button' ),
					'type'        => array( 'integer', 'null' ),
					'context'     => array( 'view', 'edit' ),
				),
				'status'          => array(
					'description' => __( 'Current status of the withdrawal request.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'enum'        => array( 'pending', 'approved', 'rejected' ),
					'context'     => array( 'view', 'edit' ),
				),
				'request_type'    => array(
					'description' => __( 'Type of withdrawal request.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'enum'        => array( 'full', 'partial' ),
					'context'     => array( 'view', 'edit' ),
				),
				'items_json'      => array(
					'description' => __( 'JSON-encoded array of line items.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'reason'          => array(
					'description' => __( 'Customer-provided reason for withdrawal.', 'wt-eu-withdrawal-button' ),
					'type'        => array( 'string', 'null' ),
					'context'     => array( 'view', 'edit' ),
				),
				'created_at'      => array(
					'description' => __( 'Date the request was created (ISO 8601).', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'updated_at'      => array(
					'description' => __( 'Date the request was last updated (ISO 8601).', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'processed_by'    => array(
					'description' => __( 'Admin user ID who processed the request.', 'wt-eu-withdrawal-button' ),
					'type'        => array( 'integer', 'null' ),
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'processed_at'    => array(
					'description' => __( 'Date the request was processed (ISO 8601).', 'wt-eu-withdrawal-button' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'logs'            => array(
					'description' => __( 'Activity log entries for this request.', 'wt-eu-withdrawal-button' ),
					'type'        => 'array',
					'context'     => array( 'view' ),
					'readonly'    => true,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'          => array(
								'type' => 'integer',
							),
							'actor_type'  => array(
								'type' => 'string',
							),
							'actor_id'    => array(
								'type' => array( 'integer', 'null' ),
							),
							'action'      => array(
								'type' => 'string',
							),
							'from_status' => array(
								'type' => array( 'string', 'null' ),
							),
							'to_status'   => array(
								'type' => array( 'string', 'null' ),
							),
							'note'        => array(
								'type' => array( 'string', 'null' ),
							),
							'created_at'  => array(
								'type'   => 'string',
								'format' => 'date-time',
							),
						),
					),
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
