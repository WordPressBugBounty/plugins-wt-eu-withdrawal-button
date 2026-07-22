/**
 * Admin layout wrapper component with pill-style tab navigation.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { __ } from '@wordpress/i18n';
import { useLocation } from 'wouter';

const AdminLayout = ( { children } ) => {
	const [ location, navigate ] = useLocation();

	const isSettingsTab = location === '/settings';

	return (
		<div id="wbte-ewb-root" className="wbte-ewb-admin">
			<header className="wbte-ewb-admin__header">
				<div className="wbte-ewb-breadcrumb">
					<span>{ __( 'WooCommerce', 'wt-eu-withdrawal-button' ) }</span>
					<span className="wbte-ewb-breadcrumb__sep">/</span>
					<span>{ __( 'Withdrawals', 'wt-eu-withdrawal-button' ) }</span>
				</div>
				<h1 className="wbte-ewb-admin__title">
					{ __( 'Withdrawals', 'wt-eu-withdrawal-button' ) }
				</h1>
				<p className="wbte-ewb-admin__subtitle">
					{ __( 'Customer-initiated order cancellations and partial refunds.', 'wt-eu-withdrawal-button' ) }
				</p>
			</header>
			<nav className="wbte-ewb-tabs">
				<button
					type="button"
					className={ `wbte-ewb-tabs__btn${ ! isSettingsTab ? ' wbte-ewb-tabs__btn--active' : '' }` }
					onClick={ () => navigate( '/' ) }
				>
					{ __( 'Requests', 'wt-eu-withdrawal-button' ) }
				</button>
				<button
					type="button"
					className={ `wbte-ewb-tabs__btn${ isSettingsTab ? ' wbte-ewb-tabs__btn--active' : '' }` }
					onClick={ () => navigate( '/settings' ) }
				>
					{ __( 'Settings', 'wt-eu-withdrawal-button' ) }
				</button>
			</nav>
			<main className="wbte-ewb-admin__content">
				<div className="wbte-ewb-admin__page">
					{ children }
				</div>
			</main>
		</div>
	);
};

export default AdminLayout;
