/**
 * Render admin warning banners injected via wbte_ewb_admin_script_data.
 *
 * Addons push entries into `window.wbteEwbAdmin.admin_warnings`.
 * Each entry: { id, message, action, url, doc, doc_url }.
 *
 * Styled to match the ReviewBanner but with a warning color scheme.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const WarningIcon = () => (
	<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
		<path d="M12 2L1 21h22L12 2zm0 3.5L19.5 19H4.5L12 5.5zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z" />
	</svg>
);

const AdminWarnings = () => {
	const warnings = window.wbteEwbAdmin?.admin_warnings || [];
	const [ dismissed, setDismissed ] = useState( [] );

	if ( ! warnings.length ) {
		return null;
	}

	const visible = warnings.filter( ( w ) => ! dismissed.includes( w.id ) );

	if ( ! visible.length ) {
		return null;
	}

	return (
		<>
			{ visible.map( ( warning ) => (
				<div
					key={ warning.id }
					className="wbte-ewb-warning-banner"
					role="alert"
				>
					<div className="wbte-ewb-warning-banner__main">
						<div className="wbte-ewb-warning-banner__icon" aria-hidden="true">
							<WarningIcon />
						</div>
						<div className="wbte-ewb-warning-banner__content">
							<p className="wbte-ewb-warning-banner__text">
								{ warning.message }
							</p>
						</div>
					</div>
					<div className="wbte-ewb-warning-banner__actions">
						{ warning.url && (
							<a
								className="wbte-ewb-warning-banner__link"
								href={ warning.url }
							>
								{ warning.action || __( 'Fix this', 'wt-eu-withdrawal-button' ) }
							</a>
						) }
						{ warning.doc_url && (
							<a
								className="wbte-ewb-warning-banner__doc"
								href={ warning.doc_url }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ warning.doc || __( 'Documentation', 'wt-eu-withdrawal-button' ) }
							</a>
						) }
						<button
							type="button"
							className="wbte-ewb-warning-banner__dismiss"
							onClick={ () => setDismissed( ( prev ) => [ ...prev, warning.id ] ) }
							aria-label={ __( 'Dismiss warning', 'wt-eu-withdrawal-button' ) }
						>
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
				</div>
			) ) }
		</>
	);
};

export default AdminWarnings;
