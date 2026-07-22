<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Only removes data when the user has opted in via the
 * `wbte_ewb_delete_data_on_uninstall` setting.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wbte_ewb_settings = get_option( 'wbte_ewb_settings', array() );

if ( empty( $wbte_ewb_settings['delete_data_on_uninstall'] ) || 'yes' !== $wbte_ewb_settings['delete_data_on_uninstall'] ) {
	return;
}

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbte_ewb_withdrawal_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbte_ewb_pending_withdrawals" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wbte_ewb_withdrawals" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Trash the withdrawal form page if it exists.
$wbte_ewb_page_id = get_option( 'wbte_ewb_withdrawal_page_id' );
if ( $wbte_ewb_page_id ) {
	wp_trash_post( absint( $wbte_ewb_page_id ) );
}

// Delete options.
delete_option( 'wbte_ewb_settings' );
delete_option( 'wbte_ewb_db_version' );
delete_option( 'wbte_ewb_plugin_version' );
delete_option( 'wbte_ewb_withdrawal_page_id' );

// Remove custom capabilities.
$wbte_ewb_roles = array( 'administrator', 'shop_manager' );
foreach ( $wbte_ewb_roles as $wbte_ewb_role_name ) {
	$role = get_role( $wbte_ewb_role_name );
	if ( $role ) {
		$role->remove_cap( 'wbte_ewb_manage_withdrawals' );
	}
}
