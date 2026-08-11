/**
 * Settings page component.
 *
 * Fetches plugin settings from the REST API and renders an editable
 * form grouped into card sections. Saves via POST on submit.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { fetchSettings, updateSettings } from '../services/api';
import ProductSearchField from './ProductSearchField';
import CategoryExclusionField from './CategoryExclusionField';
import OrderStatusPickerField from './OrderStatusPickerField';
import EmailRecipientsField from './EmailRecipientsField';

/* -------------------------------------------------------------------------
   Helpers
   ------------------------------------------------------------------------- */

const toBool = ( value ) => value === 'yes' || value === true;
const toYesNo = ( value ) => ( value ? 'yes' : 'no' );

/* -------------------------------------------------------------------------
   Inline SVG Icons (16px viewBox, stroke-based)
   ------------------------------------------------------------------------- */

const IconCog = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<circle cx="12" cy="12" r="3" />
		<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
	</svg>
);

const IconBan = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<circle cx="12" cy="12" r="10" />
		<line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
	</svg>
);

const IconFlow = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<polyline points="16 3 21 3 21 8" />
		<line x1="4" y1="20" x2="21" y2="3" />
		<polyline points="21 16 21 21 16 21" />
		<line x1="15" y1="15" x2="21" y2="21" />
		<line x1="4" y1="4" x2="9" y2="9" />
	</svg>
);

const IconDatabase = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<ellipse cx="12" cy="5" rx="9" ry="3" />
		<path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
		<path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
	</svg>
);

const IconMail = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<rect x="2" y="4" width="20" height="16" rx="2" />
		<path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
	</svg>
);

const IconCode = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<polyline points="16 18 22 12 16 6" />
		<polyline points="8 6 2 12 8 18" />
	</svg>
);

const IconFileText = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
		<polyline points="14 2 14 8 20 8" />
		<line x1="16" y1="13" x2="8" y2="13" />
		<line x1="16" y1="17" x2="8" y2="17" />
		<polyline points="10 9 9 9 8 9" />
	</svg>
);

const IconLayout = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
		<line x1="3" y1="9" x2="21" y2="9" />
		<line x1="9" y1="21" x2="9" y2="9" />
	</svg>
);

const IconGlobe = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<circle cx="12" cy="12" r="10" />
		<line x1="2" y1="12" x2="22" y2="12" />
		<path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
	</svg>
);

const IconShield = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
	</svg>
);

const IconZap = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
	</svg>
);

const IconCreditCard = () => (
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
		<rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
		<line x1="1" y1="10" x2="23" y2="10" />
	</svg>
);

const SECTION_ICONS = {
	pro_terms_conditions: IconFileText,
	pro_form_display: IconLayout,
	pro_auto_processing: IconZap,
	pro_geo_targeting: IconGlobe,
	pro_recaptcha: IconShield,
	pro_refund_preference: IconCreditCard,
	pro_import_export: () => (
		<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
			<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
			<polyline points="7 10 12 15 17 10" />
			<line x1="12" y1="15" x2="12" y2="3" />
		</svg>
	),
};

const CheckIcon = () => (
	<svg className="wbte-ewb-chip__check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round">
		<polyline points="20 6 9 17 4 12" />
	</svg>
);

/* -------------------------------------------------------------------------
   Sub-components
   ------------------------------------------------------------------------- */

/** Custom toggle switch (38x22). */
const Toggle = ( { checked, onChange, id } ) => (
	<label className="wbte-ewb-toggle" htmlFor={ id }>
		<input
			id={ id }
			type="checkbox"
			checked={ checked }
			onChange={ ( e ) => onChange( e.target.checked ) }
		/>
		<span className="wbte-ewb-toggle__track" />
		<span className="wbte-ewb-toggle__thumb" />
	</label>
);

/** Toggle with label/description on the right. */
const ToggleRow = ( { checked, onChange, label, desc, tag, tagVariant, children, id } ) => (
	<div className="wbte-ewb-toggle-row">
		<Toggle checked={ checked } onChange={ onChange } id={ id } />
		<div className="wbte-ewb-toggle-row__text">
			<p className="wbte-ewb-toggle-row__label">
				{ label }
				{ tag && (
					<span className={ `wbte-ewb-field__tag${ tagVariant === 'danger' ? ' wbte-ewb-field__tag--danger' : '' }` }>
						{ tag }
					</span>
				) }
			</p>
			{ desc && <p className="wbte-ewb-toggle-row__desc">{ desc }</p> }
			{ children }
		</div>
	</div>
);

/** Standard non-toggle field wrapper. */
const Field = ( { label, desc, tag, children } ) => (
	<div className="wbte-ewb-field">
		{ label && (
			<p className="wbte-ewb-field__label">
				{ label }
				{ tag && <span className="wbte-ewb-field__tag">{ tag }</span> }
			</p>
		) }
		{ desc && <p className="wbte-ewb-field__desc">{ desc }</p> }
		{ children }
	</div>
);

/** Segmented control. */
const SegmentedControl = ( { options, value, onChange } ) => (
	<div className="wbte-ewb-seg">
		{ options.map( ( opt ) => (
			<button
				key={ opt.value }
				type="button"
				className={ `wbte-ewb-seg__btn${ value === opt.value ? ' wbte-ewb-seg__btn--active' : '' }` }
				onClick={ () => onChange( opt.value ) }
			>
				{ opt.label }
			</button>
		) ) }
	</div>
);

/** Chip (pill-shaped selectable item). */
const Chip = ( { selected, label, onClick } ) => (
	<button
		type="button"
		className={ `wbte-ewb-chip${ selected ? ' wbte-ewb-chip--selected' : '' }` }
		onClick={ onClick }
	>
		<span className="wbte-ewb-chip__box">
			<CheckIcon />
		</span>
		{ label }
	</button>
);

/* -------------------------------------------------------------------------
   Constants
   ------------------------------------------------------------------------- */

const PRODUCT_TYPE_OPTIONS = [
	{ value: 'virtual', label: __( 'Virtual', 'wt-eu-withdrawal-button' ) },
	{ value: 'downloadable', label: __( 'Downloadable', 'wt-eu-withdrawal-button' ) },
	{ value: 'grouped', label: __( 'Grouped', 'wt-eu-withdrawal-button' ) },
	{ value: 'external', label: __( 'External / Affiliate', 'wt-eu-withdrawal-button' ) },
];

const DISPLAY_SCOPE_OPTIONS = [
	{ value: 'global', label: __( 'Global', 'wt-eu-withdrawal-button' ) },
	{ value: 'woocommerce_only', label: __( 'WooCommerce only', 'wt-eu-withdrawal-button' ) },
];


const QUICK_DAYS = [ 2, 7, 14, 30 ];

/* -------------------------------------------------------------------------
   Main Component
   ------------------------------------------------------------------------- */

/* -------------------------------------------------------------------------
   Tab definitions
   ------------------------------------------------------------------------- */

const CORE_TABS = [
	{ key: 'general', label: __( 'General', 'wt-eu-withdrawal-button' ) },
	{ key: 'rules', label: __( 'Rules & Exclusions', 'wt-eu-withdrawal-button' ) },
	{ key: 'workflow', label: __( 'Workflow', 'wt-eu-withdrawal-button' ) },
	{ key: 'advanced', label: __( 'Advanced', 'wt-eu-withdrawal-button' ) },
];

/** License tab component. */
const LicenseTab = ( { onStatusChange } ) => {
	const licenseData = window.wbteEwbAdmin?.license || {};
	const li = licenseData.i18n || {};
	const [ key, setKey ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ msg, setMsg ] = useState( null );
	const [ status, setStatus ] = useState( licenseData.status || '' );
	const [ maskedKey, setMaskedKey ] = useState( licenseData.key || '' );
	const [ licEmail, setLicEmail ] = useState( licenseData.email || '' );

	const isActive = status === 'active';

	const handleActivate = async () => {
		if ( ! key.trim() ) {
			setMsg( { ok: false, text: li.enter_key_error || 'Please enter a license key.' } );
			return;
		}
		setBusy( true );
		setMsg( null );
		try {
			const res = await apiFetch( { path: '/wbte-ewb-pro/v1/license/activate', method: 'POST', data: { key } } );
			if ( res.success ) {
				const masked = key.substring( 0, 4 ) + '****' + key.substring( key.length - 4 );
				setStatus( 'active' );
				setMaskedKey( masked );
				setLicEmail( res.email || '' );
				setKey( '' );
				setMsg( { ok: true, text: res.message } );
				if ( window.wbteEwbAdmin?.license ) {
					window.wbteEwbAdmin.license.status = 'active';
					window.wbteEwbAdmin.license.is_active = true;
					window.wbteEwbAdmin.license.key = masked;
					window.wbteEwbAdmin.license.email = res.email || '';
				}
				if ( onStatusChange ) onStatusChange( true );
			} else {
				setMsg( { ok: false, text: res.message } );
			}
		} catch ( err ) {
			setMsg( { ok: false, text: err.message || li.activation_failed || 'Activation failed.' } );
		} finally {
			setBusy( false );
		}
	};

	const handleDeactivate = async () => {
		setBusy( true );
		setMsg( null );
		try {
			const res = await apiFetch( { path: '/wbte-ewb-pro/v1/license/deactivate', method: 'POST' } );
			setStatus( '' );
			setMaskedKey( '' );
			setLicEmail( '' );
			setMsg( { ok: true, text: res.message } );
			if ( window.wbteEwbAdmin?.license ) {
				window.wbteEwbAdmin.license.status = '';
				window.wbteEwbAdmin.license.is_active = false;
				window.wbteEwbAdmin.license.key = '';
				window.wbteEwbAdmin.license.email = '';
			}
			if ( onStatusChange ) onStatusChange( false );
		} catch ( err ) {
			setMsg( { ok: false, text: err.message || li.deactivation_failed || 'Deactivation failed.' } );
		} finally {
			setBusy( false );
		}
	};

	return (
		<div className="wbte-ewb-card">
			<div className="ch">
				<div className="ch__top">
					<span className="ch__icon"><IconShield /></span>
					<h2>{ li.license || 'License' }</h2>
				</div>
				<p className="ch__sub">
					{ li.activate_desc || 'Activate your license key to receive plugin updates and premium support.' }
				</p>
			</div>
			<div className="cb">
				{ msg && (
					<div style={ {
						padding: '10px 16px', marginBottom: '16px', borderRadius: '8px', fontSize: '13px',
						background: msg.ok ? '#ecfdf5' : '#fef2f2',
						color: msg.ok ? '#047857' : '#dc2626',
						border: `1px solid ${ msg.ok ? '#a7f3d0' : '#fca5a5' }`,
					} }>{ msg.text }</div>
				) }

				{ isActive ? (
					<div style={ { padding: '18px 22px', background: '#fafffe', borderRadius: '8px', border: '1px solid #e0f2e9' } }>
						<div style={ { display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '12px' } }>
							<div style={ { minWidth: 0 } }>
								<div style={ { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '10px' } }>
									<span style={ { width: '9px', height: '9px', borderRadius: '50%', background: '#22c55e', flexShrink: 0 } } />
									<span style={ { fontSize: '14px', fontWeight: 600, color: '#1a1a1a' } }>
										{ li.license_active || 'License Active' }
									</span>
								</div>
								<table style={ { borderCollapse: 'collapse', fontSize: '13px', color: '#374151' } }>
									<tbody>
										{ maskedKey && (
											<tr>
												<td style={ { padding: '2px 0', paddingRight: '12px', color: '#6b7280', whiteSpace: 'nowrap' } }>{ li.key_label || 'Key' }</td>
												<td style={ { padding: '2px 0', fontFamily: 'ui-monospace, monospace', fontSize: '12.5px', color: '#374151' } }>{ maskedKey }</td>
											</tr>
										) }
										{ licEmail && (
											<tr>
												<td style={ { padding: '2px 0', paddingRight: '12px', color: '#6b7280', whiteSpace: 'nowrap' } }>{ li.licensed_to || 'Licensed to' }</td>
												<td style={ { padding: '2px 0', color: '#374151' } }>{ licEmail }</td>
											</tr>
										) }
									</tbody>
								</table>
							</div>
							<button
								type="button"
								onClick={ handleDeactivate }
								disabled={ busy }
								style={ {
									padding: '6px 16px', borderRadius: '6px', border: '1px solid #e5e7eb',
									background: '#fff', color: '#9ca3af', fontWeight: 500, fontSize: '12px',
									cursor: busy ? 'wait' : 'pointer', opacity: busy ? 0.6 : 1,
									alignSelf: 'flex-start', transition: 'all 0.2s ease',
								} }
								onMouseEnter={ ( e ) => { e.currentTarget.style.color = '#ef4444'; e.currentTarget.style.borderColor = '#fca5a5'; e.currentTarget.style.background = '#fef2f2'; } }
								onMouseLeave={ ( e ) => { e.currentTarget.style.color = '#9ca3af'; e.currentTarget.style.borderColor = '#e5e7eb'; e.currentTarget.style.background = '#fff'; } }
							>
								{ li.deactivate || 'Deactivate' }
							</button>
						</div>
					</div>
				) : (
					<div style={ { padding: '18px 22px', background: '#fafafa', borderRadius: '8px', border: '1px solid #e5e7eb' } }>
						<div style={ { display: 'flex', alignItems: 'center', gap: '7px', marginBottom: '10px' } }>
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg>
							<span style={ { fontSize: '14px', fontWeight: 600, color: '#374151' } }>
								{ li.not_activated || 'License not activated' }
							</span>
						</div>
						<p style={ { margin: '0 0 14px', fontSize: '13px', color: '#6b7280', lineHeight: '1.5' } }>
							{ li.not_activated_desc || 'Enter your license key to enable automatic updates and premium support.' }
						</p>
						<div style={ { display: 'flex', gap: '8px', flexWrap: 'wrap', alignItems: 'center' } }>
							<input
								type="text"
								className="wbte-ewb-input"
								value={ key }
								onChange={ ( e ) => setKey( e.target.value ) }
								placeholder={ li.enter_key_placeholder || 'Enter license key' }
								style={ { maxWidth: '360px', flex: '1 1 200px' } }
								onKeyDown={ ( e ) => { if ( e.key === 'Enter' ) handleActivate(); } }
							/>
							<button
								type="button"
								onClick={ handleActivate }
								disabled={ busy }
								style={ {
									padding: '0 22px', borderRadius: '6px', border: 'none',
									background: '#3b54d9', color: '#fff', fontWeight: 500, fontSize: '13px',
									cursor: busy ? 'wait' : 'pointer', opacity: busy ? 0.6 : 1,
									height: '36px', whiteSpace: 'nowrap',
								} }
							>
								{ busy ? ( li.activating || 'Activating...' ) : ( li.activate || 'Activate' ) }
							</button>
						</div>
					</div>
				) }
			</div>
		</div>
	);
};

/** Import/Export settings component. */
const ImportExportSettings = () => {
	const [ busy, setBusy ] = useState( false );
	const [ msg, setMsg ] = useState( null );
	const [ importData, setImportData ] = useState( null ); // staged file data awaiting confirmation
	const [ progress, setProgress ] = useState( '' ); // status text during import
	const ajaxUrl = window.wbteEwbAdmin?.admin_url ? window.wbteEwbAdmin.admin_url + 'admin-ajax.php' : '/wp-admin/admin-ajax.php';
	const nonce = window.wbteEwbAdmin?.nonce || '';
	const currentVersion = window.wbteEwbAdmin?.version || '0.0.0';

	const compareVersions = ( a, b ) => {
		const pa = ( a || '0' ).split( '.' ).map( Number );
		const pb = ( b || '0' ).split( '.' ).map( Number );
		for ( let i = 0; i < Math.max( pa.length, pb.length ); i++ ) {
			const diff = ( pa[ i ] || 0 ) - ( pb[ i ] || 0 );
			if ( diff !== 0 ) return diff;
		}
		return 0;
	};

	const handleExport = async () => {
		setBusy( true );
		setMsg( null );
		setImportData( null );
		try {
			const body = new FormData();
			body.append( 'action', 'wbte_ewb_export_settings' );
			body.append( '_wpnonce', nonce );
			const raw = await fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body } );
			const json = await raw.json();
			if ( json.success && json.data ) {
				const blob = new Blob( [ JSON.stringify( json.data, null, 2 ) ], { type: 'application/json' } );
				const url = URL.createObjectURL( blob );
				const a = document.createElement( 'a' );
				a.href = url;
				a.download = 'ewb-settings-' + new Date().toISOString().slice( 0, 10 ) + '.json';
				a.click();
				URL.revokeObjectURL( url );
				setMsg( { ok: true, text: __( 'Settings exported successfully.', 'wt-eu-withdrawal-button' ) } );
			} else {
				throw new Error( json.data?.message || 'Export failed.' );
			}
		} catch ( err ) {
			setMsg( { ok: false, text: err.message } );
		} finally {
			setBusy( false );
		}
	};

	const handleFileSelect = async ( e ) => {
		const file = e.target.files?.[ 0 ];
		if ( ! file ) return;
		e.target.value = '';
		setMsg( null );
		setProgress( '' );

		try {
			const text = await file.text();
			const parsed = JSON.parse( text );

			if ( ! parsed.plugin || parsed.plugin !== 'wbte-ewb' ) {
				setMsg( { ok: false, text: __( 'Invalid settings file. Please select a valid export file.', 'wt-eu-withdrawal-button' ) } );
				return;
			}

			setImportData( { parsed, fileName: file.name } );
		} catch {
			setMsg( { ok: false, text: __( 'Could not read the file. Make sure it is a valid JSON file.', 'wt-eu-withdrawal-button' ) } );
		}
	};

	const confirmImport = async () => {
		if ( ! importData ) return;

		setBusy( true );
		setMsg( null );
		setProgress( __( 'Importing settings...', 'wt-eu-withdrawal-button' ) );

		try {
			const body = new FormData();
			body.append( 'action', 'wbte_ewb_import_settings' );
			body.append( '_wpnonce', nonce );
			body.append( 'settings_json', JSON.stringify( importData.parsed ) );
			const raw = await fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body } );
			const json = await raw.json();
			if ( json.success ) {
				setProgress( '' );
				setImportData( null );
				setMsg( { ok: true, text: __( 'Settings imported successfully. Reloading page...', 'wt-eu-withdrawal-button' ) } );
				setTimeout( () => window.location.reload(), 1500 );
			} else {
				throw new Error( json.data?.message || 'Import failed.' );
			}
		} catch ( err ) {
			setProgress( '' );
			setMsg( { ok: false, text: err.message } );
		} finally {
			setBusy( false );
		}
	};

	const cancelImport = () => {
		setImportData( null );
		setMsg( null );
		setProgress( '' );
	};

	const exportVersion = importData?.parsed?.version || '';
	const isOlderVersion = exportVersion && compareVersions( currentVersion, exportVersion ) < 0;

	return (
		<div style={ { padding: '8px 0' } }>
			{ msg && (
				<div style={ {
					padding: '8px 14px', marginBottom: '12px', borderRadius: '6px', fontSize: '13px',
					background: msg.ok ? 'var(--ok-soft, #ecfdf5)' : 'var(--danger-soft, #fef2f2)',
					color: msg.ok ? 'var(--ok, #047857)' : 'var(--danger, #dc2626)',
					border: `1px solid ${ msg.ok ? '#b3dcc5' : '#e8c4c0' }`,
				} }>{ msg.text }</div>
			) }

			{ progress && (
				<div style={ { padding: '10px 14px', marginBottom: '12px', borderRadius: '6px', fontSize: '13px', background: '#eff6ff', color: '#1d4ed8', border: '1px solid #bfdbfe', display: 'flex', alignItems: 'center', gap: '10px' } }>
					<span className="wbte-ewb-modal__spinner" style={ { width: '16px', height: '16px', borderWidth: '2px', display: 'inline-block', flexShrink: 0 } } />
					{ progress }
				</div>
			) }

			{ importData ? (
				<div style={ { padding: '14px 16px', background: '#f9fafb', border: '1px solid #e5e7eb', borderRadius: '8px' } }>
					<p style={ { margin: '0 0 6px', fontWeight: 600, fontSize: '14px', color: '#111827' } }>
						{ __( 'Confirm import', 'wt-eu-withdrawal-button' ) }
					</p>
					<p style={ { margin: '0 0 4px', fontSize: '13px', color: '#4b5563' } }>
						{ importData.fileName }
						{ importData.parsed.exported_at && (
							<span style={ { color: '#9ca3af', marginLeft: '8px' } }>({ importData.parsed.exported_at })</span>
						) }
					</p>
					{ exportVersion && (
						<p style={ { margin: '0 0 4px', fontSize: '12px', color: '#6b7280' } }>
							{ __( 'Export version:', 'wt-eu-withdrawal-button' ) } { exportVersion }
							{ ' — ' }
							{ __( 'Current version:', 'wt-eu-withdrawal-button' ) } { currentVersion }
						</p>
					) }

					{ isOlderVersion && (
						<div style={ { margin: '8px 0', padding: '8px 12px', borderRadius: '6px', fontSize: '12.5px', background: '#fffbeb', color: '#92400e', border: '1px solid #fde68a' } }>
							{ __( 'The export file is from a newer version. Some settings may not be compatible with your current version. Consider updating the plugin before importing.', 'wt-eu-withdrawal-button' ) }
						</div>
					) }

					<p style={ { margin: '8px 0 12px', fontSize: '12.5px', color: '#dc2626' } }>
						{ __( 'This will overwrite all current plugin settings. This action cannot be undone.', 'wt-eu-withdrawal-button' ) }
					</p>

					<div style={ { display: 'flex', gap: '10px' } }>
						<button
							type="button"
							onClick={ confirmImport }
							disabled={ busy }
							style={ {
								padding: '7px 18px', borderRadius: '6px', border: 'none',
								background: '#3b54d9', color: '#fff', fontWeight: 500, fontSize: '13px',
								cursor: busy ? 'wait' : 'pointer', opacity: busy ? 0.6 : 1,
							} }
						>
							{ __( 'Confirm import', 'wt-eu-withdrawal-button' ) }
						</button>
						<button
							type="button"
							onClick={ cancelImport }
							disabled={ busy }
							style={ {
								padding: '7px 18px', borderRadius: '6px', border: '1px solid #d1d5db',
								background: '#fff', color: '#374151', fontWeight: 500, fontSize: '13px',
								cursor: 'pointer',
							} }
						>
							{ __( 'Cancel', 'wt-eu-withdrawal-button' ) }
						</button>
					</div>
				</div>
			) : (
				<>
					<div style={ { display: 'flex', gap: '12px', flexWrap: 'wrap' } }>
						<button
							type="button"
							onClick={ handleExport }
							disabled={ busy }
							style={ {
								padding: '8px 20px', borderRadius: '6px', border: '1px solid #d1d5db',
								background: '#fff', color: '#374151', fontWeight: 500, fontSize: '13px',
								cursor: busy ? 'wait' : 'pointer', opacity: busy ? 0.6 : 1,
							} }
						>
							{ __( 'Export settings', 'wt-eu-withdrawal-button' ) }
						</button>
						<label style={ {
							padding: '8px 20px', borderRadius: '6px', border: '1px solid #d1d5db',
							background: '#fff', color: '#374151', fontWeight: 500, fontSize: '13px',
							cursor: busy ? 'wait' : 'pointer', opacity: busy ? 0.6 : 1, display: 'inline-block',
						} }>
							{ __( 'Import settings', 'wt-eu-withdrawal-button' ) }
							<input type="file" accept=".json" onChange={ handleFileSelect } disabled={ busy } style={ { display: 'none' } } />
						</label>
					</div>
					<p style={ { margin: '8px 0 0', fontSize: '12px', color: '#9ca3af' } }>
						{ __( 'Export all plugin settings as a JSON file, or import settings from a previously exported file.', 'wt-eu-withdrawal-button' ) }
					</p>
				</>
			) }
		</div>
	);
};

/** Terms & Conditions page manager component. */
const TermsPageManager = () => {
	const initData = ( window.wbteEwbAdmin && window.wbteEwbAdmin.terms_page ) || {};
	const [ pageData, setPageData ] = useState( initData );
	const [ busy, setBusy ] = useState( false );
	const [ msg, setMsg ] = useState( null );

	const ajaxUrl = window.wbteEwbAdmin?.admin_url ? window.wbteEwbAdmin.admin_url + 'admin-ajax.php' : '/wp-admin/admin-ajax.php';
	const nonce = window.wbteEwbAdmin?.nonce || '';

	const generate = async () => {
		setBusy( true );
		setMsg( null );
		try {
			const body = new FormData();
			body.append( 'action', 'wbte_ewb_generate_terms_page' );
			body.append( '_wpnonce', nonce );
			const raw = await fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body } );
			const json = await raw.json();
			if ( json.success ) {
				setPageData( json.data );
				setMsg( { ok: true, text: json.data.message || __( 'Page created.', 'wt-eu-withdrawal-button' ) } );
			} else {
				throw new Error( json.data?.message || __( 'Failed to create page.', 'wt-eu-withdrawal-button' ) );
			}
		} catch ( err ) {
			setMsg( { ok: false, text: err.message || __( 'Failed to create page.', 'wt-eu-withdrawal-button' ) } );
		} finally {
			setBusy( false );
		}
	};

	const remove = async () => {
		if ( ! window.confirm( __( 'Are you sure you want to delete the Terms & Conditions page?', 'wt-eu-withdrawal-button' ) ) ) {
			return;
		}
		setBusy( true );
		setMsg( null );
		try {
			const body = new FormData();
			body.append( 'action', 'wbte_ewb_delete_terms_page' );
			body.append( '_wpnonce', nonce );
			const raw = await fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body } );
			const json = await raw.json();
			if ( json.success ) {
				setPageData( json.data );
				setMsg( { ok: true, text: json.data.message || __( 'Page deleted.', 'wt-eu-withdrawal-button' ) } );
			} else {
				throw new Error( json.data?.message || __( 'Failed to delete page.', 'wt-eu-withdrawal-button' ) );
			}
		} catch ( err ) {
			setMsg( { ok: false, text: err.message || __( 'Failed to delete page.', 'wt-eu-withdrawal-button' ) } );
		} finally {
			setBusy( false );
		}
	};

	const exists = pageData && pageData.exists;

	return (
		<div className="wbte-ewb-field" style={ { padding: '16px 0 8px' } }>
			{ msg && (
				<div style={ {
					padding: '8px 14px',
					marginBottom: '12px',
					borderRadius: '6px',
					fontSize: '13px',
					background: msg.ok ? 'var(--ok-soft, #ecfdf5)' : 'var(--danger-soft, #fef2f2)',
					color: msg.ok ? 'var(--ok, #047857)' : 'var(--danger, #dc2626)',
					border: `1px solid ${ msg.ok ? '#b3dcc5' : '#e8c4c0' }`,
				} }>
					{ msg.text }
				</div>
			) }
			{ ! exists ? (
				<div>
					<p className="wbte-ewb-field__desc" style={ { marginBottom: '12px' } }>
						{ __( 'Generate a withdrawal Terms & Conditions page based on EU Directive 2011/83/EU. The page includes dynamic shortcodes for excluded products, categories, and product types that stay in sync with your settings.', 'wt-eu-withdrawal-button' ) }
					</p>
					<button
						type="button"
						className="wbte-ewb-btn wbte-ewb-btn--primary"
						onClick={ generate }
						disabled={ busy }
						style={ {
							padding: '8px 20px',
							borderRadius: '6px',
							border: 'none',
							background: '#3b54d9',
							color: '#fff',
							fontWeight: 500,
							fontSize: '13px',
							cursor: busy ? 'wait' : 'pointer',
							opacity: busy ? 0.6 : 1,
						} }
					>
						{ busy ? __( 'Creating…', 'wt-eu-withdrawal-button' ) : __( 'Generate T&C page', 'wt-eu-withdrawal-button' ) }
					</button>
				</div>
			) : (
				<div>
					<div style={ {
						padding: '16px 20px',
						background: '#f9fafb',
						borderRadius: '8px',
						border: '1px solid #e5e7eb',
						marginBottom: '12px',
					} }>
						<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }>
							<div>
								<p style={ { margin: '0 0 4px', fontWeight: 600, fontSize: '14px', color: '#111827' } }>
									{ pageData.title || __( 'Withdrawal Terms & Conditions', 'wt-eu-withdrawal-button' ) }
								</p>
								<p style={ { margin: 0, fontSize: '12px', color: '#6b7280' } }>
									{ __( 'Published', 'wt-eu-withdrawal-button' ) }
								</p>
							</div>
							<div style={ { display: 'flex', gap: '8px' } }>
								{ pageData.view_url && (
									<a
										href={ pageData.view_url }
										target="_blank"
										rel="noopener noreferrer"
										style={ {
											padding: '6px 14px',
											borderRadius: '5px',
											border: '1px solid #d1d5db',
											background: '#fff',
											color: '#374151',
											fontSize: '12px',
											fontWeight: 500,
											textDecoration: 'none',
											cursor: 'pointer',
										} }
									>
										{ __( 'Preview', 'wt-eu-withdrawal-button' ) }
									</a>
								) }
								{ pageData.edit_url && (
									<a
										href={ pageData.edit_url }
										target="_blank"
										rel="noopener noreferrer"
										style={ {
											padding: '6px 14px',
											borderRadius: '5px',
											border: '1px solid #d1d5db',
											background: '#fff',
											color: '#374151',
											fontSize: '12px',
											fontWeight: 500,
											textDecoration: 'none',
											cursor: 'pointer',
										} }
									>
										{ __( 'Edit page', 'wt-eu-withdrawal-button' ) }
									</a>
								) }
								<button
									type="button"
									onClick={ remove }
									disabled={ busy }
									style={ {
										padding: '6px 14px',
										borderRadius: '5px',
										border: '1px solid #fca5a5',
										background: '#fff',
										color: '#dc2626',
										fontSize: '12px',
										fontWeight: 500,
										cursor: busy ? 'wait' : 'pointer',
										opacity: busy ? 0.6 : 1,
									} }
								>
									{ __( 'Delete', 'wt-eu-withdrawal-button' ) }
								</button>
							</div>
						</div>
					</div>
					<p className="wbte-ewb-field__desc" style={ { fontSize: '12px', color: '#9ca3af' } }>
						{ __( 'Available shortcodes: [wbte_ewb_excluded_product_types], [wbte_ewb_excluded_categories], [wbte_ewb_excluded_products], [wbte_ewb_withdrawal_period], [wbte_ewb_store_name], [wbte_ewb_store_email]', 'wt-eu-withdrawal-button' ) }
					</p>
				</div>
			) }
		</div>
	);
};

const SettingsPage = () => {
	const [ settings, setSettings ] = useState( null );
	const [ savedSnapshot, setSavedSnapshot ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ pages, setPages ] = useState( [] );
	const [ categories, setCategories ] = useState( [] );
	const [ activeTab, setActiveTab ] = useState( 'general' );
	const [ customizeTab, setCustomizeTab ] = useState( 'footer' );
	const [ licenseActive, setLicenseActive ] = useState( window.wbteEwbAdmin?.license?.is_active || false );
	const justSaved = useRef( false );

	const orderStatuses = ( window.wbteEwbAdmin && window.wbteEwbAdmin.order_statuses ) || {};
	const orderStatusOptions = Object.entries( orderStatuses ).map( ( [ value, label ] ) => ( {
		value,
		label,
	} ) );

	/* --- Load settings on mount --- */
	useEffect( () => {
		const load = async () => {
			try {
				const [ settingsData, pagesData, categoriesData ] = await Promise.all( [
					fetchSettings(),
					apiFetch( { path: '/wp/v2/pages?per_page=100&_fields=id,title' } ),
					apiFetch( { path: '/wbte-ewb/v1/lookups/product-categories' } ),
				] );

				const data = settingsData.data || settingsData;
				const storeEmail = ( window.wbteEwbAdmin && window.wbteEwbAdmin.default_store_email ) || '';

				if (
					! Array.isArray( data.withdrawal_period_start_statuses )
					|| ! data.withdrawal_period_start_statuses.length
				) {
					const legacyStatus = data.withdrawal_period_start_status || 'order_created';
					data.withdrawal_period_start_statuses = [ legacyStatus ];
				}

				if (
					storeEmail
					&& ( ! Array.isArray( data.admin_notification_recipients ) || ! data.admin_notification_recipients.length )
				) {
					data.admin_notification_recipients = [ storeEmail ];
				}

				if ( storeEmail && ! data.customer_contact_email ) {
					data.customer_contact_email = storeEmail;
				}

				setSettings( data );
				setSavedSnapshot( JSON.stringify( data ) );

				const pageOptions = [
					{ value: '0', label: __( '-- Select a page --', 'wt-eu-withdrawal-button' ) },
					...( pagesData || [] ).map( ( p ) => ( {
						value: String( p.id ),
						label: p.title.rendered || p.title.raw || `Page #${ p.id }`,
					} ) ),
				];
				setPages( pageOptions );

				const categoryItems = categoriesData.data || categoriesData || [];
				setCategories( categoryItems );
			} catch ( err ) {
				setNotice( {
					status: 'error',
					message: err.message || __( 'Failed to load settings.', 'wt-eu-withdrawal-button' ),
				} );
			} finally {
				setLoading( false );
			}
		};

		load();
	}, [] );

	/* --- Dirty detection --- */
	const isDirty = settings && savedSnapshot ? JSON.stringify( settings ) !== savedSnapshot : false;

	/* --- Field updater --- */
	const updateField = useCallback( ( key, value ) => {
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}, [] );

	/* --- Toggle array item --- */
	const toggleArrayItem = ( key, item ) => {
		setSettings( ( prev ) => {
			const current = Array.isArray( prev[ key ] ) ? [ ...prev[ key ] ] : [];
			const index = current.indexOf( item );
			if ( index > -1 ) {
				current.splice( index, 1 );
			} else {
				current.push( item );
			}
			return { ...prev, [ key ]: current };
		} );
	};

	/* --- Save --- */
	const handleSave = async () => {
		const recipients = Array.isArray( settings.admin_notification_recipients )
			? settings.admin_notification_recipients.filter( Boolean )
			: [];

		if ( ! recipients.length ) {
			setNotice( {
				status: 'error',
				message: __( 'Add at least one admin notification recipient before saving.', 'wt-eu-withdrawal-button' ),
			} );
			return;
		}

		setSaving( true );
		setNotice( null );

		try {
			await updateSettings( settings );
			setSavedSnapshot( JSON.stringify( settings ) );
			justSaved.current = true;
			setNotice( {
				status: 'success',
				message: __( 'Settings saved successfully.', 'wt-eu-withdrawal-button' ),
			} );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message: err.message || __( 'Failed to save settings.', 'wt-eu-withdrawal-button' ),
			} );
		} finally {
			setSaving( false );
		}
	};

	/* --- Discard --- */
	const handleDiscard = () => {
		if ( savedSnapshot ) {
			setSettings( JSON.parse( savedSnapshot ) );
		}
		setNotice( null );
	};

	/* --- Loading state --- */
	if ( loading ) {
		return (
			<div className="wbte-ewb-loading">
				<Spinner />
				<p>{ __( 'Loading settings...', 'wt-eu-withdrawal-button' ) }</p>
			</div>
		);
	}

	/* --- Error state --- */
	if ( ! settings ) {
		return (
			<div className="wbte-ewb-error">
				{ __( 'Unable to load settings.', 'wt-eu-withdrawal-button' ) }
			</div>
		);
	}

	const excludedTypes = Array.isArray( settings.excluded_product_types )
		? settings.excluded_product_types
		: [];
	const periodStartStatuses = Array.isArray( settings.withdrawal_period_start_statuses )
		? settings.withdrawal_period_start_statuses
		: [ 'order_created' ];
	const periodStartOptions = [
		{ value: 'order_created', label: __( 'Order created', 'wt-eu-withdrawal-button' ) },
		...orderStatusOptions,
	];
	const excludedCategories = Array.isArray( settings.excluded_categories )
		? settings.excluded_categories.map( ( id ) => parseInt( id, 10 ) )
		: [];
	const excludedProducts = Array.isArray( settings.excluded_products )
		? settings.excluded_products.map( ( id ) => parseInt( id, 10 ) ).filter( Boolean )
		: [];
	const adminNotificationRecipients = Array.isArray( settings.admin_notification_recipients )
		? settings.admin_notification_recipients.filter( Boolean )
		: [];
	const hasValidRecipients = adminNotificationRecipients.length > 0;
	const canSave = isDirty && hasValidRecipients;

	/* --- Render the combined appearance customizer block --- */
	const renderAppearanceCustomizer = () => {
		const footerSection = extraSections.find( ( s ) => s.id === 'pro_footer_link_inline' );
		const myaccountSection = extraSections.find( ( s ) => s.id === 'pro_myaccount_inline' );

		if ( ! footerSection && ! myaccountSection ) return null;

		const myaccountToggle = myaccountSection ? ( myaccountSection.fields || [] ).find( ( f ) => f.key === 'show_myaccount_button' ) : null;

		// Current tab config.
		const isFooter = customizeTab === 'footer';
		const prefix = isFooter ? 'footer_link' : 'myaccount_button';
		const textKey = isFooter ? 'footer_link_text' : 'my_account_order_button_text';
		const textPlaceholder = 'Request Withdrawal';
		const displayTypeKey = prefix + '_display_type';
		const colorModeKey = prefix + '_color_mode';
		const defaultDisplayType = isFooter ? 'link' : 'button';

		// Separate text color keys for button vs link display.
		const displayType_ = settings[ displayTypeKey ] || defaultDisplayType;
		const textColorKey = ( isFooter && displayType_ === 'button' ) ? 'footer_button_text_color'
			: ( isFooter ? 'footer_link_text_color'
			: ( displayType_ === 'link' ? 'myaccount_link_text_color' : 'myaccount_button_text_color' ) );
		const hoverTextColorKey = ( isFooter && displayType_ === 'button' ) ? 'footer_button_hover_text_color'
			: ( isFooter ? 'footer_link_hover_text_color'
			: ( displayType_ === 'link' ? 'myaccount_link_hover_text_color' : 'myaccount_button_hover_text_color' ) );

		// Disabled when the parent toggle is off.
		const isDisabled = isFooter
			? ! toBool( settings.embed_footer_link )
			: ! toBool( settings.show_myaccount_button );

		const displayType = settings[ displayTypeKey ] || defaultDisplayType;
		const colorMode = settings[ colorModeKey ] || 'theme';
		const isBtn = displayType === 'button';
		const isCustom = colorMode === 'custom';
		const previewText = settings[ textKey ] || textPlaceholder;

		// Use theme colors for "Theme default" mode, custom colors for "Custom" mode.
		const tc = window.wbteEwbAdmin?.theme_colors || {};
		const themeLinkColor = tc.link_color || '#3b54d9';
		const themeBtnBg = tc.button_bg || '#3b54d9';
		const themeBtnText = tc.button_color || '#ffffff';
		const linkDefaultColor = '#1d2327';

		// For links with custom colors, avoid white-on-white by checking lightness.
		const savedTextColor = settings[ textColorKey ] || '';
		const isLightColor = ( c ) => {
			if ( ! c || c.length < 4 ) return false;
			const hex = c.replace( '#', '' );
			const r = parseInt( hex.substring( 0, 2 ), 16 ) || 0;
			const g = parseInt( hex.substring( 2, 4 ), 16 ) || 0;
			const b = parseInt( hex.substring( 4, 6 ), 16 ) || 0;
			return ( r * 0.299 + g * 0.587 + b * 0.114 ) > 220;
		};
		const textColor = isCustom
			? ( isBtn
				? ( savedTextColor || '#ffffff' )
				: ( savedTextColor && ! isLightColor( savedTextColor ) ? savedTextColor : linkDefaultColor ) )
			: ( isBtn ? themeBtnText : themeLinkColor );
		const bgColor = isCustom
			? ( settings[ prefix + '_bg_color' ] || themeBtnBg )
			: themeBtnBg;

		const pStyle = isBtn
			? { display: 'inline-block', padding: '10px 24px', borderRadius: '6px', textDecoration: 'none', fontWeight: 500, background: bgColor, color: textColor }
			: { textDecoration: 'underline', color: textColor };

		const isAppearanceLocked = ! licenseActive && window.wbteEwbAdmin?.license !== undefined;

		return (
			<>
				{ myaccountToggle && (
					<ToggleRow
						id="show_myaccount_button"
						checked={ toBool( settings.show_myaccount_button ) }
						onChange={ ( val ) => updateField( 'show_myaccount_button', toYesNo( val ) ) }
						label={ myaccountToggle.label }
						desc={ myaccountToggle.desc }
					/>
				) }

				<div style={ { background: '#fafafa', borderRadius: '10px', border: '1px solid #e5e7eb', padding: '18px 20px', marginTop: '4px', position: isAppearanceLocked ? 'relative' : 'static' } }>
					{ isAppearanceLocked && (
						<div
							onClick={ () => setActiveTab( 'license' ) }
							style={ {
							position: 'absolute', top: 0, left: 0, right: 0, bottom: 0,
							background: 'rgba(255,255,255,0.8)', zIndex: 10,
							display: 'flex', alignItems: 'center', justifyContent: 'center',
							borderRadius: '10px', cursor: 'pointer',
						} }>
							<div style={ {
								textAlign: 'center', padding: '16px 24px',
								background: '#fff', borderRadius: '8px',
								boxShadow: '0 2px 8px rgba(0,0,0,0.08)',
								maxWidth: '300px',
							} }>
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={ { marginBottom: '4px' } }><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
								<p style={ { fontWeight: 600, fontSize: '13px', margin: '4px 0 2px', color: '#374151' } }>
									{ window.wbteEwbAdmin?.license?.i18n?.license_required || 'License Required' }
								</p>
								<p style={ { fontSize: '12px', color: '#6b7280', margin: 0 } }>
									{ window.wbteEwbAdmin?.license?.i18n?.activate_to_use || 'Activate your license key to use this feature.' }
								</p>
							</div>
						</div>
					) }
					<p className="wbte-ewb-field__label" style={ { margin: '0 0 8px', fontSize: '13.5px' } }>{ __( 'Customize appearance', 'wt-eu-withdrawal-button' ) }</p>
					<div style={ { marginBottom: '16px' } }>
						<SegmentedControl
							options={ [
								{ value: 'footer', label: __( 'Footer', 'wt-eu-withdrawal-button' ) },
								{ value: 'myaccount', label: __( 'My account page', 'wt-eu-withdrawal-button' ) },
							] }
							value={ customizeTab }
							onChange={ setCustomizeTab }
						/>
					</div>

					{ isDisabled && (
						<p style={ { fontSize: '13px', color: '#9ca3af', fontStyle: 'italic', margin: '0 0 12px' } }>
							{ isFooter
								? __( 'Enable "Show withdrawal button in footer" above to customize.', 'wt-eu-withdrawal-button' )
								: __( 'Enable "Show withdrawal button in My Account" above to customize.', 'wt-eu-withdrawal-button' )
							}
						</p>
					) }

					<div style={ { display: 'flex', flexWrap: 'wrap', gap: '14px', alignItems: 'start', opacity: isDisabled ? 0.35 : 1, pointerEvents: isDisabled ? 'none' : 'auto', transition: 'opacity .15s' } }>
						<div style={ { flex: '1 1 140px', minWidth: '140px' } }>
							<p style={ { fontWeight: 500, fontSize: '12px', margin: '0 0 5px', color: '#374151', textTransform: 'uppercase', letterSpacing: '0.3px' } }>{ isFooter ? __( 'Link text', 'wt-eu-withdrawal-button' ) : __( 'Button text', 'wt-eu-withdrawal-button' ) }</p>
							<input
								type="text"
								className="wbte-ewb-input"
								value={ settings[ textKey ] || '' }
								onChange={ ( e ) => updateField( textKey, e.target.value ) }
								placeholder={ textPlaceholder }
								style={ { width: '100%' } }
							/>
						</div>
						<div style={ { flex: '0 0 auto' } }>
							<p style={ { fontWeight: 500, fontSize: '12px', margin: '0 0 5px', color: '#374151', textTransform: 'uppercase', letterSpacing: '0.3px' } }>{ __( 'Display as', 'wt-eu-withdrawal-button' ) }</p>
							<SegmentedControl
								options={ [
									{ value: 'link', label: __( 'Link', 'wt-eu-withdrawal-button' ) },
									{ value: 'button', label: __( 'Button', 'wt-eu-withdrawal-button' ) },
								] }
								value={ displayType }
								onChange={ ( val ) => updateField( displayTypeKey, val ) }
							/>
						</div>
						<div style={ { flex: '0 0 auto' } }>
							<p style={ { fontWeight: 500, fontSize: '12px', margin: '0 0 5px', color: '#374151', textTransform: 'uppercase', letterSpacing: '0.3px' } }>{ __( 'Colors', 'wt-eu-withdrawal-button' ) }</p>
							<SegmentedControl
								options={ [ { value: 'theme', label: __( 'Theme default', 'wt-eu-withdrawal-button' ) }, { value: 'custom', label: __( 'Custom', 'wt-eu-withdrawal-button' ) } ] }
								value={ colorMode }
								onChange={ ( val ) => updateField( colorModeKey, val ) }
							/>
						</div>
						<div style={ { flex: '1 1 180px', minWidth: '180px', padding: '10px 14px', background: '#fff', borderRadius: '8px', border: '1px solid #e5e7eb', textAlign: 'center', minHeight: '60px', display: 'flex', flexDirection: 'column', justifyContent: 'center', alignItems: 'center' } }>
							<p style={ { margin: '0 0 6px', fontSize: '10px', fontWeight: 600, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.5px' } }>Preview</p>
							<span style={ pStyle }>{ previewText }</span>
						</div>
					</div>

				{ isCustom && ! isDisabled && ( () => {
					const defaultTextColor = isBtn ? '#ffffff' : linkDefaultColor;
					const defaultHoverColor = isBtn ? '#ffffff' : '#3b54d9';
					const effectiveText = settings[ textColorKey ] && ! ( ! isBtn && isLightColor( settings[ textColorKey ] ) ) ? settings[ textColorKey ] : defaultTextColor;
					const effectiveHover = settings[ hoverTextColorKey ] && ! ( ! isBtn && isLightColor( settings[ hoverTextColorKey ] ) ) ? settings[ hoverTextColorKey ] : defaultHoverColor;

					return (
					<div style={ { display: 'flex', flexWrap: 'wrap', gap: '16px', marginTop: '12px' } }>
						{ isBtn && (
							<div>
								<p style={ { fontSize: '12px', fontWeight: 500, margin: '0 0 4px', color: '#374151' } }>{ __( 'Background', 'wt-eu-withdrawal-button' ) }</p>
								<div style={ { display: 'flex', alignItems: 'center', gap: '6px' } }>
									<input type="color" value={ settings[ prefix + '_bg_color' ] || '#3b54d9' } onChange={ ( e ) => updateField( prefix + '_bg_color', e.target.value ) } style={ { width: '40px', height: '40px', padding: '2px', border: '1px solid #ccc', borderRadius: '4px', cursor: 'pointer', boxSizing: 'border-box' } } />
									<input type="text" className="wbte-ewb-input" value={ settings[ prefix + '_bg_color' ] || '#3b54d9' } onChange={ ( e ) => updateField( prefix + '_bg_color', e.target.value ) } style={ { width: '80px', height: '40px', fontFamily: 'monospace', fontSize: '12px', boxSizing: 'border-box' } } />
								</div>
							</div>
						) }
						<div>
							<p style={ { fontSize: '12px', fontWeight: 500, margin: '0 0 4px', color: '#374151' } }>{ __( 'Text', 'wt-eu-withdrawal-button' ) }</p>
							<div style={ { display: 'flex', alignItems: 'center', gap: '6px' } }>
								<input type="color" value={ effectiveText } onChange={ ( e ) => updateField( textColorKey, e.target.value ) } style={ { width: '40px', height: '40px', padding: '2px', border: '1px solid #ccc', borderRadius: '4px', cursor: 'pointer', boxSizing: 'border-box' } } />
								<input type="text" className="wbte-ewb-input" value={ effectiveText } onChange={ ( e ) => updateField( textColorKey, e.target.value ) } style={ { width: '80px', height: '40px', fontFamily: 'monospace', fontSize: '12px', boxSizing: 'border-box' } } />
							</div>
						</div>
						{ isBtn && (
							<div>
								<p style={ { fontSize: '12px', fontWeight: 500, margin: '0 0 4px', color: '#374151' } }>{ __( 'Hover bg', 'wt-eu-withdrawal-button' ) }</p>
								<div style={ { display: 'flex', alignItems: 'center', gap: '6px' } }>
									<input type="color" value={ settings[ prefix + '_hover_bg_color' ] || '#2d43b5' } onChange={ ( e ) => updateField( prefix + '_hover_bg_color', e.target.value ) } style={ { width: '40px', height: '40px', padding: '2px', border: '1px solid #ccc', borderRadius: '4px', cursor: 'pointer', boxSizing: 'border-box' } } />
									<input type="text" className="wbte-ewb-input" value={ settings[ prefix + '_hover_bg_color' ] || '#2d43b5' } onChange={ ( e ) => updateField( prefix + '_hover_bg_color', e.target.value ) } style={ { width: '80px', height: '40px', fontFamily: 'monospace', fontSize: '12px', boxSizing: 'border-box' } } />
								</div>
							</div>
						) }
						<div>
							<p style={ { fontSize: '12px', fontWeight: 500, margin: '0 0 4px', color: '#374151' } }>{ __( 'Hover text', 'wt-eu-withdrawal-button' ) }</p>
							<div style={ { display: 'flex', alignItems: 'center', gap: '6px' } }>
								<input type="color" value={ effectiveHover } onChange={ ( e ) => updateField( hoverTextColorKey, e.target.value ) } style={ { width: '40px', height: '40px', padding: '2px', border: '1px solid #ccc', borderRadius: '4px', cursor: 'pointer', boxSizing: 'border-box' } } />
								<input type="text" className="wbte-ewb-input" value={ effectiveHover } onChange={ ( e ) => updateField( hoverTextColorKey, e.target.value ) } style={ { width: '80px', height: '40px', fontFamily: 'monospace', fontSize: '12px', boxSizing: 'border-box' } } />
							</div>
						</div>
					</div>
					);
				} )() }
				</div>
			</>
		);
	};

	/* --- Render an extra section (addon-provided) --- */
	const renderExtraSection = ( section ) => {
		const SectionIcon = SECTION_ICONS[ section.id ] || IconCog;
		const isLocked = ! licenseActive && window.wbteEwbAdmin?.license !== undefined;
		return (
		<div className="wbte-ewb-card" key={ section.id } style={ isLocked ? { position: 'relative', overflow: 'hidden' } : {} }>
			{ isLocked && (
				<div
					onClick={ () => setActiveTab( 'license' ) }
					style={ {
					position: 'absolute', top: 0, left: 0, right: 0, bottom: 0,
					background: 'rgba(255,255,255,0.8)', zIndex: 10,
					display: 'flex', alignItems: 'center', justifyContent: 'center',
					borderRadius: '12px', cursor: 'pointer',
				} }>
					<div style={ {
						textAlign: 'center', padding: '10px 20px',
					} }>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
						<p style={ { fontWeight: 600, fontSize: '13px', margin: '4px 0 2px', color: '#374151' } }>
							{ window.wbteEwbAdmin?.license?.i18n?.license_required || 'License Required' }
						</p>
						<p style={ { fontSize: '12px', color: '#6b7280', margin: 0 } }>
							{ window.wbteEwbAdmin?.license?.i18n?.activate_to_use || 'Activate your license key to use this feature.' }
						</p>
					</div>
				</div>
			) }
			<div className="ch">
				<div className="ch__top" style={ section.header_toggle ? { justifyContent: 'space-between' } : {} }>
					<span style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
						<span className="ch__icon"><SectionIcon /></span>
						<h2 style={ { margin: 0 } }>
							{ section.title }
							{ section.badge && (
								<span style={ {
									display: 'inline-block',
									marginLeft: '8px',
									padding: '2px 8px',
									fontSize: '11px',
									fontWeight: 600,
									color: '#fff',
									background: '#7b2cf5',
									borderRadius: '3px',
									verticalAlign: 'middle',
									textTransform: 'uppercase',
								} }>{ section.badge }</span>
							) }
						</h2>
					</span>
					{ section.header_toggle && (
						<Toggle
							id={ section.header_toggle }
							checked={ toBool( settings[ section.header_toggle ] ) }
							onChange={ ( val ) => updateField( section.header_toggle, toYesNo( val ) ) }
						/>
					) }
				</div>
				{ section.desc && <p className="ch__sub">{ section.desc }</p> }
			</div>
			<div className="cb">
				{ ( section.fields || [] ).map( ( field ) => {
					if ( field.show_if ) {
						const depVal = settings[ field.show_if.key ];
						if ( field.show_if.value !== undefined && depVal !== field.show_if.value ) return null;
						if ( field.show_if.truthy && ! toBool( depVal ) ) return null;
					}
					if ( field.show_if_also ) {
						const depVal2 = settings[ field.show_if_also.key ];
						if ( field.show_if_also.value !== undefined && depVal2 !== field.show_if_also.value ) return null;
						if ( field.show_if_also.truthy && ! toBool( depVal2 ) ) return null;
					}
					if ( field.type === 'heading' ) {
						return ( <p key={ field.key } style={ { fontWeight: 600, fontSize: '14px', margin: '20px 0 4px', paddingTop: '16px', borderTop: '2px solid #e5e7eb' } }>{ field.label }</p> );
					}
					if ( field.type === 'preview' ) {
						const previewType = field.preview_type || '';
						let previewStyle = {};
						let previewText = 'Request Withdrawal';
						if ( previewType === 'footer_link' ) {
							previewText = settings.footer_link_text || 'Request Withdrawal';
							const isBtn = settings.footer_link_display_type === 'button';
							const isCustom = settings.footer_link_color_mode === 'custom';
							previewStyle = isBtn
								? { display: 'inline-block', padding: '10px 24px', borderRadius: '6px', textDecoration: 'none', fontWeight: 500, background: isCustom ? ( settings.footer_link_bg_color || '#3b54d9' ) : '#3b54d9', color: isCustom ? ( settings.footer_link_text_color || '#fff' ) : '#fff' }
								: { textDecoration: 'underline', color: isCustom ? ( settings.footer_link_text_color || '#3b54d9' ) : '#3b54d9' };
						} else if ( previewType === 'myaccount_button' ) {
							previewText = settings.my_account_order_button_text || 'Request Withdrawal';
							const isBtn = settings.myaccount_button_display_type !== 'link';
							const isCustom = settings.myaccount_button_color_mode === 'custom';
							previewStyle = isBtn
								? { display: 'inline-block', padding: '8px 18px', borderRadius: '5px', textDecoration: 'none', fontWeight: 500, fontSize: '13px', border: 'none', background: isCustom ? ( settings.myaccount_button_bg_color || '#3b54d9' ) : '#3b54d9', color: isCustom ? ( settings.myaccount_button_text_color || '#fff' ) : '#fff' }
								: { textDecoration: 'underline', color: isCustom ? ( settings.myaccount_button_text_color || '#3b54d9' ) : '#3b54d9', background: 'none', border: 'none', padding: 0, fontSize: '13px' };
						}
						return (
							<div key={ field.key } style={ { margin: '16px 0 8px', padding: '16px 20px', background: '#f9fafb', borderRadius: '8px', border: '1px dashed #d1d5db' } }>
								<p style={ { margin: '0 0 8px', fontSize: '12px', fontWeight: 600, color: '#6b7280', textTransform: 'uppercase', letterSpacing: '0.5px' } }>Preview</p>
								<span style={ previewStyle }>{ previewText }</span>
							</div>
						);
					}
					if ( field.type === 'warning' ) {
						return (
							<div key={ field.key } className="wbte-ewb-warning-banner" role="alert" style={ { margin: '12px 0' } }>
								<div className="wbte-ewb-warning-banner__main">
									<div className="wbte-ewb-warning-banner__icon" aria-hidden="true">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L1 21h22L12 2zm0 3.5L19.5 19H4.5L12 5.5zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z" /></svg>
									</div>
									<div className="wbte-ewb-warning-banner__content">
										<p className="wbte-ewb-warning-banner__text">{ field.message }</p>
									</div>
								</div>
								<div className="wbte-ewb-warning-banner__actions">
									{ field.url && <a className="wbte-ewb-warning-banner__link" href={ field.url }>{ field.action }</a> }
									{ field.doc_url && <a className="wbte-ewb-warning-banner__doc" href={ field.doc_url } target="_blank" rel="noopener noreferrer">{ field.doc }</a> }
								</div>
							</div>
						);
					}
					if ( field.type === 'toggle' ) {
						return ( <ToggleRow key={ field.key } id={ field.key } checked={ toBool( settings[ field.key ] ) } onChange={ ( val ) => updateField( field.key, toYesNo( val ) ) } label={ field.label } desc={ field.desc } /> );
					}
					if ( field.type === 'segmented' ) {
						return ( <Field key={ field.key } label={ field.label } desc={ field.desc }><SegmentedControl options={ ( field.options || [] ).map( ( o ) => ( { value: o.value, label: o.label } ) ) } value={ settings[ field.key ] ?? field.default ?? '' } onChange={ ( val ) => updateField( field.key, val ) } /></Field> );
					}
					if ( field.type === 'chips' ) {
						const selected = Array.isArray( settings[ field.key ] ) ? settings[ field.key ] : [];
						return ( <Field key={ field.key } label={ field.label } desc={ field.desc }><div className="wbte-ewb-chips">{ ( field.options || [] ).map( ( opt ) => ( <Chip key={ opt.value } label={ opt.label } selected={ selected.includes( opt.value ) } onClick={ () => toggleArrayItem( field.key, opt.value ) } /> ) ) }</div></Field> );
					}
					if ( field.type === 'category_search' ) {
						const catVal = Array.isArray( settings[ field.key ] ) ? settings[ field.key ].map( ( id ) => parseInt( id, 10 ) ) : [];
						return ( <Field key={ field.key } label={ field.label } desc={ field.desc }><CategoryExclusionField categories={ categories } value={ catVal } onChange={ ( ids ) => updateField( field.key, ids ) } /></Field> );
					}
					if ( field.type === 'product_search' ) {
						const prodVal = Array.isArray( settings[ field.key ] ) ? settings[ field.key ].map( ( id ) => parseInt( id, 10 ) ).filter( Boolean ) : [];
						return ( <Field key={ field.key } label={ field.label } desc={ field.desc }><ProductSearchField value={ prodVal } onChange={ ( ids ) => updateField( field.key, ids ) } /></Field> );
					}
					if ( field.type === 'number' ) {
						return ( <Field key={ field.key } label={ field.label } desc={ field.desc }><input type="number" className="wbte-ewb-input" value={ settings[ field.key ] ?? field.default ?? '' } min={ field.min } max={ field.max } step={ field.step } onChange={ ( e ) => updateField( field.key, parseFloat( e.target.value ) || 0 ) } style={ { width: '120px' } } /></Field> );
					}
					if ( field.type === 'select' ) {
						return ( <Field key={ field.key } label={ field.label } desc={ field.desc }><select className="wbte-ewb-select" value={ settings[ field.key ] ?? field.default ?? '' } onChange={ ( e ) => updateField( field.key, e.target.value ) }>{ ( field.options || [] ).map( ( opt ) => ( <option key={ opt.value } value={ opt.value }>{ opt.label }</option> ) ) }</select></Field> );
					}
					if ( field.type === 'textarea' ) {
						return ( <Field key={ field.key } label={ field.label } desc={ field.desc }><textarea className="wbte-ewb-input" value={ settings[ field.key ] ?? '' } onChange={ ( e ) => updateField( field.key, e.target.value ) } rows={ field.rows || 4 } style={ { width: '100%', maxWidth: '400px' } } /></Field> );
					}
					if ( field.type === 'text' ) {
						return ( <Field key={ field.key } label={ field.label } desc={ field.desc }><input type="text" className="wbte-ewb-input" value={ settings[ field.key ] ?? '' } onChange={ ( e ) => updateField( field.key, e.target.value ) } placeholder={ field.placeholder || '' } style={ { maxWidth: '480px' } } /></Field> );
					}
					if ( field.type === 'color' ) {
						return (
							<Field key={ field.key } label={ field.label } desc={ field.desc }>
								<div style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
									<input
										type="color"
										value={ settings[ field.key ] ?? field.default ?? '#000000' }
										onChange={ ( e ) => updateField( field.key, e.target.value ) }
										style={ { width: '40px', height: '32px', padding: '2px', border: '1px solid #ccc', borderRadius: '4px', cursor: 'pointer' } }
									/>
									<input
										type="text"
										className="wbte-ewb-input"
										value={ settings[ field.key ] ?? field.default ?? '' }
										onChange={ ( e ) => updateField( field.key, e.target.value ) }
										style={ { width: '90px', fontFamily: 'monospace', fontSize: '13px' } }
									/>
								</div>
							</Field>
						);
					}
					if ( field.type === 'terms_page_manager' ) {
						return ( <TermsPageManager key={ field.key } /> );
					}
					if ( field.type === 'import_export' ) {
						return ( <ImportExportSettings key={ field.key } /> );
					}
					if ( field.type === 'shortcode_help' ) {
						return (
							<div key={ field.key } className="wbte-ewb-shortcode-help" style={ { marginTop: '8px' } }>
								<p className="wbte-ewb-shortcode-help__label">{ field.label || __( 'Shortcode', 'wt-eu-withdrawal-button' ) }</p>
								{ ( field.examples || [] ).map( ( ex, i ) => (
									<code key={ i } className="wbte-ewb-shortcode-help__code">{ ex }</code>
								) ) }
								{ field.attrs && (
									<ul className="wbte-ewb-shortcode-help__attrs">
										{ field.attrs.map( ( attr, i ) => (
											<li key={ i }><strong>{ attr.name }</strong>{ ' — ' + attr.desc }</li>
										) ) }
									</ul>
								) }
							</div>
						);
					}
					return null;
				} ) }
			</div>
		</div>
	);
	};

	/* --- Build tabs: core + any extra tabs from addon sections --- */
	const extraSections = window.wbteEwbAdmin?.extra_sections || [];
	const addonTabKeys = new Set();
	extraSections.forEach( ( s ) => {
		if ( s.tab && ! CORE_TABS.find( ( t ) => t.key === s.tab ) ) {
			addonTabKeys.add( s.tab );
		}
	} );
	const addonTabs = [ ...addonTabKeys ].map( ( key ) => {
		const first = extraSections.find( ( s ) => s.tab === key );
		return { key, label: first?.tab_label || key.charAt( 0 ).toUpperCase() + key.slice( 1 ) };
	} );
	const extraTabs = ( window.wbteEwbAdmin?.extra_tabs || [] ).filter( ( t ) => ! CORE_TABS.find( ( c ) => c.key === t.key ) && ! addonTabs.find( ( a ) => a.key === t.key ) );
	const allTabs = [ ...CORE_TABS, ...addonTabs, ...extraTabs ];

	return (
		<div className="wbte-ewb-settings">
			{ notice && (
				<div
					className={ `wbte-ewb-settings__notice` }
					style={ {
						background: notice.status === 'success' ? 'var(--ok-soft)' : 'var(--danger-soft)',
						color: notice.status === 'success' ? 'var(--ok)' : 'var(--danger)',
						border: `1px solid ${ notice.status === 'success' ? '#b3dcc5' : '#e8c4c0' }`,
						borderRadius: 'var(--radius-sm)',
						padding: '12px 18px',
						fontSize: '13.5px',
						marginBottom: '18px',
						display: 'flex',
						justifyContent: 'space-between',
						alignItems: 'center',
					} }
				>
					<span>{ notice.message }</span>
					<button
						type="button"
						onClick={ () => setNotice( null ) }
						style={ {
							background: 'none',
							border: 'none',
							cursor: 'pointer',
							fontSize: '16px',
							color: 'inherit',
							padding: '0 0 0 12px',
							lineHeight: 1,
						} }
					>
						&times;
					</button>
				</div>
			) }

			{ /* ============================================================
			     Tab Navigation
			     ============================================================ */ }
			<div className="wbte-ewb-subtabs">
				{ allTabs.map( ( tab ) => {
					let icon = null;
					if ( tab.key === 'license' && window.wbteEwbAdmin?.license ) {
						icon = licenseActive
							? <span style={ { marginLeft: '5px', color: '#10b981', fontSize: '11px' } }>&#9679;</span>
							: <span style={ { marginLeft: '5px', color: '#f59e0b', fontSize: '13px', lineHeight: 1 } }>&#9888;</span>;
					}
					return (
						<button
							key={ tab.key }
							type="button"
							className={ `wbte-ewb-subtabs__btn${ activeTab === tab.key ? ' wbte-ewb-subtabs__btn--active' : '' }` }
							onClick={ () => setActiveTab( tab.key ) }
						>
							{ tab.label }{ icon }
						</button>
					);
				} ) }
			</div>

			{ /* ============================================================
			     General (tab: general)
			     ============================================================ */ }
			{ activeTab === 'general' && (
			<>

			<div className="wbte-ewb-card">
				<div className="ch">
					<div className="ch__top">
						<span className="ch__icon"><IconCog /></span>
						<h2>{ __( 'General', 'wt-eu-withdrawal-button' ) }</h2>
					</div>
					<p className="ch__sub">
						{ __( 'Core withdrawal form behaviour and display preferences.', 'wt-eu-withdrawal-button' ) }
					</p>
				</div>
				<div className="cb">
					<Field
						label={ __( 'Withdrawal page', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Select the page where customers can submit withdrawal requests.', 'wt-eu-withdrawal-button' ) }
					>
						<select
							className="wbte-ewb-select wbte-ewb-select--page"
							value={ String( settings.withdrawal_page || 0 ) }
							onChange={ ( e ) => updateField( 'withdrawal_page', parseInt( e.target.value, 10 ) ) }
						>
							{ pages.map( ( p ) => (
								<option key={ p.value } value={ p.value }>{ p.label }</option>
							) ) }
						</select>
					</Field>

					<ToggleRow
						id="embed_footer_link"
						checked={ toBool( settings.embed_footer_link ) }
						onChange={ ( val ) => updateField( 'embed_footer_link', toYesNo( val ) ) }
						label={ __( 'Show withdrawal button in footer', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Display a withdrawal button or link in the site footer.', 'wt-eu-withdrawal-button' ) }
					/>

					{ /* Combined appearance customizer (Pro: footer link + My Account) */ }
					{ extraSections.some( ( s ) => s.id === 'pro_footer_link_inline' || s.id === 'pro_myaccount_inline' )
						? renderAppearanceCustomizer()
						: toBool( settings.embed_footer_link ) && (
							<div className="wbte-ewb-toggle-row__child">
								<Field
									label={ __( 'Footer link text', 'wt-eu-withdrawal-button' ) }
									desc={ __( 'The text displayed for the footer withdrawal link.', 'wt-eu-withdrawal-button' ) }
								>
									<input
										type="text"
										className="wbte-ewb-input"
										value={ settings.footer_link_text || '' }
										onChange={ ( e ) => updateField( 'footer_link_text', e.target.value ) }
										placeholder={ __( 'Request Withdrawal', 'wt-eu-withdrawal-button' ) }
									/>
								</Field>
							</div>
						)
					}

					<Field
						label={ __( 'Display scope', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Where the withdrawal form and links are displayed.', 'wt-eu-withdrawal-button' ) }
					>
						<SegmentedControl
							options={ DISPLAY_SCOPE_OPTIONS }
							value={ settings.display_scope || 'global' }
							onChange={ ( val ) => updateField( 'display_scope', val ) }
						/>
					</Field>

					<ToggleRow
						id="allow_partial_withdrawals"
						checked={ toBool( settings.allow_partial_withdrawals ) }
						onChange={ ( val ) => updateField( 'allow_partial_withdrawals', toYesNo( val ) ) }
						label={ __( 'Allow partial withdrawals', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Let customers withdraw individual items instead of the entire order.', 'wt-eu-withdrawal-button' ) }
						tag={ __( 'Recommended', 'wt-eu-withdrawal-button' ) }
					/>

					<Field
						label={ __( 'Withdrawal period', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Number of days after the selected start status during which a withdrawal request can be submitted.', 'wt-eu-withdrawal-button' ) }
					>
						<div className="wbte-ewb-period">
							<div className="wbte-ewb-period__input-wrap">
								<input
									type="number"
									min={ 1 }
									step={ 1 }
									value={ settings.withdrawal_period || 14 }
									onChange={ ( e ) => updateField( 'withdrawal_period', parseInt( e.target.value, 10 ) || 14 ) }
								/>
								<span className="wbte-ewb-period__suffix">{ __( 'days', 'wt-eu-withdrawal-button' ) }</span>
							</div>
							<div className="wbte-ewb-period__quick">
								{ QUICK_DAYS.map( ( d ) => (
									<button
										key={ d }
										type="button"
										className={ `wbte-ewb-period__quick-btn${ ( settings.withdrawal_period || 14 ) === d ? ' wbte-ewb-period__quick-btn--active' : '' }` }
										onClick={ () => updateField( 'withdrawal_period', d ) }
									>
										{ d }d
									</button>
								) ) }
							</div>
						</div>
					</Field>

					<Field
						label={ __( 'Start countdown from', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Select one or more order statuses. The countdown begins on the earliest date the order reaches any selected status.', 'wt-eu-withdrawal-button' ) }
					>
						<OrderStatusPickerField
							options={ periodStartOptions }
							value={ periodStartStatuses }
							onChange={ ( statuses ) => updateField( 'withdrawal_period_start_statuses', statuses ) }
							minSelected={ 1 }
						/>
					</Field>

					<ToggleRow
						id="reason_required"
						checked={ toBool( settings.reason_required ) }
						onChange={ ( val ) => updateField( 'reason_required', toYesNo( val ) ) }
						label={ __( 'Reason required', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Require customers to provide a reason when submitting a withdrawal request.', 'wt-eu-withdrawal-button' ) }
					/>
				</div>
			</div>

			{ /* --- Extra sections assigned to "general" tab (excluding inline ones) --- */ }
			{ extraSections.filter( ( s ) => s.tab === 'general' && s.id !== 'pro_footer_link_inline' && s.id !== 'pro_myaccount_inline' && s.id !== 'pro_button_appearance' ).map( ( section ) => renderExtraSection( section ) ) }
			</>
			) }

			{ /* ============================================================
			     Rules & Exclusions (tab: rules)
			     ============================================================ */ }
			{ activeTab === 'rules' && (
			<>
			{ /* --- Extra sections assigned to "rules" tab (rendered first, e.g. Pro auto-processing) --- */ }
			{ extraSections.filter( ( s ) => s.tab === 'rules' ).map( ( section ) => renderExtraSection( section ) ) }

			<div className="wbte-ewb-card">
				<div className="ch">
					<div className="ch__top">
						<span className="ch__icon"><IconBan /></span>
						<h2>{ __( 'Exclusions', 'wt-eu-withdrawal-button' ) }</h2>
					</div>
					<p className="ch__sub">
						{ __( 'Exclude product types, categories, or specific products from withdrawal eligibility.', 'wt-eu-withdrawal-button' ) }
					</p>
				</div>
				<div className="cb">
					<Field
						label={ __( 'Excluded product types', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Product types that cannot be withdrawn.', 'wt-eu-withdrawal-button' ) }
					>
						<div className="wbte-ewb-chips">
							{ PRODUCT_TYPE_OPTIONS.map( ( opt ) => (
								<Chip
									key={ opt.value }
									label={ opt.label }
									selected={ excludedTypes.includes( opt.value ) }
									onClick={ () => toggleArrayItem( 'excluded_product_types', opt.value ) }
								/>
							) ) }
						</div>
					</Field>

					<Field
						label={ __( 'Excluded categories', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Products in these categories cannot be withdrawn.', 'wt-eu-withdrawal-button' ) }
					>
						<CategoryExclusionField
							categories={ categories }
							value={ excludedCategories }
							onChange={ ( categoryIds ) => updateField( 'excluded_categories', categoryIds ) }
						/>
					</Field>

					<Field
						label={ __( 'Excluded products', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Search and select specific products that cannot be withdrawn.', 'wt-eu-withdrawal-button' ) }
					>
						<ProductSearchField
							value={ excludedProducts }
							onChange={ ( productIds ) => updateField( 'excluded_products', productIds ) }
						/>
					</Field>
				</div>
			</div>

			</>
			) }

			{ /* ============================================================
			     Workflow (tab: workflow)
			     ============================================================ */ }
			{ activeTab === 'workflow' && (
			<>
			<div className="wbte-ewb-card">
				<div className="ch">
					<div className="ch__top">
						<span className="ch__icon"><IconFlow /></span>
						<h2>{ __( 'Order status', 'wt-eu-withdrawal-button' ) }</h2>
					</div>
					<p className="ch__sub">
						{ __( 'Automatically update order statuses when withdrawal requests are submitted or approved.', 'wt-eu-withdrawal-button' ) }
					</p>
				</div>
				<div className="cb">
					<ToggleRow
						id="change_status_on_submission"
						checked={ toBool( settings.change_status_on_submission ) }
						onChange={ ( val ) => updateField( 'change_status_on_submission', toYesNo( val ) ) }
						label={ __( 'Change order status on submission', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Automatically change the order status when a withdrawal request is submitted.', 'wt-eu-withdrawal-button' ) }
					>
						{ toBool( settings.change_status_on_submission ) && (
							<div className="wbte-ewb-toggle-row__child">
								<span className="wbte-ewb-toggle-row__child-label">
									{ __( 'Set status to', 'wt-eu-withdrawal-button' ) }
								</span>
								<select
									className="wbte-ewb-select"
									value={ settings.submission_order_status || '' }
									onChange={ ( e ) => updateField( 'submission_order_status', e.target.value ) }
								>
									{ orderStatusOptions.map( ( opt ) => (
										<option key={ opt.value } value={ opt.value }>{ opt.label }</option>
									) ) }
								</select>
							</div>
						) }
					</ToggleRow>

					<ToggleRow
						id="change_status_on_approval"
						checked={ toBool( settings.change_status_on_approval ) }
						onChange={ ( val ) => updateField( 'change_status_on_approval', toYesNo( val ) ) }
						label={ __( 'Change order status on approval', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Automatically change the order status when a withdrawal request is approved.', 'wt-eu-withdrawal-button' ) }
					>
						{ toBool( settings.change_status_on_approval ) && (
							<div className="wbte-ewb-toggle-row__child">
								<span className="wbte-ewb-toggle-row__child-label">
									{ __( 'Set status to', 'wt-eu-withdrawal-button' ) }
								</span>
								<select
									className="wbte-ewb-select"
									value={ settings.approval_order_status || '' }
									onChange={ ( e ) => updateField( 'approval_order_status', e.target.value ) }
								>
									{ orderStatusOptions.map( ( opt ) => (
										<option key={ opt.value } value={ opt.value }>{ opt.label }</option>
									) ) }
								</select>
							</div>
						) }
					</ToggleRow>
				</div>
			</div>

			{ /* ============================================================
			     Email Notifications
			     ============================================================ */ }
			<div className="wbte-ewb-card">
				<div className="ch">
					<div className="ch__top">
						<span className="ch__icon"><IconMail /></span>
						<h2>{ __( 'Email notifications', 'wt-eu-withdrawal-button' ) }</h2>
					</div>
					<p className="ch__sub">
						{ __( 'Configure admin notification recipients and the customer contact email shown in rejected withdrawal emails.', 'wt-eu-withdrawal-button' ) }
					</p>
				</div>
				<div className="cb">
					<Field
						label={ __( 'Recipient(s)', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Email addresses that receive the new withdrawal request notification.', 'wt-eu-withdrawal-button' ) }
					>
						<EmailRecipientsField
							value={ adminNotificationRecipients }
							onChange={ ( emails ) => updateField( 'admin_notification_recipients', emails ) }
						/>
					</Field>
					<Field
						label={ __( 'Customer contact email', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Email address shown to customers when a withdrawal request is rejected and they need to contact you.', 'wt-eu-withdrawal-button' ) }
					>
						<input
							type="email"
							className="wbte-ewb-input wbte-ewb-email-field"
							value={ settings.customer_contact_email || '' }
							placeholder={ ( window.wbteEwbAdmin && window.wbteEwbAdmin.default_store_email ) || '' }
							onChange={ ( e ) => updateField( 'customer_contact_email', e.target.value ) }
						/>
					</Field>
				</div>
			</div>

			{ /* --- Extra sections assigned to "workflow" tab --- */ }
			{ extraSections.filter( ( s ) => s.tab === 'workflow' ).map( ( section ) => renderExtraSection( section ) ) }
			</>
			) }

			{ /* ============================================================
			     Advanced (tab: advanced)
			     ============================================================ */ }
			{ activeTab === 'advanced' && (
			<>
			<div className="wbte-ewb-card">
				<div className="ch">
					<div className="ch__top">
						<span className="ch__icon"><IconCode /></span>
						<h2>{ __( 'Withdrawal button shortcode', 'wt-eu-withdrawal-button' ) }</h2>
					</div>
					<p className="ch__sub">
						{ __( 'Add a withdrawal request button to any page or post. The button links to your configured withdrawal page.', 'wt-eu-withdrawal-button' ) }
					</p>
				</div>
				<div className="cb">
					<div className="wbte-ewb-shortcode-help">
						<p className="wbte-ewb-shortcode-help__label">{ __( 'Examples', 'wt-eu-withdrawal-button' ) }</p>
						<code className="wbte-ewb-shortcode-help__code">[wt_eu_order_withdrawal]</code>
						<code className="wbte-ewb-shortcode-help__code">[wt_eu_order_withdrawal label=&quot;Cancel my order&quot;]</code>
						<ul className="wbte-ewb-shortcode-help__attrs">
							<li>
								<strong>label</strong>
								{ __( ' — Optional. Custom button text. Uses the default shortcode label when omitted.', 'wt-eu-withdrawal-button' ) }
							</li>
							<li>
								<strong>class</strong>
								{ __( ' — Optional. Extra CSS classes added to the button.', 'wt-eu-withdrawal-button' ) }
							</li>
						</ul>
					</div>
				</div>
			</div>

			{ /* --- Extra sections assigned to "advanced" tab --- */ }
			{ extraSections.filter( ( s ) => s.tab === 'advanced' ).map( ( section ) => renderExtraSection( section ) ) }

			{ /* ============================================================
			     Data Management
			     ============================================================ */ }
			<div className="wbte-ewb-card">
				<div className="ch">
					<div className="ch__top">
						<span className="ch__icon"><IconDatabase /></span>
						<h2>{ __( 'Data management', 'wt-eu-withdrawal-button' ) }</h2>
					</div>
					<p className="ch__sub">
						{ __( 'Control what happens to plugin data when the plugin is removed.', 'wt-eu-withdrawal-button' ) }
					</p>
				</div>
				<div className="cb">
					<ToggleRow
						id="delete_data_on_uninstall"
						checked={ toBool( settings.delete_data_on_uninstall ) }
						onChange={ ( val ) => updateField( 'delete_data_on_uninstall', toYesNo( val ) ) }
						label={ __( 'Delete data on uninstall', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Remove all plugin data (settings, withdrawal requests, database tables) when the plugin is uninstalled. This action is irreversible.', 'wt-eu-withdrawal-button' ) }
						tag={ __( 'Destructive', 'wt-eu-withdrawal-button' ) }
						tagVariant="danger"
					/>
				</div>
			</div>

			</>
			) }

			{ /* --- Addon tabs (custom tabs created by extra_sections with new tab keys) --- */ }
			{ addonTabs.map( ( tab ) => (
				activeTab === tab.key ? (
					<div key={ tab.key }>
						{ extraSections.filter( ( s ) => s.tab === tab.key ).map( ( section ) => renderExtraSection( section ) ) }
					</div>
				) : null
			) ) }

			{ /* --- License tab --- */ }
			{ activeTab === 'license' && window.wbteEwbAdmin?.license && (
				<LicenseTab onStatusChange={ setLicenseActive } />
			) }

			{ /* ============================================================
			     Sticky Save Bar
			     ============================================================ */ }
			<div className="wbte-ewb-savebar">
				<div className="wbte-ewb-savebar__status">
					<span className={ `wbte-ewb-savebar__dot ${ isDirty ? 'wbte-ewb-savebar__dot--dirty' : 'wbte-ewb-savebar__dot--clean' }` } />
					<span>
						{ isDirty && ! hasValidRecipients
							? __( 'Add at least one recipient to save', 'wt-eu-withdrawal-button' )
							: isDirty
								? __( 'Unsaved changes', 'wt-eu-withdrawal-button' )
								: __( 'All changes saved', 'wt-eu-withdrawal-button' )
						}
					</span>
				</div>
				<div className="wbte-ewb-savebar__actions">
					{ isDirty && (
						<button
							type="button"
							className="wbte-ewb-btn wbte-ewb-btn--ghost"
							onClick={ handleDiscard }
						>
							{ __( 'Discard', 'wt-eu-withdrawal-button' ) }
						</button>
					) }
					<button
						type="button"
						className="wbte-ewb-btn wbte-ewb-btn--primary"
						disabled={ saving || ! canSave }
						onClick={ handleSave }
					>
						{ saving
							? __( 'Saving...', 'wt-eu-withdrawal-button' )
							: __( 'Save settings', 'wt-eu-withdrawal-button' )
						}
					</button>
				</div>
			</div>
		</div>
	);
};

export default SettingsPage;
