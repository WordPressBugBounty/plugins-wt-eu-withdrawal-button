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
}
