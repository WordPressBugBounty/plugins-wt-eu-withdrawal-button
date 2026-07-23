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
		const savedTextColor = settings[ prefix + '_text_color' ] || '';
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

				<div style={ { background: '#fafafa', borderRadius: '10px', border: '1px solid #e5e7eb', padding: '18px 20px', marginTop: '4px' } }>
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
					const effectiveText = settings[ prefix + '_text_color' ] && ! ( ! isBtn && isLightColor( settings[ prefix + '_text_color' ] ) ) ? settings[ prefix + '_text_color' ] : defaultTextColor;
					const effectiveHover = settings[ prefix + '_hover_text_color' ] && ! ( ! isBtn && isLightColor( settings[ prefix + '_hover_text_color' ] ) ) ? settings[ prefix + '_hover_text_color' ] : defaultHoverColor;

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
								<input type="color" value={ effectiveText } onChange={ ( e ) => updateField( prefix + '_text_color', e.target.value ) } style={ { width: '40px', height: '40px', padding: '2px', border: '1px solid #ccc', borderRadius: '4px', cursor: 'pointer', boxSizing: 'border-box' } } />
								<input type="text" className="wbte-ewb-input" value={ effectiveText } onChange={ ( e ) => updateField( prefix + '_text_color', e.target.value ) } style={ { width: '80px', height: '40px', fontFamily: 'monospace', fontSize: '12px', boxSizing: 'border-box' } } />
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
								<input type="color" value={ effectiveHover } onChange={ ( e ) => updateField( prefix + '_hover_text_color', e.target.value ) } style={ { width: '40px', height: '40px', padding: '2px', border: '1px solid #ccc', borderRadius: '4px', cursor: 'pointer', boxSizing: 'border-box' } } />
								<input type="text" className="wbte-ewb-input" value={ effectiveHover } onChange={ ( e ) => updateField( prefix + '_hover_text_color', e.target.value ) } style={ { width: '80px', height: '40px', fontFamily: 'monospace', fontSize: '12px', boxSizing: 'border-box' } } />
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
	const renderExtraSection = ( section ) => (
		<div className="wbte-ewb-card" key={ section.id }>
			<div className="ch">
				<div className="ch__top" style={ section.header_toggle ? { justifyContent: 'space-between' } : {} }>
					<span style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
						<span className="ch__icon"><IconCog /></span>
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
						return ( <Field key={ field.key } label={ field.label } desc={ field.desc }><input type="text" className="wbte-ewb-input" value={ settings[ field.key ] ?? '' } onChange={ ( e ) => updateField( field.key, e.target.value ) } placeholder={ field.placeholder || '' } /></Field> );
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
					return null;
				} ) }
			</div>
		</div>
	);

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
	const allTabs = [ ...CORE_TABS, ...addonTabs ];

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
				{ allTabs.map( ( tab ) => (
					<button
						key={ tab.key }
						type="button"
						className={ `wbte-ewb-subtabs__btn${ activeTab === tab.key ? ' wbte-ewb-subtabs__btn--active' : '' }` }
						onClick={ () => setActiveTab( tab.key ) }
					>
						{ tab.label }
					</button>
				) ) }
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
