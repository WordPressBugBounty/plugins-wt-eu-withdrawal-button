<?php
/**
 * Pending guest withdrawal request repository.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Pending_Request_Repository
 *
 * @since 1.1.0
 */
class Wbte_Ewb_Pending_Request_Repository {

	/**
	 * Return the pending withdrawals table name.
	 *
	 * @return string
	 */
	private function get_table() {
		global $wpdb;

		return $wpdb->prefix . 'wbte_ewb_pending_withdrawals';
	}

	/**
	 * Create a pending withdrawal request.
	 *
	 * @param array<string, mixed> $data Pending request data.
	 * @return Wbte_Ewb_Pending_Request|null
	 */
	public function create( $data ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$this->get_table(),
			array(
				'order_number'   => isset( $data['order_number'] ) ? sanitize_text_field( $data['order_number'] ) : '',
				'customer_email' => isset( $data['customer_email'] ) ? sanitize_email( $data['customer_email'] ) : '',
				'reason'         => isset( $data['reason'] ) ? sanitize_textarea_field( $data['reason'] ) : '',
				'verify_token'   => isset( $data['verify_token'] ) ? sanitize_text_field( $data['verify_token'] ) : '',
				'status'         => 'pending_verification',
				'created_at'     => $now,
				'expires_at'     => isset( $data['expires_at'] ) ? $data['expires_at'] : $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return null;
		}

		return $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Find a pending request by ID.
	 *
	 * @param int $id Pending request ID.
	 * @return Wbte_Ewb_Pending_Request|null
	 */
	public function find( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		return $row ? Wbte_Ewb_Pending_Request::from_row( $row ) : null;
	}

	/**
	 * Find a pending request by verification token.
	 *
	 * @param string $token Verification token.
	 * @return Wbte_Ewb_Pending_Request|null
	 */
	public function find_by_token( $token ) {
		global $wpdb;

		$token = sanitize_text_field( $token );

		if ( '' === $token ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE verify_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$token
			)
		);

		return $row ? Wbte_Ewb_Pending_Request::from_row( $row ) : null;
	}

	/**
	 * Check whether an active pending verification exists for an order and email.
	 *
	 * @since 1.1.0
	 *
	 * @param string $order_number   Customer-facing order number.
	 * @param string $customer_email Customer billing email.
	 * @return bool
	 */
	public function has_active_pending_verification( $order_number, $customer_email ) {
		global $wpdb;

		$order_number   = sanitize_text_field( $order_number );
		$customer_email = sanitize_email( $customer_email );

		if ( '' === $order_number || '' === $customer_email ) {
			return false;
		}

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->get_table()} WHERE order_number = %s AND LOWER(customer_email) = LOWER(%s) AND status = %s AND expires_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_number,
				$customer_email,
				'pending_verification',
				$now
			)
		);

		return $count > 0;
	}

	/**
	 * Check whether a guest has an open verification or verified-but-unsubmitted session.
	 *
	 * @since 1.0.3
	 *
	 * @param string $order_number   Customer-facing order number.
	 * @param string $customer_email Customer billing email.
	 * @return bool
	 */
	public function has_open_guest_session( $order_number, $customer_email ) {
		global $wpdb;

		$order_number   = sanitize_text_field( $order_number );
		$customer_email = sanitize_email( $customer_email );

		if ( '' === $order_number || '' === $customer_email ) {
			return false;
		}

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->get_table()} WHERE order_number = %s AND LOWER(customer_email) = LOWER(%s) AND expires_at >= %s AND ( status = %s OR ( status = %s AND ( withdrawal_id IS NULL OR withdrawal_id = 0 ) ) )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_number,
				$customer_email,
				$now,
				'pending_verification',
				'verified'
			)
		);

		return $count > 0;
	}

	/**
	 * Update a pending request.
	 *
	 * @param int                  $id   Pending request ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$allowed = array(
			'status',
			'failure_reason',
			'withdrawal_id',
			'verified_at',
		);

		$update = array();
		$format = array();

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			if ( 'withdrawal_id' === $key ) {
				$update[ $key ] = absint( $data[ $key ] );
				$format[]       = '%d';
				continue;
			}

			$update[ $key ] = sanitize_text_field( (string) $data[ $key ] );
			$format[]       = '%s';
		}

		if ( empty( $update ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			$this->get_table(),
			$update,
			array( 'id' => absint( $id ) ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Delete pending guest withdrawal records for an order number.
	 *
	 * @since 1.0.3
	 *
	 * @param string $order_number Customer-facing order number.
	 * @return int Number of rows deleted.
	 */
	public function delete_by_order_number( $order_number ) {
		global $wpdb;

		$order_number = sanitize_text_field( $order_number );

		if ( '' === $order_number ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->get_table()} WHERE order_number = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_number
			)
		);

		return false === $deleted ? 0 : (int) $deleted;
	}

	/**
	 * Delete expired pending requests.
	 *
	 * @return void
	 */
	public function delete_expired() {
		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->get_table()} WHERE status = %s AND expires_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'pending_verification',
				$now
			)
		);
	}
}
