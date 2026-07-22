<?php
/**
 * Plugin settings handler.
 *
 * Central registry for all plugin settings. Every setting is defined
 * once in the schema returned by `get_schema()`. All other classes
 * (Install, REST, Admin) derive their behaviour from this schema.
 *
 * To add a new setting:
 *   1. Add an entry to `get_schema()` below.
 *   2. Add the UI field in SettingsPage.jsx (React) or WC settings tab.
 *   That's it — defaults, sanitization, REST validation, and the
 *   allowed-keys whitelist are all generated automatically.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Settings
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Settings {

	/**
	 * Button label context: site footer withdrawal link.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	const LABEL_FOOTER_LINK = 'footer_link';

	/**
	 * Button label context: My Account orders list action.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	const LABEL_MY_ACCOUNT_ORDER = 'my_account_order';

	/**
	 * Button label context: [wt_eu_order_withdrawal] shortcode button.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	const LABEL_SHORTCODE_BUTTON = 'shortcode_button';

	/**
	 * Button label context: withdrawal button template fallback.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	const LABEL_WITHDRAWAL_BUTTON = 'withdrawal_button';

	/**
	 * Option name in the wp_options table.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_KEY = 'wbte_ewb_settings';

	/**
	 * Cached schema array.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private static $schema_cache = null;

	/**
	 * Cached defaults array.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private static $defaults_cache = null;

	/**
	 * Return the settings schema.
	 *
	 * Each key maps to an array with:
	 *   - 'default'   (mixed)    Default value.
	 *   - 'type'      (string)   Data type: 'integer', 'string', 'boolean_string', 'array_string', 'array_int'.
	 *   - 'options'   (array)    Allowed values for enum-style settings (optional).
	 *   - 'sanitize'  (callable) Custom sanitizer (optional, auto-derived from type otherwise).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array> Settings schema.
	 */
	public static function get_schema() {
		if ( null !== self::$schema_cache ) {
			return self::$schema_cache;
		}

		$schema = array(
			'withdrawal_page'              => array(
				'default' => 0,
				'type'    => 'integer',
			),
			'embed_footer_link'            => array(
				'default' => 'yes',
				'type'    => 'boolean_string',
			),
			'footer_link_text'               => array(
				'default' => 'Request Withdrawal',
				'type'    => 'string',
			),
			'my_account_order_button_text' => array(
				'default' => 'Request Withdrawal',
				'type'    => 'string',
			),
			'shortcode_button_text'        => array(
				'default' => 'Request Withdrawal',
				'type'    => 'string',
			),
			'withdrawal_button_text'       => array(
				'default' => 'Request Withdrawal',
				'type'    => 'string',
			),
			'display_scope'                => array(
				'default' => 'global',
				'type'    => 'string',
				'options' => array( 'global', 'woocommerce_only' ),
			),
			'allow_partial_withdrawals'    => array(
				'default' => 'yes',
				'type'    => 'boolean_string',
			),
			'withdrawal_period'              => array(
				'default' => 14,
				'type'    => 'integer',
			),
			'withdrawal_period_start_statuses' => array(
				'default'  => array( 'order_created' ),
				'type'     => 'array_string',
				'sanitize' => array( 'Wbte_Ewb_Withdrawal_Period', 'sanitize_start_statuses' ),
			),
			'reason_required'              => array(
				'default' => 'no',
				'type'    => 'boolean_string',
			),
			'excluded_product_types'       => array(
				'default' => array(),
				'type'    => 'array_string',
			),
			'excluded_categories'          => array(
				'default' => array(),
				'type'    => 'array_int',
			),
			'excluded_products'            => array(
				'default' => array(),
				'type'    => 'array_int',
			),
			'excluded_behavior'            => array(
				'default' => 'hide',
				'type'    => 'string',
				'options' => array( 'hide', 'disable' ),
			),
			'change_status_on_submission'   => array(
				'default' => 'no',
				'type'    => 'boolean_string',
			),
			'submission_order_status'       => array(
				'default' => 'wc-pending-wdraw',
				'type'    => 'string',
			),
			'change_status_on_approval'     => array(
				'default' => 'no',
				'type'    => 'boolean_string',
			),
			'approval_order_status'         => array(
				'default' => 'wc-withdrawn',
				'type'    => 'string',
			),
			'delete_data_on_uninstall'      => array(
				'default' => 'no',
				'type'    => 'boolean_string',
			),
			'guest_verification_expiry_hours' => array(
				'default' => 48,
				'type'    => 'integer',
			),
			'admin_notification_recipients' => array(
				'default'  => array(),
				'type'     => 'array_string',
				'sanitize' => array( __CLASS__, 'sanitize_email_array' ),
			),
			'customer_contact_email'        => array(
				'default'  => '',
				'type'     => 'string',
				'sanitize' => array( __CLASS__, 'sanitize_email_setting' ),
			),
		);

		/**
		 * Filters the settings schema.
		 *
		 * Third-party plugins can register additional settings by adding
		 * entries to this array. Each entry must follow the schema format
		 * documented above.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array> $schema Settings schema.
		 */
		self::$schema_cache = apply_filters( 'wbte_ewb_settings_schema', $schema );

		return self::$schema_cache;
	}

	/**
	 * Return the default values derived from the schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults() {
		if ( null !== self::$defaults_cache ) {
			return self::$defaults_cache;
		}

		$defaults = array();
		foreach ( self::get_schema() as $key => $definition ) {
			$defaults[ $key ] = $definition['default'];
		}

		self::$defaults_cache = $defaults;

		return $defaults;
	}

	/**
	 * Return the list of all registered setting keys.
	 *
	 * Used by the REST API and admin to determine allowed keys
	 * without maintaining a separate whitelist.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function get_keys() {
		return array_keys( self::get_schema() );
	}

	/**
	 * Retrieve a single setting value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value if key does not exist.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::get_all();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	/**
	 * Map button label UI contexts to their setting keys.
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, string>
	 */
	public static function get_button_label_key_map() {
		return array(
			self::LABEL_FOOTER_LINK       => 'footer_link_text',
			self::LABEL_MY_ACCOUNT_ORDER  => 'my_account_order_button_text',
			self::LABEL_SHORTCODE_BUTTON  => 'shortcode_button_text',
			self::LABEL_WITHDRAWAL_BUTTON => 'withdrawal_button_text',
		);
	}

	/**
	 * Setting keys for button/link labels that support multilingual plugins.
	 *
	 * @since 1.0.1
	 *
	 * @return string[]
	 */
	public static function get_translatable_button_label_keys() {
		return array_values( self::get_button_label_key_map() );
	}

	/**
	 * Retrieve a withdrawal button/link label for a specific UI context.
	 *
	 * Each context has its own setting key so labels can be customised
	 * independently in future versions.
	 *
	 * @since 1.0.1
	 *
	 * @param string $context One of the LABEL_* constants.
	 * @return string
	 */
	public static function get_button_label( $context ) {
		$key_map = self::get_button_label_key_map();

		$translated_default = __( 'Request Withdrawal', 'wt-eu-withdrawal-button' );

		if ( ! isset( $key_map[ $context ] ) ) {
			return apply_filters( 'wbte_ewb_button_label', $translated_default, $context );
		}

		$setting_key    = $key_map[ $context ];
		$schema_default = self::get_defaults()[ $setting_key ] ?? 'Request Withdrawal';
		$label          = self::get( $setting_key, '' );

		// Translate the built-in default via the plugin text domain.
		if ( '' === $label || $label === $schema_default ) {
			/**
			 * Filters the default withdrawal button label before it is returned.
			 *
			 * @since 1.0.1
			 *
			 * @param string $translated_default Localised default label text.
			 * @param string $context            UI context identifier.
			 */
			$label = apply_filters( 'wbte_ewb_default_button_label', $translated_default, $context );
		}

		/**
		 * Filters a withdrawal button/link label for a specific UI context.
		 *
		 * @since 1.0.1
		 *
		 * @param string $label   Resolved label text.
		 * @param string $context UI context identifier.
		 */
		return apply_filters( 'wbte_ewb_button_label', $label, $context );
	}

	/**
	 * Retrieve all settings merged with defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$merged = wp_parse_args( $stored, self::get_defaults() );

		if (
			! empty( $stored['withdrawal_period_start_status'] )
			&& empty( $stored['withdrawal_period_start_statuses'] )
		) {
			$legacy_status = sanitize_text_field( (string) $stored['withdrawal_period_start_status'] );
			$merged['withdrawal_period_start_statuses'] = array( $legacy_status );
		}

		return $merged;
	}

	/**
	 * Return the WooCommerce store email address.
	 *
	 * @since 1.0.3
	 *
	 * @return string
	 */
	public static function get_store_email() {
		$email = get_option( 'woocommerce_store_email', '' );

		if ( ! $email || ! is_email( $email ) ) {
			$email = get_option( 'woocommerce_email_from_address', '' );
		}

		if ( ! $email || ! is_email( $email ) ) {
			$email = get_option( 'admin_email', '' );
		}

		return sanitize_email( $email );
	}

	/**
	 * Get the configured withdrawal page ID for the current language.
	 *
	 * Resolves WPML (and compatible) translated page IDs via wpml_object_id.
	 *
	 * @since 1.0.4
	 *
	 * @return int Page ID, or 0 when not configured.
	 */
	public static function get_withdrawal_page_id() {
		$page_id = absint( self::get( 'withdrawal_page', 0 ) );

		if ( ! $page_id ) {
			return 0;
		}

		if ( has_filter( 'wpml_object_id' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML core filter.
			$page_id = (int) apply_filters( 'wpml_object_id', $page_id, 'page', true );
		}

		/**
		 * Filters the withdrawal page ID used for front-end links.
		 *
		 * @since 1.0.4
		 *
		 * @param int $page_id Localized withdrawal page ID.
		 */
		return (int) apply_filters( 'wbte_ewb_withdrawal_page_id', $page_id );
	}

	/**
	 * Get the withdrawal page permalink for the current language.
	 *
	 * @since 1.0.4
	 *
	 * @return string Permalink, or empty string when not configured.
	 */
	public static function get_withdrawal_page_url() {
		$page_id = self::get_withdrawal_page_id();

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		/**
		 * Filters the withdrawal page URL used for front-end links.
		 *
		 * @since 1.0.4
		 *
		 * @param string $url     Localized withdrawal page permalink.
		 * @param int    $page_id Localized withdrawal page ID.
		 */
		return (string) apply_filters( 'wbte_ewb_withdrawal_page_url', $url, $page_id );
	}

	/**
	 * Check whether the current request is a WooCommerce page context.
	 *
	 * @since 1.0.4
	 *
	 * @return bool
	 */
	public static function is_woocommerce_page_context() {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return false;
		}

		return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
	}

	/**
	 * Whether withdrawal links and UI may be shown on the current request.
	 *
	 * When display scope is WooCommerce only, links are limited to WooCommerce
	 * pages and the configured withdrawal form page.
	 *
	 * @since 1.0.4
	 *
	 * @return bool
	 */
	public static function is_withdrawal_display_allowed() {
		$scope = (string) self::get( 'display_scope', 'global' );

		if ( 'woocommerce' === $scope ) {
			$scope = 'woocommerce_only';
		}

		if ( 'global' === $scope ) {
			return true;
		}

		if ( self::is_woocommerce_page_context() ) {
			return true;
		}

		$page_id = self::get_withdrawal_page_id();

		if ( $page_id && is_page( $page_id ) ) {
			return true;
		}

		/**
		 * Filters whether withdrawal links/UI should display for the current request.
		 *
		 * @since 1.0.4
		 *
		 * @param bool $allowed Whether display is allowed. Default false when scope is WooCommerce only.
		 */
		return (bool) apply_filters( 'wbte_ewb_is_withdrawal_display_allowed', false );
	}

	/**
	 * Return admin withdrawal notification recipients.
	 *
	 * Uses the saved list when present. Falls back to the store email only
	 * when the setting has never been stored.
	 *
	 * @since 1.0.3
	 *
	 * @return string[]
	 */
	public static function get_admin_notification_recipients() {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) || ! array_key_exists( 'admin_notification_recipients', $stored ) ) {
			$store_email = self::get_store_email();
			return $store_email ? array( $store_email ) : array();
		}

		return self::sanitize_email_array( $stored['admin_notification_recipients'] );
	}

	/**
	 * Return admin withdrawal notification recipients as a comma-separated string.
	 *
	 * @since 1.0.3
	 *
	 * @return string
	 */
	public static function get_admin_notification_recipients_string() {
		return implode( ', ', self::get_admin_notification_recipients() );
	}

	/**
	 * Return contact email address(es) shown to customers in withdrawal emails.
	 *
	 * Defaults to the configured customer contact email, then the WooCommerce store email.
	 *
	 * @since 1.0.4
	 *
	 * @param Wbte_Ewb_Request|null $request Withdrawal request.
	 * @param WC_Order|null         $order   WooCommerce order.
	 * @return string[]
	 */
	public static function get_customer_contact_emails( $request = null, $order = null ) {
		$email  = self::get_customer_contact_email();
		$emails = $email ? array( $email ) : array();

		/**
		 * Filters the contact email address(es) shown in customer withdrawal emails.
		 *
		 * @since 1.0.4
		 *
		 * @param string[]              $emails  Contact email addresses.
		 * @param Wbte_Ewb_Request|null $request Withdrawal request.
		 * @param WC_Order|null         $order   WooCommerce order.
		 */
		return apply_filters( 'wbte_ewb_customer_contact_emails', $emails, $request, $order );
	}

	/**
	 * Return the customer-facing store contact email address.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public static function get_customer_contact_email() {
		$stored = sanitize_email( (string) self::get( 'customer_contact_email', '' ) );

		if ( $stored && is_email( $stored ) ) {
			return $stored;
		}

		return self::get_store_email();
	}

	/**
	 * Sanitize an array (or comma-separated string) of email addresses.
	 *
	 * @since 1.0.3
	 *
	 * @param mixed $value Raw input value.
	 * @return string[] Sanitized unique email addresses.
	 */
	public static function sanitize_email_array( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$emails = array();

		foreach ( $value as $email ) {
			$email = sanitize_email( trim( (string) $email ) );

			if ( $email && is_email( $email ) ) {
				$emails[] = $email;
			}
		}

		return array_values( array_unique( $emails ) );
	}

	/**
	 * Sanitize a single email address setting.
	 *
	 * @since 1.0.4
	 *
	 * @param mixed $value Raw input value.
	 * @return string Sanitized email address or empty string.
	 */
	public static function sanitize_email_setting( $value ) {
		$email = sanitize_email( trim( (string) $value ) );

		return ( $email && is_email( $email ) ) ? $email : '';
	}

	/**
	 * Sanitize and save settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings Raw settings to save.
	 * @return bool True if the option was updated, false otherwise.
	 */
	public static function update( $settings ) {
		$current   = self::get_all();
		$sanitized = self::sanitize( $settings );
		$merged    = array_merge( $current, $sanitized );
		$result    = update_option( self::OPTION_KEY, $merged );

		/**
		 * Fires after plugin settings are updated.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $sanitized The sanitized settings that were saved.
		 * @param array<string, mixed> $merged    All settings after the merge.
		 */
		do_action( 'wbte_ewb_settings_updated', $sanitized, $merged );

		return $result;
	}

	/**
	 * Sanitize settings array using the schema.
	 *
	 * Only keys present in the schema are processed.
	 * Unknown keys are silently dropped.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed> Sanitized settings.
	 */
	private static function sanitize( $settings ) {
		$schema    = self::get_schema();
		$sanitized = array();

		foreach ( $settings as $key => $value ) {
			if ( ! isset( $schema[ $key ] ) ) {
				continue;
			}

			$def = $schema[ $key ];

			// Custom sanitizer takes priority.
			if ( ! empty( $def['sanitize'] ) && is_callable( $def['sanitize'] ) ) {
				$sanitized[ $key ] = call_user_func( $def['sanitize'], $value );
				continue;
			}

			// Auto-sanitize by type.
			switch ( $def['type'] ) {
				case 'integer':
					$sanitized[ $key ] = absint( $value );
					break;

				case 'boolean_string':
					$sanitized[ $key ] = in_array( $value, array( 'yes', 'no' ), true ) ? $value : $def['default'];
					break;

				case 'string':
					$clean = sanitize_text_field( $value );
					if ( 'display_scope' === $key && 'woocommerce' === $clean ) {
						$clean = 'woocommerce_only';
					}
					if ( ! empty( $def['options'] ) && ! in_array( $clean, $def['options'], true ) ) {
						$clean = $def['default'];
					}
					$sanitized[ $key ] = $clean;
					break;

				case 'array_string':
					$sanitized[ $key ] = is_array( $value )
						? array_map( 'sanitize_text_field', $value )
						: $def['default'];
					break;

				case 'array_int':
					$sanitized[ $key ] = is_array( $value )
						? array_map( 'absint', $value )
						: $def['default'];
					break;

				default:
					$sanitized[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Clear internal caches.
	 *
	 * Useful after the `wbte_ewb_settings_schema` filter changes
	 * at runtime (e.g. in tests).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$schema_cache   = null;
		self::$defaults_cache = null;
	}
}
