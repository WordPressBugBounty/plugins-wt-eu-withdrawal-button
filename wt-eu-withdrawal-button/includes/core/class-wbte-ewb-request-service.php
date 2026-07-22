<?php
/**
 * Withdrawal request service layer.
 *
 * Orchestrates submission, approval, and rejection of withdrawal
 * requests, including order status transitions and logging.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Request_Service
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Request_Service {

	/**
	 * Repository instance.
	 *
	 * @since 1.0.0
	 * @var Wbte_Ewb_Request_Repository
	 */
	private $repository;

	/**
	 * Whether WooCommerce customer order status emails should be suppressed.
	 *
	 * @var bool
	 */
	private static $suppress_customer_order_emails = false;

	/**
	 * Customer order email IDs to suppress during status restore.
	 *
	 * @var string[]
	 */
	private static $suppressed_wc_email_ids = array(
		'customer_processing_order',
		'customer_completed_order',
		'customer_on_hold_order',
		'customer_refunded_order',
		'customer_invoice',
		'customer_note',
	);

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Wbte_Ewb_Request_Repository $repository Repository instance.
	 */
	public function __construct( Wbte_Ewb_Request_Repository $repository ) {
		$this->repository = $repository;

		add_filter( 'woocommerce_allow_send_woocommerce_email', array( $this, 'maybe_suppress_customer_order_emails' ), 10, 3 );
	}

	/**
	 * Submit a new withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data {
	 *     Submission data.
	 *
	 *     @type int    $order_id     WooCommerce order ID.
	 *     @type string $reason       Optional reason for withdrawal.
	 *     @type string $request_type 'full' or 'partial'.
	 *     @type string $items_json   JSON-encoded items for partial withdrawals.
	 *     @type string $meta_json    JSON-encoded additional metadata.
	 * }
	 * @return Wbte_Ewb_Request|\WP_Error
	 */
	public function submit( $data ) {
		$order_id = isset( $data['order_id'] ) ? absint( $data['order_id'] ) : 0;
		$order    = wc_get_order( $order_id );

		// 1. Validate order exists.
		if ( ! $order ) {
			return new \WP_Error(
				'wbte_ewb_invalid_order',
				__( 'The specified order does not exist.', 'wt-eu-withdrawal-button' ),
				array( 'status' => 404 )
			);
		}

		// 2. Resolve customer identity.
		$order_email   = $order->get_billing_email();
		$order_user_id = absint( $order->get_customer_id() );

		$customer_email   = isset( $data['customer_email'] ) ? sanitize_email( $data['customer_email'] ) : '';
		$customer_user_id = isset( $data['customer_user_id'] ) ? absint( $data['customer_user_id'] ) : 0;

		// Also accept 'email' and 'user_id' keys from REST controller.
		if ( empty( $customer_email ) && ! empty( $data['email'] ) ) {
			$customer_email = sanitize_email( $data['email'] );
		}
		if ( empty( $customer_user_id ) && ! empty( $data['user_id'] ) ) {
			$customer_user_id = absint( $data['user_id'] );
		}

		// Validate ownership (skip for admins).
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$email_match = ! empty( $customer_email ) && strtolower( $order_email ) === strtolower( $customer_email );
			$user_match  = ! empty( $customer_user_id ) && 0 !== $order_user_id && $order_user_id === $customer_user_id;

			if ( ! $email_match && ! $user_match ) {
				return new \WP_Error(
					'wbte_ewb_ownership_mismatch',
					__( 'You are not authorised to submit a withdrawal for this order.', 'wt-eu-withdrawal-button' ),
					array( 'status' => 403 )
				);
			}
		}

		// 3. Check eligibility.
		$eligibility = new Wbte_Ewb_Eligibility();

		if ( ! $eligibility->is_order_eligible( $order ) ) {
			return new \WP_Error(
				'wbte_ewb_not_eligible',
				__( 'This order is not eligible for withdrawal.', 'wt-eu-withdrawal-button' ),
				array( 'status' => 422 )
			);
		}

		// 4. Build request items from the server-side eligible-item allowlist.
		$remaining_qtys = $eligibility->get_remaining_quantities( $order );
		$request_type   = isset( $data['request_type'] ) ? sanitize_text_field( $data['request_type'] ) : 'full';
		$submitted_items = $this->parse_submitted_items( $data );

		if ( is_wp_error( $submitted_items ) ) {
			return $submitted_items;
		}

		if ( empty( $submitted_items ) && 'full' === $request_type ) {
			$enriched_items = $this->build_full_withdrawal_items( $order, $remaining_qtys );
		} else {
			if ( empty( $submitted_items ) ) {
				return new \WP_Error(
					'wbte_ewb_items_required',
					__( 'Please select at least one item to withdraw.', 'wt-eu-withdrawal-button' ),
					array( 'status' => 400 )
				);
			}

			$enriched_items = $this->validate_and_enrich_submitted_items( $order, $submitted_items, $remaining_qtys );
		}

		if ( is_wp_error( $enriched_items ) ) {
			return $enriched_items;
		}

		$request_type = $this->normalize_request_type( $order, $enriched_items, $request_type );

		$items_json = wp_json_encode( $enriched_items );

		$request_data = array(
			'order_id'         => $order_id,
			'order_number'     => $order->get_order_number(),
			'customer_email'   => $order_email,
			'customer_user_id' => $order_user_id,
			'request_type'     => $request_type,
			'items_json'       => $items_json,
			'reason'           => isset( $data['reason'] ) ? sanitize_textarea_field( $data['reason'] ) : '',
			'meta_json'        => isset( $data['meta_json'] ) ? $data['meta_json'] : '{}',
			'created_by'       => $customer_user_id ? $customer_user_id : get_current_user_id(),
		);

		$request = $this->repository->create( $request_data );

		if ( ! $request ) {
			return new \WP_Error(
				'wbte_ewb_create_failed',
				__( 'Failed to create the withdrawal request.', 'wt-eu-withdrawal-button' ),
				array( 'status' => 500 )
			);
		}

		Wbte_Ewb_Review_Banner::increment_withdrawal_count();

		// 5. Store previous status, then optionally change order status.
		// Re-fetch the order fresh (same pattern as approve/reject).
		$fresh_order = wc_get_order( $order_id );

		if ( $fresh_order ) {
			$current_status      = $fresh_order->get_status();
			$withdrawal_statuses = array( 'pending-wdraw', 'withdrawn' );

			if ( ! in_array( $current_status, $withdrawal_statuses, true ) ) {
				$fresh_order->update_meta_data( '_wbte_ewb_previous_status', 'wc-' . $current_status );
			}

			$fresh_order->save();

			if ( 'yes' === Wbte_Ewb_Settings::get( 'change_status_on_submission', 'no' ) ) {
				$submission_status = Wbte_Ewb_Settings::get( 'submission_order_status', 'wc-pending-wdraw' );
				$submission_status = str_replace( 'wc-', '', $submission_status );

				if ( $current_status !== $submission_status ) {
					$fresh_order->update_status( $submission_status, __( 'Withdrawal request submitted.', 'wt-eu-withdrawal-button' ) );
				}
			}
		}

		// 6. Add log entry.
		$this->repository->add_log(
			$request->id,
			array(
				'actor_type'  => 'customer',
				'actor_id'    => $customer_user_id,
				'action'      => 'submitted',
				'from_status' => '',
				'to_status'   => 'pending',
				'note'        => '',
			)
		);

		/**
		 * Fires after a withdrawal request is submitted.
		 *
		 * @since 1.0.0
		 *
		 * @param Wbte_Ewb_Request $request The submitted request.
		 * @param \WC_Order       $order   The associated order.
		 */
		do_action( 'wbte_ewb_request_submitted', $request, $order );

		/**
		 * Fires the notification action for WC_Email classes.
		 *
		 * @since 1.0.0
		 *
		 * @param Wbte_Ewb_Request $request The submitted request.
		 * @param \WC_Order       $order   The associated order.
		 */
		// Ensure WC mailer is initialised so email classes are loaded.
		WC()->mailer();
		do_action( 'wbte_ewb_request_submitted_notification', $request, $order );

		return $request;
	}

	/**
	 * Parse submitted line items from request data.
	 *
	 * @since 1.0.4
	 *
	 * @param array<string, mixed> $data Submission data.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private function parse_submitted_items( $data ) {
		if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
			return $data['items'];
		}

		if ( empty( $data['items_json'] ) ) {
			return array();
		}

		$decoded = json_decode( $data['items_json'], true );

		if ( ! is_array( $decoded ) ) {
			return new \WP_Error(
				'wbte_ewb_invalid_items_json',
				__( 'The submitted withdrawal items are invalid.', 'wt-eu-withdrawal-button' ),
				array( 'status' => 400 )
			);
		}

		return $decoded;
	}

	/**
	 * Build a full-withdrawal snapshot from eligible remaining quantities only.
	 *
	 * @since 1.0.4
	 *
	 * @param \WC_Order          $order          WooCommerce order.
	 * @param array<int, int>    $remaining_qtys Remaining quantity keyed by line item ID.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private function build_full_withdrawal_items( $order, $remaining_qtys ) {
		$items = array();

		foreach ( $remaining_qtys as $line_item_id => $max_qty ) {
			$max_qty = absint( $max_qty );

			if ( $max_qty < 1 ) {
				continue;
			}

			$line_item = $order->get_item( $line_item_id );

			if ( ! $line_item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$items[] = array(
				'line_item_id' => (int) $line_item_id,
				'product_id'   => (int) $line_item->get_product_id(),
				'qty'          => $max_qty,
				'name'         => $line_item->get_name(),
			);
		}

		if ( empty( $items ) ) {
			return new \WP_Error(
				'wbte_ewb_item_unavailable',
				__( 'No items are currently available for withdrawal on this order.', 'wt-eu-withdrawal-button' ),
				array( 'status' => 400 )
			);
		}

		return $items;
	}

	/**
	 * Resolve whether a request should be stored as full or partial.
	 *
	 * @since 1.0.4
	 *
	 * @param \WC_Order                        $order          WooCommerce order.
	 * @param array<int, array<string, mixed>> $enriched_items Validated request items.
	 * @param string                           $request_type   Client-provided request type.
	 * @return string Either 'full' or 'partial'.
	 */
	private function normalize_request_type( $order, $enriched_items, $request_type ) {
		if ( empty( $enriched_items ) || ! $order instanceof \WC_Order ) {
			return 'full' === $request_type ? 'full' : 'partial';
		}

		$eligibility    = new Wbte_Ewb_Eligibility();
		$eligible_items = $eligibility->get_eligible_items( $order );
		$requested_ids  = array();

		foreach ( $enriched_items as $item ) {
			$line_item_id = isset( $item['line_item_id'] ) ? absint( $item['line_item_id'] ) : 0;
			$qty          = isset( $item['qty'] ) ? absint( $item['qty'] ) : 1;

			if ( ! $line_item_id ) {
				return 'partial';
			}

			$requested_ids[] = $line_item_id;
			$line_item       = $order->get_item( $line_item_id );

			if ( ! $line_item instanceof WC_Order_Item_Product ) {
				return 'partial';
			}

			if ( $qty < (int) $line_item->get_quantity() ) {
				return 'partial';
			}
		}

		$requested_ids = array_values( array_unique( $requested_ids ) );

		if ( count( $requested_ids ) < count( $eligible_items ) ) {
			return 'partial';
		}

		foreach ( array_keys( $eligible_items ) as $eligible_id ) {
			if ( ! in_array( (int) $eligible_id, $requested_ids, true ) ) {
				return 'partial';
			}
		}

		return 'full';
	}

	/**
	 * Validate customer-submitted items against the eligible-item allowlist.
	 *
	 * @since 1.0.4
	 *
	 * @param \WC_Order                       $order          WooCommerce order.
	 * @param array<int, array<string, mixed>> $submitted_items Submitted line items.
	 * @param array<int, int>                 $remaining_qtys Remaining quantity keyed by line item ID.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private function validate_and_enrich_submitted_items( $order, $submitted_items, $remaining_qtys ) {
		$enriched_items = array();

		foreach ( $submitted_items as $item_entry ) {
			if ( ! is_array( $item_entry ) ) {
				return new \WP_Error(
					'wbte_ewb_invalid_line_item',
					__( 'One or more selected items are invalid for this order.', 'wt-eu-withdrawal-button' ),
					array( 'status' => 400 )
				);
			}

			$line_item_id = isset( $item_entry['line_item_id'] ) ? absint( $item_entry['line_item_id'] ) : 0;
			$product_id   = isset( $item_entry['product_id'] ) ? absint( $item_entry['product_id'] ) : 0;
			$qty          = isset( $item_entry['qty'] ) ? absint( $item_entry['qty'] ) : 1;
			$line_item    = $line_item_id ? $order->get_item( $line_item_id ) : null;

			if ( ! $line_item instanceof WC_Order_Item_Product ) {
				return new \WP_Error(
					'wbte_ewb_invalid_line_item',
					__( 'One or more selected items are invalid for this order.', 'wt-eu-withdrawal-button' ),
					array( 'status' => 400 )
				);
			}

			$name = $line_item->get_name();

			if ( $product_id && absint( $line_item->get_product_id() ) !== $product_id ) {
				return new \WP_Error(
					'wbte_ewb_invalid_line_item',
					__( 'One or more selected items are invalid for this order.', 'wt-eu-withdrawal-button' ),
					array( 'status' => 400 )
				);
			}

			if ( ! array_key_exists( $line_item_id, $remaining_qtys ) ) {
				return new \WP_Error(
					'wbte_ewb_item_ineligible',
					sprintf(
						/* translators: %s: product name */
						__( '"%s" is not eligible for withdrawal on this order.', 'wt-eu-withdrawal-button' ),
						$name ? $name : __( 'This item', 'wt-eu-withdrawal-button' )
					),
					array( 'status' => 400 )
				);
			}

			$max_qty = absint( $remaining_qtys[ $line_item_id ] );

			if ( $qty < 1 ) {
				$qty = 1;
			}

			if ( $max_qty < 1 ) {
				return new \WP_Error(
					'wbte_ewb_item_unavailable',
					sprintf(
						/* translators: %s: product name */
						__( '"%s" is no longer available for withdrawal on this order.', 'wt-eu-withdrawal-button' ),
						$name ? $name : __( 'This item', 'wt-eu-withdrawal-button' )
					),
					array( 'status' => 400 )
				);
			}

			if ( $qty > $max_qty ) {
				return new \WP_Error(
					'wbte_ewb_invalid_item_qty',
					sprintf(
						/* translators: 1: product name, 2: maximum quantity */
						__( 'The withdrawal quantity for "%1$s" cannot exceed %2$d.', 'wt-eu-withdrawal-button' ),
						$name ? $name : __( 'this item', 'wt-eu-withdrawal-button' ),
						$max_qty
					),
					array( 'status' => 400 )
				);
			}

			$enriched_items[] = array(
				'line_item_id' => $line_item_id,
				'product_id'   => $product_id ? $product_id : (int) $line_item->get_product_id(),
				'qty'          => $qty,
				'name'         => $name,
			);
		}

		return $enriched_items;
	}

	/**
	 * Approve a pending withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id       Request ID.
	 * @param int $admin_id Admin user ID who is approving. Default 0.
	 * @return Wbte_Ewb_Request|\WP_Error
	 */
	public function approve( $id, $admin_id = 0 ) {
		$request = $this->repository->find( $id );

		if ( null === $request ) {
			return new \WP_Error(
				'wbte_ewb_not_found',
				__( 'Withdrawal request not found.', 'wt-eu-withdrawal-button' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $request->is_pending() ) {
			return new \WP_Error(
				'wbte_ewb_not_pending',
				__( 'Only pending requests can be approved.', 'wt-eu-withdrawal-button' ),
				array( 'status' => 422 )
			);
		}

		// Update request.
		$now = current_time( 'mysql', true );
		$this->repository->update(
			$id,
			array(
				'status'       => 'approved',
				'processed_by' => absint( $admin_id ),
				'processed_at' => $now,
			)
		);

		// Refresh the request.
		$request = $this->repository->find( $id );

		// Optionally update order status once all requests on the order are processed.
		$order = wc_get_order( $request->order_id );

		if ( $order ) {
			$this->sync_order_status_after_request_change( $order );
		}

		// Add log entry.
		$this->repository->add_log(
			$id,
			array(
				'actor_type'  => 'admin',
				'actor_id'    => absint( $admin_id ),
				'action'      => 'approved',
				'from_status' => 'pending',
				'to_status'   => 'approved',
				'note'        => '',
			)
		);

		/**
		 * Fires after a withdrawal request is approved.
		 *
		 * @since 1.0.0
		 *
		 * @param Wbte_Ewb_Request   $request The approved request.
		 * @param \WC_Order|false   $order   The associated order.
		 * @param int               $admin_id The admin who approved it.
		 */
		do_action( 'wbte_ewb_request_approved', $request, $order, $admin_id );

		// Ensure WC mailer is initialised so email classes are loaded.
		WC()->mailer();
		do_action( 'wbte_ewb_request_approved_notification', $request, $order );

		return $request;
	}

	/**
	 * Reject a pending withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $id       Request ID.
	 * @param int    $admin_id Admin user ID who is rejecting. Default 0.
	 * @param string $note     Optional rejection note. Default ''.
	 * @return Wbte_Ewb_Request|\WP_Error
	 */
	public function reject( $id, $admin_id = 0, $note = '' ) {
		$request = $this->repository->find( $id );

		if ( null === $request ) {
			return new \WP_Error(
				'wbte_ewb_not_found',
				__( 'Withdrawal request not found.', 'wt-eu-withdrawal-button' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $request->is_pending() ) {
			return new \WP_Error(
				'wbte_ewb_not_pending',
				__( 'Only pending requests can be rejected.', 'wt-eu-withdrawal-button' ),
				array( 'status' => 422 )
			);
		}

		$note = sanitize_textarea_field( $note );

		// Update request and persist the rejection note for customer emails.
		$now  = current_time( 'mysql', true );
		$meta = $request->get_meta();
		$meta['reject_reason'] = $note;

		$this->repository->update(
			$id,
			array(
				'status'       => 'rejected',
				'processed_by' => absint( $admin_id ),
				'processed_at' => $now,
				'meta_json'    => wp_json_encode( $meta ),
			)
		);

		// Refresh the request.
		$request = $this->repository->find( $id );

		// Update order status based on remaining pending requests.
		$order = wc_get_order( $request->order_id );

		if ( $order ) {
			$this->sync_order_status_after_request_change( $order );
		}

		// Add log entry.
		$this->repository->add_log(
			$id,
			array(
				'actor_type'  => 'admin',
				'actor_id'    => absint( $admin_id ),
				'action'      => 'rejected',
				'from_status' => 'pending',
				'to_status'   => 'rejected',
				'note'        => $note,
			)
		);

		/**
		 * Fires after a withdrawal request is rejected.
		 *
		 * @since 1.0.0
		 *
		 * @param Wbte_Ewb_Request $request  The rejected request.
		 * @param \WC_Order|false $order    The associated order.
		 * @param int             $admin_id The admin who rejected it.
		 * @param string          $note     Rejection note.
		 */
		do_action( 'wbte_ewb_request_rejected', $request, $order, $admin_id, $note );

		// Ensure WC mailer is initialised so email classes are loaded.
		WC()->mailer();
		do_action( 'wbte_ewb_request_rejected_notification', $request, $order, $note );

		return $request;
	}

	/**
	 * Sync the WooCommerce order status after a request is approved or rejected.
	 *
	 * Keeps the order in pending withdrawal while other requests are still open,
	 * moves it to withdrawn when all requests are processed and no quantity remains,
	 * or restores the previous status when partial quantity remains.
	 *
	 * @since 1.0.5
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return void
	 */
	private function sync_order_status_after_request_change( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( $this->repository->has_pending_by_order( $order->get_id() ) ) {
			if ( 'yes' === Wbte_Ewb_Settings::get( 'change_status_on_submission', 'no' ) ) {
				$submission_status = str_replace( 'wc-', '', Wbte_Ewb_Settings::get( 'submission_order_status', 'wc-pending-wdraw' ) );

				if ( $order->get_status() !== $submission_status ) {
					$order->update_status(
						$submission_status,
						__( 'Withdrawal request pending review.', 'wt-eu-withdrawal-button' )
					);
				}
			}

			return;
		}

		if ( 'yes' !== Wbte_Ewb_Settings::get( 'change_status_on_approval', 'no' ) ) {
			return;
		}

		$eligibility = new Wbte_Ewb_Eligibility();

		if ( ! $eligibility->has_remaining_withdrawable_quantity( $order ) ) {
			$approval_status = str_replace( 'wc-', '', Wbte_Ewb_Settings::get( 'approval_order_status', 'wc-withdrawn' ) );

			if ( $order->get_status() !== $approval_status ) {
				$order->update_status( $approval_status, __( 'Withdrawal request approved.', 'wt-eu-withdrawal-button' ) );
			}

			return;
		}

		$this->restore_order_status_after_partial_approval( $order );
	}

	/**
	 * Restore the order status after a partial approval when items remain.
	 *
	 * @since 1.0.4
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return void
	 */
	private function restore_order_status_after_partial_approval( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$withdrawal_statuses = array( 'withdrawn', 'pending-wdraw' );

		if ( ! in_array( $order->get_status(), $withdrawal_statuses, true ) ) {
			return;
		}

		$previous_status = $order->get_meta( '_wbte_ewb_previous_status' );

		if ( ! empty( $previous_status ) ) {
			$restore_status = str_replace( 'wc-', '', $previous_status );
		} else {
			$restore_status = 'processing';
		}

		if ( $order->get_status() === $restore_status ) {
			return;
		}

		$this->restore_order_status_without_customer_emails(
			$order,
			$restore_status,
			__( 'Partial withdrawal approved. Restoring order status for remaining items.', 'wt-eu-withdrawal-button' )
		);
	}

	/**
	 * Restore an order status without sending WooCommerce customer status emails.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Order $order          Order object.
	 * @param string   $restore_status Target status slug without wc- prefix.
	 * @param string   $note           Optional order note.
	 * @return void
	 */
	private function restore_order_status_without_customer_emails( $order, $restore_status, $note = '' ) {
		$restore_status = str_replace( 'wc-', '', $restore_status );

		if ( $order->get_status() === $restore_status ) {
			return;
		}

		self::$suppress_customer_order_emails = true;

		foreach ( self::$suppressed_wc_email_ids as $email_id ) {
			add_filter( 'woocommerce_email_enabled_' . $email_id, array( $this, 'disable_customer_order_email' ), 999 );
		}

		$order->update_status( $restore_status, $note, true );

		foreach ( self::$suppressed_wc_email_ids as $email_id ) {
			remove_filter( 'woocommerce_email_enabled_' . $email_id, array( $this, 'disable_customer_order_email' ), 999 );
		}

		self::$suppress_customer_order_emails = false;
	}

	/**
	 * Disable a WooCommerce customer order email during status restore.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $enabled Whether the email is enabled.
	 * @return bool
	 */
	public function disable_customer_order_email( $enabled ) {
		return self::$suppress_customer_order_emails ? false : $enabled;
	}

	/**
	 * Suppress WooCommerce customer order emails during status restore.
	 *
	 * @since 1.1.0
	 *
	 * @param bool        $allow  Whether the email should be sent.
	 * @param WC_Email|null $email WooCommerce email instance.
	 * @param mixed       $object Email context object.
	 * @return bool
	 */
	public function maybe_suppress_customer_order_emails( $allow, $email, $object = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! self::$suppress_customer_order_emails || ! ( $email instanceof WC_Email ) ) {
			return $allow;
		}

		if ( in_array( $email->id, self::$suppressed_wc_email_ids, true ) ) {
			return false;
		}

		return $allow;
	}
}
