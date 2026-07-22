<?php
/**
 * My Account integration.
 *
 * Adds a "Withdrawals" tab to the WooCommerce My Account area,
 * shows withdrawal request buttons on eligible orders, and
 * displays withdrawal status badges on order details.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_My_Account
 *
 * @since 1.0.0
 */
class Wbte_Ewb_My_Account {

	/**
	 * Endpoint slug for the Withdrawals tab.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ENDPOINT = 'withdrawals';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_order_action' ), 10, 2 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'show_withdrawal_status' ) );
	}

	/**
	 * Register the withdrawals rewrite endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * Add the Withdrawals tab to the My Account menu.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $items Menu items.
	 * @return array<string, string>
	 */
	public function add_menu_item( $items ) {
		/**
		 * Filters whether to show the Withdrawals tab in My Account.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $show Whether to show the tab. Default true.
		 */
		if ( ! apply_filters( 'wbte_ewb_show_my_account_tab', true ) ) {
			return $items;
		}

		// Insert before the logout item.
		$logout = array();
		if ( isset( $items['customer-logout'] ) ) {
			$logout = array( 'customer-logout' => $items['customer-logout'] );
			unset( $items['customer-logout'] );
		}

		$items[ self::ENDPOINT ] = __( 'Withdrawals', 'wt-eu-withdrawal-button' );

		return array_merge( $items, $logout );
	}

	/**
	 * Register the endpoint query variable.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $vars Query vars.
	 * @return array<int, string>
	 */
	public function add_query_var( $vars ) {
		$vars[] = self::ENDPOINT;

		return $vars;
	}

	/**
	 * Render the Withdrawals endpoint content.
	 *
	 * Queries the withdrawal repository for the current customer
	 * and displays their requests in a table.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_endpoint_content() {
		$customer_id = get_current_user_id();

		if ( ! $customer_id ) {
			echo '<p>' . esc_html__( 'You must be logged in to view your withdrawal requests.', 'wt-eu-withdrawal-button' ) . '</p>';
			return;
		}

		$requests = $this->get_customer_requests( $customer_id );

		if ( empty( $requests ) ) {
			echo '<p>' . esc_html__( 'You have not submitted any withdrawal requests yet.', 'wt-eu-withdrawal-button' ) . '</p>';
			return;
		}

		/**
		 * Fires before the withdrawals table in My Account.
		 *
		 * @since 1.0.0
		 *
		 * @param array $requests The customer's withdrawal requests.
		 */
		do_action( 'wbte_ewb_before_my_account_withdrawals', $requests );
		?>
		<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive wbte-ewb-withdrawals-table">
			<thead>
				<tr>
					<th class="wbte-ewb-withdrawals-table__header wbte-ewb-withdrawals-table__header--request-id">
						<?php esc_html_e( 'Request', 'wt-eu-withdrawal-button' ); ?>
					</th>
					<th class="wbte-ewb-withdrawals-table__header wbte-ewb-withdrawals-table__header--order">
						<?php esc_html_e( 'Order', 'wt-eu-withdrawal-button' ); ?>
					</th>
					<th class="wbte-ewb-withdrawals-table__header wbte-ewb-withdrawals-table__header--date">
						<?php esc_html_e( 'Date', 'wt-eu-withdrawal-button' ); ?>
					</th>
					<th class="wbte-ewb-withdrawals-table__header wbte-ewb-withdrawals-table__header--type">
						<?php esc_html_e( 'Type', 'wt-eu-withdrawal-button' ); ?>
					</th>
					<th class="wbte-ewb-withdrawals-table__header wbte-ewb-withdrawals-table__header--status">
						<?php esc_html_e( 'Status', 'wt-eu-withdrawal-button' ); ?>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $requests as $request ) : ?>
					<tr class="wbte-ewb-withdrawals-table__row">
						<td class="wbte-ewb-withdrawals-table__cell" data-title="<?php esc_attr_e( 'Request', 'wt-eu-withdrawal-button' ); ?>">
							<?php echo esc_html( '#' . $request->id ); ?>
						</td>
						<td class="wbte-ewb-withdrawals-table__cell" data-title="<?php esc_attr_e( 'Order', 'wt-eu-withdrawal-button' ); ?>">
							<?php echo esc_html( '#' . $request->order_number ); ?>
						</td>
						<td class="wbte-ewb-withdrawals-table__cell" data-title="<?php esc_attr_e( 'Date', 'wt-eu-withdrawal-button' ); ?>">
							<?php echo esc_html( Wbte_Ewb_Request::format_datetime( $request->created_at ) ); ?>
						</td>
						<td class="wbte-ewb-withdrawals-table__cell" data-title="<?php esc_attr_e( 'Type', 'wt-eu-withdrawal-button' ); ?>">
							<?php
							echo 'partial' === $request->request_type
								? esc_html__( 'Partial', 'wt-eu-withdrawal-button' )
								: esc_html__( 'Full', 'wt-eu-withdrawal-button' );
							?>
						</td>
						<td class="wbte-ewb-withdrawals-table__cell" data-title="<?php esc_attr_e( 'Status', 'wt-eu-withdrawal-button' ); ?>">
							<?php echo wp_kses_post( $this->get_status_badge( $request->status ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php

		/**
		 * Fires after the withdrawals table in My Account.
		 *
		 * @since 1.0.0
		 *
		 * @param array $requests The customer's withdrawal requests.
		 */
		do_action( 'wbte_ewb_after_my_account_withdrawals', $requests );
	}

	/**
	 * Add "Request Withdrawal" action button to eligible orders.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, string>> $actions Existing order actions.
	 * @param WC_Order                             $order   The order object.
	 * @return array<string, array<string, string>>
	 */
	public function add_order_action( $actions, $order ) {
		// Don't show on the order-received (thank you) page.
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			return $actions;
		}

		if ( ! $this->is_order_eligible( $order ) ) {
			return $actions;
		}

		$withdrawal_page_id = Wbte_Ewb_Settings::get_withdrawal_page_id();

		if ( ! $withdrawal_page_id ) {
			return $actions;
		}

		$country = Wbte_Ewb_Customer_Context::get_country_code( $order );

		/**
		 * Filters whether the My Account order withdrawal action should be shown.
		 *
		 * @since 1.0.5
		 *
		 * @param bool     $show    Whether to show the withdrawal action on order rows.
		 * @param WC_Order $order   The order object.
		 * @param string   $country Customer ISO country code, if known.
		 */
		if ( ! apply_filters( 'wbte_ewb_show_my_account_order_withdrawal_link', true, $order, $country ) ) {
			return $actions;
		}

		$withdrawal_url = add_query_arg(
			array( 'order_id' => $order->get_id() ),
			Wbte_Ewb_Settings::get_withdrawal_page_url()
		);

		$actions['withdrawal'] = array(
			'url'  => $withdrawal_url,
			'name' => Wbte_Ewb_Settings::get_button_label( Wbte_Ewb_Settings::LABEL_MY_ACCOUNT_ORDER ),
		);

		return $actions;
	}

	/**
	 * Display a withdrawal status badge on the order details page.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order The order object.
	 * @return void
	 */
	public function show_withdrawal_status( $order ) {
		$requests = $this->get_order_requests( $order->get_id() );

		if ( empty( $requests ) ) {
			return;
		}

		$heading = count( $requests ) > 1
			? __( 'Withdrawal Requests', 'wt-eu-withdrawal-button' )
			: __( 'Withdrawal Request', 'wt-eu-withdrawal-button' );

		?>
		<div class="wbte-ewb-order-withdrawal-status">
			<h3><?php echo esc_html( $heading ); ?></h3>
			<div class="wbte-ewb-order-withdrawal-timeline">
				<?php foreach ( $requests as $request ) : ?>
					<?php $this->render_order_request_summary( $request ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single withdrawal request summary on the order details page.
	 *
	 * @since 1.0.7
	 *
	 * @param Wbte_Ewb_Request $request Withdrawal request.
	 * @return void
	 */
	private function render_order_request_summary( $request ) {
		if ( ! $request instanceof Wbte_Ewb_Request ) {
			return;
		}

		$rejection_note = $this->get_request_rejection_note( $request );
		$status_class   = sanitize_html_class( $request->status );
		?>
		<div class="wbte-ewb-order-withdrawal-timeline__item wbte-ewb-order-withdrawal-timeline__item--<?php echo esc_attr( $status_class ); ?>">
			<div class="wbte-ewb-order-withdrawal-timeline__marker" aria-hidden="true">
				<span class="wbte-ewb-order-withdrawal-timeline__dot"></span>
				<span class="wbte-ewb-order-withdrawal-timeline__line"></span>
			</div>
			<div class="wbte-ewb-order-withdrawal-timeline__content">
				<div class="wbte-ewb-order-withdrawal-timeline__header">
					<span class="wbte-ewb-order-withdrawal-timeline__title">
						<?php
						printf(
							/* translators: %s: request ID */
							esc_html__( 'Request #%s', 'wt-eu-withdrawal-button' ),
							esc_html( $request->id )
						);
						?>
					</span>
					<?php echo wp_kses_post( $this->get_status_badge( $request->status ) ); ?>
				</div>
				<p class="wbte-ewb-order-withdrawal-timeline__meta">
					<?php
					printf(
						/* translators: %s: withdrawal request type label */
						esc_html__( 'Type: %s', 'wt-eu-withdrawal-button' ),
						esc_html( $request->get_request_type_label() )
					);
					?>
				</p>
				<ul class="wbte-ewb-order-withdrawal-timeline__events">
					<?php if ( ! empty( $request->created_at ) ) : ?>
						<li class="wbte-ewb-order-withdrawal-timeline__event">
							<span class="wbte-ewb-order-withdrawal-timeline__event-label">
								<?php esc_html_e( 'Submitted', 'wt-eu-withdrawal-button' ); ?>
							</span>
							<span class="wbte-ewb-order-withdrawal-timeline__event-value">
								<?php echo esc_html( Wbte_Ewb_Request::format_datetime( $request->created_at ) ); ?>
							</span>
						</li>
					<?php endif; ?>
					<?php if ( ! $request->is_pending() && ! empty( $request->processed_at ) ) : ?>
						<li class="wbte-ewb-order-withdrawal-timeline__event">
							<span class="wbte-ewb-order-withdrawal-timeline__event-label">
								<?php esc_html_e( 'Processed', 'wt-eu-withdrawal-button' ); ?>
							</span>
							<span class="wbte-ewb-order-withdrawal-timeline__event-value">
								<?php echo esc_html( Wbte_Ewb_Request::format_datetime( $request->processed_at ) ); ?>
							</span>
						</li>
					<?php endif; ?>
				</ul>
				<?php if ( $request->is_rejected() && '' !== $rejection_note ) : ?>
					<p class="wbte-ewb-order-withdrawal-timeline__note">
						<strong><?php esc_html_e( 'Reason', 'wt-eu-withdrawal-button' ); ?>:</strong>
						<?php echo esc_html( $rejection_note ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the rejection note stored on a withdrawal request.
	 *
	 * @since 1.0.7
	 *
	 * @param Wbte_Ewb_Request $request Withdrawal request.
	 * @return string
	 */
	private function get_request_rejection_note( $request ) {
		if ( ! $request instanceof Wbte_Ewb_Request || ! $request->is_rejected() ) {
			return '';
		}

		$meta = $request->get_meta();

		if ( ! empty( $meta['reject_reason'] ) ) {
			return sanitize_textarea_field( $meta['reject_reason'] );
		}

		if ( ! empty( $meta['admin_note'] ) ) {
			return sanitize_textarea_field( $meta['admin_note'] );
		}

		return '';
	}

	/**
	 * Check whether an order is eligible for withdrawal.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order The order.
	 * @return bool
	 */
	private function is_order_eligible( $order ) {
		$eligibility = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'eligibility' ) : new Wbte_Ewb_Eligibility();

		if ( ! $eligibility instanceof Wbte_Ewb_Eligibility ) {
			return false;
		}

		return $eligibility->is_order_eligible( $order );
	}

	/**
	 * Query withdrawal requests for a given customer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $customer_id WordPress user ID.
	 * @return array<Wbte_Ewb_Request>
	 */
	private function get_customer_requests( $customer_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wbte_ewb_withdrawals';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_user_id = %d ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$customer_id
			)
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$requests = array();
		foreach ( $rows as $row ) {
			$requests[] = Wbte_Ewb_Request::from_row( $row );
		}

		return $requests;
	}

	/**
	 * Get all withdrawal requests for an order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return Wbte_Ewb_Request[]
	 */
	private function get_order_requests( $order_id ) {
		$repository = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'repository' ) : null;

		if ( ! $repository instanceof Wbte_Ewb_Request_Repository ) {
			return array();
		}

		return $repository->find_by_order( absint( $order_id ) );
	}

	/**
	 * Generate an HTML status badge.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Request status.
	 * @return string HTML markup.
	 */
	private function get_status_badge( $status ) {
		$labels = array(
			'pending'  => __( 'Pending', 'wt-eu-withdrawal-button' ),
			'approved' => __( 'Approved', 'wt-eu-withdrawal-button' ),
			'rejected' => __( 'Rejected', 'wt-eu-withdrawal-button' ),
		);

		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );

		return sprintf(
			'<span class="wbte-ewb-badge wbte-ewb-badge--%s">%s</span>',
			esc_attr( sanitize_html_class( $status ) ),
			esc_html( $label )
		);
	}
}
