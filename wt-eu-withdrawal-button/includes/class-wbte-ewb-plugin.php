<?php
/**
 * Main plugin bootstrap class.
 *
 * Singleton that wires every subsystem together and acts as a
 * lightweight service container for the rest of the plugin.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Plugin
 *
 * @since 1.0.0
 */
final class Wbte_Ewb_Plugin {

	/**
	 * The single instance of this class.
	 *
	 * @since 1.0.0
	 * @var Wbte_Ewb_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Simple service container.
	 *
	 * @since 1.0.0
	 * @var array<string, object>
	 */
	private $services = array();

	/**
	 * Return the singleton instance, creating it on first call.
	 *
	 * @since 1.0.0
	 *
	 * @return Wbte_Ewb_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->load_dependencies();
			self::$instance->define_hooks();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Prevent cloning.
	 *
	 * @since 1.0.0
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @since 1.0.0
	 *
	 * @throws \RuntimeException Always.
	 */
	public function __wakeup() {
		throw new \RuntimeException( 'Cannot unserialize a singleton.' );
	}

	/**
	 * Register a service in the container.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key      Service identifier.
	 * @param object $instance Service instance.
	 * @return void
	 */
	public function set( $key, $instance ) {
		$this->services[ $key ] = $instance;
	}

	/**
	 * Retrieve a service from the container.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Service identifier.
	 * @return object|null The service instance or null if not found.
	 */
	public function get( $key ) {
		return isset( $this->services[ $key ] ) ? $this->services[ $key ] : null;
	}

	/**
	 * Load all required dependency files.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$includes_dir = WBTE_EWB_PLUGIN_DIR . 'includes/';

		// Core infrastructure.
		require_once $includes_dir . 'class-wbte-ewb-i18n.php';
		require_once $includes_dir . 'class-wbte-ewb-install.php';
		require_once $includes_dir . 'class-wbte-ewb-post-types.php';
		require_once $includes_dir . 'class-wbte-ewb-settings.php';
		require_once $includes_dir . 'class-wbte-ewb-shortcodes.php';

		// Core domain classes.
		require_once $includes_dir . 'core/class-wbte-ewb-request.php';
		require_once $includes_dir . 'core/class-wbte-ewb-request-repository.php';
		require_once $includes_dir . 'core/class-wbte-ewb-request-service.php';
		require_once $includes_dir . 'core/class-wbte-ewb-order-cleanup.php';
		require_once $includes_dir . 'core/class-wbte-ewb-eligibility.php';
		require_once $includes_dir . 'core/class-wbte-ewb-withdrawal-period.php';
		require_once $includes_dir . 'core/class-wbte-ewb-customer-context.php';
		require_once $includes_dir . 'core/class-wbte-ewb-logger.php';
		require_once $includes_dir . 'core/class-wbte-ewb-review-banner.php';
		require_once $includes_dir . 'core/class-wbte-ewb-pending-request.php';
		require_once $includes_dir . 'core/class-wbte-ewb-pending-request-repository.php';
		require_once $includes_dir . 'core/class-wbte-ewb-guest-withdrawal-service.php';
		require_once $includes_dir . 'frontend/class-wbte-ewb-form-renderer.php';
		require_once $includes_dir . 'frontend/class-wbte-ewb-guest-verification.php';

		// Compat layer.
		require_once $includes_dir . 'compat/class-wbte-ewb-sequential-orders.php';
		require_once $includes_dir . 'compat/class-wbte-ewb-multilingual.php';
	}

	/**
	 * Register all hooks for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function define_hooks() {
		// Internationalisation — must be on 'init' or later (WP 6.7+).
		$i18n = new Wbte_Ewb_I18n();
		$this->set( 'i18n', $i18n );
		add_action( 'init', array( $i18n, 'load_textdomain' ) );

		// Multilingual plugin compatibility (WPML, Polylang, TranslatePress).
		$multilingual = new Wbte_Ewb_Multilingual();
		$this->set( 'multilingual', $multilingual );

		// Database version check (runs on every request so migrations apply).
		add_action( 'plugins_loaded', array( 'Wbte_Ewb_Install', 'check_version' ) );

		// Core services.
		$repository = new Wbte_Ewb_Request_Repository();
		$this->set( 'repository', $repository );

		$service = new Wbte_Ewb_Request_Service( $repository );
		$this->set( 'service', $service );

		$eligibility = new Wbte_Ewb_Eligibility();
		$this->set( 'eligibility', $eligibility );

		add_action( 'init', array( 'Wbte_Ewb_Withdrawal_Period', 'init' ) );

		Wbte_Ewb_Review_Banner::init();

		$logger = new Wbte_Ewb_Logger();
		$this->set( 'logger', $logger );

		$pending_repository = new Wbte_Ewb_Pending_Request_Repository();
		$this->set( 'pending_repository', $pending_repository );

		$guest_withdrawal = new Wbte_Ewb_Guest_Withdrawal_Service( $pending_repository, $repository );
		$this->set( 'guest_withdrawal', $guest_withdrawal );

		$order_cleanup = new Wbte_Ewb_Order_Cleanup( $repository, $pending_repository );
		$this->set( 'order_cleanup', $order_cleanup );

		// Custom order statuses.
		$post_types = new Wbte_Ewb_Post_Types();
		$this->set( 'post_types', $post_types );
		add_action( 'init', array( $post_types, 'register_post_statuses' ) );
		add_filter( 'wc_order_statuses', array( $post_types, 'add_order_statuses' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( $post_types, 'add_bulk_actions' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $post_types, 'add_bulk_actions' ) );

		// REST API controllers.
		add_action( 'rest_api_init', array( $this, 'register_rest_controllers' ) );
		add_filter( 'rest_pre_dispatch', array( $this, 'switch_rest_locale' ), 10, 3 );

		// Context-aware loading.
		if ( is_admin() ) {
			$this->load_admin();
		} else {
			$this->load_frontend();
		}

		$guest_verification = new Wbte_Ewb_Guest_Verification( $guest_withdrawal );
		$this->set( 'guest_verification', $guest_verification );

		// Emails.
		$this->load_emails();

		// Shortcodes.
		$this->load_shortcodes();
	}

	/**
	 * Register REST API controllers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_rest_controllers() {
		$api_dir = WBTE_EWB_PLUGIN_DIR . 'includes/api/';
		if ( is_dir( $api_dir ) ) {
			// Load the base controller first.
			if ( file_exists( $api_dir . 'class-wbte-ewb-rest-controller.php' ) ) {
				require_once $api_dir . 'class-wbte-ewb-rest-controller.php';
			}

			// Load all other REST controller files.
			foreach ( glob( $api_dir . 'class-wbte-ewb-rest-*.php' ) as $file ) {
				require_once $file;
			}

			// Instantiate and register each controller.
			$controllers = array(
				'rest_requests'      => 'Wbte_Ewb_REST_Requests',
				'rest_settings'      => 'Wbte_Ewb_REST_Settings',
				'rest_customer'      => 'Wbte_Ewb_REST_Customer',
				'rest_lookups'       => 'Wbte_Ewb_REST_Lookups',
				'rest_review_banner' => 'Wbte_Ewb_REST_Review_Banner',
			);

			foreach ( $controllers as $key => $class_name ) {
				if ( class_exists( $class_name ) ) {
					$controller = new $class_name();
					$controller->register_routes();
					$this->set( $key, $controller );
				}
			}

			/**
			 * Fires after REST controller files have been loaded and routes registered.
			 *
			 * @since 1.0.0
			 */
			do_action( 'wbte_ewb_rest_api_init' );
		}
	}

	/**
	 * Switch WordPress locale for REST API requests when a locale parameter is provided.
	 *
	 * Multilingual plugins (Polylang, WPML) may not automatically apply the
	 * frontend language to REST API calls. This ensures translated strings
	 * returned by __() match the language the visitor is browsing in.
	 *
	 * @since 1.0.9
	 *
	 * @param mixed           $result  Response to replace the requested version with.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 * @return mixed Unmodified $result (pass-through).
	 */
	public function switch_rest_locale( $result, $server, $request ) {
		$route = $request->get_route();

		// Only apply to this plugin's REST routes.
		if ( strpos( $route, '/wbte-ewb/' ) === false ) {
			return $result;
		}

		$locale = sanitize_text_field( $request->get_param( 'locale' ) );

		if ( $locale && $locale !== get_locale() ) {
			switch_to_locale( $locale );
			load_plugin_textdomain( 'wt-eu-withdrawal-button', false, dirname( WBTE_EWB_PLUGIN_BASENAME ) . '/languages/' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
		}

		return $result;
	}

	/**
	 * Load admin-specific classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_admin() {
		$admin_file = WBTE_EWB_PLUGIN_DIR . 'includes/admin/class-wbte-ewb-admin.php';
		if ( file_exists( $admin_file ) ) {
			require_once $admin_file;
			$admin = new Wbte_Ewb_Admin();
			$this->set( 'admin', $admin );
		}
	}

	/**
	 * Load front-end classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_frontend() {
		$frontend_file = WBTE_EWB_PLUGIN_DIR . 'includes/frontend/class-wbte-ewb-frontend.php';
		if ( file_exists( $frontend_file ) ) {
			require_once $frontend_file;
			$frontend = new Wbte_Ewb_Frontend();
			$this->set( 'frontend', $frontend );
		}
	}

	/**
	 * Load email classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_emails() {
		$emails_file = WBTE_EWB_PLUGIN_DIR . 'includes/emails/class-wbte-ewb-emails.php';
		if ( file_exists( $emails_file ) ) {
			require_once $emails_file;
			$emails = new Wbte_Ewb_Emails();
			$this->set( 'emails', $emails );
		}
	}

	/**
	 * Load shortcode classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_shortcodes() {
		$shortcodes_file = WBTE_EWB_PLUGIN_DIR . 'includes/class-wbte-ewb-shortcodes.php';
		if ( file_exists( $shortcodes_file ) ) {
			require_once $shortcodes_file;
			$shortcodes = new Wbte_Ewb_Shortcodes();
			$this->set( 'shortcodes', $shortcodes );
		}
	}

}
