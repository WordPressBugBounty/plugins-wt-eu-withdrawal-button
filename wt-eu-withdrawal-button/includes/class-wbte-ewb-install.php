<?php
/**
 * Installation and database migration handler.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Install
 *
 * Creates custom database tables, stores default settings,
 * and handles version-based migrations.
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Install {

	/**
	 * Run the full installation routine.
	 *
	 * Called on activation and whenever the stored DB version
	 * is lower than the code-level constant.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function install() {
		self::create_tables();
		self::create_default_settings();
		update_option( 'wbte_ewb_db_version', WBTE_EWB_DB_VERSION );
	}

	/**
	 * Create or update the plugin's custom database tables.
	 *
	 * Uses WordPress's dbDelta() so the schema is applied
	 * idempotently and existing data is preserved on upgrades.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$withdrawals_table        = $wpdb->prefix . 'wbte_ewb_withdrawals';
		$logs_table               = $wpdb->prefix . 'wbte_ewb_withdrawal_logs';
		$pending_withdrawals_table = $wpdb->prefix . 'wbte_ewb_pending_withdrawals';

		$sql = "CREATE TABLE {$withdrawals_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL,
			order_number VARCHAR(64) NOT NULL DEFAULT '',
			customer_email VARCHAR(190) NOT NULL DEFAULT '',
			customer_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'pending',
			request_type VARCHAR(20) NOT NULL DEFAULT 'full',
			items_json LONGTEXT NOT NULL,
			reason TEXT NULL DEFAULT NULL,
			meta_json LONGTEXT NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			created_by BIGINT UNSIGNED NULL DEFAULT NULL,
			processed_by BIGINT UNSIGNED NULL DEFAULT NULL,
			processed_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY customer_email (customer_email),
			KEY customer_user_id (customer_user_id)
		) {$charset_collate};

		CREATE TABLE {$logs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			withdrawal_id BIGINT UNSIGNED NOT NULL,
			actor_type VARCHAR(20) NOT NULL DEFAULT '',
			actor_id BIGINT UNSIGNED NULL DEFAULT NULL,
			action VARCHAR(64) NOT NULL DEFAULT '',
			from_status VARCHAR(32) NULL DEFAULT NULL,
			to_status VARCHAR(32) NULL DEFAULT NULL,
			note TEXT NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY withdrawal_id (withdrawal_id)
		) {$charset_collate};

		CREATE TABLE {$pending_withdrawals_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_number VARCHAR(64) NOT NULL DEFAULT '',
			customer_email VARCHAR(190) NOT NULL DEFAULT '',
			reason TEXT NULL DEFAULT NULL,
			verify_token VARCHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(32) NOT NULL DEFAULT 'pending_verification',
			failure_reason TEXT NULL DEFAULT NULL,
			withdrawal_id BIGINT UNSIGNED NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			expires_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			verified_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY verify_token (verify_token),
			KEY customer_email (customer_email),
			KEY status (status),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Store default plugin settings if they do not already exist.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function create_default_settings() {
		// Derive defaults from the single source of truth.
		$defaults = Wbte_Ewb_Settings::get_defaults();

		$store_email = Wbte_Ewb_Settings::get_store_email();
		if ( $store_email ) {
			if ( empty( $defaults['admin_notification_recipients'] ) ) {
				$defaults['admin_notification_recipients'] = array( $store_email );
			}

			if ( empty( $defaults['customer_contact_email'] ) ) {
				$defaults['customer_contact_email'] = $store_email;
			}
		}

		// Only add if the option does not already exist so that
		// existing user configuration is never overwritten.
		if ( false === get_option( Wbte_Ewb_Settings::OPTION_KEY ) ) {
			add_option( Wbte_Ewb_Settings::OPTION_KEY, $defaults, '', 'no' );
		}
	}

	/**
	 * Check the stored DB version and re-run table creation if needed.
	 *
	 * Hooked to `plugins_loaded` so migrations run on every page load
	 * after a plugin file update (before the user visits any admin page).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function check_version() {
		$current_db_version = get_option( 'wbte_ewb_db_version', '0' );

		if ( version_compare( $current_db_version, WBTE_EWB_DB_VERSION, '<' ) ) {
			self::install();
			self::run_migrations( $current_db_version );
		}
	}

	/**
	 * Run version-specific migrations.
	 *
	 * Each migration is a versioned callback that runs once when
	 * upgrading past that version. To add a migration for v1.1.0:
	 *
	 *   1. Add an entry: '1.1.0' => array( __CLASS__, 'migrate_to_1_1_0' ),
	 *   2. Create the static method with the migration logic.
	 *   3. Bump WBTE_EWB_DB_VERSION in the main plugin file.
	 *
	 * Migrations are skipped on fresh installs (previous version '0').
	 *
	 * @since 1.0.0
	 *
	 * @param string $from_version The DB version before the upgrade.
	 * @return void
	 */
	private static function run_migrations( $from_version ) {
		// Skip migrations on fresh installs.
		if ( '0' === $from_version ) {
			return;
		}

		/**
		 * Version-keyed migration callbacks.
		 *
		 * Add new entries when a version requires data changes
		 * that go beyond what dbDelta() handles (e.g. backfills,
		 * column renames, setting migrations).
		 *
		 * Example:
		 *   '1.1.0' => array( __CLASS__, 'migrate_to_1_1_0' ),
		 *   '1.2.0' => array( __CLASS__, 'migrate_to_1_2_0' ),
		 */
		$migrations = array();

		/**
		 * Filters the migration callbacks.
		 *
		 * Third-party plugins can register their own migrations
		 * that run during the upgrade process.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $migrations   Version-keyed callbacks.
		 * @param string $from_version The version being upgraded from.
		 */
		$migrations = apply_filters( 'wbte_ewb_migrations', $migrations, $from_version );

		uksort( $migrations, 'version_compare' );

		foreach ( $migrations as $version => $callback ) {
			if ( version_compare( $from_version, $version, '<' ) && is_callable( $callback ) ) {
				call_user_func( $callback );
			}
		}
	}

	/**
	 * Create the withdrawal form page and publish it.
	 *
	 * Uses WooCommerce's wc_create_page() which handles:
	 * - Duplicate prevention via option check
	 * - Slug-based search for existing pages
	 * - Trashed page recovery
	 *
	 * The page is published immediately so the plugin is ready
	 * to use out of the box.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function maybe_create_page() {
		if ( ! function_exists( 'wc_create_page' ) ) {
			include_once WC()->plugin_path() . '/includes/admin/wc-admin-functions.php';
		}

		// Check if a page already exists (e.g. from a prior install that created it as draft).
		$existing_page_id = absint( get_option( 'wbte_ewb_withdrawal_page_id', 0 ) );

		if ( $existing_page_id ) {
			$existing_page = get_post( $existing_page_id );

			// Publish the page if it exists but is not published yet.
			if ( $existing_page && 'publish' !== $existing_page->post_status ) {
				wp_update_post( array(
					'ID'          => $existing_page_id,
					'post_status' => 'publish',
				) );
			}

			if ( $existing_page ) {
				self::update_page_content( $existing_page_id );

				$settings = get_option( 'wbte_ewb_settings', array() );
				$settings['withdrawal_page'] = $existing_page_id;
				update_option( 'wbte_ewb_settings', $settings );
				return;
			}
		}

		$page_id = wc_create_page(
			esc_sql( _x( 'withdrawal-request', 'Page slug', 'wt-eu-withdrawal-button' ) ),
			'wbte_ewb_withdrawal_page_id',
			_x( 'Withdrawal Request', 'Page title', 'wt-eu-withdrawal-button' ),
			'',
			0,
			'publish'
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			self::update_page_content( $page_id );

			// Store the page ID in our plugin settings as well.
			$settings = get_option( 'wbte_ewb_settings', array() );
			$settings['withdrawal_page'] = absint( $page_id );
			update_option( 'wbte_ewb_settings', $settings );
		}
	}

	/**
	 * Set the shortcode content on the withdrawal page.
	 *
	 * Block-editor aware: wraps the shortcode in a wp:shortcode
	 * block if the page uses block content.
	 *
	 * @since 1.0.0
	 *
	 * @param int $page_id The page to update.
	 * @return void
	 */
	private static function update_page_content( $page_id ) {
		$shortcode = '[wbte_ewb_withdrawal_form]';

		// Check if the page uses block editor content.
		$post    = get_post( $page_id );
		$content = $post ? $post->post_content : '';

		// Don't add if the shortcode is already present.
		if ( false !== strpos( $content, $shortcode ) ) {
			return;
		}

		if ( function_exists( 'has_blocks' ) && ( empty( $content ) || has_blocks( $content ) ) ) {
			$shortcode_block = '<!-- wp:shortcode -->' . "\n" . $shortcode . "\n" . '<!-- /wp:shortcode -->';
		} else {
			$shortcode_block = $shortcode;
		}

		$new_content = trim( $content . "\n\n" . $shortcode_block );

		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => $new_content,
			)
		);
	}
}
