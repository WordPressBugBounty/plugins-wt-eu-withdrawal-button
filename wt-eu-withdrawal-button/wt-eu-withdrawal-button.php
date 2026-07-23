<?php
/**
 * Plugin Name:       WebToffee EU Order Withdrawal Button for WooCommerce
 * Plugin URI:        https://www.wordpress.org/plugins/wt-eu-withdrawal-button
 * Description:       Manage withdrawal of contract / order cancellation requests for WooCommerce orders. EU-compliant, HPOS-compatible.
 * Version:           1.0.8
 * Author:            webtoffee
 * Author URI:        https://www.webtoffee.com/
 * License:           GPL-3.0+
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       wt-eu-withdrawal-button
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * Requires at least: 6.0
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   10.9.4
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @since 1.0.0
 */
define( 'WBTE_EWB_VERSION', '1.0.8' );

/**
 * Plugin file path.
 *
 * @since 1.0.0
 */
define( 'WBTE_EWB_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path.
 *
 * @since 1.0.0
 */
define( 'WBTE_EWB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 *
 * @since 1.0.0
 */
define( 'WBTE_EWB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 *
 * @since 1.0.0
 */
define( 'WBTE_EWB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Database schema version.
 *
 * @since 1.0.0
 */
define( 'WBTE_EWB_DB_VERSION', '1.1.0' );

/**
 * Declare HPOS compatibility.
 *
 * @since 1.0.0
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Check if WooCommerce is active before initialising.
 *
 * @since 1.0.0
 *
 * @return bool
 */
function wbte_ewb_is_woocommerce_active() {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter name.
	return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true )
		|| ( is_multisite() && array_key_exists( 'woocommerce/woocommerce.php', get_site_option( 'active_sitewide_plugins', array() ) ) );
}

/**
 * Display an admin notice if WooCommerce is not active.
 *
 * @since 1.0.0
 */
function wbte_ewb_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo esc_html__(
				'WebToffee EU Withdrawal Button for WooCommerce requires WooCommerce to be installed and active.',
				'wt-eu-withdrawal-button'
			);
			?>
		</p>
	</div>
	<?php
}

// Activation / deactivation hooks (must be registered before any conditional returns).
require_once WBTE_EWB_PLUGIN_DIR . 'includes/class-wbte-ewb-activator.php';
require_once WBTE_EWB_PLUGIN_DIR . 'includes/class-wbte-ewb-deactivator.php';

register_activation_hook( __FILE__, array( 'Wbte_Ewb_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Wbte_Ewb_Deactivator', 'deactivate' ) );

if ( ! wbte_ewb_is_woocommerce_active() ) {
	add_action( 'admin_notices', 'wbte_ewb_woocommerce_missing_notice' );
	return;
}

// Bootstrap the plugin.
require_once WBTE_EWB_PLUGIN_DIR . 'includes/class-wbte-ewb-plugin.php';

/**
 * Return the singleton plugin instance.
 *
 * @since 1.0.0
 *
 * @return Wbte_Ewb_Plugin
 */
function wbte_ewb() {
	return Wbte_Ewb_Plugin::instance();
}

// Ignition.
wbte_ewb();
