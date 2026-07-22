/**
 * Searchable category picker for exclusion settings.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * @param {Object}        props
 * @param {Array<{id:number,name:string}>} props.categories Available categories.
 * @param {number[]}      props.value      Selected category IDs.
 * @param {Function}      props.onChange   Selection change handler.
 */
const CategoryExclusionField = ( { categories = [], value = [], onChange } ) => {
	const [ search, setSearch ] = useState( '' );
	const [ open, setOpen ] = useState( false );
	const wrapRef = useRef( null );
	const selectedIds = Array.isArray( value )
		? value.map( ( id ) => parseInt( id, 10 ) ).filter( Boolean )
		: [];

	const filteredCategories = useMemo( () => {
		const term = search.trim().toLowerCase();

		return [ ...categories ]
			.filter( ( category ) => ! term || category.name.toLowerCase().includes( term ) )
			.sort( ( a, b ) => a.name.localeCompare( b.name ) );
	}, [ categories, search ] );

	useEffect( () => {
		const handleClickOutside = ( event ) => {
			if ( wrapRef.current && ! wrapRef.current.contains( event.target ) ) {
				setOpen( false );
			}
		};

		document.addEventListener( 'mousedown', handleClickOutside );
		return () => document.removeEventListener( 'mousedown', handleClickOutside );
	}, [] );

	const toggleCategory = ( categoryId ) => {
		if ( selectedIds.includes( categoryId ) ) {
			onChange( selectedIds.filter( ( id ) => id !== categoryId ) );
			return;
		}

		onChange( [ ...selectedIds, categoryId ] );
	};

	const removeCategory = ( categoryId ) => {
		onChange( selectedIds.filter( ( id ) => id !== categoryId ) );
	};

	const categoryNameById = useMemo( () => {
		const map = {};

		categories.forEach( ( category ) => {
			map[ category.id ] = category.name;
		} );

		return map;
	}, [ categories ] );

	const hasSearchTerm = search.trim().length > 0;
	const showAllOnOpen = categories.length <= 20;
	const visibleCategories = hasSearchTerm || showAllOnOpen ? filteredCategories : [];

	if ( ! categories.length ) {
		return (
			<p className="wbte-ewb-field__desc">
				{ __( 'No product categories found.', 'wt-eu-withdrawal-button' ) }
			</p>
		);
	}

	return (
		<div className="wbte-ewb-picker" ref={ wrapRef }>
			{ selectedIds.length > 0 && (
				<div className="wbte-ewb-picker__selected-wrap">
					<div className="wbte-ewb-picker__selected-header">
						<span className="wbte-ewb-picker__selected-count">
							{ sprintf(
								/* translators: %d: number of selected categories */
								__( '%d selected', 'wt-eu-withdrawal-button' ),
								selectedIds.length
							) }
						</span>
						<button
							type="button"
							className="wbte-ewb-picker__clear"
							onClick={ () => onChange( [] ) }
						>
							{ __( 'Clear all', 'wt-eu-withdrawal-button' ) }
						</button>
					</div>
					<div className="wbte-ewb-picker__selected">
						{ selectedIds.map( ( categoryId ) => (
							<span key={ categoryId } className="wbte-ewb-picker__tag">
								<span>{ categoryNameById[ categoryId ] || `#${ categoryId }` }</span>
								<button
									type="button"
									className="wbte-ewb-picker__remove"
									onClick={ () => removeCategory( categoryId ) }
									aria-label={ __( 'Remove category', 'wt-eu-withdrawal-button' ) }
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
					placeholder={ __( 'Search categories…', 'wt-eu-withdrawal-button' ) }
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
							{ __( 'Type to filter categories…', 'wt-eu-withdrawal-button' ) }
						</p>
					) : visibleCategories.length > 0 ? (
						visibleCategories.map( ( category ) => {
							const isSelected = selectedIds.includes( category.id );

							return (
								<button
									key={ category.id }
									type="button"
									role="option"
									aria-selected={ isSelected }
									className={ `wbte-ewb-picker__option${ isSelected ? ' wbte-ewb-picker__option--selected' : '' }` }
									onClick={ () => toggleCategory( category.id ) }
								>
									<span className="wbte-ewb-picker__option-check" aria-hidden="true">
										{ isSelected ? '✓' : '' }
									</span>
									<span className="wbte-ewb-picker__option-label">{ category.name }</span>
								</button>
							);
						} )
					) : (
						<p className="wbte-ewb-picker__empty">
							{ __( 'No categories match your search.', 'wt-eu-withdrawal-button' ) }
						</p>
					) }
				</div>
			) }
		</div>
	);
};

export default CategoryExclusionField;
