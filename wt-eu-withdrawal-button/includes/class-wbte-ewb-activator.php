<?php
/**
 * Fired during plugin activation.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Activator
 *
 * Handles all logic that runs on plugin activation.
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Activator {

	/**
	 * Run activation routines.
	 *
	 * Creates database tables, grants capabilities, flushes
	 * rewrite rules, and stores the plugin version.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function activate() {
		// Ensure the install class is available.
		require_once WBTE_EWB_PLUGIN_DIR . 'includes/class-wbte-ewb-install.php';

		// Create / update database tables and default settings.
		Wbte_Ewb_Install::install();

		// Record install time for review banner eligibility (new installs only).
		require_once WBTE_EWB_PLUGIN_DIR . 'includes/core/class-wbte-ewb-review-banner.php';
		Wbte_Ewb_Review_Banner::set_install_time_on_activation();

		// Create the withdrawal form page (draft) if it doesn't exist yet.
		Wbte_Ewb_Install::maybe_create_page();

		// Grant custom capability to privileged roles.
		self::add_capabilities();

		// Flush rewrite rules so any custom endpoints take effect.
		flush_rewrite_rules();

		// Store the plugin version for future upgrade checks.
		update_option( 'wbte_ewb_plugin_version', WBTE_EWB_VERSION );
	}

	/**
	 * Add the plugin's custom capabilities to appropriate roles.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function add_capabilities() {
		$roles = array( 'administrator', 'shop_manager' );

		foreach ( $roles as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role instanceof WP_Role ) {
				$role->add_cap( 'wbte_ewb_manage_withdrawals' );
			}
		}
	}
}
