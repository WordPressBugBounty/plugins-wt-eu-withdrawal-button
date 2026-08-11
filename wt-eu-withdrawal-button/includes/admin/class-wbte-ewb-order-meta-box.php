<?php
/**
 * Order meta box for withdrawal requests.
 *
 * Displays withdrawal request information on the WooCommerce
 * order edit screen and adds a status column to the orders list.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Order_Meta_Box
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Order_Meta_Box {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Meta box — legacy CPT orders.
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );

		// Meta box — HPOS orders.
		add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( $this, 'register_meta_box' ) );

		// Withdrawal badge next to order line items.
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'display_item_withdrawal_badge' ), 10, 3 );
	}

	/**
	 * Get the request repository from the service container.
	 *
	 * @since 1.0.0
	 *
	 * @return Wbte_Ewb_Request_Repository|null
	 */
	private function get_repository() {
		return function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'repository' ) : null;
	}

	/**
	 * Register the meta box on the order edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_meta_box() {
		$screen = $this->get_order_screen_id();

		add_meta_box(
			'wbte-ewb-withdrawal-request',
			__( 'Withdrawal Requests', 'wt-eu-withdrawal-button' ),
			array( $this, 'render_meta_box' ),
			$screen,
			'side',
			'high'
		);
	}

	/**
	 * Render the meta box content.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post|WC_Order $post_or_order The post or order object.
	 * @return void
	 */
	public function render_meta_box( $post_or_order ) {
		$order_id = $this->get_order_id( $post_or_order );

		if ( ! $order_id ) {
			echo '<p>' . esc_html__( 'Order not found.', 'wt-eu-withdrawal-button' ) . '</p>';
			return;
		}

		$requests = array();

		$repository = $this->get_repository();
		if ( $repository ) {
			$requests = $repository->find_by_order( $order_id );
		}

		echo '<div class="wbte-ewb-metabox" data-order-id="' . esc_attr( $order_id ) . '">';

		if ( empty( $requests ) ) {
			echo '<p class="wbte-ewb-no-request">' . esc_html__( 'No withdrawal request for this order.', 'wt-eu-withdrawal-button' ) . '</p>';
			$this->render_metabox_review_prompt();
			echo '</div>';
			return;
		}

		$total = count( $requests );

		foreach ( $requests as $index => $request ) {
			$this->render_single_request( $request, $index + 1, $total );
		}

		$this->render_metabox_review_prompt();

		echo '</div>';
	}

	/**
	 * Render a single withdrawal request inside the order meta box.
	 *
	 * @since 1.0.4
	 *
	 * @param Wbte_Ewb_Request $request     Request object.
	 * @param int              $position    1-based position in the list.
	 * @param int              $total       Total requests for the order.
	 * @return void
	 */
	private function render_single_request( $request, $position, $total ) {
		if ( ! $request instanceof Wbte_Ewb_Request ) {
			return;
		}

		echo '<div class="wbte-ewb-metabox-request" data-request-id="' . esc_attr( $request->id ) . '">';

		echo '<p class="wbte-ewb-metabox-request__heading">';
		if ( $total > 1 ) {
			echo '<strong>' . esc_html( sprintf(
				/* translators: 1: request position, 2: total requests, 3: request ID */
				__( 'Request %1$d of %2$d (#%3$d)', 'wt-eu-withdrawal-button' ),
				$position,
				$total,
				$request->id
			) ) . '</strong>';
		} else {
			echo '<strong>' . esc_html( sprintf(
				/* translators: %d: request ID */
				__( 'Request #%d', 'wt-eu-withdrawal-button' ),
				$request->id
			) ) . '</strong>';
		}
		echo '</p>';

		// Status badge.
		echo '<p>';
		echo '<strong>' . esc_html__( 'Status:', 'wt-eu-withdrawal-button' ) . '</strong> ';
		$this->render_status_badge( $request->status );
		echo '</p>';

		// Request type.
		echo '<p>';
		echo '<strong>' . esc_html__( 'Type:', 'wt-eu-withdrawal-button' ) . '</strong> ';
		if ( $request->is_partial_withdrawal() ) {
			echo esc_html__( 'Partial Withdrawal', 'wt-eu-withdrawal-button' );
		} else {
			echo esc_html__( 'Full Withdrawal', 'wt-eu-withdrawal-button' );
		}
		echo '</p>';

		// Items summary.
		$items = $this->get_request_summary_items( $request );
		if ( ! empty( $items ) ) {
			echo '<div class="wbte-ewb-items-summary">';
			echo '<strong>' . esc_html__( 'Items:', 'wt-eu-withdrawal-button' ) . '</strong>';
			echo '<ul>';
			foreach ( $items as $item ) {
				$name = isset( $item['name'] ) ? $item['name'] : __( 'Unknown item', 'wt-eu-withdrawal-button' );
				$qty  = isset( $item['qty'] ) ? absint( $item['qty'] ) : 1;
				echo '<li>' . esc_html( $name ) . ' &times; ' . esc_html( $qty ) . '</li>';
			}
			echo '</ul>';
			echo '</div>';
		}

		// Reason.
		if ( ! empty( $request->reason ) ) {
			echo '<p>';
			echo '<strong>' . esc_html__( 'Reason:', 'wt-eu-withdrawal-button' ) . '</strong><br>';
			echo esc_html( $request->reason );
			echo '</p>';
		}

		// Date submitted.
		echo '<p>';
		echo '<strong>' . esc_html__( 'Submitted:', 'wt-eu-withdrawal-button' ) . '</strong> ';
		echo esc_html( Wbte_Ewb_Request::format_datetime( $request->created_at ) );
		echo '</p>';

		$verification_code = $request->get_verification_code();

		if ( '' !== $verification_code ) {
			echo '<p class="wbte-ewb-verification-code-row">';
			echo '<strong>' . esc_html__( 'Receipt hash:', 'wt-eu-withdrawal-button' ) . '</strong>';
			echo '<span class="wbte-ewb-verification-code">' . esc_html( $verification_code ) . '</span>';
			echo '</p>';
		}

		/**
		 * Fires after the core request details in the order meta box.
		 *
		 * @since 1.1.0
		 *
		 * @param Wbte_Ewb_Request $request The withdrawal request.
		 * @param WC_Order         $order   The WooCommerce order.
		 */
		do_action( 'wbte_ewb_order_meta_box_after_details', $request, wc_get_order( $request->order_id ) );

		// Processed info for non-pending requests.
		if ( ! $request->is_pending() && ! empty( $request->processed_at ) ) {
			echo '<p>';
			echo '<strong>' . esc_html__( 'Processed:', 'wt-eu-withdrawal-button' ) . '</strong> ';
			echo esc_html( Wbte_Ewb_Request::format_datetime( $request->processed_at ) );

			if ( $request->processed_by ) {
				$user = get_userdata( $request->processed_by );
				if ( $user ) {
					echo ' ' . esc_html__( 'by', 'wt-eu-withdrawal-button' ) . ' ';
					echo esc_html( $user->display_name );
				}
			}
			echo '</p>';
		}

		if ( class_exists( 'Wbte_Ewb_Admin_Menu' ) ) {
			echo '<p class="wbte-ewb-metabox-request__link">';
			echo '<a href="' . esc_url( Wbte_Ewb_Admin_Menu::get_dashboard_url( $request->id ) ) . '">';
			echo esc_html__( 'View in dashboard', 'wt-eu-withdrawal-button' );
			echo '</a>';
			echo '</p>';
		}

		// Action buttons for pending requests.
		if ( $request->is_pending() ) {
			echo '<div class="wbte-ewb-metabox-actions">';
			echo '<button type="button" class="button button-primary wbte-ewb-approve-btn" data-request-id="' . esc_attr( $request->id ) . '">';
			echo esc_html__( 'Approve', 'wt-eu-withdrawal-button' );
			echo '</button> ';
			echo '<button type="button" class="button wbte-ewb-reject-btn" data-request-id="' . esc_attr( $request->id ) . '">';
			echo esc_html__( 'Reject', 'wt-eu-withdrawal-button' );
			echo '</button>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Render a compact review request at the bottom of the meta box.
	 *
	 * @since 1.0.5
	 *
	 * @return void
	 */
	private function render_metabox_review_prompt() {
		if ( ! class_exists( 'Wbte_Ewb_Review_Banner' ) || ! Wbte_Ewb_Review_Banner::should_display() ) {
			return;
		}

		$state     = Wbte_Ewb_Review_Banner::get_state();
		$milestone = isset( $state['milestone'] ) ? (string) $state['milestone'] : '';
		$message   = Wbte_Ewb_Review_Banner::get_message( $milestone );

		echo '<div class="wbte-ewb-metabox-review">';
		echo '<p class="wbte-ewb-metabox-review__text">' . esc_html( $message ) . '</p>';
		echo '<div class="wbte-ewb-metabox-review__actions">';
		echo '<a class="button button-primary button-small wbte-ewb-metabox-review__link" href="' . esc_url( Wbte_Ewb_Review_Banner::REVIEW_URL ) . '" target="_blank" rel="noopener noreferrer">';
		echo esc_html__( 'Leave a review', 'wt-eu-withdrawal-button' );
		echo '</a>';
		echo '<button type="button" class="button button-small wbte-ewb-metabox-review__later">';
		echo esc_html__( 'Remind me later', 'wt-eu-withdrawal-button' );
		echo '</button>';
		echo '</div>';
		echo '<button type="button" class="wbte-ewb-metabox-review__dismiss" aria-label="' . esc_attr__( 'Dismiss review request', 'wt-eu-withdrawal-button' ) . '">';
		echo '<span aria-hidden="true">&times;</span>';
		echo '</button>';
		echo '</div>';
	}

	/**
	 * Build the item list shown for a single request in the meta box.
	 *
	 * @since 1.0.4
	 *
	 * @param Wbte_Ewb_Request $request Request object.
	 * @return array<int, array{name: string, qty: int}>
	 */
	private function get_request_summary_items( $request ) {
		if ( ! $request instanceof Wbte_Ewb_Request ) {
			return array();
		}

		$summary      = array();
		$stored_items = $request->get_items();
		$order        = $request->get_order();

		if ( ! empty( $stored_items ) ) {
			foreach ( $stored_items as $item ) {
				$line_item_id = isset( $item['line_item_id'] ) ? absint( $item['line_item_id'] ) : 0;
				$name         = isset( $item['name'] ) ? (string) $item['name'] : '';
				$qty          = isset( $item['qty'] ) ? absint( $item['qty'] ) : ( isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1 );

				if ( $order instanceof WC_Order && '' === $name && $line_item_id ) {
					$line_item = $order->get_item( $line_item_id );

					if ( $line_item instanceof WC_Order_Item_Product ) {
						$name = $line_item->get_name();
					}
				}

				$summary[] = array(
					'name' => $name ? $name : __( 'Unknown item', 'wt-eu-withdrawal-button' ),
					'qty'  => max( 1, $qty ),
				);
			}

			return $summary;
		}

		foreach ( $request->get_display_items() as $item ) {
			if ( $request->is_partial_withdrawal() && empty( $item['in_withdrawal'] ) ) {
				continue;
			}

			$qty = 1;

			if ( isset( $item['withdrawal_qty'] ) && $item['withdrawal_qty'] > 0 ) {
				$qty = absint( $item['withdrawal_qty'] );
			} elseif ( isset( $item['qty'] ) ) {
				$qty = absint( $item['qty'] );
			} elseif ( isset( $item['quantity'] ) ) {
				$qty = absint( $item['quantity'] );
			}

			$summary[] = array(
				'name' => isset( $item['name'] ) ? (string) $item['name'] : '',
				'qty'  => max( 1, $qty ),
			);
		}

		return $summary;
	}

	/**
	 * Return pending withdrawal requests for an order.
	 *
	 * @since 1.0.4
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return Wbte_Ewb_Request[]
	 */
	private function get_pending_requests_for_order( $order_id ) {
		$repository = $this->get_repository();

		if ( ! $repository ) {
			return array();
		}

		$pending = array();

		foreach ( $repository->find_by_order( $order_id ) as $request ) {
			if ( $request->is_pending() ) {
				$pending[] = $request;
			}
		}

		return $pending;
	}

	/**
	 * Resolve a withdrawal request for order-list AJAX actions.
	 *
	 * @since 1.0.4
	 *
	 * @param int $order_id   WooCommerce order ID.
	 * @param int $request_id Optional specific request ID.
	 * @return Wbte_Ewb_Request|null
	 */
	private function resolve_request_for_order_action( $order_id, $request_id = 0 ) {
		$repository = $this->get_repository();

		if ( ! $repository || ! $order_id ) {
			return null;
		}

		if ( $request_id > 0 ) {
			$request = $repository->find( $request_id );

			if ( $request && (int) $request->order_id === (int) $order_id && $request->is_pending() ) {
				return $request;
			}

			return null;
		}

		$pending = $this->get_pending_requests_for_order( $order_id );

		return 1 === count( $pending ) ? $pending[0] : null;
	}

	/**
	 * Add approve/reject action buttons to the orders list for
	 * orders with pending withdrawal status.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $actions Existing order actions.
	 * @param WC_Order $order   The order object.
	 * @return array Modified actions.
	 */
	public function add_order_actions( $actions, $order ) {
		if ( ! $order->has_status( 'pending-wdraw' ) ) {
			return $actions;
		}

		$pending = $this->get_pending_requests_for_order( $order->get_id() );

		if ( empty( $pending ) ) {
			return $actions;
		}

		foreach ( $pending as $request ) {
			$approve_url = wp_nonce_url(
				admin_url( 'admin-ajax.php?action=wbte_ewb_approve_withdrawal&order_id=' . $order->get_id() . '&request_id=' . $request->id ),
				'wbte_ewb_approve_withdrawal'
			);
			$reject_url  = wp_nonce_url(
				admin_url( 'admin-ajax.php?action=wbte_ewb_reject_withdrawal&order_id=' . $order->get_id() . '&request_id=' . $request->id ),
				'wbte_ewb_reject_withdrawal'
			);

			$approve_key = 'wbte_ewb_approve_' . $request->id;
			$reject_key  = 'wbte_ewb_reject_' . $request->id;

			if ( count( $pending ) > 1 ) {
				$approve_label = sprintf(
					/* translators: %d: withdrawal request ID */
					__( 'Approve withdrawal request #%d', 'wt-eu-withdrawal-button' ),
					$request->id
				);
				$reject_label  = sprintf(
					/* translators: %d: withdrawal request ID */
					__( 'Reject withdrawal request #%d', 'wt-eu-withdrawal-button' ),
					$request->id
				);
			} else {
				$approve_label = __( 'Approve withdrawal request', 'wt-eu-withdrawal-button' );
				$reject_label  = __( 'Reject withdrawal request', 'wt-eu-withdrawal-button' );
			}

			$actions[ $approve_key ] = array(
				'url'    => $approve_url,
				'name'   => $approve_label,
				'action' => 'wbte_ewb_approve',
			);

			$actions[ $reject_key ] = array(
				'url'    => $reject_url,
				'name'   => $reject_label,
				'action' => 'wbte_ewb_reject',
			);
		}

		return $actions;
	}

	/**
	 * Handle AJAX approve withdrawal from orders list.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_approve_withdrawal() {
		check_ajax_referer( 'wbte_ewb_approve_withdrawal' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$order_id   = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$request_id = isset( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0;

		if ( ! $order_id ) {
			wp_die( -1 );
		}

		$service = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'service' ) : null;

		if ( ! $service ) {
			wp_die( -1 );
		}

		$request = $this->resolve_request_for_order_action( $order_id, $request_id );

		if ( $request ) {
			$service->approve( $request->id, get_current_user_id() );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
		exit;
	}

	/**
	 * Handle AJAX reject withdrawal from orders list.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_reject_withdrawal() {
		check_ajax_referer( 'wbte_ewb_reject_withdrawal' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$order_id   = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$request_id = isset( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0;

		if ( ! $order_id ) {
			wp_die( -1 );
		}

		$service = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'service' ) : null;

		if ( ! $service ) {
			wp_die( -1 );
		}

		$request = $this->resolve_request_for_order_action( $order_id, $request_id );

		if ( $request ) {
			$service->reject( $request->id, get_current_user_id(), __( 'Rejected from orders list.', 'wt-eu-withdrawal-button' ) );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
		exit;
	}

	/**
	 * Render an inline status badge.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status The request status.
	 * @return void
	 */
	private function render_status_badge( $status ) {
		$labels = array(
			'pending'  => __( 'Pending', 'wt-eu-withdrawal-button' ),
			'approved' => __( 'Approved', 'wt-eu-withdrawal-button' ),
			'rejected' => __( 'Rejected', 'wt-eu-withdrawal-button' ),
		);

		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : esc_html( $status );

		echo '<span class="wbte-ewb-badge wbte-ewb-badge--' . esc_attr( $status ) . '">';
		echo esc_html( $label );
		echo '</span>';
	}

	/**
	 * Determine the correct screen ID for adding the meta box.
	 *
	 * Returns the HPOS screen if available, otherwise falls back
	 * to the legacy `shop_order` post type.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_order_screen_id() {
		$screen = get_current_screen();

		if ( $screen && 'woocommerce_page_wc-orders' === $screen->id ) {
			return $screen->id;
		}

		return 'shop_order';
	}

	/**
	 * Extract the order ID from a post, order, or integer.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $post_or_order Post object, order object, or integer.
	 * @return int The order ID, or 0 if it cannot be determined.
	 */
	private function get_order_id( $post_or_order ) {
		if ( $post_or_order instanceof WC_Order ) {
			return $post_or_order->get_id();
		}

		if ( $post_or_order instanceof WP_Post ) {
			return (int) $post_or_order->ID;
		}

		if ( is_numeric( $post_or_order ) ) {
			return absint( $post_or_order );
		}

		return 0;
	}

	/**
	 * Display a withdrawal badge next to order line items.
	 *
	 * Shows "Requested Nx" or "Withdrawn Nx" badges next to items
	 * that are part of a withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param int                   $item_id The order item ID.
	 * @param WC_Order_Item|object  $item    The order item object.
	 * @param WC_Product|null       $product The product object or null.
	 * @return void
	 */
	public function display_item_withdrawal_badge( $item_id, $item, $product ) {
		if ( ! is_callable( array( $item, 'get_order_id' ) ) ) {
			return;
		}

		$order_id = $item->get_order_id();
		$item_map = $this->get_order_item_withdrawal_map( $order_id );

		if ( ! isset( $item_map[ $item_id ] ) ) {
			return;
		}

		$entry = $item_map[ $item_id ];

		if ( $entry['approved'] > 0 ) {
			printf(
				'<mark class="wbte-ewb-item-badge wbte-ewb-item-badge--withdrawn">%s</mark>',
				wp_kses_post( sprintf(
					/* translators: %s: quantity withdrawn */
					_x( 'Withdrawn %sx', 'item badge', 'wt-eu-withdrawal-button' ),
					$entry['approved']
				) )
			);
		}

		if ( $entry['pending'] > 0 ) {
			printf(
				'<mark class="wbte-ewb-item-badge wbte-ewb-item-badge--requested">%s</mark>',
				wp_kses_post( sprintf(
					/* translators: %s: quantity requested */
					_x( 'Requested %sx', 'item badge', 'wt-eu-withdrawal-button' ),
					$entry['pending']
				) )
			);
		}
	}

	/**
	 * Build a map of item_id => withdrawal quantities for an order.
	 *
	 * Caches per request to avoid repeated DB queries when
	 * rendering multiple items in the same order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id The WooCommerce order ID.
	 * @return array<int, array{pending: int, approved: int}>
	 */
	private function get_order_item_withdrawal_map( $order_id ) {
		static $cache = array();

		if ( isset( $cache[ $order_id ] ) ) {
			return $cache[ $order_id ];
		}

		$cache[ $order_id ] = array();
		$repository         = $this->get_repository();

		if ( ! $repository ) {
			return $cache[ $order_id ];
		}

		$requests = $repository->find_by_order( $order_id );

		if ( empty( $requests ) ) {
			return $cache[ $order_id ];
		}

		foreach ( $requests as $request ) {
			$status = $request->status;

			// Only show badges for active requests.
			if ( ! in_array( $status, array( 'pending', 'approved' ), true ) ) {
				continue;
			}

			$items = $request->get_items();

			if ( empty( $items ) ) {
				// Full withdrawal — apply only to eligible line items.
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$eligibility    = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'eligibility' ) : null;
					$eligible_items = $eligibility ? $eligibility->get_eligible_items( $order ) : $order->get_items();

					foreach ( $eligible_items as $line_item ) {
						$lid = $line_item->get_id();
						if ( ! isset( $cache[ $order_id ][ $lid ] ) ) {
							$cache[ $order_id ][ $lid ] = array( 'pending' => 0, 'approved' => 0 );
						}
						$qty = $line_item->get_quantity();
						$cache[ $order_id ][ $lid ][ $status ] += $qty;
					}
				}
			} else {
				foreach ( $items as $item_data ) {
					$lid = isset( $item_data['line_item_id'] ) ? absint( $item_data['line_item_id'] ) : 0;
					$qty = isset( $item_data['qty'] ) ? absint( $item_data['qty'] ) : 1;

					if ( 0 === $lid ) {
						continue;
					}

					if ( ! isset( $cache[ $order_id ][ $lid ] ) ) {
						$cache[ $order_id ][ $lid ] = array( 'pending' => 0, 'approved' => 0 );
					}

					$cache[ $order_id ][ $lid ][ $status ] += $qty;
				}
			}
		}

		return $cache[ $order_id ];
	}
}
