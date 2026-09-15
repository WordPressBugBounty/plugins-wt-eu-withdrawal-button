/**
 * Main requests list page component.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useEffect, useState } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useLocation } from 'wouter';
import useRequests from '../hooks/useRequests';
import { fetchStats } from '../services/api';
import Filters from './Filters';
import UpgradeBanner, { shouldShowUpgradeBanner } from './UpgradeBanner';
import formatWithdrawalDatetime from '../utils/formatWithdrawalDatetime';

const PER_PAGE = 20;

/**
 * Format a date string for the requests list.
 *
 * @param {Object} request Request row from the REST API.
 * @return {string} Formatted date.
 */
const formatDate = ( request ) => formatWithdrawalDatetime( request, 'created_at', true );

/**
 * Get two-letter avatar initials from an email.
 *
 * @param {string} email Customer email.
 * @return {string} Two uppercase letters.
 */
const getInitials = ( email ) => {
	if ( ! email ) {
		return '??';
	}
	const username = email.split( '@' )[ 0 ] || '';
	return username.substring( 0, 2 ).toUpperCase();
};

/**
 * Format a request ID as #001.
 *
 * @param {number|string} id Request ID.
 * @return {string} Formatted ID.
 */
const formatId = ( id ) => {
	return '#' + String( id ).padStart( 3, '0' );
};

const RequestsList = () => {
	const {
		requests,
		total,
		totalPages,
		loading,
		error,
		fetchData,
		currentPage,
		setCurrentPage,
	} = useRequests();

	const [ , navigate ] = useLocation();
	const [ stats, setStats ] = useState( null );

	useEffect( () => {
		fetchData( { page: currentPage, per_page: PER_PAGE } );
	}, [ currentPage ] ); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect( () => {
		fetchStats().then( ( res ) => {
			setStats( res.data || res );
		} ).catch( () => {} );
	}, [] );

	const handleFilter = ( params ) => {
		setCurrentPage( 1 );
		fetchData( { ...params, page: 1, per_page: PER_PAGE } );
	};

	const handleRowClick = ( id ) => {
		navigate( `/request/${ id }` );
	};

	const handlePageClick = ( page ) => {
		setCurrentPage( page );
	};

	/* Build numbered page list */
	const pageNumbers = [];
	for ( let i = 1; i <= totalPages; i++ ) {
		pageNumbers.push( i );
	}

	const showFrom = ( currentPage - 1 ) * PER_PAGE + 1;
	const showTo = Math.min( currentPage * PER_PAGE, total );

	/* ── Compute stat deltas ── */
	let openDelta = null;
	if ( stats ) {
		const diff = stats.open_last_7d - stats.open_prev_7d;
		if ( diff === 0 ) {
			openDelta = { text: __( 'No change vs last week', 'wt-eu-withdrawal-button' ), cls: '' };
		} else {
			const arrow = diff > 0 ? '\u2191' : '\u2193';
			const cls = diff > 0 ? 'wbte-ewb-stat-card__delta--warn' : 'wbte-ewb-stat-card__delta--ok';
			openDelta = { text: `${ arrow } ${ Math.abs( diff ) } vs last week`, cls };
		}
	}

	let approvedDelta = null;
	if ( stats ) {
		if ( stats.approved_prev_30d === 0 && stats.approved_30d === 0 ) {
			approvedDelta = { text: __( 'No data', 'wt-eu-withdrawal-button' ), cls: '' };
		} else if ( stats.approved_prev_30d === 0 ) {
			approvedDelta = { text: '\u2191 100%', cls: 'wbte-ewb-stat-card__delta--ok' };
		} else {
			const pct = Math.round( ( ( stats.approved_30d - stats.approved_prev_30d ) / stats.approved_prev_30d ) * 100 );
			if ( pct === 0 ) {
				approvedDelta = { text: __( 'No change', 'wt-eu-withdrawal-button' ), cls: '' };
			} else {
				const arrow = pct > 0 ? '\u2191' : '\u2193';
				const cls = pct > 0 ? 'wbte-ewb-stat-card__delta--ok' : 'wbte-ewb-stat-card__delta--warn';
				approvedDelta = { text: `${ arrow } ${ Math.abs( pct ) }%`, cls };
			}
		}
	}

	const showUpgradeBanner = shouldShowUpgradeBanner();

	return (
		<div className={ `wbte-ewb-requests-list${ showUpgradeBanner ? ' wbte-ewb-requests-list--has-sidebar' : '' }` }>
		<div className="wbte-ewb-requests-list__main">
			{ /* ── Stat Strip ── */ }
			<div className="wbte-ewb-stats">
				<div className="wbte-ewb-stat-card">
					<div className="wbte-ewb-stat-card__label">
						<span className="wbte-ewb-stat-card__dot wbte-ewb-stat-card__dot--orange"></span>
						{ __( 'Open Requests', 'wt-eu-withdrawal-button' ) }
					</div>
					<div className="wbte-ewb-stat-card__value">
						{ stats ? stats.open : '\u2014' }
					</div>
					{ openDelta && (
						<div className={ `wbte-ewb-stat-card__delta ${ openDelta.cls }` }>
							{ openDelta.text }
						</div>
					) }
				</div>
				<div className="wbte-ewb-stat-card">
					<div className="wbte-ewb-stat-card__label">
						<span className="wbte-ewb-stat-card__dot wbte-ewb-stat-card__dot--green"></span>
						{ __( 'Approved (30d)', 'wt-eu-withdrawal-button' ) }
					</div>
					<div className="wbte-ewb-stat-card__value">
						{ stats ? stats.approved_30d : '\u2014' }
					</div>
					{ approvedDelta && (
						<div className={ `wbte-ewb-stat-card__delta ${ approvedDelta.cls }` }>
							{ approvedDelta.text }
						</div>
					) }
				</div>
			</div>

			{ /* ── Filter Bar ── */ }
			<Filters onFilter={ handleFilter } />

			{ /* ── Error ── */ }
			{ error && (
				<div className="wbte-ewb-notice wbte-ewb-notice--error">
					{ error }
				</div>
			) }

			{ /* ── Loading ── */ }
			{ loading && (
				<div className="wbte-ewb-req-card wbte-ewb-req-loading">
					<Spinner />
				</div>
			) }

			{ /* ── Empty State ── */ }
			{ ! loading && ! error && requests.length === 0 && (
				<div className="wbte-ewb-req-card wbte-ewb-req-empty">
					<p>{ __( 'No withdrawal requests found.', 'wt-eu-withdrawal-button' ) }</p>
				</div>
			) }

			{ /* ── Table ── */ }
			{ ! loading && requests.length > 0 && (
				<div className="wbte-ewb-req-card">
					{ /* Header */ }
					<div className="wbte-ewb-req-grid wbte-ewb-req-grid--header">
						<span>{ __( 'ID', 'wt-eu-withdrawal-button' ) }</span>
						<span>{ __( 'Order #', 'wt-eu-withdrawal-button' ) }</span>
						<span>{ __( 'Customer', 'wt-eu-withdrawal-button' ) }</span>
						<span>{ __( 'Type', 'wt-eu-withdrawal-button' ) }</span>
						<span>{ __( 'Status', 'wt-eu-withdrawal-button' ) }</span>
						<span>{ __( 'Date', 'wt-eu-withdrawal-button' ) }</span>
						<span style={ { textAlign: 'right' } }>{ __( 'Actions', 'wt-eu-withdrawal-button' ) }</span>
					</div>

					{ /* Rows */ }
					{ requests.map( ( req ) => {
						const isPartial = req.is_partial ?? req.request_type === 'partial';
						const typeBadgeClass = isPartial
							? 'wbte-ewb-type-badge--partial'
							: 'wbte-ewb-type-badge--full';

						let statusClass = 'wbte-ewb-status-pill--pending';
						if ( req.status === 'approved' ) {
							statusClass = 'wbte-ewb-status-pill--approved';
						} else if ( req.status === 'rejected' ) {
							statusClass = 'wbte-ewb-status-pill--rejected';
						}

						const statusLabel =
							req.status === 'approved'
								? __( 'Approved', 'wt-eu-withdrawal-button' )
								: req.status === 'rejected'
									? __( 'Rejected', 'wt-eu-withdrawal-button' )
									: __( 'Pending', 'wt-eu-withdrawal-button' );

						return (
							<div
								key={ req.id }
								className="wbte-ewb-req-grid wbte-ewb-req-grid--row"
								onClick={ () => handleRowClick( req.id ) }
								role="button"
								tabIndex={ 0 }
								onKeyDown={ ( e ) => {
									if ( e.key === 'Enter' ) {
										handleRowClick( req.id );
									}
								} }
							>
								<span className="wbte-ewb-req-cell--id">
									{ formatId( req.id ) }
								</span>
								<span className="wbte-ewb-req-cell--order">
									{ req.order_number || req.order_id || '\u2014' }
								</span>
								<span className="wbte-ewb-req-cell--customer">
									<span className="wbte-ewb-avatar">
										{ getInitials( req.customer_email ) }
									</span>
									<span className="wbte-ewb-req-cell--email">{ req.customer_email }</span>
								</span>
								<span>
									<span className={ `wbte-ewb-type-badge ${ typeBadgeClass }` }>
										<span className="wbte-ewb-type-badge__dot"></span>
										{ isPartial
											? __( 'Partial', 'wt-eu-withdrawal-button' )
											: __( 'Full', 'wt-eu-withdrawal-button' )
										}
									</span>
								</span>
								<span>
									<span className={ `wbte-ewb-status-pill ${ statusClass }` }>
										<span className="wbte-ewb-status-pill__dot"></span>
										{ statusLabel }
									</span>
								</span>
								<span className="wbte-ewb-req-cell--date">
									{ formatDate( req ) }
								</span>
								<span className="wbte-ewb-req-cell--actions">
									<button
										type="button"
										className="wbte-ewb-view-btn"
										onClick={ ( e ) => {
											e.stopPropagation();
											handleRowClick( req.id );
										} }
									>
										{ __( 'View', 'wt-eu-withdrawal-button' ) }
										<span aria-hidden="true"> &rarr;</span>
									</button>
								</span>
							</div>
						);
					} ) }

					{ /* Pagination */ }
					<div className="wbte-ewb-req-pagination">
						<span className="wbte-ewb-req-pagination__info">
							{ total > 0
								? sprintf(
									/* translators: 1: first visible item number, 2: last visible item number, 3: total item count */
									__( 'Showing %1$d–%2$d of %3$d', 'wt-eu-withdrawal-button' ),
									showFrom,
									showTo,
									total
								)
								: __( 'Showing 0 of 0', 'wt-eu-withdrawal-button' )
							}
						</span>
						<div className="wbte-ewb-req-pagination__pages">
							{ pageNumbers.map( ( p ) => (
								<button
									key={ p }
									type="button"
									className={
										`wbte-ewb-req-pagination__page-btn` +
										( p === currentPage ? ' wbte-ewb-req-pagination__page-btn--active' : '' )
									}
									onClick={ () => handlePageClick( p ) }
								>
									{ p }
								</button>
							) ) }
						</div>
						<span className="wbte-ewb-req-pagination__rows">
							{ `Rows per page: ${ PER_PAGE }` }
						</span>
					</div>
				</div>
			) }
		</div>{ /* /.wbte-ewb-requests-list__main */ }

		{ showUpgradeBanner && (
			<aside className="wbte-ewb-requests-list__sidebar">
				<UpgradeBanner />
			</aside>
		) }
		</div>
	);
};

export default RequestsList;
