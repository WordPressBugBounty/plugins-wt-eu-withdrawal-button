<?php
/**
 * Customer context helpers for front-end display rules.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Customer_Context
 *
 * @since 1.0.4
 */
class Wbte_Ewb_Customer_Context {

	/**
	 * Resolve the current visitor's ISO 3166-1 alpha-2 country code.
	 *
	 * Priority: order billing country, WooCommerce customer session,
	 * then IP geolocation.
	 *
	 * @since 1.0.4
	 *
	 * @param WC_Order|null $order Optional order context.
	 * @return string Two-letter country code, or empty string when unknown.
	 */
	public static function get_country_code( $order = null ) {
		$country = '';

		if ( $order instanceof WC_Order ) {
			$country = $order->get_billing_country();

			if ( ! $country ) {
				$country = $order->get_shipping_country();
			}
		}

		if ( ! $country && function_exists( 'WC' ) && WC()->customer ) {
			$country = WC()->customer->get_billing_country();

			if ( ! $country ) {
				$country = WC()->customer->get_shipping_country();
			}
		}

		if ( ! $country && class_exists( 'WC_Geolocation' ) ) {
			$location = WC_Geolocation::geolocate_ip();

			if ( ! empty( $location['country'] ) ) {
				$country = $location['country'];
			}
		}

		$country = strtoupper( sanitize_text_field( (string) $country ) );

		/**
		 * Filters the resolved customer country code used for front-end display rules.
		 *
		 * @since 1.0.4
		 *
		 * @param string        $country Two-letter country code.
		 * @param WC_Order|null $order   Order context when available.
		 */
		return apply_filters( 'wbte_ewb_customer_country_code', $country, $order );
	}
}
