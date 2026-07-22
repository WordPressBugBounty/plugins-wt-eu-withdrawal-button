<?php
/**
 * Withdrawal request data model.
 *
 * Represents a single withdrawal request row from the
 * `wbte_ewb_withdrawals` database table.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Request
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Request {

	/**
	 * Request ID.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $id = 0;

	/**
	 * WooCommerce order ID.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $order_id = 0;

	/**
	 * Display order number (may differ from order_id with sequential plugins).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $order_number = '';

	/**
	 * Customer email address.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $customer_email = '';

	/**
	 * Customer WordPress user ID (0 for guests).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $customer_user_id = 0;

	/**
	 * Request status: pending, approved, rejected.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $status = 'pending';

	/**
	 * Request type: full or partial.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $request_type = 'full';

	/**
	 * JSON-encoded line items for partial withdrawal.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $items_json = '[]';

	/**
	 * Customer-provided reason for withdrawal.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $reason = '';

	/**
	 * JSON-encoded additional metadata.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $meta_json = '{}';

	/**
	 * Creation timestamp.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $created_at = '';

	/**
	 * Last-updated timestamp.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $updated_at = '';

	/**
	 * User ID or identifier who created the request.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $created_by = 0;

	/**
	 * Admin user ID who processed the request.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $processed_by = 0;

	/**
	 * Timestamp when the request was processed.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $processed_at = '';

	/**
	 * Decode and return items_json as an array.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_items() {
		$items = json_decode( $this->items_json, true );

		return is_array( $items ) ? $items : array();
	}

	/**
	 * Decode and return meta_json as an array.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_meta() {
		$meta = json_decode( $this->meta_json, true );

		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Whether this request withdraws only part of the order.
	 *
	 * @since 1.0.4
	 *
	 * @return bool
	 */
	public function is_partial_withdrawal() {
		if ( 'partial' === $this->request_type ) {
			return true;
		}

		$order = $this->get_order();

		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		$stored_items = $this->get_items();

		if ( empty( $stored_items ) ) {
			return false;
		}

		$eligibility    = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'eligibility' ) : new Wbte_Ewb_Eligibility();
		$eligible_items = $eligibility instanceof Wbte_Ewb_Eligibility ? $eligibility->get_eligible_items( $order ) : array();
		$requested_ids  = array();

		foreach ( $stored_items as $item ) {
			$line_item_id = isset( $item['line_item_id'] ) ? absint( $item['line_item_id'] ) : 0;
			$qty          = isset( $item['qty'] ) ? absint( $item['qty'] ) : ( isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1 );

			if ( ! $line_item_id ) {
				continue;
			}

			$requested_ids[] = $line_item_id;
			$line_item       = $order->get_item( $line_item_id );

			if ( ! $line_item instanceof \WC_Order_Item_Product ) {
				return true;
			}

			if ( $qty < (int) $line_item->get_quantity() ) {
				return true;
			}
		}

		$requested_ids = array_values( array_unique( $requested_ids ) );

		return count( $requested_ids ) < count( $eligible_items );
	}

	/**
	 * Get a WooCommerce order line item total including tax when present.
	 *
	 * @since 1.0.3
	 *
	 * @param \WC_Order_Item_Product $line_item Order line item.
	 * @return string Formatted decimal amount.
	 */
	public static function get_line_item_total_incl_tax( $line_item ) {
		if ( ! $line_item instanceof \WC_Order_Item_Product ) {
			return '';
		}

		$total = (float) $line_item->get_total() + (float) $line_item->get_total_tax();

		return wc_format_decimal( $total, '' );
	}

	/**
	 * Build a line-item snapshot from a WooCommerce order.
	 *
	 * @since 1.0.1
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return array<int, array<string, mixed>>
	 */
	public static function snapshot_order_items( $order, $request = null ) {
		if ( ! $order instanceof \WC_Order ) {
			return array();
		}

		$items = array();

		foreach ( $order->get_items() as $line_item_id => $line_item ) {
			if ( ! $line_item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$total = self::get_line_item_total_incl_tax( $line_item );

			/**
			 * Filters the line total for order items shown in the admin dashboard.
			 *
			 * Also used in withdrawal request REST responses and email templates.
			 *
			 * @since 1.0.3
			 *
			 * @param float|string           $total     Line total (incl. tax by default when present).
			 * @param \WC_Order_Item_Product $line_item Order line item.
			 * @param \WC_Order              $order     Order object.
			 * @param Wbte_Ewb_Request|null  $request   Withdrawal request when available.
			 */
			$total = apply_filters( 'wbte_ewb_dashboard_item_line_total', $total, $line_item, $order, $request );

			$items[] = array(
				'line_item_id'    => (int) $line_item_id,
				'product_id'      => (int) $line_item->get_product_id(),
				'name'            => $line_item->get_name(),
				'qty'             => (int) $line_item->get_quantity(),
				'quantity'        => (int) $line_item->get_quantity(),
				'total'           => $total,
				'line_total'      => $total,
				'total_formatted' => self::format_price_plain( $total, $order->get_currency() ),
			);
		}

		return $items;
	}

	/**
	 * Format a price for plain-text display (admin UI, REST).
	 *
	 * @since 1.0.1
	 *
	 * @param float|int|string $amount   Price amount.
	 * @param string           $currency Optional currency code.
	 * @return string
	 */
	private static function format_price_plain( $amount, $currency = '' ) {
		$args = array();

		if ( $currency ) {
			$args['currency'] = $currency;
		}

		$formatted = wc_price( $amount, $args );
		$plain     = wp_strip_all_tags( $formatted );

		return html_entity_decode( $plain, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Return items for display in admin, emails, and REST responses.
	 *
	 * For partial withdrawals, returns every order line item and marks which
	 * lines are included in the request. For full withdrawals, all order
	 * items are returned and marked as included.
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_display_items() {
		$order = $this->get_order();

		if ( ! $order ) {
			$stored = $this->get_items();

			if ( empty( $stored ) ) {
				return array();
			}

			return $this->mark_items_in_withdrawal( $this->enrich_items_from_order( $stored ), true );
		}

		$request_lines = $this->map_request_items_by_line_id( $this->enrich_items_from_order( $this->get_items() ) );
		$order_items   = self::snapshot_order_items( $order, $this );
		$stored_items  = $this->get_items();

		if ( empty( $stored_items ) && 'partial' !== $this->request_type ) {
			return $this->mark_items_in_withdrawal( $order_items, true );
		}

		if ( empty( $stored_items ) ) {
			return $order_items;
		}

		foreach ( $order_items as &$item ) {
			$line_id               = isset( $item['line_item_id'] ) ? absint( $item['line_item_id'] ) : 0;
			$in_request            = $line_id && isset( $request_lines[ $line_id ] );
			$item['in_withdrawal'] = $in_request;

			if ( $in_request ) {
				$requested_qty = $request_lines[ $line_id ]['qty'];

				if ( $requested_qty > 0 && $requested_qty !== (int) $item['quantity'] ) {
					$item['withdrawal_qty']  = $requested_qty;
					$item['quantity_label']  = sprintf(
						/* translators: 1: requested quantity, 2: order line quantity */
						__( '%1$d of %2$d', 'wt-eu-withdrawal-button' ),
						$requested_qty,
						$item['quantity']
					);
				}
			}
		}
		unset( $item );

		return $order_items;
	}

	/**
	 * Map stored request items by WooCommerce line item ID.
	 *
	 * @since 1.0.1
	 *
	 * @param array<int, array<string, mixed>> $items Stored request items.
	 * @return array<int, array{qty:int}>
	 */
	private function map_request_items_by_line_id( $items ) {
		$map = array();

		foreach ( $items as $item ) {
			$line_id = isset( $item['line_item_id'] ) ? absint( $item['line_item_id'] ) : 0;

			if ( ! $line_id ) {
				continue;
			}

			$map[ $line_id ] = array(
				'qty' => isset( $item['qty'] ) ? absint( $item['qty'] ) : ( isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1 ),
			);
		}

		return $map;
	}

	/**
	 * Mark each item as included or excluded from the withdrawal request.
	 *
	 * @since 1.0.1
	 *
	 * @param array<int, array<string, mixed>> $items  Item rows.
	 * @param bool                             $included Whether items are in the request.
	 * @return array<int, array<string, mixed>>
	 */
	private function mark_items_in_withdrawal( $items, $included ) {
		foreach ( $items as &$item ) {
			$item['in_withdrawal'] = $included;
		}
		unset( $item );

		return $items;
	}

	/**
	 * Enrich stored request items with order line totals and names.
	 *
	 * @since 1.0.1
	 *
	 * @param array<int, array<string, mixed>> $items Stored request items.
	 * @return array<int, array<string, mixed>>
	 */
	private function enrich_items_from_order( $items ) {
		$order = $this->get_order();

		if ( ! $order ) {
			return $items;
		}

		$enriched = array();

		foreach ( $items as $item ) {
			$line_item_id = isset( $item['line_item_id'] ) ? absint( $item['line_item_id'] ) : 0;
			$name         = isset( $item['name'] ) ? $item['name'] : '';
			$qty          = isset( $item['qty'] ) ? absint( $item['qty'] ) : ( isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1 );
			$total        = isset( $item['total'] ) ? $item['total'] : ( isset( $item['line_total'] ) ? $item['line_total'] : '' );

			$line_item = null;

			if ( $line_item_id ) {
				$line_item = $order->get_item( $line_item_id );

				if ( $line_item instanceof \WC_Order_Item_Product ) {
					if ( '' === $name ) {
						$name = $line_item->get_name();
					}

					if ( '' === $total ) {
						$total = self::get_line_item_total_incl_tax( $line_item );
					}

					if ( empty( $qty ) ) {
						$qty = (int) $line_item->get_quantity();
					}
				} else {
					$line_item = null;
				}
			}

			if ( '' !== $total && $line_item instanceof \WC_Order_Item_Product ) {
				/**
				 * Filters the line total when building stored withdrawal request item rows.
				 *
				 * @since 1.0.3
				 *
				 * @param float|string           $total     Line total (incl. tax by default when present).
				 * @param \WC_Order_Item_Product $line_item Order line item.
				 * @param \WC_Order              $order     Order object.
				 * @param Wbte_Ewb_Request       $request   Withdrawal request.
				 */
				$total = apply_filters( 'wbte_ewb_request_item_line_total', $total, $line_item, $order, $this );
			}

			if ( '' !== $total && empty( $item['total_formatted'] ) ) {
				$item['total_formatted'] = self::format_price_plain( $total, $order->get_currency() );
			}

			$enriched[] = array_merge(
				$item,
				array(
					'name'          => $name,
					'qty'           => $qty,
					'quantity'      => $qty,
					'total'         => $total,
					'line_total'    => $total,
					'in_withdrawal' => true,
				)
			);
		}

		return $enriched;
	}

	/**
	 * Retrieve the associated WooCommerce order.
	 *
	 * @since 1.0.0
	 *
	 * @return \WC_Order|false
	 */
	public function get_order() {
		return wc_get_order( $this->order_id );
	}

	/**
	 * Get the localized request type label.
	 *
	 * @since 1.0.3
	 *
	 * @return string
	 */
	public function get_request_type_label() {
		if ( $this->is_partial_withdrawal() ) {
			return __( 'Partial', 'wt-eu-withdrawal-button' );
		}

		return __( 'Full', 'wt-eu-withdrawal-button' );
	}

	/**
	 * Format a stored UTC datetime for display in the site timezone (date + time).
	 *
	 * Required for EU withdrawal acknowledgement compliance (Directive 2023/2673).
	 * Output format: DD/MM/YYYY at HH:MM AM/PM (UTC+offset), e.g. 25/07/2026 at 02:30 AM (UTC+10).
	 *
	 * @since 1.0.4
	 *
	 * @param string $datetime MySQL datetime string stored in UTC.
	 * @return string Formatted datetime, or empty string when unavailable.
	 */
	public static function format_datetime( $datetime ) {
		$date = self::parse_utc_datetime( $datetime );

		if ( ! $date ) {
			return '';
		}

		$formatted = sprintf(
			/* translators: 1: date (DD/MM/YYYY), 2: time (hh:mm AM/PM), 3: timezone offset label e.g. UTC+10 */
			__( '%1$s at %2$s (%3$s)', 'wt-eu-withdrawal-button' ),
			$date->format( 'd/m/Y' ),
			$date->format( 'h:i A' ),
			self::format_timezone_offset( $date )
		);

		/**
		 * Filters a UTC withdrawal datetime formatted for display.
		 *
		 * @since 1.0.7
		 *
		 * @param string             $formatted Formatted datetime string.
		 * @param string             $datetime  Original UTC MySQL datetime.
		 * @param DateTimeInterface  $date      Parsed datetime in the site timezone.
		 */
		return apply_filters( 'wbte_ewb_formatted_datetime', $formatted, $datetime, $date );
	}

	/**
	 * Format a stored UTC datetime as a site-local date only (DD/MM/YYYY).
	 *
	 * @since 1.0.7
	 *
	 * @param string $datetime MySQL datetime string stored in UTC.
	 * @return string Formatted date, or empty string when unavailable.
	 */
	public static function format_datetime_date( $datetime ) {
		$date = self::parse_utc_datetime( $datetime );

		if ( ! $date ) {
			return '';
		}

		$formatted = $date->format( 'd/m/Y' );

		/**
		 * Filters a UTC withdrawal date formatted for display.
		 *
		 * @since 1.0.7
		 *
		 * @param string            $formatted Formatted date string.
		 * @param string            $datetime  Original UTC MySQL datetime.
		 * @param DateTimeInterface $date      Parsed datetime in the site timezone.
		 */
		return apply_filters( 'wbte_ewb_formatted_date', $formatted, $datetime, $date );
	}

	/**
	 * Parse a UTC MySQL datetime into the WordPress site timezone.
	 *
	 * @since 1.0.7
	 *
	 * @param string $datetime MySQL datetime string stored in UTC.
	 * @return DateTimeInterface|null
	 */
	private static function parse_utc_datetime( $datetime ) {
		if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
			return null;
		}

		try {
			$utc     = new DateTimeZone( 'UTC' );
			$site_tz = wp_timezone();

			if ( class_exists( 'WC_DateTime' ) ) {
				$date = new WC_DateTime( $datetime, $utc );
			} else {
				$date = new DateTime( $datetime, $utc );
			}

			$date->setTimezone( $site_tz );

			return $date;
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return null;
		}
	}

	/**
	 * Format a datetime offset as an explicit UTC label, e.g. UTC+10 or UTC+5:30.
	 *
	 * @since 1.0.7
	 *
	 * @param DateTimeInterface $datetime Datetime in the target timezone.
	 * @return string
	 */
	private static function format_timezone_offset( $datetime ) {
		if ( ! $datetime instanceof DateTimeInterface ) {
			return 'UTC+0';
		}

		$offset_seconds = $datetime->getOffset();
		$sign           = $offset_seconds >= 0 ? '+' : '-';
		$offset_seconds = abs( $offset_seconds );
		$hours          = (int) floor( $offset_seconds / HOUR_IN_SECONDS );
		$minutes        = (int) ( ( $offset_seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

		if ( $minutes > 0 ) {
			return sprintf( 'UTC%s%d:%02d', $sign, $hours, $minutes );
		}

		return sprintf( 'UTC%s%d', $sign, $hours );
	}

	/**
	 * Get the formatted submission timestamp (date + time).
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public function get_created_at_formatted() {
		return self::format_datetime( $this->created_at );
	}

	/**
	 * Get the formatted approval/rejection timestamp (date + time).
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public function get_processed_at_formatted() {
		return self::format_datetime( $this->processed_at );
	}

	/**
	 * Get the customer's formatted full name from the linked order.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public function get_formatted_full_name() {
		$order = $this->get_order();

		if ( ! $order ) {
			return '';
		}

		$name = $order->get_formatted_billing_full_name();

		return is_string( $name ) ? $name : '';
	}

	/**
	 * Collect WooCommerce line item IDs included in this withdrawal request.
	 *
	 * @since 1.0.4
	 *
	 * @return string[]
	 */
	private function get_verification_item_ids() {
		$item_ids = array();

		foreach ( $this->get_items() as $item ) {
			$line_id = 0;

			if ( isset( $item['line_item_id'] ) ) {
				$line_id = absint( $item['line_item_id'] );
			} elseif ( isset( $item['id'] ) ) {
				$line_id = absint( $item['id'] );
			}

			if ( $line_id ) {
				$item_ids[] = (string) $line_id;
			}
		}

		return $item_ids;
	}

	/**
	 * Get the UTC submission timestamp used for the receipt hash.
	 *
	 * @since 1.0.4
	 *
	 * @return int
	 */
	private function get_created_at_timestamp() {
		if ( empty( $this->created_at ) || '0000-00-00 00:00:00' === $this->created_at ) {
			return 0;
		}

		$timestamp = strtotime( $this->created_at . ' UTC' );

		return false === $timestamp ? 0 : (int) $timestamp;
	}

	/**
	 * Compute the SHA-256 receipt hash for the current request data.
	 *
	 * Mirrors the durable-medium integrity hash used by EU withdrawal plugins:
	 * request ID, customer name, email, reason, order number, submission time,
	 * and included line item IDs.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public function get_current_verification_code() {
		if ( empty( $this->id ) ) {
			return '';
		}

		$to_hash = implode(
			'|',
			array(
				(string) $this->id,
				$this->get_formatted_full_name(),
				$this->customer_email,
				$this->reason,
				$this->order_number,
				(string) $this->get_created_at_timestamp(),
				implode( '|', $this->get_verification_item_ids() ),
			)
		);

		return hash( 'sha256', $to_hash );
	}

	/**
	 * Get the stored receipt hash from request meta.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public function get_stored_verification_code() {
		$meta = $this->get_meta();

		return isset( $meta['verification_code'] ) ? (string) $meta['verification_code'] : '';
	}

	/**
	 * Persist a receipt hash in request meta.
	 *
	 * @since 1.0.4
	 *
	 * @param string $code Receipt hash.
	 * @return void
	 */
	public function set_verification_code( $code ) {
		$meta                        = $this->get_meta();
		$meta['verification_code']   = (string) $code;
		$this->meta_json             = wp_json_encode( $meta );
	}

	/**
	 * Get the receipt hash, falling back to a live hash when not stored.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public function get_verification_code() {
		$stored = $this->get_stored_verification_code();

		if ( '' !== $stored ) {
			return $stored;
		}

		return $this->get_current_verification_code();
	}

	/**
	 * Compute and store the receipt hash for this request.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public function sync_verification_code() {
		if ( empty( $this->id ) ) {
			return '';
		}

		$code = $this->get_current_verification_code();
		$this->set_verification_code( $code );

		return $code;
	}

	/**
	 * Check whether this request is pending.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_pending() {
		return 'pending' === $this->status;
	}

	/**
	 * Check whether this request is approved.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_approved() {
		return 'approved' === $this->status;
	}

	/**
	 * Check whether this request is rejected.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_rejected() {
		return 'rejected' === $this->status;
	}

	/**
	 * Convert all properties to an associative array.
	 *
	 * Suitable for REST API responses.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array() {
		$order_edit_url = '';
		$order_status   = '';
		if ( $this->order_id && function_exists( 'wc_get_order' ) ) {
			$wc_order = wc_get_order( $this->order_id );
			if ( $wc_order ) {
				$order_edit_url = $wc_order->get_edit_order_url();
				$order_status   = wc_get_order_status_name( $wc_order->get_status() );
			}
		}

		$data = array(
			'id'               => (int) $this->id,
			'order_id'         => (int) $this->order_id,
			'order_number'     => $this->order_number,
			'order_edit_url'   => $order_edit_url,
			'order_status'     => $order_status,
			'customer_email'   => $this->customer_email,
			'customer_user_id' => (int) $this->customer_user_id,
			'status'           => $this->status,
			'request_type'     => $this->request_type,
			'is_partial'       => $this->is_partial_withdrawal(),
			'items'            => $this->get_display_items(),
			'reason'             => $this->reason,
			'meta'               => $this->get_meta(),
			'verification_code'  => $this->get_verification_code(),
			'created_at'         => $this->created_at,
			'created_at_formatted' => $this->get_created_at_formatted(),
			'created_at_date_formatted' => self::format_datetime_date( $this->created_at ),
			'updated_at'       => $this->updated_at,
			'created_by'       => (int) $this->created_by,
			'processed_by'     => (int) $this->processed_by,
			'processed_at'     => $this->processed_at,
			'processed_at_formatted' => $this->get_processed_at_formatted(),
		);

		/**
		 * Filters the request data array before returning.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $data    The request data.
		 * @param Wbte_Ewb_Request      $request The request object.
		 */
		return apply_filters( 'wbte_ewb_request_data', $data, $this );
	}

	/**
	 * Factory method to create a Wbte_Ewb_Request from a database row.
	 *
	 * @since 1.0.0
	 *
	 * @param object $row Database row object.
	 * @return Wbte_Ewb_Request
	 */
	public static function from_row( $row ) {
		$request = new self();

		$request->id               = isset( $row->id ) ? (int) $row->id : 0;
		$request->order_id         = isset( $row->order_id ) ? (int) $row->order_id : 0;
		$request->order_number     = isset( $row->order_number ) ? $row->order_number : '';
		$request->customer_email   = isset( $row->customer_email ) ? $row->customer_email : '';
		$request->customer_user_id = isset( $row->customer_user_id ) ? (int) $row->customer_user_id : 0;
		$request->status           = isset( $row->status ) ? $row->status : 'pending';
		$request->request_type     = isset( $row->request_type ) ? $row->request_type : 'full';
		$request->items_json       = isset( $row->items_json ) ? $row->items_json : '[]';
		$request->reason           = isset( $row->reason ) ? $row->reason : '';
		$request->meta_json        = isset( $row->meta_json ) ? $row->meta_json : '{}';
		$request->created_at       = isset( $row->created_at ) ? $row->created_at : '';
		$request->updated_at       = isset( $row->updated_at ) ? $row->updated_at : '';
		$request->created_by       = isset( $row->created_by ) ? (int) $row->created_by : 0;
		$request->processed_by     = isset( $row->processed_by ) ? (int) $row->processed_by : 0;
		$request->processed_at     = isset( $row->processed_at ) ? $row->processed_at : '';

		return $request;
	}
}
