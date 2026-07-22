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

const SettingsPage = () => {
	const [ settings, setSettings ] = useState( null );
	const [ savedSnapshot, setSavedSnapshot ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ pages, setPages ] = useState( [] );
	const [ categories, setCategories ] = useState( [] );
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
			     General
			     ============================================================ */ }
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
						label={ __( 'Embed footer link', 'wt-eu-withdrawal-button' ) }
						desc={ __( 'Automatically add a link to the withdrawal page in the site footer.', 'wt-eu-withdrawal-button' ) }
					/>

					{ toBool( settings.embed_footer_link ) && (
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
					) }

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

			{ /* ============================================================
			     Exclusions
			     ============================================================ */ }
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

			{ /* ============================================================
			     Order Status
			     ============================================================ */ }
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

			{ /* ============================================================
			     Withdrawal Button Shortcode
			     ============================================================ */ }
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
