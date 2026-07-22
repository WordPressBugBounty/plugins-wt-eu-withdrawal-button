<?php
/**
 * Admin menu registration.
 *
 * Adds the "Withdrawals" submenu page under WooCommerce
 * that hosts the React admin dashboard.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Admin_Menu
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Admin_Menu {

	/**
	 * Admin page slug for the withdrawals dashboard.
	 *
	 * @var string
	 */
	const WT_EWB_PAGE_SLUG = 'wbte-ewb-withdrawals';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'in_admin_header', array( $this, 'hide_admin_notices' ) );
	}

	/**
	 * Register the WooCommerce submenu page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Withdrawal Requests', 'wt-eu-withdrawal-button' ),
			__( 'Withdrawals', 'wt-eu-withdrawal-button' ),
			'manage_woocommerce',
			self::WT_EWB_PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Return the admin URL for the withdrawals dashboard.
	 *
	 * @since 1.0.1
	 *
	 * @param int $request_id Optional withdrawal request ID for a direct detail link.
	 * @return string
	 */
	public static function get_dashboard_url( $request_id = 0 ) {
		$url = admin_url( 'admin.php?page=' . self::WT_EWB_PAGE_SLUG );

		if ( $request_id > 0 ) {
			$url .= '#/request/' . absint( $request_id );
		}

		return $url;
	}

	/**
	 * Render the React app mount point.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_page() {
		?>
		<div id="wbte-ewb-admin-root">
			<div class="wbte-ewb-loading-indicator" style="text-align:center;padding:40px 0;">
				<span class="spinner is-active" style="float:none;"></span>
				<p><?php echo esc_html__( 'Loading withdrawal dashboard…', 'wt-eu-withdrawal-button' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Hide all admin notices on our plugin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function hide_admin_notices() {
		$screen = get_current_screen();

		if ( $screen && 'woocommerce_page_wbte-ewb-withdrawals' === $screen->id ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}
}
