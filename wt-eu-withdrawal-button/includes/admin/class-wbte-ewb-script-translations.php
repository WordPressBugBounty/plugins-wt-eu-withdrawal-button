<?php
/**
 * Script translation loader for the React admin dashboard.
 *
 * Loads Jed JSON when shipped for a locale, and falls back to gettext / WPML
 * String Translation so languages without a JSON file (e.g. French) still work.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Script_Translations
 *
 * @since 1.0.3
 */
class Wbte_Ewb_Script_Translations {

	/**
	 * Plugin text domain.
	 *
	 * @since 1.0.3
	 * @var string
	 */
	const TEXT_DOMAIN = 'wt-eu-withdrawal-button';

	/**
	 * Dashboard script handle.
	 *
	 * @since 1.0.3
	 * @var string
	 */
	const SCRIPT_HANDLE = 'wbte-ewb-admin-dashboard';

	/**
	 * Built JS path relative to the plugin root (used for the Jed filename hash).
	 *
	 * @since 1.0.3
	 * @var string
	 */
	const SCRIPT_RELATIVE_PATH = 'react/build/admin-dashboard.js';

	/**
	 * Active WPML language code for this request.
	 *
	 * @since 1.0.3
	 * @var string|null
	 */
	private static $active_language_code = null;

	/**
	 * Whether WPML language was switched for this request.
	 *
	 * @since 1.0.3
	 * @var bool
	 */
	private static $wpml_language_switched = false;

	/**
	 * Whether the WordPress locale was switched for this request.
	 *
	 * @since 1.0.3
	 * @var bool
	 */
	private static $locale_switched = false;

	/**
	 * Register hooks.
	 *
	 * @since 1.0.3
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'pre_load_script_translations', array( __CLASS__, 'pre_load_dashboard_translations' ), 10, 4 );
	}

	/**
	 * Inject Jed JSON for the dashboard.
	 *
	 * @since 1.0.3
	 *
	 * @param string|false|null $translations Existing translations.
	 * @param string|false      $file         Expected JSON file path.
	 * @param string            $handle       Script handle.
	 * @param string            $domain       Text domain.
	 * @return string|false|null
	 */
	public static function pre_load_dashboard_translations( $translations, $file, $handle, $domain ) {
		if ( self::SCRIPT_HANDLE !== $handle || self::TEXT_DOMAIN !== $domain ) {
			return $translations;
		}

		$messages = self::get_locale_messages();

		if ( empty( $messages ) ) {
			return $translations;
		}

		return wp_json_encode(
			array(
				'domain'      => 'messages',
				'locale_data' => array(
					'messages' => $messages,
				),
			)
		);
	}

	/**
	 * Print wp.i18n.setLocaleData() before the dashboard bundle.
	 *
	 * @since 1.0.3
	 *
	 * @param string $handle Script handle.
	 * @return void
	 */
	public static function enqueue_inline_locale_data( $handle ) {
		$messages = self::get_locale_messages();

		if ( empty( $messages ) ) {
			return;
		}

		wp_add_inline_script(
			$handle,
			sprintf(
				'wp.i18n.setLocaleData( %1$s, %2$s );',
				wp_json_encode( $messages ),
				wp_json_encode( self::TEXT_DOMAIN )
			),
			'before'
		);
	}

	/**
	 * Build Jed messages for the active admin language.
	 *
	 * @since 1.0.3
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function get_locale_messages() {
		$msgids = self::get_dashboard_msgids();

		if ( empty( $msgids ) ) {
			return array();
		}

		$json_messages = self::load_json_messages_array();

		self::switch_translation_context();

		$messages         = array();
		$messages['']     = self::get_jed_metadata();

		foreach ( $msgids as $msgid ) {
			$translated = self::translate_msgid( $msgid );

			if ( $translated !== $msgid ) {
				$messages[ $msgid ] = array( $translated );
				continue;
			}

			if ( isset( $json_messages[ $msgid ] ) && is_array( $json_messages[ $msgid ] ) ) {
				$messages[ $msgid ] = $json_messages[ $msgid ];
				continue;
			}

			$messages[ $msgid ] = array( $msgid );
		}

		self::restore_translation_context();

		return $messages;
	}

	/**
	 * MD5 hash WordPress uses for Jed JSON filenames.
	 *
	 * @since 1.0.3
	 *
	 * @return string
	 */
	public static function get_bundle_hash() {
		return md5( self::SCRIPT_RELATIVE_PATH );
	}

	/**
	 * Find the best Jed JSON file for the current admin request.
	 *
	 * Searches WordPress.org language directories first, then the plugin
	 * languages folder, so community translations work for the React dashboard.
	 *
	 * @since 1.0.3
	 *
	 * @return string|null
	 */
	public static function find_json_file() {
		$hash    = self::get_bundle_hash();
		$pattern = self::TEXT_DOMAIN . '-%s-' . $hash . '.json';

		foreach ( self::get_locale_candidates() as $locale ) {
			foreach ( self::resolve_json_locale_slugs( $locale ) as $json_locale ) {
				$filename = sprintf( $pattern, $json_locale );

				foreach ( self::get_json_search_directories() as $dir ) {
					$file = $dir . $filename;

					if ( is_readable( $file ) ) {
						return $file;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Directories that may contain Jed JSON translation files.
	 *
	 * @since 1.0.7
	 *
	 * @return string[]
	 */
	private static function get_json_search_directories() {
		$dirs = array();

		if ( defined( 'WP_LANG_DIR' ) && WP_LANG_DIR ) {
			$dirs[] = trailingslashit( WP_LANG_DIR ) . 'plugins/';
			$dirs[] = trailingslashit( WP_LANG_DIR );
		}

		$dirs[] = trailingslashit( WBTE_EWB_PLUGIN_DIR ) . 'languages/';

		return array_values( array_unique( $dirs ) );
	}

	/**
	 * Dashboard msgids taken from any available Jed JSON catalog.
	 *
	 * @since 1.0.3
	 *
	 * @return string[]
	 */
	private static function get_dashboard_msgids() {
		$hash  = self::get_bundle_hash();
		$files = array();

		foreach ( self::get_json_search_directories() as $dir ) {
			$matched = glob( $dir . self::TEXT_DOMAIN . '-*-' . $hash . '.json' );
			if ( ! empty( $matched ) ) {
				$files = array_merge( $files, $matched );
			}
		}

		$files = array_values( array_unique( $files ) );

		if ( empty( $files ) ) {
			return array();
		}

		$best_messages = array();
		$best_count    = 0;

		foreach ( $files as $file ) {
			$jed = json_decode( (string) file_get_contents( $file ), true );

			if ( empty( $jed['locale_data']['messages'] ) || ! is_array( $jed['locale_data']['messages'] ) ) {
				continue;
			}

			$messages = array_filter(
				array_keys( $jed['locale_data']['messages'] ),
				static function ( $key ) {
					return '' !== $key;
				}
			);

			$count = count( $messages );

			if ( $count > $best_count ) {
				$best_count    = $count;
				$best_messages = $messages;
			}
		}

		return array_values( $best_messages );
	}

	/**
	 * Load Jed messages from the JSON file for the active locale, if any.
	 *
	 * @since 1.0.3
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function load_json_messages_array() {
		$file = self::find_json_file();

		if ( ! $file ) {
			return array();
		}

		$jed = json_decode( (string) file_get_contents( $file ), true );

		if ( empty( $jed['locale_data']['messages'] ) || ! is_array( $jed['locale_data']['messages'] ) ) {
			return array();
		}

		return $jed['locale_data']['messages'];
	}

	/**
	 * Translate a dashboard msgid via bundled gettext, then WPML String Translation.
	 *
	 * WPML registers scanned gettext strings with md5( $msgid ) as the string name,
	 * not the source text itself.
	 *
	 * @since 1.0.3
	 *
	 * @param string $msgid Source string.
	 * @return string
	 */
	private static function translate_msgid( $msgid ) {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Dynamic React dashboard msgids resolved at runtime.
		$translated = __( $msgid, 'wt-eu-withdrawal-button' );

		if ( is_string( $translated ) && '' !== $translated && $translated !== $msgid ) {
			return $translated;
		}

		$lang = self::$active_language_code;

		if ( function_exists( 'icl_translate' ) ) {
			$has_translation = false;
			$wpml            = icl_translate(
				self::TEXT_DOMAIN,
				md5( $msgid ),
				$msgid,
				false,
				$has_translation,
				$lang
			);

			if ( $has_translation && is_string( $wpml ) && '' !== $wpml && $wpml !== $msgid ) {
				return $wpml;
			}
		}

		if ( has_filter( 'wpml_translate_single_string' ) ) {
			$wpml = apply_filters(
				'wpml_translate_single_string', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML hook.
				$msgid,
				self::TEXT_DOMAIN,
				md5( $msgid ),
				$lang
			);

			if ( is_string( $wpml ) && '' !== $wpml && $wpml !== $msgid ) {
				return $wpml;
			}
		}

		return $msgid;
	}

	/**
	 * Switch WPML / locale context before translating dashboard strings.
	 *
	 * @since 1.0.3
	 *
	 * @return void
	 */
	private static function switch_translation_context() {
		self::$active_language_code = self::get_primary_language_code();
		$lang                       = self::$active_language_code;

		global $sitepress;
		if ( $lang && isset( $sitepress ) && is_object( $sitepress ) && method_exists( $sitepress, 'switch_lang' ) ) {
			$sitepress->switch_lang( $lang, true );
			self::$wpml_language_switched = true;
		} elseif ( $lang && did_action( 'wpml_loaded' ) ) {
			do_action( 'wpml_switch_language', $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML hook.
			self::$wpml_language_switched = true;
		}

		$locale = self::resolve_primary_json_locale();

		if ( $locale && determine_locale() !== $locale ) {
			switch_to_locale( $locale );
			self::$locale_switched = true;
		}

		unload_textdomain( self::TEXT_DOMAIN );

		// Prefer WordPress.org / WP_LANG_DIR translations, then bundled plugin files.
		$locale_for_mo = $locale ? $locale : determine_locale();
		foreach ( self::get_mo_file_candidates( $locale_for_mo ) as $mofile ) {
			if ( is_readable( $mofile ) && load_textdomain( self::TEXT_DOMAIN, $mofile ) ) {
				return;
			}
		}

		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Fallback when no MO was found above.
		load_plugin_textdomain(
			self::TEXT_DOMAIN,
			false,
			dirname( plugin_basename( WBTE_EWB_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Candidate .mo paths for a locale (WP.org locations first).
	 *
	 * @since 1.0.7
	 *
	 * @param string $locale Locale slug.
	 * @return string[]
	 */
	private static function get_mo_file_candidates( $locale ) {
		$filename = self::TEXT_DOMAIN . '-' . $locale . '.mo';
		$files    = array();

		if ( defined( 'WP_LANG_DIR' ) && WP_LANG_DIR ) {
			$files[] = trailingslashit( WP_LANG_DIR ) . 'plugins/' . $filename;
			$files[] = trailingslashit( WP_LANG_DIR ) . $filename;
		}

		$files[] = trailingslashit( WBTE_EWB_PLUGIN_DIR ) . 'languages/' . $filename;

		return $files;
	}

	/**
	 * Restore locale after translating dashboard strings.
	 *
	 * @since 1.0.3
	 *
	 * @return void
	 */
	private static function restore_translation_context() {
		if ( self::$locale_switched ) {
			restore_previous_locale();
			self::$locale_switched = false;
		}

		if ( self::$wpml_language_switched ) {
			global $sitepress;
			if ( isset( $sitepress ) && is_object( $sitepress ) && method_exists( $sitepress, 'get_admin_language' ) ) {
				$admin_lang = $sitepress->get_admin_language();
				if ( is_string( $admin_lang ) && '' !== $admin_lang && 'all' !== $admin_lang ) {
					$sitepress->switch_lang( $admin_lang, true );
				}
			}
			self::$wpml_language_switched = false;
		}

		self::$active_language_code = null;
	}

	/**
	 * Jed metadata block for wp.i18n.
	 *
	 * @since 1.0.3
	 *
	 * @return array<string, string>
	 */
	private static function get_jed_metadata() {
		return array(
			'domain'       => 'messages',
			'lang'         => self::resolve_primary_json_locale() ?: determine_locale(),
			'plural-forms' => 'nplurals=2; plural=(n != 1);',
		);
	}

	/**
	 * Primary WPML/Polylang language code for the admin request.
	 *
	 * @since 1.0.3
	 *
	 * @return string|null
	 */
	private static function get_primary_language_code() {
		foreach ( self::get_locale_candidates() as $locale ) {
			if ( preg_match( '/^[a-z]{2}$/', $locale ) ) {
				return $locale;
			}

			if ( preg_match( '/^([a-z]{2})_/', $locale, $matches ) ) {
				return $matches[1];
			}
		}

		return null;
	}

	/**
	 * Best locale slug for the active admin language.
	 *
	 * @since 1.0.3
	 *
	 * @return string|null
	 */
	private static function resolve_primary_json_locale() {
		$file = self::find_json_file();

		if ( $file && preg_match(
			'/^' . preg_quote( self::TEXT_DOMAIN, '/' ) . '-(.+)-' . preg_quote( self::get_bundle_hash(), '/' ) . '$/',
			basename( $file, '.json' ),
			$matches
		) ) {
			return $matches[1];
		}

		return determine_locale();
	}

	/**
	 * Map WPML/Polylang short codes to locale slugs used in JSON filenames.
	 *
	 * @since 1.0.3
	 *
	 * @param string $locale Locale candidate.
	 * @return string[]
	 */
	private static function resolve_json_locale_slugs( $locale ) {
		$slugs = array();

		global $sitepress;
		if ( isset( $sitepress ) && is_object( $sitepress ) && method_exists( $sitepress, 'get_locale_from_language_code' ) && preg_match( '/^[a-z]{2}$/', $locale ) ) {
			$wpml_locale = $sitepress->get_locale_from_language_code( $locale );
			if ( is_string( $wpml_locale ) && '' !== $wpml_locale ) {
				$slugs[] = $wpml_locale;
			}
		}

		$slugs[] = $locale;

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Build locale candidates, prioritising WPML admin language over site/user locale.
	 *
	 * @since 1.0.3
	 *
	 * @return string[]
	 */
	private static function get_locale_candidates() {
		$candidates = array();

		/**
		 * Filter the locale used to load dashboard script translations.
		 *
		 * @since 1.0.3
		 *
		 * @param string|null $locale Override locale.
		 */
		$forced = apply_filters( 'wbte_ewb_admin_script_locale', null );
		if ( is_string( $forced ) && '' !== $forced ) {
			$candidates[] = $forced;
		}

		if ( is_admin() && ! empty( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WPML admin language switcher.
			$candidates[] = sanitize_key( wp_unslash( $_GET['lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		global $sitepress;
		if ( isset( $sitepress ) && is_object( $sitepress ) && method_exists( $sitepress, 'get_admin_language' ) ) {
			$wpml_admin_lang = $sitepress->get_admin_language();
			if ( is_string( $wpml_admin_lang ) && '' !== $wpml_admin_lang && 'all' !== $wpml_admin_lang ) {
				$candidates[] = $wpml_admin_lang;
			}
		}

		if ( has_filter( 'wpml_current_language' ) ) {
			$wpml_lang = apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			if ( is_string( $wpml_lang ) && '' !== $wpml_lang ) {
				$candidates[] = $wpml_lang;
			}
		}

		if ( function_exists( 'pll_current_language' ) ) {
			$pll_locale = pll_current_language( 'locale' );
			if ( is_string( $pll_locale ) && '' !== $pll_locale ) {
				$candidates[] = $pll_locale;
			}

			$pll_lang = pll_current_language( 'slug' );
			if ( is_string( $pll_lang ) && '' !== $pll_lang ) {
				$candidates[] = $pll_lang;
			}
		}

		$candidates[] = get_locale();
		$candidates[] = determine_locale();

		if ( function_exists( 'get_user_locale' ) ) {
			$candidates[] = get_user_locale();
		}

		return array_values( array_unique( array_filter( $candidates ) ) );
	}
}
