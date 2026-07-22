<?php
/**
 * Plugin logger.
 *
 * Thin wrapper around the WooCommerce logger with a fixed source
 * identifier and action hooks for each log level.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Logger
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Logger {

	/**
	 * Logger source identifier.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE = 'wt-eu-withdrawal-button';

	/**
	 * WooCommerce logger instance.
	 *
	 * @since 1.0.0
	 * @var \WC_Logger_Interface|null
	 */
	private $logger = null;

	/**
	 * Return the WooCommerce logger instance.
	 *
	 * @since 1.0.0
	 *
	 * @return \WC_Logger_Interface
	 */
	private function get_logger() {
		if ( null === $this->logger ) {
			$this->logger = wc_get_logger();
		}

		return $this->logger;
	}

	/**
	 * Log an informational message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	public function info( $message ) {
		$this->get_logger()->info( $message, array( 'source' => self::SOURCE ) );

		/**
		 * Fires after an info-level log message is recorded.
		 *
		 * @since 1.0.0
		 *
		 * @param string $message The log message.
		 */
		do_action( 'wbte_ewb_log_info', $message );
	}

	/**
	 * Log a warning message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	public function warning( $message ) {
		$this->get_logger()->warning( $message, array( 'source' => self::SOURCE ) );

		/**
		 * Fires after a warning-level log message is recorded.
		 *
		 * @since 1.0.0
		 *
		 * @param string $message The log message.
		 */
		do_action( 'wbte_ewb_log_warning', $message );
	}

	/**
	 * Log an error message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	public function error( $message ) {
		$this->get_logger()->error( $message, array( 'source' => self::SOURCE ) );

		/**
		 * Fires after an error-level log message is recorded.
		 *
		 * @since 1.0.0
		 *
		 * @param string $message The log message.
		 */
		do_action( 'wbte_ewb_log_error', $message );
	}

	/**
	 * Log a debug message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	public function debug( $message ) {
		$this->get_logger()->debug( $message, array( 'source' => self::SOURCE ) );

		/**
		 * Fires after a debug-level log message is recorded.
		 *
		 * @since 1.0.0
		 *
		 * @param string $message The log message.
		 */
		do_action( 'wbte_ewb_log_debug', $message );
	}
}
