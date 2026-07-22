<?php
/**
 * Withdrawal request repository.
 *
 * Handles all database CRUD operations for withdrawal requests
 * and their associated log entries.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Request_Repository
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Request_Repository {

	/**
	 * Return the withdrawals table name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_table() {
		global $wpdb;

		return $wpdb->prefix . 'wbte_ewb_withdrawals';
	}

	/**
	 * Return the withdrawal logs table name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_logs_table() {
		global $wpdb;

		return $wpdb->prefix . 'wbte_ewb_withdrawal_logs';
	}

	/**
	 * Whether WooCommerce is storing orders in the HPOS tables.
	 *
	 * @since 1.0.3
	 *
	 * @return bool
	 */
	private function uses_hpos_orders_table() {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * SQL fragments to limit queries to requests whose order still exists and is not trashed.
	 *
	 * @since 1.0.3
	 *
	 * @return array{join: string, where: string}
	 */
	private function get_non_trashed_order_sql() {
		global $wpdb;

		if ( $this->uses_hpos_orders_table() ) {
			$orders_table = $wpdb->prefix . 'wc_orders';

			return array(
				'join'  => "INNER JOIN {$orders_table} wbte_ewb_o ON wbte_ewb_o.id = w.order_id",
				'where' => "wbte_ewb_o.status != 'trash'",
			);
		}

		return array(
			'join'  => "INNER JOIN {$wpdb->posts} wbte_ewb_o ON wbte_ewb_o.ID = w.order_id AND wbte_ewb_o.post_type IN ( 'shop_order', 'shop_order_placehold' )",
			'where' => "wbte_ewb_o.post_status != 'trash'",
		);
	}

	/**
	 * Find a single withdrawal request by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Request ID.
	 * @return Wbte_Ewb_Request|null
	 */
	public function find( $id ) {
		global $wpdb;

		$table = $this->get_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( null === $row ) {
			return null;
		}

		return Wbte_Ewb_Request::from_row( $row );
	}

	/**
	 * Check whether a customer already has a pending or approved withdrawal for an order.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $order_id       WooCommerce order ID.
	 * @param string $customer_email Customer billing email.
	 * @return bool
	 */
	public function has_submitted_request_for_customer( $order_id, $customer_email ) {
		global $wpdb;

		$table = $this->get_table();
		$order_id = absint( $order_id );
		$customer_email = sanitize_email( $customer_email );

		if ( ! $order_id || '' === $customer_email ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND status IN ('pending', 'approved') AND LOWER(customer_email) = LOWER(%s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id,
				$customer_email
			)
		);

		return $count > 0;
	}

	/**
	 * Find all withdrawal requests for a given order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return Wbte_Ewb_Request[]
	 */
	public function find_by_order( $order_id ) {
		global $wpdb;

		$table = $this->get_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC", $order_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$requests = array();
		foreach ( $rows as $row ) {
			$requests[] = Wbte_Ewb_Request::from_row( $row );
		}

		return $requests;
	}

	/**
	 * Count all recorded withdrawal requests.
	 *
	 * @since 1.0.5
	 *
	 * @return int
	 */
	public function count_all() {
		global $wpdb;

		$table = $this->get_table();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.

		return absint( $count );
	}

	/**
	 * Find pending and approved withdrawal requests for an order.
	 *
	 * @since 1.0.4
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return Wbte_Ewb_Request[]
	 */
	public function find_active_by_order( $order_id ) {
		$requests = $this->find_by_order( $order_id );
		$active   = array();

		foreach ( $requests as $request ) {
			if ( in_array( $request->status, array( 'pending', 'approved' ), true ) ) {
				$active[] = $request;
			}
		}

		return $active;
	}

	/**
	 * Check whether an order still has pending withdrawal requests.
	 *
	 * @since 1.0.5
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return bool
	 */
	public function has_pending_by_order( $order_id ) {
		foreach ( $this->find_by_order( $order_id ) as $request ) {
			if ( $request->is_pending() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find pending and approved withdrawal requests for an order and customer email.
	 *
	 * @since 1.0.3
	 *
	 * @param int    $order_id       WooCommerce order ID.
	 * @param string $customer_email Customer billing email.
	 * @return Wbte_Ewb_Request[]
	 */
	public function find_active_by_order_and_email( $order_id, $customer_email ) {
		$requests = $this->find_active_by_order( $order_id );
		$active   = array();
		$email    = strtolower( sanitize_email( $customer_email ) );

		if ( ! $email ) {
			return array();
		}

		foreach ( $requests as $request ) {
			if ( strtolower( $request->customer_email ) === $email ) {
				$active[] = $request;
			}
		}

		return $active;
	}

	/**
	 * Run a paginated and filtered query for withdrawal requests.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args {
	 *     Query arguments.
	 *
	 *     @type string $status           Filter by status.
	 *     @type string $customer_email   Filter by customer email.
	 *     @type int    $customer_user_id Filter by customer user ID.
	 *     @type int    $order_id         Filter by order ID.
	 *     @type string $order_number      Filter by order number.
	 *     @type string $verification_code Filter by receipt hash (full or partial match).
	 *     @type string $search            Search by order number or receipt hash.
	 *     @type string $date_from        Filter requests created on or after this date (Y-m-d).
	 *     @type string $date_to          Filter requests created on or before this date (Y-m-d).
	 *     @type int    $per_page         Results per page. Default 20.
	 *     @type int    $page             Page number. Default 1.
	 *     @type string $orderby          Column to sort by. Default 'created_at'.
	 *     @type string $order            Sort direction: ASC or DESC. Default 'DESC'.
	 * }
	 * @return array{ items: Wbte_Ewb_Request[], total: int }
	 */
	public function query( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'           => '',
			'customer_email'   => '',
			'customer_user_id' => 0,
			'order_id'         => 0,
			'order_number'      => '',
			'verification_code' => '',
			'search'            => '',
			'date_from'        => '',
			'date_to'          => '',
			'per_page'         => 20,
			'page'             => 1,
			'orderby'          => 'created_at',
			'order'            => 'DESC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $this->get_table();
		$order_sql = $this->get_non_trashed_order_sql();

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'w.status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['customer_email'] ) ) {
			$where[]  = 'w.customer_email = %s';
			$values[] = sanitize_email( $args['customer_email'] );
		}

		if ( ! empty( $args['customer_user_id'] ) ) {
			$where[]  = 'w.customer_user_id = %d';
			$values[] = absint( $args['customer_user_id'] );
		}

		if ( ! empty( $args['order_id'] ) ) {
			$where[]  = 'w.order_id = %d';
			$values[] = absint( $args['order_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$search              = sanitize_text_field( (string) $args['search'] );
			$verification_search = strtolower( preg_replace( '/[^a-f0-9]/i', '', $search ) );
			$search_conditions   = array( 'w.order_number = %s' );
			$search_values       = array( $search );

			if ( '' !== $verification_search ) {
				$search_conditions[] = 'w.meta_json LIKE %s';
				$search_values[]     = '%"verification_code":"' . $wpdb->esc_like( $verification_search ) . '%';
			}

			$where[] = '(' . implode( ' OR ', $search_conditions ) . ')';
			$values  = array_merge( $values, $search_values );
		} else {
			if ( ! empty( $args['order_number'] ) ) {
				$where[]  = 'w.order_number = %s';
				$values[] = sanitize_text_field( $args['order_number'] );
			}

			if ( ! empty( $args['verification_code'] ) ) {
				$code = strtolower( preg_replace( '/[^a-f0-9]/i', '', (string) $args['verification_code'] ) );

				if ( '' !== $code ) {
					$where[]  = 'w.meta_json LIKE %s';
					$values[] = '%"verification_code":"' . $wpdb->esc_like( $code ) . '%';
				}
			}
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'w.created_at >= %s';
			$values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'w.created_at <= %s';
			$values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where ) . ' AND ' . $order_sql['where'];

		// Whitelist sortable columns.
		$allowed_orderby = array( 'id', 'order_id', 'status', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $args['order'] ) : 'DESC';

		$per_page = absint( $args['per_page'] );
		$page     = absint( $args['page'] );

		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		if ( $page < 1 ) {
			$page = 1;
		}

		$offset = ( $page - 1 ) * $per_page;

		// Count total results.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, dynamic WHERE.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} w {$order_sql['join']} WHERE {$where_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				...$values
			)
		);

		// Fetch paginated rows.
		$limit_values   = $values;
		$limit_values[] = $offset;
		$limit_values[] = $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, dynamic WHERE.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic WHERE placeholders are counted separately.
			$wpdb->prepare(
				"SELECT w.* FROM {$table} w {$order_sql['join']} WHERE {$where_clause} ORDER BY w.{$orderby} {$order} LIMIT %d, %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$limit_values
			)
		);

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = Wbte_Ewb_Request::from_row( $row );
		}

		$result = array(
			'items' => $items,
			'total' => $total,
		);

		/**
		 * Filters the withdrawal request query results.
		 *
		 * @since 1.0.0
		 *
		 * @param array{ items: Wbte_Ewb_Request[], total: int } $result Query results.
		 * @param array<string, mixed>                          $args   Original query arguments.
		 */
		return apply_filters( 'wbte_ewb_request_query_results', $result, $args );
	}

	/**
	 * Create a new withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Request data.
	 * @return Wbte_Ewb_Request|false The created request or false on failure.
	 */
	public function create( $data ) {
		global $wpdb;

		$now   = current_time( 'mysql', true );
		$table = $this->get_table();

		$insert_data = array(
			'order_id'         => isset( $data['order_id'] ) ? absint( $data['order_id'] ) : 0,
			'order_number'     => isset( $data['order_number'] ) ? sanitize_text_field( $data['order_number'] ) : '',
			'customer_email'   => isset( $data['customer_email'] ) ? sanitize_email( $data['customer_email'] ) : '',
			'customer_user_id' => isset( $data['customer_user_id'] ) ? absint( $data['customer_user_id'] ) : 0,
			'status'           => 'pending',
			'request_type'     => isset( $data['request_type'] ) ? sanitize_text_field( $data['request_type'] ) : 'full',
			'items_json'       => isset( $data['items_json'] ) ? $data['items_json'] : '[]',
			'reason'           => isset( $data['reason'] ) ? sanitize_textarea_field( $data['reason'] ) : '',
			'meta_json'        => isset( $data['meta_json'] ) ? $data['meta_json'] : '{}',
			'created_at'       => $now,
			'updated_at'       => $now,
			'created_by'       => isset( $data['created_by'] ) ? absint( $data['created_by'] ) : 0,
			'processed_by'     => 0,
			'processed_at'     => null,
		);

		$format = array(
			'%d', // order_id
			'%s', // order_number
			'%s', // customer_email
			'%d', // customer_user_id
			'%s', // status
			'%s', // request_type
			'%s', // items_json
			'%s', // reason
			'%s', // meta_json
			'%s', // created_at
			'%s', // updated_at
			'%d', // created_by
			'%d', // processed_by
			'%s', // processed_at
		);

		$inserted = $wpdb->insert( $table, $insert_data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( false === $inserted ) {
			return false;
		}

		$request = $this->find( $wpdb->insert_id );

		if ( null !== $request ) {
			$request = $this->sync_request_verification_code( $request );
		}

		if ( null !== $request ) {
			/**
			 * Fires after a withdrawal request is created.
			 *
			 * @since 1.0.0
			 *
			 * @param Wbte_Ewb_Request      $request The created request.
			 * @param array<string, mixed>  $data    The original data used to create the request.
			 */
			do_action( 'wbte_ewb_request_created', $request, $data );
		}

		return $request;
	}

	/**
	 * Update an existing withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $id   Request ID.
	 * @param array<string, mixed> $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$table = $this->get_table();

		$data['updated_at'] = current_time( 'mysql', true );

		$allowed_fields = array(
			'status',
			'request_type',
			'items_json',
			'reason',
			'meta_json',
			'processed_by',
			'processed_at',
			'updated_at',
		);

		$update_data = array();
		$format      = array();

		foreach ( $allowed_fields as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}

			$update_data[ $field ] = $data[ $field ];

			if ( in_array( $field, array( 'processed_by' ), true ) ) {
				$format[] = '%d';
			} else {
				$format[] = '%s';
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$hash_fields = array( 'items_json', 'reason', 'customer_email', 'order_number', 'request_type' );
		$sync_code   = false;

		foreach ( $hash_fields as $field ) {
			if ( array_key_exists( $field, $update_data ) ) {
				$sync_code = true;
				break;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => absint( $id ) ),
			$format,
			array( '%d' )
		);

		if ( false !== $result ) {
			$request = $this->find( $id );

			if ( $sync_code && $request ) {
				$request = $this->sync_request_verification_code( $request );
			}

			/**
			 * Fires after a withdrawal request is updated.
			 *
			 * @since 1.0.0
			 *
			 * @param int                   $id      Request ID.
			 * @param array<string, mixed>  $data    The data that was updated.
			 * @param Wbte_Ewb_Request|null  $request The updated request object.
			 */
			do_action( 'wbte_ewb_request_updated', $id, $data, $request );
		}

		return false !== $result;
	}

	/**
	 * Delete all log entries for a withdrawal request.
	 *
	 * @since 1.0.3
	 *
	 * @param int $withdrawal_id Withdrawal request ID.
	 * @return void
	 */
	private function delete_logs_for_withdrawal( $withdrawal_id ) {
		global $wpdb;

		$logs_table = $this->get_logs_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->delete(
			$logs_table,
			array( 'withdrawal_id' => absint( $withdrawal_id ) ),
			array( '%d' )
		);
	}

	/**
	 * Delete all withdrawal requests (and logs) linked to an order.
	 *
	 * @since 1.0.3
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return int Number of withdrawal requests deleted.
	 */
	public function delete_by_order( $order_id ) {
		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return 0;
		}

		$requests = $this->find_by_order( $order_id );
		$deleted  = 0;

		foreach ( $requests as $request ) {
			if ( $this->delete( $request->id ) ) {
				++$deleted;
			}
		}

		/**
		 * Fires after withdrawal requests for an order are deleted.
		 *
		 * @since 1.0.3
		 *
		 * @param int $order_id WooCommerce order ID.
		 * @param int $deleted  Number of withdrawal requests deleted.
		 */
		do_action( 'wbte_ewb_requests_deleted_for_order', $order_id, $deleted );

		return $deleted;
	}

	/**
	 * Delete a withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Request ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $id ) {
		global $wpdb;

		$id    = absint( $id );
		$table = $this->get_table();

		$this->delete_logs_for_withdrawal( $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$result = $wpdb->delete(
			$table,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false !== $result && $result > 0 ) {
			/**
			 * Fires after a withdrawal request is deleted.
			 *
			 * @since 1.0.0
			 *
			 * @param int $id The deleted request ID.
			 */
			do_action( 'wbte_ewb_request_deleted', $id );
		}

		return false !== $result && $result > 0;
	}

	/**
	 * Add a log entry for a withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $withdrawal_id The withdrawal request ID.
	 * @param array<string, mixed> $data {
	 *     Log entry data.
	 *
	 *     @type string $actor_type  Type of actor: 'customer', 'admin', 'system'.
	 *     @type int    $actor_id    User ID of the actor.
	 *     @type string $action      Action performed.
	 *     @type string $from_status Previous status.
	 *     @type string $to_status   New status.
	 *     @type string $note        Optional note.
	 * }
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function add_log( $withdrawal_id, $data ) {
		global $wpdb;

		$table = $this->get_logs_table();

		$log_data = array(
			'withdrawal_id' => absint( $withdrawal_id ),
			'actor_type'    => isset( $data['actor_type'] ) ? sanitize_text_field( $data['actor_type'] ) : '',
			'actor_id'      => isset( $data['actor_id'] ) ? absint( $data['actor_id'] ) : 0,
			'action'        => isset( $data['action'] ) ? sanitize_text_field( $data['action'] ) : '',
			'from_status'   => isset( $data['from_status'] ) ? sanitize_text_field( $data['from_status'] ) : '',
			'to_status'     => isset( $data['to_status'] ) ? sanitize_text_field( $data['to_status'] ) : '',
			'note'          => isset( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : '',
			'created_at'    => current_time( 'mysql', true ),
		);

		$format = array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' );

		$inserted = $wpdb->insert( $table, $log_data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( false === $inserted ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get dashboard statistics for withdrawal requests.
	 *
	 * Returns open (pending) count with week-over-week delta,
	 * and approved-in-last-30-days count with period-over-period percentage change.
	 *
	 * @since 1.0.0
	 *
	 * @return array{open: int, open_prev_week: int, approved_30d: int, approved_prev_30d: int}
	 */
	public function get_stats() {
		global $wpdb;

		$table     = $this->get_table();
		$now       = current_time( 'mysql', true );
		$order_sql = $this->get_non_trashed_order_sql();
		$from      = "{$table} w {$order_sql['join']}";
		$order_where = $order_sql['where'];

		// Open (pending) requests right now.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$open = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$from} WHERE w.status = %s AND {$order_where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'pending'
			)
		);

		// Pending requests created in the last 7 days (new this week).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$open_last_7d = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$from} WHERE w.status = %s AND {$order_where} AND w.created_at >= DATE_SUB(%s, INTERVAL 7 DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'pending',
				$now
			)
		);

		// Pending requests created between 14 and 7 days ago (previous week).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$open_prev_7d = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$from} WHERE w.status = %s AND {$order_where} AND w.created_at >= DATE_SUB(%s, INTERVAL 14 DAY) AND w.created_at < DATE_SUB(%s, INTERVAL 7 DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'pending',
				$now,
				$now
			)
		);

		// Approved in last 30 days.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$approved_30d = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$from} WHERE w.status = %s AND {$order_where} AND w.processed_at >= DATE_SUB(%s, INTERVAL 30 DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'approved',
				$now
			)
		);

		// Approved in the previous 30-day period (30-60 days ago).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$approved_prev_30d = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$from} WHERE w.status = %s AND {$order_where} AND w.processed_at >= DATE_SUB(%s, INTERVAL 60 DAY) AND w.processed_at < DATE_SUB(%s, INTERVAL 30 DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'approved',
				$now,
				$now
			)
		);

		return array(
			'open'              => $open,
			'open_last_7d'      => $open_last_7d,
			'open_prev_7d'      => $open_prev_7d,
			'approved_30d'      => $approved_30d,
			'approved_prev_30d' => $approved_prev_30d,
		);
	}

	/**
	 * Retrieve all log entries for a withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param int $withdrawal_id The withdrawal request ID.
	 * @return array Log entries ordered by created_at DESC.
	 */
	public function get_logs( $withdrawal_id ) {
		global $wpdb;

		$table = $this->get_logs_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE withdrawal_id = %d ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $withdrawal_id )
			)
		);
	}

	/**
	 * Compute and persist the SHA-256 receipt hash for a request.
	 *
	 * @since 1.0.4
	 *
	 * @param Wbte_Ewb_Request $request Withdrawal request.
	 * @return Wbte_Ewb_Request|null
	 */
	private function sync_request_verification_code( $request ) {
		if ( ! $request instanceof Wbte_Ewb_Request || empty( $request->id ) ) {
			return $request;
		}

		$request->sync_verification_code();

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->update(
			$this->get_table(),
			array( 'meta_json' => $request->meta_json ),
			array( 'id' => absint( $request->id ) ),
			array( '%s' ),
			array( '%d' )
		);

		return $this->find( $request->id );
	}
}
