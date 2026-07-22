<?php
/**
 * WooCommerce Settings tab for withdrawal configuration.
 *
 * Adds a "Withdrawal" tab to WooCommerce > Settings and maps
 * fields to the plugin's core Wbte_Ewb_Settings accessor.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Admin_Settings
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Admin_Settings {

	/**
	 * Settings field ID prefix.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PREFIX = 'wbte_ewb_';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_withdrawal', array( $this, 'output_settings' ) );
		add_action( 'woocommerce_update_options_withdrawal', array( $this, 'save_settings' ) );
		add_filter( 'option_' . self::PREFIX . 'admin_notification_recipients', array( $this, 'filter_admin_notification_recipients_option' ) );
	}

	/**
	 * Display admin notification recipients from the unified settings store.
	 *
	 * @since 1.0.3
	 *
	 * @param mixed $value Stored WooCommerce option value.
	 * @return string
	 */
	public function filter_admin_notification_recipients_option( $value ) {
		if ( is_string( $value ) && '' !== trim( $value ) ) {
			return $value;
		}

		$recipients = Wbte_Ewb_Settings::get( 'admin_notification_recipients', array() );

		if ( is_array( $recipients ) && ! empty( $recipients ) ) {
			return implode( ', ', $recipients );
		}

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Add "Withdrawal" tab to WooCommerce settings tabs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['withdrawal'] = __( 'Withdrawal', 'wt-eu-withdrawal-button' );

		return $tabs;
	}

	/**
	 * Output the settings fields for the Withdrawal tab.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function output_settings() {
		woocommerce_admin_fields( $this->get_settings_fields() );
	}

	/**
	 * Save the settings when the Withdrawal tab is submitted.
	 *
	 * Saves via the WooCommerce settings API first, then syncs
	 * the values into the plugin's centralised settings store.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save_settings() {
		woocommerce_update_options( $this->get_settings_fields() );

		// Sync WC option values into the plugin's unified settings.
		// Derive keys and defaults from the central registry.
		$defaults = Wbte_Ewb_Settings::get_defaults();
		$mapped   = array();

		foreach ( $defaults as $key => $default ) {
			$mapped[ $key ] = get_option( self::PREFIX . $key, $default );
		}

		Wbte_Ewb_Settings::update( $mapped );
	}

	/**
	 * Define all settings fields using the WooCommerce Settings API format.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function get_settings_fields() {
		$settings = array(

			// --- General Section ---.
			array(
				'title' => __( 'Withdrawal Settings', 'wt-eu-withdrawal-button' ),
				'type'  => 'title',
				'desc'  => __( 'Configure how withdrawal / right-of-return requests work in your store.', 'wt-eu-withdrawal-button' ),
				'id'    => self::PREFIX . 'general_section',
			),

			array(
				'title'    => __( 'Withdrawal Page', 'wt-eu-withdrawal-button' ),
				'desc'     => __( 'Select the page where customers can submit withdrawal requests.', 'wt-eu-withdrawal-button' ),
				'id'       => self::PREFIX . 'withdrawal_page',
				'type'     => 'single_select_page',
				'default'  => '',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:300px;',
				'desc_tip' => true,
			),

			array(
				'title'   => __( 'Embed Footer Link', 'wt-eu-withdrawal-button' ),
				'desc'    => __( 'Automatically add a link to the withdrawal page in the site footer.', 'wt-eu-withdrawal-button' ),
				'id'      => self::PREFIX . 'embed_footer_link',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'title'    => __( 'Display Scope', 'wt-eu-withdrawal-button' ),
				'desc'     => __( 'Where the withdrawal form and links are displayed.', 'wt-eu-withdrawal-button' ),
				'id'       => self::PREFIX . 'display_scope',
				'type'     => 'select',
				'options'  => array(
					'global'             => __( 'Global (all pages)', 'wt-eu-withdrawal-button' ),
					'woocommerce_only'   => __( 'WooCommerce pages only', 'wt-eu-withdrawal-button' ),
				),
				'default'  => 'global',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:300px;',
				'desc_tip' => true,
			),

			array(
				'title'   => __( 'Allow Partial Withdrawals', 'wt-eu-withdrawal-button' ),
				'desc'    => __( 'Let customers withdraw individual items instead of the entire order.', 'wt-eu-withdrawal-button' ),
				'id'      => self::PREFIX . 'allow_partial_withdrawals',
				'type'    => 'checkbox',
				'default' => 'yes',
			),

			array(
				'title'             => __( 'Withdrawal Period (days)', 'wt-eu-withdrawal-button' ),
				'desc'              => __( 'Number of days after the selected start status during which a withdrawal request can be submitted.', 'wt-eu-withdrawal-button' ),
				'id'                => self::PREFIX . 'withdrawal_period',
				'type'              => 'number',
				'default'           => '14',
				'css'               => 'width:80px;',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
				'desc_tip'          => true,
			),

			array(
				'title'    => __( 'Withdrawal Period Starts From', 'wt-eu-withdrawal-button' ),
				'desc'     => __( 'Select one or more order statuses. The countdown begins on the earliest date the order reaches any selected status.', 'wt-eu-withdrawal-button' ),
				'id'       => self::PREFIX . 'withdrawal_period_start_statuses',
				'type'     => 'multiselect',
				'options'  => self::get_withdrawal_period_start_status_options(),
				'default'  => 'order_created',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:300px;',
				'desc_tip' => true,
			),

			array(
				'title'   => __( 'Reason Required', 'wt-eu-withdrawal-button' ),
				'desc'    => __( 'Require customers to provide a reason when submitting a withdrawal request.', 'wt-eu-withdrawal-button' ),
				'id'      => self::PREFIX . 'reason_required',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'title'    => __( 'Excluded Product Types', 'wt-eu-withdrawal-button' ),
				'desc'     => __( 'Product types that are excluded from withdrawal eligibility.', 'wt-eu-withdrawal-button' ),
				'id'       => self::PREFIX . 'excluded_product_types',
				'type'     => 'multiselect',
				'options'  => array(
					'virtual'      => __( 'Virtual', 'wt-eu-withdrawal-button' ),
					'downloadable' => __( 'Downloadable', 'wt-eu-withdrawal-button' ),
					'grouped'      => __( 'Grouped', 'wt-eu-withdrawal-button' ),
					'external'     => __( 'External / Affiliate', 'wt-eu-withdrawal-button' ),
				),
				'default'  => '',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:300px;',
				'desc_tip' => true,
			),

			array(
				'title'    => __( 'Excluded Categories', 'wt-eu-withdrawal-button' ),
				'desc'     => __( 'Product categories that are excluded from withdrawal eligibility.', 'wt-eu-withdrawal-button' ),
				'id'       => self::PREFIX . 'excluded_categories',
				'type'     => 'multiselect',
				'options'  => $this->get_product_categories(),
				'default'  => '',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:300px;',
				'desc_tip' => true,
			),

			array(
				'title'    => __( 'Excluded Products', 'wt-eu-withdrawal-button' ),
				'desc'     => __( 'Specific products that are excluded from withdrawal eligibility.', 'wt-eu-withdrawal-button' ),
				'id'       => self::PREFIX . 'excluded_products',
				'type'     => 'multiselect',
				'class'    => 'wc-product-search',
				'options'  => $this->get_selected_product_options(),
				'default'  => '',
				'desc_tip' => true,
			),

			array(
				'title'    => __( 'Excluded Behavior', 'wt-eu-withdrawal-button' ),
				'desc'     => __( 'How excluded products are handled in the withdrawal form.', 'wt-eu-withdrawal-button' ),
				'id'       => self::PREFIX . 'excluded_behavior',
				'type'     => 'select',
				'options'  => array(
					'hide'                       => __( 'Hide excluded items', 'wt-eu-withdrawal-button' ),
					'show_disabled_with_notice'  => __( 'Show disabled with notice', 'wt-eu-withdrawal-button' ),
				),
				'default'  => 'hide',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:300px;',
				'desc_tip' => true,
			),

			array(
				'type' => 'sectionend',
				'id'   => self::PREFIX . 'general_section',
			),

			// --- Order Status Section ---.
			array(
				'title' => __( 'Order Status', 'wt-eu-withdrawal-button' ),
				'type'  => 'title',
				'desc'  => __( 'Control whether order statuses are changed automatically during the withdrawal workflow.', 'wt-eu-withdrawal-button' ),
				'id'    => self::PREFIX . 'order_status_section',
			),

			array(
				'title'   => __( 'Change order status on submission', 'wt-eu-withdrawal-button' ),
				'desc'    => __( 'Automatically change the order status when a withdrawal request is submitted.', 'wt-eu-withdrawal-button' ),
				'id'      => self::PREFIX . 'change_status_on_submission',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'title'    => __( 'Submission order status', 'wt-eu-withdrawal-button' ),
				'desc'     => __( 'The order status to set when a withdrawal request is submitted.', 'wt-eu-withdrawal-button' ),
				'id'       => self::PREFIX . 'submission_order_status',
				'type'     => 'select',
				'options'  => function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array(),
				'default'  => 'wc-pending-wdraw',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:300px;',
				'desc_tip' => true,
			),

			array(
				'title'   => __( 'Change order status on approval', 'wt-eu-withdrawal-button' ),
				'desc'    => __( 'Automatically change the order status when a withdrawal request is approved.', 'wt-eu-withdrawal-button' ),
				'id'      => self::PREFIX . 'change_status_on_approval',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'title'    => __( 'Approval order status', 'wt-eu-withdrawal-button' ),
				'desc'     => __( 'The order status to set when a withdrawal request is approved.', 'wt-eu-withdrawal-button' ),
				'id'       => self::PREFIX . 'approval_order_status',
				'type'     => 'select',
				'options'  => function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array(),
				'default'  => 'wc-withdrawn',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:300px;',
				'desc_tip' => true,
			),

			array(
				'type' => 'sectionend',
				'id'   => self::PREFIX . 'order_status_section',
			),

			// --- Email Notifications Section ---.
			array(
				'title' => __( 'Email Notifications', 'wt-eu-withdrawal-button' ),
				'type'  => 'title',
				'desc'  => __( 'Configure admin recipients for withdrawal notification emails.', 'wt-eu-withdrawal-button' ),
				'id'    => self::PREFIX . 'email_section',
			),

			array(
				'title'       => __( 'Recipient(s)', 'wt-eu-withdrawal-button' ),
				'desc'        => __( 'Comma-separated email addresses for admin withdrawal notifications.', 'wt-eu-withdrawal-button' ),
				'id'          => self::PREFIX . 'admin_notification_recipients',
				'type'        => 'text',
				'default'     => Wbte_Ewb_Settings::get_store_email(),
				'css'         => 'min-width:400px;',
				'placeholder' => Wbte_Ewb_Settings::get_store_email(),
				'desc_tip'    => true,
			),

			array(
				'type' => 'sectionend',
				'id'   => self::PREFIX . 'email_section',
			),

			// --- Shortcode Section ---.
			array(
				'title' => __( 'Withdrawal Button Shortcode', 'wt-eu-withdrawal-button' ),
				'type'  => 'title',
				'desc'  => $this->get_withdrawal_button_shortcode_help(),
				'id'    => self::PREFIX . 'shortcode_section',
			),

			array(
				'type' => 'sectionend',
				'id'   => self::PREFIX . 'shortcode_section',
			),

			// --- Data Management Section ---.
			array(
				'title' => __( 'Data Management', 'wt-eu-withdrawal-button' ),
				'type'  => 'title',
				'desc'  => '',
				'id'    => self::PREFIX . 'data_section',
			),

			array(
				'title'   => __( 'Delete Data on Uninstall', 'wt-eu-withdrawal-button' ),
				'desc'    => __( 'Remove all plugin data (settings, withdrawal requests, database tables) when the plugin is uninstalled.', 'wt-eu-withdrawal-button' )
					. ' <strong style="color:#d63638;">'
					. __( 'Warning: This action is irreversible.', 'wt-eu-withdrawal-button' )
					. '</strong>',
				'id'      => self::PREFIX . 'delete_data_on_uninstall',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'type' => 'sectionend',
				'id'   => self::PREFIX . 'data_section',
			),
		);

		/**
		 * Filters the WooCommerce Withdrawal settings tab fields.
		 *
		 * @since 1.0.0
		 *
		 * @param array $settings Settings fields array.
		 */
		return apply_filters( 'wbte_ewb_wc_settings_fields', $settings );
	}

	/**
	 * Options for the withdrawal period start status setting.
	 *
	 * @since 1.0.4
	 *
	 * @return array<string, string>
	 */
	private function get_withdrawal_period_start_status_options() {
		$options = array(
			'order_created' => __( 'Order created', 'wt-eu-withdrawal-button' ),
		);

		if ( function_exists( 'wc_get_order_statuses' ) ) {
			$options = array_merge( $options, wc_get_order_statuses() );
		}

		return $options;
	}

	/**
	 * Retrieve product categories as an ID => name associative array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	private function get_product_categories() {
		$categories = array();

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[ $term->term_id ] = $term->name;
			}
		}

		return $categories;
	}

	/**
	 * Help text for the withdrawal button shortcode.
	 *
	 * @since 1.0.3
	 *
	 * @return string
	 */
	private function get_withdrawal_button_shortcode_help() {
		$html  = '<p>' . esc_html__( 'Add a withdrawal request button to any page or post. The button links to your configured withdrawal page.', 'wt-eu-withdrawal-button' ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'Examples', 'wt-eu-withdrawal-button' ) . '</strong></p>';
		$html .= '<p><code>[wt_eu_order_withdrawal]</code><br /><code>[wt_eu_order_withdrawal label=&quot;Cancel my order&quot;]</code></p>';
		$html .= '<ul style="margin-left:1.2em;list-style:disc;">';
		$html .= '<li><strong>label</strong> &mdash; ' . esc_html__( 'Optional. Custom button text. Uses the default shortcode label when omitted.', 'wt-eu-withdrawal-button' ) . '</li>';
		$html .= '<li><strong>class</strong> &mdash; ' . esc_html__( 'Optional. Extra CSS classes added to the button.', 'wt-eu-withdrawal-button' ) . '</li>';
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Retrieve selected excluded products as an ID => name associative array.
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, string>
	 */
	private function get_selected_product_options() {
		$products = array();
		$ids      = array_map( 'absint', (array) Wbte_Ewb_Settings::get( 'excluded_products', array() ) );

		foreach ( $ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$products[ $product_id ] = wp_strip_all_tags( $product->get_formatted_name() );
			}
		}

		return $products;
	}
}
