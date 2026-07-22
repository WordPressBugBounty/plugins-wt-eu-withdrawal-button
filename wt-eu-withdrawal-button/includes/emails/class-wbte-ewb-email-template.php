<?php
/**
 * Shared inline styles for withdrawal email templates.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Email_Template
 *
 * @since 1.0.4
 */
class Wbte_Ewb_Email_Template {

	/**
	 * Two-column details table style.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public static function details_table_style() {
		return 'width:100%;border-collapse:collapse;border:1px solid #e5e5e5;table-layout:fixed;';
	}

	/**
	 * Label column style for details tables.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public static function details_th_style() {
		return 'text-align:left;padding:12px;border:1px solid #e5e5e5;width:35%;white-space:nowrap;vertical-align:top;font-weight:600;';
	}

	/**
	 * Value column style for details tables.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public static function details_td_style() {
		return 'text-align:left;padding:12px;border:1px solid #e5e5e5;width:65%;word-break:break-word;vertical-align:top;';
	}

	/**
	 * Receipt hash value style.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public static function verification_code_style() {
		return 'display:block;font-family:Consolas,Monaco,"Andale Mono","Ubuntu Mono",monospace;font-size:12px;line-height:1.5;word-break:break-all;overflow-wrap:anywhere;';
	}

	/**
	 * Items table style.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public static function items_table_style() {
		return 'width:100%;border-collapse:collapse;border:1px solid #e5e5e5;table-layout:fixed;';
	}

	/**
	 * Items table header style.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public static function items_th_style() {
		return 'text-align:left;padding:12px;border:1px solid #e5e5e5;vertical-align:top;font-weight:600;';
	}

	/**
	 * Items table cell style.
	 *
	 * @since 1.0.4
	 *
	 * @return string
	 */
	public static function items_td_style() {
		return 'text-align:left;padding:12px;border:1px solid #e5e5e5;vertical-align:top;word-break:break-word;';
	}

	/**
	 * Output a fixed-width colgroup for two-column details tables.
	 *
	 * @since 1.0.4
	 *
	 * @return void
	 */
	public static function render_details_colgroup() {
		echo '<colgroup><col width="35%" /><col width="65%" /></colgroup>';
	}
}
