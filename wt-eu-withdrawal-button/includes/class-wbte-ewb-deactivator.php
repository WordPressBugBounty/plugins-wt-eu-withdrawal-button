<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Deactivator
 *
 * Handles logic that runs on plugin deactivation.
 * Intentionally does NOT drop tables or delete options so
 * data is preserved if the plugin is re-activated later.
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Deactivator {

	/**
	 * Run deactivation routines.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Flush rewrite rules to remove any custom endpoints.
		flush_rewrite_rules();
	}
}
