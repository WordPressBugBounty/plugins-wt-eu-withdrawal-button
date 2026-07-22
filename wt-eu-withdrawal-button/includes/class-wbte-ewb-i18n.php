<?php
/**
 * Internationalisation handler.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_I18n
 *
 * Since WordPress 4.6+, translations for plugins hosted on WordPress.org
 * are loaded automatically for WordPress.org plugins. Bundled MO files still
 * require an explicit load_plugin_textdomain() call.
 *
 * @since 1.0.0
 */
class Wbte_Ewb_I18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * Loads bundled translation files from the plugin languages directory.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_textdomain() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Bundled MO files are not auto-loaded off WordPress.org.
		load_plugin_textdomain(
			'wt-eu-withdrawal-button',
			false,
			dirname( plugin_basename( WBTE_EWB_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
