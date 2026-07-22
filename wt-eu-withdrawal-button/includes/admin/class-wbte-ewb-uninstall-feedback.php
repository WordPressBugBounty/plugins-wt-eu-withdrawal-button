<?php
/**
 * Uninstall feedback collector.
 *
 * Displays a modal on the Plugins screen when deactivating the plugin
 * and sends anonymised feedback to WebToffee.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Uninstall_Feedback
 *
 * @since 1.0.1
 */
class Wbte_Ewb_Uninstall_Feedback {

	/**
	 * Feedback API endpoint.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	protected $api_url = 'https://feedback.webtoffee.com/wp-json/euwithdrawalbutton/v1/uninstall';

	/**
	 * Plugin identifier sent with feedback.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	protected $plugin_id = 'euwithdrawalbutton';

	/**
	 * Auth key for the feedback API.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	protected $auth_key = 'euwithdrawalbutton_uninstall_1234#';

	/**
	 * Nonce action for AJAX requests.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	const NONCE_ACTION = 'wbte_ewb_uninstall_feedback';


	/**
	 * Constructor.
	 *
	 * @since 1.0.1
	 */
	public function __construct() {
		add_filter( 'plugin_action_links_' . WBTE_EWB_PLUGIN_BASENAME, array( $this, 'modify_deactivate_link' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_modal' ) );
		add_action( 'wp_ajax_wbte_ewb_submit_uninstall_reason', array( $this, 'send_uninstall_reason' ) );
	}

	/**
	 * Replace the default Deactivate link with one that opens the feedback modal.
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, string> $links Plugin action links.
	 * @return array<string, string>
	 */
	public function modify_deactivate_link( $links ) {
		if ( ! isset( $links['deactivate'] ) ) {
			return $links;
		}

		$deactivation_url = wp_nonce_url(
			'plugins.php?action=deactivate&amp;plugin=' . rawurlencode( WBTE_EWB_PLUGIN_BASENAME ),
			'deactivate-plugin_' . WBTE_EWB_PLUGIN_BASENAME
		);

		$links['deactivate'] = sprintf(
			'<a href="%1$s" class="wbte-ewb-deactivate-link" id="wbte-ewb-deactivate-link">%2$s</a>',
			esc_url( $deactivation_url ),
			esc_html__( 'Deactivate', 'wt-eu-withdrawal-button' )
		);

		return $links;
	}

	/**
	 * Enqueue modal assets on the Plugins screen.
	 *
	 * @since 1.0.1
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'plugins.php' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wbte-ewb-uninstall-feedback',
			WBTE_EWB_PLUGIN_URL . 'assets/css/wbte-ewb-uninstall-feedback.css',
			array(),
			WBTE_EWB_VERSION
		);

		wp_enqueue_script(
			'wbte-ewb-uninstall-feedback',
			WBTE_EWB_PLUGIN_URL . 'assets/js/wbte-ewb-uninstall-feedback.js',
			array( 'jquery' ),
			WBTE_EWB_VERSION,
			true
		);

		wp_localize_script(
			'wbte-ewb-uninstall-feedback',
			'wbteEwbUninstallFeedback',
			array(
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'processing'       => __( 'Processing...', 'wt-eu-withdrawal-button' ),
					'invalid_email'    => __( 'Please enter a valid email address.', 'wt-eu-withdrawal-button' ),
					'submit_deactivate' => __( 'Submit & Deactivate', 'wt-eu-withdrawal-button' ),
				),
			)
		);
	}

	/**
	 * Get uninstall reason options.
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_uninstall_reasons() {
		return array(
			array(
				'id'         => 'withdrawal-workflow-issue',
				'text'       => __( 'Issue with the withdrawal workflow', 'wt-eu-withdrawal-button' ),
				'type'       => 'main_reason',
				'sub_reason' => array(
					array(
						'id'          => 'guest-withdrawal',
						'text'        => __( 'Guest withdrawal', 'wt-eu-withdrawal-button' ),
						'type'        => 'textarea',
						'placeholder' => __( 'Could you tell us a bit more?', 'wt-eu-withdrawal-button' ),
					),
					array(
						'id'          => 'partial-withdrawal',
						'text'        => __( 'Partial withdrawal', 'wt-eu-withdrawal-button' ),
						'type'        => 'textarea',
						'placeholder' => __( 'Could you tell us a bit more?', 'wt-eu-withdrawal-button' ),
					),
					array(
						'id'          => 'email-notifications',
						'text'        => __( 'Email notifications', 'wt-eu-withdrawal-button' ),
						'type'        => 'textarea',
						'placeholder' => __( 'Could you tell us a bit more?', 'wt-eu-withdrawal-button' ),
					),
					array(
						'id'          => 'eu-compliance',
						'text'        => __( 'EU compliance / legal concerns', 'wt-eu-withdrawal-button' ),
						'type'        => 'textarea',
						'placeholder' => __( 'Could you tell us a bit more?', 'wt-eu-withdrawal-button' ),
					),
					array(
						'id'          => 'workflow-other',
						'text'        => __( 'Other', 'wt-eu-withdrawal-button' ),
						'type'        => 'textarea',
						'placeholder' => __( 'Could you tell us a bit more?', 'wt-eu-withdrawal-button' ),
					),
				),
			),
			array(
				'id'          => 'found-better-plugin',
				'text'        => __( 'I found a better plugin', 'wt-eu-withdrawal-button' ),
				'type'        => 'text',
				'placeholder' => __( 'Which plugin?', 'wt-eu-withdrawal-button' ),
			),
			array(
				'id'          => 'not-have-that-feature',
				'text'        => __( 'The plugin is great, but I need a specific feature that you don\'t support', 'wt-eu-withdrawal-button' ),
				'type'        => 'textarea',
				'placeholder' => __( 'Could you tell us more about that feature?', 'wt-eu-withdrawal-button' ),
			),
			array(
				'id'          => 'looking-for-other',
				'text'        => __( 'It\'s not what I was looking for', 'wt-eu-withdrawal-button' ),
				'type'        => 'textarea',
				'placeholder' => __( 'Could you tell us a bit more?', 'wt-eu-withdrawal-button' ),
			),
			array(
				'id'          => 'conflict-with-another-plugin',
				'text'        => __( 'A conflict with another plugin', 'wt-eu-withdrawal-button' ),
				'type'        => 'textarea',
				'placeholder' => __( 'Which plugin?', 'wt-eu-withdrawal-button' ),
			),
			array(
				'id'          => 'other',
				'text'        => __( 'Other', 'wt-eu-withdrawal-button' ),
				'type'        => 'textarea',
				'placeholder' => __( 'Could you tell us a bit more?', 'wt-eu-withdrawal-button' ),
			),
		);
	}

	/**
	 * Render the uninstall feedback modal markup.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public function render_modal() {
		global $pagenow;

		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		$reasons     = $this->get_uninstall_reasons();
		$support_url = 'https://wordpress.org/support/plugin/wt-eu-withdrawal-button/';
		?>
		<div class="wbte-ewb-uninstall-modal" id="wbte-ewb-uninstall-modal" aria-hidden="true">
			<div class="wbte-ewb-uninstall-modal__wrap">
				<div class="wbte-ewb-uninstall-modal__header">
					<h3><?php esc_html_e( 'If you have a moment, please let us know why you are deactivating:', 'wt-eu-withdrawal-button' ); ?></h3>
				</div>
				<div class="wbte-ewb-uninstall-modal__body">
					<ul class="wbte-ewb-uninstall-reasons">
						<?php foreach ( $reasons as $reason ) : ?>
							<li data-type="<?php echo esc_attr( $reason['type'] ); ?>" data-placeholder="<?php echo esc_attr( isset( $reason['placeholder'] ) ? $reason['placeholder'] : '' ); ?>">
								<label>
									<input type="radio" name="wbte-ewb-selected-reason" value="<?php echo esc_attr( $reason['id'] ); ?>">
									<?php echo esc_html( $reason['text'] ); ?>
								</label>
								<?php if ( 'main_reason' === $reason['type'] && ! empty( $reason['sub_reason'] ) ) : ?>
									<ul class="wbte-ewb-uninstall-sub-reasons" data-parent="<?php echo esc_attr( $reason['id'] ); ?>">
										<?php foreach ( $reason['sub_reason'] as $sub_reason ) : ?>
											<li data-type="<?php echo esc_attr( $sub_reason['type'] ); ?>" data-placeholder="<?php echo esc_attr( $sub_reason['placeholder'] ); ?>">
												<label>
													<input type="radio" name="wbte-ewb-selected-sub-reason" value="<?php echo esc_attr( $sub_reason['id'] ); ?>">
													<?php echo esc_html( $sub_reason['text'] ); ?>
												</label>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>

					<label class="wbte-ewb-uninstall-contact-label">
						<span class="wbte-ewb-uninstall-checkbox-top-border"></span>
						<input type="checkbox" id="wbte-ewb-contact-me-checkbox" name="wbte-ewb-contact-me-checkbox" value="1">
						<?php esc_html_e( 'WebToffee can contact me about this feedback.', 'wt-eu-withdrawal-button' ); ?>
					</label>
					<div id="wbte-ewb-email-field-wrap" class="wbte-ewb-uninstall-email-wrap" hidden>
						<label for="wbte-ewb-contact-email" class="wbte-ewb-uninstall-email-label">
							<?php esc_html_e( 'Enter your email address.', 'wt-eu-withdrawal-button' ); ?>
						</label>
						<input type="email" id="wbte-ewb-contact-email" name="wbte-ewb-contact-email" class="input-text wbte-ewb-uninstall-email-input" placeholder="<?php esc_attr_e( 'Enter email address', 'wt-eu-withdrawal-button' ); ?>">
						<div id="wbte-ewb-email-error" class="wbte-ewb-uninstall-email-error" hidden></div>
					</div>

					<div class="wbte-ewb-uninstall-policy">
						<?php esc_html_e( 'We do not collect any personal data when you submit this form. It\'s your feedback that we value.', 'wt-eu-withdrawal-button' ); ?>
						<a href="https://www.webtoffee.com/privacy-policy/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy Policy', 'wt-eu-withdrawal-button' ); ?></a>
					</div>
				</div>
				<div class="wbte-ewb-uninstall-modal__footer">
					<a class="button button-primary" href="<?php echo esc_url( $support_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-external"></span>
						<?php esc_html_e( 'Go to support', 'wt-eu-withdrawal-button' ); ?>
					</a>
					<button type="button" class="button button-primary wbte-ewb-uninstall-submit"><?php esc_html_e( 'Submit & Deactivate', 'wt-eu-withdrawal-button' ); ?></button>
					<button type="button" class="button button-secondary wbte-ewb-uninstall-cancel"><?php esc_html_e( 'Cancel', 'wt-eu-withdrawal-button' ); ?></button>
					<a href="#" class="wbte-ewb-uninstall-skip"><?php esc_html_e( 'I rather wouldn\'t say', 'wt-eu-withdrawal-button' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX submission of uninstall feedback.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public function send_uninstall_reason() {
		global $wpdb;

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error();
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error();
		}

		if ( ! isset( $_POST['reason_id'] ) ) {
			wp_send_json_error();
		}

		$data = array(
			'reason_id'                  => sanitize_text_field( wp_unslash( $_POST['reason_id'] ) ),
			'plugin'                     => $this->plugin_id,
			'auth'                       => $this->auth_key,
			'date'                       => gmdate( 'M d, Y h:i:s A' ),
			'url'                        => '',
			'user_email'                 => isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '',
			'reason_info'                => isset( $_REQUEST['reason_info'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['reason_info'] ) ) ) : '',
			'software'                   => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
			'php_version'                => phpversion(),
			'mysql_version'              => $wpdb->db_version(),
			'wp_version'                 => get_bloginfo( 'version' ),
			'wc_version'                 => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'locale'                     => get_locale(),
			'languages'                  => implode( ',', get_available_languages() ),
			'theme'                      => wp_get_theme()->get( 'Name' ),
			'multisite'                  => is_multisite() ? 'Yes' : 'No',
			'euwithdrawalbutton_version' => WBTE_EWB_VERSION,
		);

		wp_remote_post(
			$this->api_url,
			array(
				'method'    => 'POST',
				'timeout'   => 45,
				'blocking'  => false,
				'body'      => $data,
			)
		);

		wp_send_json_success();
	}
}
