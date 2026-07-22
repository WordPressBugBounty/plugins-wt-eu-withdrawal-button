/**
 * Searchable order status picker for multi-select settings.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * @param {Object}   props
 * @param {Array<{value:string,label:string}>} props.options  Available statuses.
 * @param {string[]} props.value    Selected status slugs.
 * @param {Function} props.onChange  Selection change handler.
 * @param {number}   props.minSelected Minimum selections required (default 1).
 */
const OrderStatusPickerField = ( {
	options = [],
	value = [],
	onChange,
	minSelected = 1,
} ) => {
	const [ search, setSearch ] = useState( '' );
	const [ open, setOpen ] = useState( false );
	const wrapRef = useRef( null );
	const selected = Array.isArray( value ) ? value.filter( Boolean ) : [];

	const filteredOptions = useMemo( () => {
		const term = search.trim().toLowerCase();

		return [ ...options ]
			.filter( ( option ) => ! term || option.label.toLowerCase().includes( term ) )
			.sort( ( a, b ) => a.label.localeCompare( b.label ) );
	}, [ options, search ] );

	useEffect( () => {
		const handleClickOutside = ( event ) => {
			if ( wrapRef.current && ! wrapRef.current.contains( event.target ) ) {
				setOpen( false );
			}
		};

		document.addEventListener( 'mousedown', handleClickOutside );
		return () => document.removeEventListener( 'mousedown', handleClickOutside );
	}, [] );

	const labelByValue = useMemo( () => {
		const map = {};

		options.forEach( ( option ) => {
			map[ option.value ] = option.label;
		} );

		return map;
	}, [ options ] );

	const toggleStatus = ( statusValue ) => {
		if ( selected.includes( statusValue ) ) {
			if ( selected.length <= minSelected ) {
				return;
			}

			onChange( selected.filter( ( item ) => item !== statusValue ) );
			return;
		}

		onChange( [ ...selected, statusValue ] );
	};

	const removeStatus = ( statusValue ) => {
		if ( selected.length <= minSelected ) {
			return;
		}

		onChange( selected.filter( ( item ) => item !== statusValue ) );
	};

	const clearAll = () => {
		if ( minSelected > 0 ) {
			return;
		}

		onChange( [] );
	};

	const hasSearchTerm = search.trim().length > 0;
	const showAllOnOpen = options.length <= 20;
	const visibleOptions = hasSearchTerm || showAllOnOpen ? filteredOptions : [];

	if ( ! options.length ) {
		return (
			<p className="wbte-ewb-field__desc">
				{ __( 'No order statuses found.', 'wt-eu-withdrawal-button' ) }
			</p>
		);
	}

	return (
		<div className="wbte-ewb-picker" ref={ wrapRef }>
			{ selected.length > 0 && (
				<div className="wbte-ewb-picker__selected-wrap">
					<div className="wbte-ewb-picker__selected-header">
						<span className="wbte-ewb-picker__selected-count">
							{ sprintf(
								/* translators: %d: number of selected order statuses */
								__( '%d selected', 'wt-eu-withdrawal-button' ),
								selected.length
							) }
						</span>
						{ minSelected === 0 && (
							<button
								type="button"
								className="wbte-ewb-picker__clear"
								onClick={ clearAll }
							>
								{ __( 'Clear all', 'wt-eu-withdrawal-button' ) }
							</button>
						) }
					</div>
					<div className="wbte-ewb-picker__selected">
						{ selected.map( ( statusValue ) => (
							<span key={ statusValue } className="wbte-ewb-picker__tag">
								<span>{ labelByValue[ statusValue ] || statusValue }</span>
								<button
									type="button"
									className="wbte-ewb-picker__remove"
									onClick={ () => removeStatus( statusValue ) }
									disabled={ selected.length <= minSelected }
									aria-label={ __( 'Remove status', 'wt-eu-withdrawal-button' ) }
								>
									&times;
								</button>
							</span>
						) ) }
					</div>
				</div>
			) }

			<div className="wbte-ewb-picker__input-wrap">
				<input
					type="search"
					className="wbte-ewb-input wbte-ewb-picker__search"
					value={ search }
					placeholder={ __( 'Search order statuses…', 'wt-eu-withdrawal-button' ) }
					onChange={ ( e ) => {
						setSearch( e.target.value );
						setOpen( true );
					} }
					onFocus={ () => setOpen( true ) }
					aria-expanded={ open }
					aria-haspopup="listbox"
				/>
			</div>

			{ open && (
				<div className="wbte-ewb-picker__dropdown" role="listbox" aria-multiselectable="true">
					{ ! hasSearchTerm && ! showAllOnOpen ? (
						<p className="wbte-ewb-picker__empty">
							{ __( 'Type to filter order statuses…', 'wt-eu-withdrawal-button' ) }
						</p>
					) : visibleOptions.length > 0 ? (
						visibleOptions.map( ( option ) => {
							const isSelected = selected.includes( option.value );

							return (
								<button
									key={ option.value }
									type="button"
									role="option"
									aria-selected={ isSelected }
									className={ `wbte-ewb-picker__option${ isSelected ? ' wbte-ewb-picker__option--selected' : '' }` }
									onClick={ () => toggleStatus( option.value ) }
								>
									<span className="wbte-ewb-picker__option-check" aria-hidden="true">
										{ isSelected ? '✓' : '' }
									</span>
									<span className="wbte-ewb-picker__option-label">{ option.label }</span>
								</button>
							);
						} )
					) : (
						<p className="wbte-ewb-picker__empty">
							{ __( 'No order statuses match your search.', 'wt-eu-withdrawal-button' ) }
						</p>
					) }
				</div>
			) }
		</div>
	);
};

export default OrderStatusPickerField;
