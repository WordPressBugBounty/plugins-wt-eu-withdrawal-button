/**
 * Premium upgrade banner component.
 *
 * Shown when the pro addon is not active on the site.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { __ } from '@wordpress/i18n';

const PRO_UPGRADE_URL = 'https://www.webtoffee.com/product/eu-withdrawal-button/?utm_source=free_plugin&utm_medium=sidebar_cta&utm_campaign=EU_Withdarawal_Button';

const PRO_FEATURES = [
	__( 'Auto-approve withdrawal requests', 'wt-eu-withdrawal-button' ),
	__( 'Issue refund and restock inventory automatically', 'wt-eu-withdrawal-button' ),
	__( 'Show the withdrawal button only to EU customers', 'wt-eu-withdrawal-button' ),
	__( 'Generate a withdrawal terms and conditions page', 'wt-eu-withdrawal-button' ),
	__( 'Collect bank details for COD and bank transfer refunds', 'wt-eu-withdrawal-button' ),
	__( 'Open the withdrawal form in a popup', 'wt-eu-withdrawal-button' ),
	__( 'Block spam submissions with reCAPTCHA v3', 'wt-eu-withdrawal-button' ),
	__( 'Copy your settings between staging and live', 'wt-eu-withdrawal-button' ),
];

const IconCheckCircle = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#10b981" stroke="none">
		<circle cx="12" cy="12" r="12" />
		<path d="M9.5 16.5L5.5 12.5L6.91 11.09L9.5 13.67L17.09 6.08L18.5 7.5L9.5 16.5Z" fill="#fff" />
	</svg>
);

const PluginLogoIcon = () => (
	<img
		src={ ( window.wbteEwbAdmin?.plugin_url || '' ) + 'assets/images/eu-logo.svg' }
		alt={ __( 'EU Withdrawal Button', 'wt-eu-withdrawal-button' ) }
		width="48"
		height="48"
	/>
);

/** Upgrade banner shown when the pro addon is not active. */
const UpgradeBanner = () => (
	<div className="wbte-ewb-upgrade-banner">
		<div className="wbte-ewb-upgrade-banner__header">
			<span className="wbte-ewb-upgrade-banner__flag"><PluginLogoIcon /></span>
			<div>
				<strong className="wbte-ewb-upgrade-banner__title">
					{ __( 'Automate Withdrawal Workflow With Premium Upgrade', 'wt-eu-withdrawal-button' ) }
				</strong>
			</div>
		</div>
		<ul className="wbte-ewb-upgrade-banner__features">
			{ PRO_FEATURES.map( ( feature, i ) => (
				<li key={ i }>
					<IconCheckCircle />
					<span>{ feature }</span>
				</li>
			) ) }
		</ul>
		<a
			href={ PRO_UPGRADE_URL }
			target="_blank"
			rel="noopener noreferrer"
			className="wbte-ewb-upgrade-banner__cta"
		>
			{ __( 'Get Plugin Now', 'wt-eu-withdrawal-button' ) }
		</a>
	</div>
);

/**
 * Check whether the upgrade banner should be displayed.
 *
 * @return {boolean} True when the pro addon is not active.
 */
export const shouldShowUpgradeBanner = () => window.wbteEwbAdmin?.license === undefined;

export default UpgradeBanner;
