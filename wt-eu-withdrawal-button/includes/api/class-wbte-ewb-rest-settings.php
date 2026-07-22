<?php
/**
 * REST controller for plugin settings (admin).
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_REST_Settings
 *
 * Registers admin-facing REST routes for reading and
 * updating plugin settings.
 *
 * @since 1.0.0
 */
class Wbte_Ewb_REST_Settings extends Wbte_Ewb_REST_Controller {

	/**
	 * Route base.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $rest_base = 'settings';

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_items' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => $this->get_update_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Get argument definitions for the settings update endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array> Argument definitions.
	 */
	protected function get_update_args() {
		return array(
			'withdrawal_page'           => array(
				'description'       => __( 'Page ID for the withdrawal form.', 'wt-eu-withdrawal-button' ),
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'embed_footer_link'         => array(
				'description'       => __( 'Whether to show a withdrawal link in the site footer.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( 'yes', 'no' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'display_scope'             => array(
				'description'       => __( 'Where to display the footer link.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( 'global', 'woocommerce_only' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'allow_partial_withdrawals' => array(
				'description'       => __( 'Whether to allow partial order withdrawals.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( 'yes', 'no' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'withdrawal_period'         => array(
				'description'       => __( 'Number of days within which withdrawal is allowed.', 'wt-eu-withdrawal-button' ),
				'type'              => 'integer',
				'required'          => false,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $value, $request, $param ) {
					$value = absint( $value );
					if ( $value < 1 ) {
						return new WP_Error(
							'wbte_ewb_invalid_period',
							/* translators: %s: parameter name */
							sprintf( __( '%s must be at least 1.', 'wt-eu-withdrawal-button' ), $param )
						);
					}
					return true;
				},
			),
			'withdrawal_period_start_statuses' => array(
				'description'       => __( 'Order statuses from which the withdrawal period countdown may begin.', 'wt-eu-withdrawal-button' ),
				'type'              => 'array',
				'required'          => false,
				'items'             => array(
					'type' => 'string',
				),
				'sanitize_callback' => array( $this, 'sanitize_start_statuses' ),
				'validate_callback' => array( $this, 'validate_withdrawal_period_start_statuses' ),
			),
			'reason_required'           => array(
				'description'       => __( 'Whether a reason is required when submitting a withdrawal.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( 'yes', 'no' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'excluded_product_types'    => array(
				'description'       => __( 'Product types excluded from withdrawal.', 'wt-eu-withdrawal-button' ),
				'type'              => 'array',
				'required'          => false,
				'items'             => array(
					'type' => 'string',
				),
				'sanitize_callback' => array( $this, 'sanitize_string_array' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
			'excluded_categories'       => array(
				'description'       => __( 'Product category IDs excluded from withdrawal.', 'wt-eu-withdrawal-button' ),
				'type'              => 'array',
				'required'          => false,
				'items'             => array(
					'type' => 'integer',
				),
				'sanitize_callback' => array( $this, 'sanitize_int_array' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
			'excluded_products'         => array(
				'description'       => __( 'Product IDs excluded from withdrawal.', 'wt-eu-withdrawal-button' ),
				'type'              => 'array',
				'required'          => false,
				'items'             => array(
					'type' => 'integer',
				),
				'sanitize_callback' => array( $this, 'sanitize_int_array' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
			'excluded_behavior'         => array(
				'description'       => __( 'How to handle excluded items in the withdrawal form.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( 'hide', 'show_disabled_with_notice' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'change_status_on_submission' => array(
				'description'       => __( 'Whether to change order status when a withdrawal is submitted.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( 'yes', 'no' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'submission_order_status'     => array(
				'description'       => __( 'Order status to set on withdrawal submission.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'change_status_on_approval'   => array(
				'description'       => __( 'Whether to change order status when a withdrawal is approved.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( 'yes', 'no' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'approval_order_status'       => array(
				'description'       => __( 'Order status to set on withdrawal approval.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'delete_data_on_uninstall'    => array(
				'description'       => __( 'Whether to delete all plugin data when uninstalling.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( 'yes', 'no' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'admin_notification_recipients' => array(
				'description'       => __( 'Email addresses that receive admin withdrawal notifications.', 'wt-eu-withdrawal-button' ),
				'type'              => 'array',
				'required'          => false,
				'items'             => array(
					'type' => 'string',
				),
				'sanitize_callback' => array( $this, 'sanitize_email_array' ),
				'validate_callback' => array( $this, 'validate_email_array' ),
			),
			'customer_contact_email'        => array(
				'description'       => __( 'Customer-facing contact email shown in rejected withdrawal emails.', 'wt-eu-withdrawal-button' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => array( $this, 'validate_customer_contact_email' ),
			),
		);
	}

	/**
	 * Sanitize an array of strings.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw input value.
	 * @return array Sanitized array of strings.
	 */
	public function sanitize_string_array( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $value );
	}

	/**
	 * Sanitize an array of integers.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw input value.
	 * @return array Sanitized array of integers.
	 */
	public function sanitize_int_array( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_map( 'absint', $value );
	}

	/**
	 * Sanitize an array of email addresses.
	 *
	 * @since 1.0.3
	 *
	 * @param mixed $value Raw input value.
	 * @return array Sanitized array of email addresses.
	 */
	public function sanitize_email_array( $value ) {
		return Wbte_Ewb_Settings::sanitize_email_array( $value );
	}

	/**
	 * Validate an array of email addresses.
	 *
	 * @since 1.0.3
	 *
	 * @param mixed           $value   Raw input value.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return true|WP_Error
	 */
	public function validate_email_array( $value, $request, $param ) {
		if ( null === $value ) {
			return true;
		}

		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'wbte_ewb_invalid_email_recipients',
				/* translators: %s: parameter name */
				sprintf( __( '%s must be an array of email addresses.', 'wt-eu-withdrawal-button' ), $param )
			);
		}

		if ( empty( $value ) ) {
			return new WP_Error(
				'wbte_ewb_empty_email_recipients',
				__( 'At least one admin notification recipient is required.', 'wt-eu-withdrawal-button' )
			);
		}

		foreach ( $value as $email ) {
			$email = sanitize_email( (string) $email );

			if ( ! $email || ! is_email( $email ) ) {
				return new WP_Error(
					'wbte_ewb_invalid_email_recipient',
					__( 'One or more notification recipients contain an invalid email address.', 'wt-eu-withdrawal-button' )
				);
			}
		}

		return true;
	}

	/**
	 * Validate the customer contact email setting.
	 *
	 * @since 1.0.4
	 *
	 * @param mixed           $value   Raw input value.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return true|WP_Error
	 */
	public function validate_customer_contact_email( $value, $request, $param ) {
		if ( null === $value || '' === $value ) {
			return true;
		}

		$email = sanitize_email( (string) $value );

		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error(
				'wbte_ewb_invalid_customer_contact_email',
				__( 'The customer contact email address is invalid.', 'wt-eu-withdrawal-button' )
			);
		}

		return true;
	}

	/**
	 * Sanitize withdrawal period start statuses.
	 *
	 * @since 1.0.4
	 *
	 * @param mixed $value Raw input value.
	 * @return string[]
	 */
	public function sanitize_start_statuses( $value ) {
		if ( ! class_exists( 'Wbte_Ewb_Withdrawal_Period' ) ) {
			return array( 'order_created' );
		}

		return Wbte_Ewb_Withdrawal_Period::sanitize_start_statuses( $value );
	}

	/**
	 * Validate withdrawal period start statuses.
	 *
	 * @since 1.0.4
	 *
	 * @param mixed           $value   Raw input value.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return true|WP_Error
	 */
	public function validate_withdrawal_period_start_statuses( $value, $request, $param ) {
		unset( $request, $param );

		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'wbte_ewb_invalid_period_start_statuses',
				__( 'Withdrawal period start statuses must be an array.', 'wt-eu-withdrawal-button' )
			);
		}

		if ( ! class_exists( 'Wbte_Ewb_Withdrawal_Period' ) ) {
			return true;
		}

		$allowed = Wbte_Ewb_Withdrawal_Period::get_allowed_start_statuses();

		foreach ( $value as $status ) {
			$status = sanitize_text_field( (string) $status );

			if ( ! in_array( $status, $allowed, true ) ) {
				return new WP_Error(
					'wbte_ewb_invalid_period_start_status',
					__( 'One or more withdrawal period start statuses are invalid.', 'wt-eu-withdrawal-button' )
				);
			}
		}

		return true;
	}

	/**
	 * Retrieve all plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, WP_Error on failure.
	 */
	public function get_items( $request ) {
		if ( ! class_exists( 'Wbte_Ewb_Settings' ) ) {
			return $this->error_response(
				'wbte_ewb_missing_dependency',
				__( 'Settings class is not available.', 'wt-eu-withdrawal-button' ),
				500
			);
		}

		$settings = Wbte_Ewb_Settings::get_all();

		/**
		 * Filters the settings data returned by the REST API.
		 *
		 * @since 1.0.0
		 *
		 * @param array           $settings All plugin settings.
		 * @param WP_REST_Request $request  The current REST request.
		 */
		$settings = apply_filters( 'wbte_ewb_rest_settings_data', $settings, $request );

		return $this->success_response( $settings );
	}

	/**
	 * Update plugin settings.
	 *
	 * Only provided fields are updated; omitted fields retain
	 * their current values.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, WP_Error on failure.
	 */
	public function update_items( $request ) {
		if ( ! class_exists( 'Wbte_Ewb_Settings' ) ) {
			return $this->error_response(
				'wbte_ewb_missing_dependency',
				__( 'Settings class is not available.', 'wt-eu-withdrawal-button' ),
				500
			);
		}

		// Derive allowed keys from the single settings registry.
		$allowed_keys = Wbte_Ewb_Settings::get_keys();

		$updates = array();

		foreach ( $allowed_keys as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) {
				$updates[ $key ] = $value;
			}
		}

		if ( empty( $updates ) ) {
			return $this->error_response(
				'wbte_ewb_no_settings',
				__( 'No valid settings provided to update.', 'wt-eu-withdrawal-button' ),
				400
			);
		}

		$result = Wbte_Ewb_Settings::update( $updates );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$settings = Wbte_Ewb_Settings::get_all();

		/**
		 * Fires after plugin settings are updated via the REST API.
		 *
		 * Note: The core `wbte_ewb_settings_updated` hook is already
		 * fired inside Wbte_Ewb_Settings::update() with the signature
		 * ( $sanitized, $merged ). This hook is specific to REST updates.
		 *
		 * @since 1.0.0
		 *
		 * @param array           $updates  The settings that were changed.
		 * @param array           $settings All settings after the update.
		 * @param WP_REST_Request $request  The current REST request.
		 */
		do_action( 'wbte_ewb_rest_settings_updated', $updates, $settings, $request );

		return $this->success_response(
			$settings,
			__( 'Settings updated successfully.', 'wt-eu-withdrawal-button' )
		);
	}

	/**
	 * Get the settings schema for REST responses.
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
			'title'      => 'wbte-ewb-settings',
			'type'       => 'object',
			'properties' => array(
				'withdrawal_page'           => array(
					'description' => __( 'Page ID for the withdrawal form.', 'wt-eu-withdrawal-button' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'embed_footer_link'         => array(
					'description' => __( 'Whether to show a withdrawal link in the site footer.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'display_scope'             => array(
					'description' => __( 'Where to display the footer link.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'enum'        => array( 'global', 'woocommerce_only' ),
					'context'     => array( 'view', 'edit' ),
				),
				'allow_partial_withdrawals' => array(
					'description' => __( 'Whether to allow partial order withdrawals.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'withdrawal_period'         => array(
					'description' => __( 'Number of days within which withdrawal is allowed.', 'wt-eu-withdrawal-button' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'withdrawal_period_start_statuses' => array(
					'description' => __( 'Order statuses from which the withdrawal period countdown may begin.', 'wt-eu-withdrawal-button' ),
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
					),
					'context'     => array( 'view', 'edit' ),
				),
				'reason_required'           => array(
					'description' => __( 'Whether a reason is required when submitting a withdrawal.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'excluded_product_types'    => array(
					'description' => __( 'Product types excluded from withdrawal.', 'wt-eu-withdrawal-button' ),
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
					),
					'context'     => array( 'view', 'edit' ),
				),
				'excluded_categories'       => array(
					'description' => __( 'Product category IDs excluded from withdrawal.', 'wt-eu-withdrawal-button' ),
					'type'        => 'array',
					'items'       => array(
						'type' => 'integer',
					),
					'context'     => array( 'view', 'edit' ),
				),
				'excluded_products'         => array(
					'description' => __( 'Product IDs excluded from withdrawal.', 'wt-eu-withdrawal-button' ),
					'type'        => 'array',
					'items'       => array(
						'type' => 'integer',
					),
					'context'     => array( 'view', 'edit' ),
				),
				'excluded_behavior'         => array(
					'description' => __( 'How to handle excluded items in the withdrawal form.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'enum'        => array( 'hide', 'show_disabled_with_notice' ),
					'context'     => array( 'view', 'edit' ),
				),
				'change_status_on_submission' => array(
					'description' => __( 'Whether to change order status when a withdrawal is submitted.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'submission_order_status'     => array(
					'description' => __( 'Order status to set on withdrawal submission.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'change_status_on_approval'   => array(
					'description' => __( 'Whether to change order status when a withdrawal is approved.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
				'approval_order_status'       => array(
					'description' => __( 'Order status to set on withdrawal approval.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'delete_data_on_uninstall'    => array(
					'description' => __( 'Whether to delete all plugin data when uninstalling.', 'wt-eu-withdrawal-button' ),
					'type'        => 'string',
					'enum'        => array( 'yes', 'no' ),
					'context'     => array( 'view', 'edit' ),
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
