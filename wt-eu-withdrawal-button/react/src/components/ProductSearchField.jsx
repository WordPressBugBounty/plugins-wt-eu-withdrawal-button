/**
 * WooCommerce product search field for exclusion settings.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useState, useEffect, useRef, useCallback, useMemo } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const API_BASE = 'wbte-ewb/v1';
const SEARCH_DEBOUNCE_MS = 400;
const MIN_SEARCH_LENGTH = 2;

/**
 * Stable comma-separated ID key for array props that change reference each render.
 *
 * @param {Array} ids Product IDs.
 * @return {string}
 */
const toIdKey = ( ids ) => {
	if ( ! Array.isArray( ids ) ) {
		return '';
	}

	return ids
		.map( ( id ) => parseInt( id, 10 ) )
		.filter( Boolean )
		.sort( ( a, b ) => a - b )
		.join( ',' );
};

/**
 * @param {Object}   props
 * @param {number[]} props.value   Selected product IDs.
 * @param {Function} props.onChange Callback when selection changes.
 */
const ProductSearchField = ( { value = [], onChange } ) => {
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ searching, setSearching ] = useState( false );
	const [ labels, setLabels ] = useState( {} );
	const [ open, setOpen ] = useState( false );
	const wrapRef = useRef( null );
	const labelsRef = useRef( {} );
	const searchCacheRef = useRef( {} );
	const searchRequestRef = useRef( 0 );
	const selectedIdsKey = toIdKey( value );

	const selectedIds = useMemo( () => {
		if ( ! selectedIdsKey ) {
			return [];
		}

		return selectedIdsKey.split( ',' ).map( ( id ) => parseInt( id, 10 ) );
	}, [ selectedIdsKey ] );

	const selectedIdsRef = useRef( selectedIds );

	const loadProductLabels = useCallback( async ( ids ) => {
		if ( ! ids.length ) {
			return;
		}

		try {
			const response = await apiFetch( {
				path: addQueryArgs( `${ API_BASE }/lookups/products`, {
					include: ids.join( ',' ),
				} ),
			} );
			const items = response.data || response || [];
			const next = {};

			items.forEach( ( item ) => {
				next[ item.id ] = item.name;
			} );

			setLabels( ( prev ) => ( { ...prev, ...next } ) );
		} catch ( err ) {
			// Keep existing labels when lookup fails.
		}
	}, [] );

	useEffect( () => {
		const missing = selectedIds.filter( ( id ) => ! labelsRef.current[ id ] );
		if ( missing.length ) {
			loadProductLabels( missing );
		}
	}, [ selectedIdsKey, loadProductLabels ] );

	selectedIdsRef.current = selectedIds;
	labelsRef.current = labels;

	useEffect( () => {
		const term = search.trim();

		if ( term.length < MIN_SEARCH_LENGTH ) {
			setResults( [] );
			setSearching( false );
			return undefined;
		}

		const cached = searchCacheRef.current[ term ];
		if ( cached ) {
			setResults( cached.filter( ( item ) => ! selectedIdsRef.current.includes( item.id ) ) );
			setSearching( false );
			return undefined;
		}

		setSearching( true );
		const requestId = ++searchRequestRef.current;

		const timer = setTimeout( async () => {
			try {
				const response = await apiFetch( {
					path: addQueryArgs( `${ API_BASE }/lookups/products`, {
						search: term,
					} ),
				} );

				if ( requestId !== searchRequestRef.current ) {
					return;
				}

				const items = response.data || response || [];
				searchCacheRef.current[ term ] = items;
				setResults( items.filter( ( item ) => ! selectedIdsRef.current.includes( item.id ) ) );
			} catch ( err ) {
				if ( requestId === searchRequestRef.current ) {
					setResults( [] );
				}
			} finally {
				if ( requestId === searchRequestRef.current ) {
					setSearching( false );
				}
			}
		}, SEARCH_DEBOUNCE_MS );

		return () => {
			clearTimeout( timer );
			searchRequestRef.current += 1;
		};
	}, [ search ] );

	useEffect( () => {
		setResults( ( prev ) => prev.filter( ( item ) => ! selectedIdsRef.current.includes( item.id ) ) );
	}, [ selectedIdsKey ] );

	useEffect( () => {
		const handleClickOutside = ( event ) => {
			if ( wrapRef.current && ! wrapRef.current.contains( event.target ) ) {
				setOpen( false );
			}
		};

		document.addEventListener( 'mousedown', handleClickOutside );
		return () => document.removeEventListener( 'mousedown', handleClickOutside );
	}, [] );

	const addProduct = ( product ) => {
		if ( selectedIds.includes( product.id ) ) {
			return;
		}

		setLabels( ( prev ) => ( { ...prev, [ product.id ]: product.name } ) );
		onChange( [ ...selectedIds, product.id ] );
		setSearch( '' );
		setResults( [] );
		setOpen( false );
	};

	const removeProduct = ( productId ) => {
		onChange( selectedIds.filter( ( id ) => id !== productId ) );
	};

	return (
		<div className="wbte-ewb-product-search wbte-ewb-picker" ref={ wrapRef }>
			{ selectedIds.length > 0 && (
				<div className="wbte-ewb-picker__selected-wrap">
					<div className="wbte-ewb-picker__selected-header">
						<span className="wbte-ewb-picker__selected-count">
							{ sprintf(
								/* translators: %d: number of selected products */
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
					<div className="wbte-ewb-product-search__selected wbte-ewb-picker__selected">
						{ selectedIds.map( ( productId ) => (
							<span key={ productId } className="wbte-ewb-product-search__tag">
								<span>{ labels[ productId ] || `#${ productId }` }</span>
								<button
									type="button"
									className="wbte-ewb-product-search__remove"
									onClick={ () => removeProduct( productId ) }
									aria-label={ __( 'Remove product', 'wt-eu-withdrawal-button' ) }
								>
									&times;
								</button>
							</span>
						) ) }
					</div>
				</div>
			) }

			<div className="wbte-ewb-product-search__input-wrap">
				<input
					type="search"
					className="wbte-ewb-input wbte-ewb-product-search__input"
					value={ search }
					placeholder={ __( 'Search for a product…', 'wt-eu-withdrawal-button' ) }
					onChange={ ( e ) => {
						setSearch( e.target.value );
						setOpen( true );
					} }
					onFocus={ () => setOpen( true ) }
				/>
				{ searching && (
					<span className="wbte-ewb-product-search__spinner">
						<Spinner />
					</span>
				) }
			</div>

			{ open && search.trim().length >= MIN_SEARCH_LENGTH && (
				<div className="wbte-ewb-product-search__results">
					{ searching && <p className="wbte-ewb-product-search__hint">{ __( 'Searching…', 'wt-eu-withdrawal-button' ) }</p> }
					{ ! searching && results.length === 0 && (
						<p className="wbte-ewb-product-search__hint">{ __( 'No products found.', 'wt-eu-withdrawal-button' ) }</p>
					) }
					{ results.map( ( product ) => (
						<button
							key={ product.id }
							type="button"
							className="wbte-ewb-product-search__result"
							onClick={ () => addProduct( product ) }
						>
							{ product.name }
						</button>
					) ) }
				</div>
			) }
		</div>
	);
};

export default ProductSearchField;
