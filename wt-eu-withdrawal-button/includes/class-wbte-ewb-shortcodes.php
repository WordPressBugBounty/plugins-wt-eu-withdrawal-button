<?php
/**
 * Shortcode registrations.
 *
 * Registers and renders front-end withdrawal shortcodes.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Shortcodes
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Shortcodes {

	/**
	 * Shortcode tag for the full withdrawal form.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const FORM_SHORTCODE = 'wbte_ewb_withdrawal_form';

	/**
	 * Shortcode tag for the withdrawal request button.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const BUTTON_SHORTCODE = 'wt_eu_order_withdrawal';

	/**
	 * Constructor — hooks shortcode registration into `init`.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	/**
	 * Register all plugin shortcodes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( self::FORM_SHORTCODE, array( $this, 'render_withdrawal_form' ) );
		add_shortcode( self::BUTTON_SHORTCODE, array( $this, 'render_withdrawal_button' ) );
	}

	/**
	 * Check whether a post contains any plugin withdrawal shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post|null $post Post object. Defaults to the global $post.
	 * @return bool
	 */
	public static function post_has_shortcode( $post = null ) {
		if ( null === $post ) {
			global $post;
		}

		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		foreach ( array( self::FORM_SHORTCODE, self::BUTTON_SHORTCODE ) as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render the withdrawal form shortcode.
	 *
	 * Loads the appropriate template (logged-in vs. guest) and
	 * returns its output via output buffering.
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $atts Shortcode attributes (unused currently).
	 * @return string Rendered HTML.
	 */
	public function render_withdrawal_form( $atts = array() ) {
		$atts = shortcode_atts( array(), $atts, 'wbte_ewb_withdrawal_form' );

		// Ensure frontend dependencies are loaded (shortcode can be
		// rendered in REST/admin context, e.g. block editor preview).
		$frontend_dir = WBTE_EWB_PLUGIN_DIR . 'includes/frontend/';
		if ( ! class_exists( 'Wbte_Ewb_Form_Handler' ) && file_exists( $frontend_dir . 'class-wbte-ewb-form-handler.php' ) ) {
			require_once $frontend_dir . 'class-wbte-ewb-form-handler.php';
		}
		if ( ! class_exists( 'Wbte_Ewb_My_Account' ) && file_exists( $frontend_dir . 'class-wbte-ewb-my-account.php' ) ) {
			require_once $frontend_dir . 'class-wbte-ewb-my-account.php';
		}
		if ( ! class_exists( 'Wbte_Ewb_Form_Renderer' ) && file_exists( $frontend_dir . 'class-wbte-ewb-form-renderer.php' ) ) {
			require_once $frontend_dir . 'class-wbte-ewb-form-renderer.php';
		}

		if ( class_exists( 'Wbte_Ewb_Guest_Verification' ) ) {
			$verified_view = Wbte_Ewb_Guest_Verification::render_verified_view();

			if ( null !== $verified_view ) {
				return $verified_view;
			}
		}

		if ( class_exists( 'Wbte_Ewb_Form_Renderer' ) ) {
			return Wbte_Ewb_Form_Renderer::render_html();
		}

		ob_start();
		include WBTE_EWB_PLUGIN_DIR . 'templates/withdrawal-form.php';

		return ob_get_clean();
	}

	/**
	 * Render the withdrawal request button shortcode.
	 *
	 * Outputs a link styled as a button that points to the configured
	 * withdrawal page. When an order ID is supplied the button is only
	 * shown for eligible orders owned by the logged-in customer.
	 *
	 * Usage:
	 *   [wt_eu_order_withdrawal]
	 *   [wt_eu_order_withdrawal order_id="123"]
	 *   [wt_eu_order_withdrawal label="Cancel my order"]
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render_withdrawal_button( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'order_id' => 0,
				'label'    => '',
				'class'    => '',
			),
			$atts,
			self::BUTTON_SHORTCODE
		);

		$order_id = absint( $atts['order_id'] );
		$order    = $order_id ? wc_get_order( $order_id ) : null;
		$country  = Wbte_Ewb_Customer_Context::get_country_code( $order );

		/**
		 * Filters whether the withdrawal button shortcode should render.
		 *
		 * @since 1.0.4
		 *
		 * @param bool            $show    Whether to render the shortcode output.
		 * @param string          $country Customer ISO country code, if known.
		 * @param array           $atts    Parsed shortcode attributes.
		 * @param WC_Order|false  $order   Order from the shortcode, when provided.
		 */
		if ( ! apply_filters( 'wbte_ewb_show_withdrawal_button_shortcode', true, $country, $atts, $order ) ) {
			return '';
		}

		if ( ! Wbte_Ewb_Settings::is_withdrawal_display_allowed() ) {
			return '';
		}

		$withdrawal_page_url = Wbte_Ewb_Settings::get_withdrawal_page_url();

		if ( ! $withdrawal_page_url ) {
			return '';
		}

		$order_id       = absint( $atts['order_id'] );
		$withdrawal_url = $withdrawal_page_url;

		if ( $order_id ) {
			if ( ! is_user_logged_in() ) {
				return '';
			}

			if ( ! $order || (int) $order->get_customer_id() !== get_current_user_id() ) {
				return '';
			}

			if ( ! class_exists( 'Wbte_Ewb_Eligibility' ) ) {
				require_once WBTE_EWB_PLUGIN_DIR . 'includes/core/class-wbte-ewb-eligibility.php';
			}

			$eligibility = new Wbte_Ewb_Eligibility();

			if ( ! $eligibility->is_order_eligible( $order ) ) {
				return '';
			}

			$withdrawal_url = add_query_arg(
				array( 'order_id' => $order_id ),
				$withdrawal_page_url
			);
		}

		$button_label = $atts['label']
			? sanitize_text_field( $atts['label'] )
			: Wbte_Ewb_Settings::get_button_label( Wbte_Ewb_Settings::LABEL_SHORTCODE_BUTTON );

		$button_class = 'wbte-ewb-btn wbte-ewb-btn--withdrawal button';

		if ( ! empty( $atts['class'] ) ) {
			$extra_classes = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', $atts['class'] ) ) );
			if ( ! empty( $extra_classes ) ) {
				$button_class .= ' ' . implode( ' ', $extra_classes );
			}
		}

		/**
		 * Filters the withdrawal button URL rendered by the shortcode.
		 *
		 * @since 1.0.0
		 *
		 * @param string $withdrawal_url Destination URL.
		 * @param int    $order_id       Order ID from the shortcode, or 0.
		 * @param array  $atts           Parsed shortcode attributes.
		 */
		$withdrawal_url = apply_filters( 'wbte_ewb_withdrawal_button_url', $withdrawal_url, $order_id, $atts );

		return wc_get_template_html(
			'my-account-withdrawal-button.php',
			array(
				'withdrawal_url' => $withdrawal_url,
				'button_label'   => $button_label,
				'button_class'   => $button_class,
			),
			'',
			WBTE_EWB_PLUGIN_DIR . 'templates/'
		);
	}
}
