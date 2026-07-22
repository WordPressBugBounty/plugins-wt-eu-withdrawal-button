<?php
/**
 * REST controller for the admin review banner.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_REST_Review_Banner
 *
 * @since 1.0.5
 */
class Wbte_Ewb_REST_Review_Banner extends Wbte_Ewb_REST_Controller {

	/**
	 * Route base.
	 *
	 * @since 1.0.5
	 * @var string
	 */
	protected $rest_base = 'review-banner';

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.5
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'review_banner_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'review_banner_permission_check' ),
					'args'                => array(
						'action' => array(
							'description'       => __( 'Review banner action.', 'wt-eu-withdrawal-button' ),
							'type'              => 'string',
							'required'          => true,
							'enum'              => array( 'dismiss', 'review', 'later' ),
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
	}

	/**
	 * Permission check for review banner endpoints.
	 *
	 * @since 1.0.5
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function review_banner_permission_check( $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return $this->error_response(
			'wbte_ewb_forbidden',
			__( 'You do not have permission to perform this action.', 'wt-eu-withdrawal-button' ),
			403
		);
	}

	/**
	 * Return the current review banner state.
	 *
	 * @since 1.0.5
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		return $this->success_response( Wbte_Ewb_Review_Banner::get_state() );
	}

	/**
	 * Handle a review banner action.
	 *
	 * @since 1.0.5
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$action = $request->get_param( 'action' );
		$result = Wbte_Ewb_Review_Banner::handle_action( $action );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response(
			Wbte_Ewb_Review_Banner::get_state(),
			__( 'Review banner updated.', 'wt-eu-withdrawal-button' )
		);
	}
}
