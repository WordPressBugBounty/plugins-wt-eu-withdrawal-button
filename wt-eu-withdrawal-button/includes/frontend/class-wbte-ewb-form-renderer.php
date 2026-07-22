<?php
/**
 * Shared withdrawal form renderer.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Form_Renderer
 *
 * @since 1.0.3
 */
class Wbte_Ewb_Form_Renderer {

	/**
	 * Render the withdrawal form markup.
	 *
	 * @since 1.0.3
	 *
	 * @return void
	 */
	public static function render() {
		$template = WBTE_EWB_PLUGIN_DIR . 'templates/withdrawal-form.php';

		if ( file_exists( $template ) ) {
			include $template;
			return;
		}

		// Fallback if the wrapper template is missing.
		if ( is_user_logged_in() ) {
			include WBTE_EWB_PLUGIN_DIR . 'includes/frontend/views/form-logged-in.php';
		} else {
			include WBTE_EWB_PLUGIN_DIR . 'includes/frontend/views/form-guest.php';
		}
	}

	/**
	 * Render the withdrawal form and return buffered HTML.
	 *
	 * @since 1.0.3
	 *
	 * @return string
	 */
	public static function render_html() {
		ob_start();
		self::render();

		return (string) ob_get_clean();
	}
}
