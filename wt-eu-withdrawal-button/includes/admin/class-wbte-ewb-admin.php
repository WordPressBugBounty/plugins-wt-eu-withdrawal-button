<?php
/**
 * Main admin orchestrator.
 *
 * Loads all admin sub-modules and registers the plugin
 * settings link on the Plugins page.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Admin
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Admin {

	/**
	 * Admin menu instance.
	 *
	 * @since 1.0.0
	 * @var Wbte_Ewb_Admin_Menu
	 */
	private $menu;

	/**
	 * Admin assets instance.
	 *
	 * @since 1.0.0
	 * @var Wbte_Ewb_Admin_Assets
	 */
	private $assets;

	/**
	 * Admin settings (WC tab) instance.
	 *
	 * @since 1.0.0
	 * @var Wbte_Ewb_Admin_Settings
	 */
	private $settings;

	/**
	 * Order meta box instance.
	 *
	 * @since 1.0.0
	 * @var Wbte_Ewb_Order_Meta_Box
	 */
	private $order_meta_box;

	/**
	 * Uninstall feedback instance.
	 *
	 * @since 1.0.1
	 * @var Wbte_Ewb_Uninstall_Feedback
	 */
	private $uninstall_feedback;

	/**
	 * Constructor.
	 *
	 * Loads dependency files, instantiates sub-modules, and
	 * registers the plugin action link.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();

		Wbte_Ewb_Script_Translations::init();

		$this->menu               = new Wbte_Ewb_Admin_Menu();
		$this->assets             = new Wbte_Ewb_Admin_Assets();
		$this->order_meta_box     = new Wbte_Ewb_Order_Meta_Box();
		$this->uninstall_feedback = new Wbte_Ewb_Uninstall_Feedback();

		add_filter( 'plugin_action_links_' . WBTE_EWB_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Load admin dependency files.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$admin_dir = WBTE_EWB_PLUGIN_DIR . 'includes/admin/';

		require_once $admin_dir . 'class-wbte-ewb-admin-menu.php';
		require_once $admin_dir . 'class-wbte-ewb-script-translations.php';
		require_once $admin_dir . 'class-wbte-ewb-admin-assets.php';
		require_once $admin_dir . 'class-wbte-ewb-settings.php';
		require_once $admin_dir . 'class-wbte-ewb-order-meta-box.php';
		require_once $admin_dir . 'class-wbte-ewb-uninstall-feedback.php';
	}

	/**
	 * Add plugin row action links on the Plugins page.
	 *
	 * @since 1.0.0
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	public function add_action_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=wbte-ewb-withdrawals#/settings' );
		$support_url  = 'https://wordpress.org/support/plugin/wt-eu-withdrawal-button/';
		$review_url   = 'https://wordpress.org/support/plugin/wt-eu-withdrawal-button/reviews/#new-post';
		$custom_links = array(
			'settings' => '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'wt-eu-withdrawal-button' ) . '</a>',
			'support'  => '<a href="' . esc_url( $support_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support', 'wt-eu-withdrawal-button' ) . '</a>',
			'review'   => '<a href="' . esc_url( $review_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Review', 'wt-eu-withdrawal-button' ) . '</a>',
		);

		return array_merge( $custom_links, $links );
	}

	/**
	 * Get the admin menu instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Wbte_Ewb_Admin_Menu
	 */
	public function get_menu() {
		return $this->menu;
	}

	/**
	 * Get the admin assets instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Wbte_Ewb_Admin_Assets
	 */
	public function get_assets() {
		return $this->assets;
	}

	/**
	 * Get the admin settings instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Wbte_Ewb_Admin_Settings
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Get the order meta box instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Wbte_Ewb_Order_Meta_Box
	 */
	public function get_order_meta_box() {
		return $this->order_meta_box;
	}
}
