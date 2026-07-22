/**
 * Reusable filters bar component.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const DEFAULT_VALUES = {
	status: '',
	date_from: '',
	date_to: '',
	search: '',
};

const Filters = ( { onFilter, initialValues = {} } ) => {
	const [ filters, setFilters ] = useState( {
		...DEFAULT_VALUES,
		...initialValues,
	} );

	const handleChange = ( key, value ) => {
		setFilters( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

	const handleApply = () => {
		onFilter( filters );
	};

	const handleReset = () => {
		setFilters( { ...DEFAULT_VALUES } );
		onFilter( { ...DEFAULT_VALUES } );
	};

	const handleKeyDown = ( e ) => {
		if ( e.key === 'Enter' ) {
			handleApply();
		}
	};

	return (
		<div className="wbte-ewb-filter-bar">
			<div className="wbte-ewb-filter-bar__field">
				<label className="wbte-ewb-filter-bar__label">
					{ __( 'Status', 'wt-eu-withdrawal-button' ) }
				</label>
				<select
					className="wbte-ewb-filter-bar__select"
					value={ filters.status }
					onChange={ ( e ) => handleChange( 'status', e.target.value ) }
				>
					<option value="">{ __( 'All Statuses', 'wt-eu-withdrawal-button' ) }</option>
					<option value="pending">{ __( 'Pending', 'wt-eu-withdrawal-button' ) }</option>
					<option value="approved">{ __( 'Approved', 'wt-eu-withdrawal-button' ) }</option>
					<option value="rejected">{ __( 'Rejected', 'wt-eu-withdrawal-button' ) }</option>
				</select>
			</div>
			<div className="wbte-ewb-filter-bar__field">
				<label className="wbte-ewb-filter-bar__label">
					{ __( 'Date From', 'wt-eu-withdrawal-button' ) }
				</label>
				<input
					type="date"
					className="wbte-ewb-filter-bar__input"
					value={ filters.date_from }
					onChange={ ( e ) => handleChange( 'date_from', e.target.value ) }
					onKeyDown={ handleKeyDown }
				/>
			</div>
			<div className="wbte-ewb-filter-bar__field">
				<label className="wbte-ewb-filter-bar__label">
					{ __( 'Date To', 'wt-eu-withdrawal-button' ) }
				</label>
				<input
					type="date"
					className="wbte-ewb-filter-bar__input"
					value={ filters.date_to }
					onChange={ ( e ) => handleChange( 'date_to', e.target.value ) }
					onKeyDown={ handleKeyDown }
				/>
			</div>
			<div className="wbte-ewb-filter-bar__field wbte-ewb-filter-bar__field--grow">
				<label className="wbte-ewb-filter-bar__label">
					{ __( 'Search', 'wt-eu-withdrawal-button' ) }
				</label>
				<input
					type="search"
					className="wbte-ewb-filter-bar__input"
					value={ filters.search }
					onChange={ ( e ) => handleChange( 'search', e.target.value ) }
					placeholder={ __( 'Order # or receipt hash', 'wt-eu-withdrawal-button' ) }
					onKeyDown={ handleKeyDown }
					spellCheck={ false }
					autoCapitalize="off"
					autoCorrect="off"
				/>
			</div>
			<div className="wbte-ewb-filter-bar__actions">
				<button
					type="button"
					className="wbte-ewb-btn wbte-ewb-btn--ghost"
					onClick={ handleReset }
				>
					<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
						<path d="M2 2v5h5" />
						<path d="M3.05 10A6 6 0 1 0 4 4.27L2 7" />
					</svg>
					{ __( 'Reset', 'wt-eu-withdrawal-button' ) }
				</button>
				<button
					type="button"
					className="wbte-ewb-btn wbte-ewb-btn--primary"
					onClick={ handleApply }
				>
					<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
						<circle cx="7" cy="7" r="5" />
						<path d="M14 14l-3.5-3.5" />
					</svg>
					{ __( 'Apply Filters', 'wt-eu-withdrawal-button' ) }
				</button>
			</div>
		</div>
	);
};

export default Filters;
