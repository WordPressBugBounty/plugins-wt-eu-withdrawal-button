<?php
/**
 * Withdrawal button template for My Account orders.
 *
 * This template is loaded via wc_get_template() so themes can
 * override it by placing a copy in:
 *   yourtheme/wbte-eu-withdrawal-button/my-account-withdrawal-button.php
 *
 * Available variables:
 *   $withdrawal_url  string  URL to the withdrawal form with order_id pre-filled.
 *   $button_label    string  Button label text.
 *   $button_class    string  CSS classes for the button element.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php
if ( ! isset( $button_label ) || '' === $button_label ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- wc_get_template variable.
	$button_label = Wbte_Ewb_Settings::get_button_label( Wbte_Ewb_Settings::LABEL_WITHDRAWAL_BUTTON ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- wc_get_template variable.
}

if ( ! isset( $button_class ) || '' === $button_class ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- wc_get_template variable.
	$button_class = 'wbte-ewb-btn wbte-ewb-btn--withdrawal button'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- wc_get_template variable.
}
?>

<a href="<?php echo esc_url( $withdrawal_url ); ?>" class="<?php echo esc_attr( $button_class ); ?>">
	<?php echo esc_html( $button_label ); ?>
</a>
