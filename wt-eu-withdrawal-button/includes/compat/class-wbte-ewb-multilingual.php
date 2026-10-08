<?php
/**
 * Multilingual plugin compatibility.
 *
 * Registers admin button/link label settings with WPML, Polylang, and
 * TranslatePress so customised values can be translated per language.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Multilingual
 *
 * @since 1.0.1
 */
class Wbte_Ewb_Multilingual {

	/**
	 * WPML / Polylang string context name.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	const STRING_CONTEXT = 'wt-eu-withdrawal-button';

	/**
	 * Constructor.
	 *
	 * @since 1.0.1
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_strings' ), 20 );
		add_action( 'wbte_ewb_settings_updated', array( $this, 'register_strings_from_settings' ), 10, 1 );
		add_filter( 'wbte_ewb_button_label', array( $this, 'translate_button_label' ), 20, 2 );
	}

	/**
	 * Register current label settings with multilingual plugins.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public function register_strings() {
		$this->register_strings_from_settings( Wbte_Ewb_Settings::get_all() );
	}

	/**
	 * Register label settings after they are saved.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, mixed> $settings Saved settings subset or full settings array.
	 * @return void
	 */
	public function register_strings_from_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return;
		}

		foreach ( Wbte_Ewb_Settings::get_translatable_button_label_keys() as $setting_key ) {
			$value = isset( $settings[ $setting_key ] ) ? (string) $settings[ $setting_key ] : '';

			if ( '' === $value ) {
				continue;
			}

			$this->register_string( $setting_key, $value );
		}
	}

	/**
	 * Register a single admin label string.
	 *
	 * @since 1.0.1
	 *
	 * @param string $setting_key Setting key.
	 * @param string $value       Setting value.
	 * @return void
	 */
	private function register_string( $setting_key, $value ) {
		/**
		 * Fires when an admin button label is registered for translation.
		 *
		 * @since 1.0.1
		 *
		 * @param string $value       Label text.
		 * @param string $setting_key Setting key.
		 */
		do_action( 'wbte_ewb_register_admin_label_string', $value, $setting_key );

		// WPML String Translation.
		do_action( 'wpml_register_single_string', self::STRING_CONTEXT, $setting_key, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML hook.

		// Polylang string translations.
		if ( function_exists( 'pll_register_string' ) ) {
			pll_register_string( $setting_key, $value, self::STRING_CONTEXT, false );
		}
	}

	/**
	 * Translate a resolved button label for the current language.
	 *
	 * @since 1.0.1
	 *
	 * @param string $label   Resolved label text.
	 * @param string $context UI context identifier.
	 * @return string
	 */
	public function translate_button_label( $label, $context ) {
		$key_map = Wbte_Ewb_Settings::get_button_label_key_map();

		if ( '' === $label || ! isset( $key_map[ $context ] ) ) {
			return $label;
		}

		return self::translate_setting_string( $label, $key_map[ $context ] );
	}

	/**
	 * Translate an admin-entered setting string for the active language.
	 *
	 * @since 1.0.1
	 *
	 * @param string $string      Stored setting value.
	 * @param string $setting_key Setting key.
	 * @return string
	 */
	public static function translate_setting_string( $string, $setting_key ) {
		if ( '' === $string ) {
			return '';
		}

		// Polylang.
		if ( function_exists( 'pll__' ) ) {
			$string = pll__( $string );
		}

		// TranslatePress.
		if ( function_exists( 'trp_translate' ) ) {
			$string = trp_translate( $string, null, false );
		} elseif ( has_filter( 'trp_translate' ) ) {
			$string = apply_filters( 'trp_translate', $string, null, 'dynamicstrings' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- TranslatePress hook.
		}

		// WPML String Translation + admin texts fallback.
		if ( has_filter( 'wpml_translate_single_string' ) ) {
			$translated = apply_filters(
				'wpml_translate_single_string', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML hook.
				$string,
				self::STRING_CONTEXT,
				$setting_key
			);

			if ( is_string( $translated ) && '' !== $translated ) {
				$string = $translated;
			}

			$admin_text_name = '[' . Wbte_Ewb_Settings::OPTION_KEY . ']' . $setting_key;
			$translated      = apply_filters(
				'wpml_translate_single_string', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML hook.
				$string,
				'admin_texts_' . Wbte_Ewb_Settings::OPTION_KEY,
				$admin_text_name
			);

			if ( is_string( $translated ) && '' !== $translated ) {
				$string = $translated;
			}
		}

		/**
		 * Filters an admin button/link label after multilingual plugins run.
		 *
		 * @since 1.0.1
		 *
		 * @param string $string      Translated label text.
		 * @param string $setting_key Setting key.
		 */
		return apply_filters( 'wbte_ewb_translate_admin_label', $string, $setting_key );
	}

	/**
	 * Resolve the order language from multilingual plugin meta.
	 *
	 * Checks WPML, TranslatePress, and Polylang order meta keys.
	 *
	 * @since 1.1.1
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return string Language code or empty string.
	 */
	public static function get_order_language( $order ) {
		if ( ! ( $order instanceof WC_Order ) ) {
			return '';
		}

		// WPML.
		$lang = $order->get_meta( 'wpml_language' );
		if ( ! empty( $lang ) ) {
			return $lang;
		}

		// TranslatePress.
		$lang = $order->get_meta( 'trp_language' );
		if ( ! empty( $lang ) ) {
			return $lang;
		}

		// Polylang (stores locale in order meta or term).
		$lang = $order->get_meta( '_pll_language' );
		if ( ! empty( $lang ) ) {
			return $lang;
		}

		return '';
	}

	/**
	 * Switch the current language context for email rendering.
	 *
	 * Supports WPML, TranslatePress, and Polylang. Call restore_email_language()
	 * after the email is sent to undo the switch.
	 *
	 * @since 1.1.1
	 *
	 * @param string $language Language code to switch to.
	 * @return bool True if a language switch was performed.
	 */
	public static function switch_email_language( $language ) {
		if ( empty( $language ) ) {
			return false;
		}

		// WPML.
		if ( has_action( 'wpml_switch_language' ) ) {
			do_action( 'wpml_switch_language', $language ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		// TranslatePress: switch language, reload textdomains, and prevent
		// the output buffer from re-translating the email HTML.
		if ( function_exists( 'trp_switch_language' ) ) {
			trp_switch_language( $language );
		}
		if ( class_exists( 'TRP_Translate_Press' ) ) {
			self::reload_plugin_textdomain( $language );
			self::$trp_gettext_cache = array();
			add_filter( 'gettext', array( __CLASS__, 'filter_gettext_for_trp' ), 20, 3 );
			add_filter( 'trp_stop_translating_page', '__return_true', 99999 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		// Polylang.
		if ( function_exists( 'PLL' ) && is_object( PLL() ) && method_exists( PLL(), 'switch_lang' ) ) {
			PLL()->switch_lang( $language );
		}

		return true;
	}

	/**
	 * Restore language context after email rendering.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	public static function restore_email_language() {
		// Remove TranslatePress gettext bridge, output-buffer block, and clear cache.
		remove_filter( 'gettext', array( __CLASS__, 'filter_gettext_for_trp' ), 20 );
		remove_filter( 'trp_stop_translating_page', '__return_true', 99999 );
		self::$trp_gettext_cache = array();

		// WPML.
		if ( has_action( 'wpml_switch_language' ) ) {
			do_action( 'wpml_switch_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		// TranslatePress.
		if ( function_exists( 'trp_restore_language' ) ) {
			trp_restore_language();
			self::reload_plugin_textdomain( get_locale() );
		}

		// Polylang: switch back to default.
		if ( function_exists( 'PLL' ) && is_object( PLL() ) && method_exists( PLL(), 'switch_lang' ) ) {
			PLL()->switch_lang( pll_default_language() );
		}
	}

	/**
	 * Reload the plugin textdomain for the given locale.
	 *
	 * After switch_to_locale() the MO files loaded for the previous locale
	 * are still in memory. Unload and reload so __() picks up the new locale.
	 *
	 * @since 1.1.1
	 *
	 * @param string $locale Locale code (e.g. 'en_US', 'hr').
	 * @return void
	 */
	private static function reload_plugin_textdomain( $locale ) {
		$domain = 'wt-eu-withdrawal-button';

		unload_textdomain( $domain );

		$mo_file = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo';
		if ( is_readable( $mo_file ) ) {
			load_textdomain( $domain, $mo_file );
			return;
		}

		$mo_file = WBTE_EWB_PLUGIN_DIR . 'languages/' . $domain . '-' . $locale . '.mo';
		if ( is_readable( $mo_file ) ) {
			load_textdomain( $domain, $mo_file );
		}
	}

	/**
	 * Get the site default language across multilingual plugins.
	 *
	 * Used for admin emails that should use the site language, not the order language.
	 *
	 * @since 1.1.1
	 *
	 * @return string Default language code or empty string.
	 */
	public static function get_default_language() {
		// WPML.
		$lang = apply_filters( 'wpml_default_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		if ( ! empty( $lang ) ) {
			return $lang;
		}

		// TranslatePress.
		if ( class_exists( 'TRP_Translate_Press' ) ) {
			$trp      = TRP_Translate_Press::get_trp_instance();
			$settings = $trp->get_component( 'settings' );
			if ( $settings ) {
				$trp_settings = $settings->get_settings();
				if ( ! empty( $trp_settings['default-language'] ) ) {
					return $trp_settings['default-language'];
				}
			}
		}

		// Polylang.
		if ( function_exists( 'pll_default_language' ) ) {
			$lang = pll_default_language();
			if ( ! empty( $lang ) ) {
				return $lang;
			}
		}

		return '';
	}

	/**
	 * In-memory cache of TranslatePress gettext translations.
	 *
	 * @since 1.1.1
	 * @var array<string, string|null>
	 */
	private static $trp_gettext_cache = array();

	/**
	 * Gettext filter that bridges __() calls to TranslatePress String Translation.
	 *
	 * Active only between switch_email_language() and restore_email_language().
	 *
	 * @since 1.1.1
	 *
	 * @param string $translated Translated text (from .mo or original).
	 * @param string $text       Original untranslated text.
	 * @param string $domain     Text domain.
	 * @return string
	 */
	public static function filter_gettext_for_trp( $translated, $text, $domain ) {
		if ( self::STRING_CONTEXT !== $domain ) {
			return $translated;
		}

		if ( $translated !== $text ) {
			return $translated;
		}

		if ( array_key_exists( $text, self::$trp_gettext_cache ) ) {
			return null !== self::$trp_gettext_cache[ $text ] ? self::$trp_gettext_cache[ $text ] : $translated;
		}

		$result = self::query_trp_gettext_db( $text, $domain );
		self::$trp_gettext_cache[ $text ] = $result;

		return null !== $result ? $result : $translated;
	}

	/**
	 * Query TranslatePress's gettext DB table for a translation.
	 *
	 * @since 1.1.1
	 *
	 * @param string $text   Original English string.
	 * @param string $domain Text domain.
	 * @return string|null Translated string or null if not found.
	 */
	private static function query_trp_gettext_db( $text, $domain ) {
		global $wpdb, $TRP_LANGUAGE;

		$language = ! empty( $TRP_LANGUAGE ) ? $TRP_LANGUAGE : get_locale();

		if ( empty( $language ) ) {
			return null;
		}

		$safe_lang       = preg_replace( '/[^a-z0-9_]/', '', strtolower( $language ) );
		$table_lang      = $wpdb->prefix . 'trp_gettext_' . $safe_lang;
		$table_originals = $wpdb->prefix . 'trp_gettext_original_strings';

		// Verify table exists, try alternative name patterns if not.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_lang ) ) ) {
			$alt_tables = array();
			if ( strpos( $safe_lang, '_' ) !== false ) {
				$alt_tables[] = $wpdb->prefix . 'trp_gettext_' . substr( $safe_lang, 0, strpos( $safe_lang, '_' ) );
			} else {
				$alt_tables[] = $wpdb->prefix . 'trp_gettext_' . $safe_lang . '_' . strtoupper( $safe_lang );
			}

			$found = false;
			foreach ( $alt_tables as $alt ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $alt ) ) ) {
					$table_lang = $alt;
					$found      = true;
					break;
				}
			}

			if ( ! $found ) {
				return null;
			}
		}

		// Status 1 = machine translated, 2 = human reviewed.
		// Table names are safe: built from $wpdb->prefix + preg_replace'd locale
		// (alphanumeric + underscore only). Verified via SHOW TABLES above.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT tt.translated FROM `' . str_replace( '`', '', $table_lang ) . '` AS tt'
				. ' INNER JOIN `' . str_replace( '`', '', $table_originals ) . '` AS ot ON tt.original_id = ot.id'
				. ' WHERE ot.original = %s AND ot.domain = %s'
				. " AND tt.translated <> '' AND tt.status IN (1, 2) LIMIT 1",
				$text,
				$domain
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return ! empty( $result ) ? $result : null;
	}
}
