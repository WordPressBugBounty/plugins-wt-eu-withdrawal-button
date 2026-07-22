<?php
/**
 * Pending guest withdrawal request data model.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Pending_Request
 *
 * @since 1.1.0
 */
class Wbte_Ewb_Pending_Request {

	/**
	 * Pending request ID.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Customer-facing order number.
	 *
	 * @var string
	 */
	public $order_number = '';

	/**
	 * Customer email address.
	 *
	 * @var string
	 */
	public $customer_email = '';

	/**
	 * Withdrawal reason.
	 *
	 * @var string
	 */
	public $reason = '';

	/**
	 * Verification token.
	 *
	 * @var string
	 */
	public $verify_token = '';

	/**
	 * Queue status.
	 *
	 * @var string
	 */
	public $status = 'pending_verification';

	/**
	 * Failure reason when verification fails.
	 *
	 * @var string
	 */
	public $failure_reason = '';

	/**
	 * Linked withdrawal request ID after verification.
	 *
	 * @var int
	 */
	public $withdrawal_id = 0;

	/**
	 * Created timestamp (MySQL datetime).
	 *
	 * @var string
	 */
	public $created_at = '';

	/**
	 * Expiry timestamp (MySQL datetime).
	 *
	 * @var string
	 */
	public $expires_at = '';

	/**
	 * Verified timestamp (MySQL datetime).
	 *
	 * @var string
	 */
	public $verified_at = '';

	/**
	 * Hydrate from a database row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_row( $row ) {
		$request = new self();

		$request->id              = isset( $row->id ) ? absint( $row->id ) : 0;
		$request->order_number    = isset( $row->order_number ) ? (string) $row->order_number : '';
		$request->customer_email  = isset( $row->customer_email ) ? (string) $row->customer_email : '';
		$request->reason          = isset( $row->reason ) ? (string) $row->reason : '';
		$request->verify_token    = isset( $row->verify_token ) ? (string) $row->verify_token : '';
		$request->status          = isset( $row->status ) ? (string) $row->status : 'pending_verification';
		$request->failure_reason  = isset( $row->failure_reason ) ? (string) $row->failure_reason : '';
		$request->withdrawal_id   = isset( $row->withdrawal_id ) ? absint( $row->withdrawal_id ) : 0;
		$request->created_at      = isset( $row->created_at ) ? (string) $row->created_at : '';
		$request->expires_at      = isset( $row->expires_at ) ? (string) $row->expires_at : '';
		$request->verified_at     = isset( $row->verified_at ) ? (string) $row->verified_at : '';

		return $request;
	}
}
