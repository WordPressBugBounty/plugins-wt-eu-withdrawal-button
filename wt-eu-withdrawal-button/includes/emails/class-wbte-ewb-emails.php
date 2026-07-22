<?php
/**
 * Email manager.
 *
 * Registers custom WooCommerce email classes and provides
 * template-location overrides so templates ship with the plugin
 * but can be overridden in the active theme.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Emails
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Emails {

	/**
	 * Template path relative to the theme root.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TEMPLATE_PATH = 'woocommerce/wbte-eu-withdrawal-button/';

	/**
	 * Hook into WooCommerce.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email_classes' ) );
		add_filter( 'woocommerce_locate_template', array( $this, 'locate_template' ), 10, 3 );

		require_once WBTE_EWB_PLUGIN_DIR . 'includes/emails/class-wbte-ewb-email-preview.php';
		require_once WBTE_EWB_PLUGIN_DIR . 'includes/emails/class-wbte-ewb-email-template.php';
		Wbte_Ewb_Email_Preview::init();
	}

	/**
	 * Register the plugin's email classes with WooCommerce.
	 *
	 * @since 1.0.0
	 *
	 * @param array $emails Existing WooCommerce email class instances.
	 * @return array
	 */
	public function register_email_classes( $emails ) {
		$dir = WBTE_EWB_PLUGIN_DIR . 'includes/emails/';

		require_once $dir . 'class-wbte-ewb-email-request-submitted-customer.php';
		require_once $dir . 'class-wbte-ewb-email-request-submitted-admin.php';
		require_once $dir . 'class-wbte-ewb-email-request-approved.php';
		require_once $dir . 'class-wbte-ewb-email-request-rejected.php';
		require_once $dir . 'class-wbte-ewb-email-guest-verification.php';

		$emails['Wbte_Ewb_Email_Request_Submitted_Customer'] = new Wbte_Ewb_Email_Request_Submitted_Customer();
		$emails['Wbte_Ewb_Email_Request_Submitted_Admin']    = new Wbte_Ewb_Email_Request_Submitted_Admin();
		$emails['Wbte_Ewb_Email_Request_Approved']           = new Wbte_Ewb_Email_Request_Approved();
		$emails['Wbte_Ewb_Email_Request_Rejected']           = new Wbte_Ewb_Email_Request_Rejected();
		$emails['Wbte_Ewb_Email_Guest_Verification']         = new Wbte_Ewb_Email_Guest_Verification();

		return $emails;
	}

	/**
	 * Allow WooCommerce to find email templates in the plugin's templates/ directory.
	 *
	 * If the template is not found in the theme, fall back to the plugin's
	 * bundled templates.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template      Full path to the located template.
	 * @param string $template_name Template name (relative).
	 * @param string $template_path Template path prefix.
	 * @return string
	 */
	public function locate_template( $template, $template_name, $template_path ) {
		if ( self::TEMPLATE_PATH !== $template_path ) {
			return $template;
		}

		$plugin_template = WBTE_EWB_PLUGIN_DIR . 'templates/' . $template_name;

		// If the theme does not override the template, use the plugin's version.
		if ( ! $template || ! file_exists( $template ) ) {
			if ( file_exists( $plugin_template ) ) {
				$template = $plugin_template;
			}
		}

		return $template;
	}
}
