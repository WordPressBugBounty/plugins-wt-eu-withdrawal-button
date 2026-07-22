/**
 * Request detail page component.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useEffect, useState, useMemo } from '@wordpress/element';
import { Spinner, Modal, Button, TextareaControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useLocation } from 'wouter';
import useRequest from '../hooks/useRequest';
import StatusBadge from './StatusBadge';
import StatusTimeline from './StatusTimeline';
import ActivityLog from './ActivityLog';
import formatWithdrawalDatetime from '../utils/formatWithdrawalDatetime';

const RequestDetail = ( { params } ) => {
	const { id } = params;
	const { request, logs, loading, error, fetch, approve, reject } = useRequest();
	const [ , navigate ] = useLocation();
	const [ showRejectModal, setShowRejectModal ] = useState( false );
	const [ rejectNote, setRejectNote ] = useState( '' );
	const [ actionLoading, setActionLoading ] = useState( false );

	useEffect( () => {
		if ( id ) {
			fetch( id );
		}
	}, [ id ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const items = useMemo( () => {
		if ( Array.isArray( request?.items ) ) {
			return request.items;
		}

		if ( ! request?.items_json ) {
			return [];
		}

		try {
			return typeof request.items_json === 'string'
				? JSON.parse( request.items_json )
				: request.items_json;
		} catch {
			return [];
		}
	}, [ request ] );

	const formatItemTotal = ( item ) => {
		if ( item.total_formatted ) {
			return item.total_formatted;
		}

		if ( item.total || item.line_total ) {
			return item.total || item.line_total;
		}

		return '—';
	};

	const formatItemQuantity = ( item ) => {
		if ( item.quantity_label ) {
			return item.quantity_label;
		}

		return item.quantity || item.qty || '—';
	};

	const isPartialRequest = request?.is_partial ?? request?.request_type === 'partial';

	const handleApprove = async () => {
		setActionLoading( true );
		await approve( '' );
		setActionLoading( false );
	};

	const handleRejectSubmit = async () => {
		if ( ! rejectNote.trim() ) {
			return;
		}
		setActionLoading( true );
		await reject( rejectNote );
		setActionLoading( false );
		setShowRejectModal( false );
		setRejectNote( '' );
	};

	if ( loading && ! request ) {
		return (
			<div className="wbte-ewb-loading">
				<Spinner />
			</div>
		);
	}

	if ( error ) {
		return (
			<div className="wbte-ewb-detail">
				<button
					type="button"
					className="button"
					onClick={ () => navigate( '/' ) }
				>
					{ __( 'Back to List', 'wt-eu-withdrawal-button' ) }
				</button>
				<div className="wbte-ewb-notice wbte-ewb-notice--error" style={ { marginTop: '16px' } }>
					{ error }
				</div>
			</div>
		);
	}

	if ( ! request ) {
		return null;
	}

	const getWithdrawalItemBadge = ( item ) => {
		if ( ! item.in_withdrawal ) {
			return {
				label: __( 'Not included', 'wt-eu-withdrawal-button' ),
				className: 'wbte-ewb-item-badge wbte-ewb-item-badge--excluded',
			};
		}

		if ( 'approved' === request.status ) {
			return {
				label: __( 'Withdrawal approved', 'wt-eu-withdrawal-button' ),
				className: 'wbte-ewb-item-badge wbte-ewb-item-badge--approved',
			};
		}

		if ( 'rejected' === request.status ) {
			return {
				label: __( 'Withdrawal rejected', 'wt-eu-withdrawal-button' ),
				className: 'wbte-ewb-item-badge wbte-ewb-item-badge--rejected',
			};
		}

		return {
			label: __( 'Withdrawal request submitted', 'wt-eu-withdrawal-button' ),
			className: 'wbte-ewb-item-badge wbte-ewb-item-badge--submitted',
		};
	};

	const getWithdrawalRowClass = ( item ) => {
		if ( ! item.in_withdrawal ) {
			return '';
		}

		if ( 'approved' === request.status ) {
			return 'wbte-ewb-table__row--withdrawal-approved';
		}

		if ( 'rejected' === request.status ) {
			return 'wbte-ewb-table__row--withdrawal-rejected';
		}

		return 'wbte-ewb-table__row--withdrawal';
	};

	return (
		<div className="wbte-ewb-detail">
			<div className="wbte-ewb-detail__header">
				<button
					type="button"
					className="button"
					onClick={ () => navigate( '/' ) }
				>
					&larr; { __( 'Back to List', 'wt-eu-withdrawal-button' ) }
				</button>

				{ request.status === 'pending' && (
					<div className="wbte-ewb-detail__actions">
						<Button
							variant="primary"
							onClick={ handleApprove }
							disabled={ actionLoading }
							isBusy={ actionLoading }
						>
							{ __( 'Approve', 'wt-eu-withdrawal-button' ) }
						</Button>
						<Button
							variant="secondary"
							isDestructive
							onClick={ () => setShowRejectModal( true ) }
							disabled={ actionLoading }
						>
							{ __( 'Reject', 'wt-eu-withdrawal-button' ) }
						</Button>
					</div>
				) }
			</div>

			<div className="wbte-ewb-detail__grid">
				{ /* Left column: Request info */ }
				<div className="wbte-ewb-card">
					<h3 className="wbte-ewb-card__title">
						{ __( 'Request Information', 'wt-eu-withdrawal-button' ) }
					</h3>
					<table className="wbte-ewb-info-table">
						<tbody>
							<tr>
								<th>{ __( 'Order #', 'wt-eu-withdrawal-button' ) }</th>
								<td>
									{ request.order_edit_url ? (
										<a
											href={ request.order_edit_url }
											target="_blank"
											rel="noopener noreferrer"
											className="wbte-ewb-order-link"
										>
											{ request.order_number || request.order_id }
										</a>
									) : (
										request.order_number || request.order_id
									) }
								</td>
							</tr>
							<tr>
								<th>{ __( 'Customer Email', 'wt-eu-withdrawal-button' ) }</th>
								<td>{ request.customer_email }</td>
							</tr>
							<tr>
								<th>{ __( 'Request Type', 'wt-eu-withdrawal-button' ) }</th>
								<td>
									{ isPartialRequest
										? __( 'Partial Withdrawal', 'wt-eu-withdrawal-button' )
										: __( 'Full Withdrawal', 'wt-eu-withdrawal-button' )
									}
								</td>
							</tr>
							<tr>
								<th>{ __( 'Reason', 'wt-eu-withdrawal-button' ) }</th>
								<td>{ request.reason || __( 'No reason provided', 'wt-eu-withdrawal-button' ) }</td>
							</tr>
							<tr>
								<th>{ __( 'Created', 'wt-eu-withdrawal-button' ) }</th>
								<td>{ formatWithdrawalDatetime( request ) }</td>
							</tr>
							{ request.verification_code && (
								<tr className="wbte-ewb-info-table__row--verification">
									<th>{ __( 'Receipt hash', 'wt-eu-withdrawal-button' ) }</th>
									<td>
										<span className="wbte-ewb-verification-code">{ request.verification_code }</span>
									</td>
								</tr>
							) }
						</tbody>
					</table>
				</div>

				{ /* Right column: Status card */ }
				<div className="wbte-ewb-card">
					<h3 className="wbte-ewb-card__title">
						{ __( 'Status', 'wt-eu-withdrawal-button' ) }
					</h3>
					<table className="wbte-ewb-info-table">
						<tbody>
							<tr>
								<th>{ __( 'Withdrawal', 'wt-eu-withdrawal-button' ) }</th>
								<td><StatusBadge status={ request.status } /></td>
							</tr>
							{ request.order_status && (
								<tr>
									<th>{ __( 'Order', 'wt-eu-withdrawal-button' ) }</th>
									<td>{ request.order_status }</td>
								</tr>
							) }
							{ request.processed_at && (
								<tr>
									<th>{ __( 'Processed', 'wt-eu-withdrawal-button' ) }</th>
									<td>{ formatWithdrawalDatetime( request, 'processed_at' ) }</td>
								</tr>
							) }
						</tbody>
					</table>
				</div>
			</div>

			{ /* Order items */ }
			<div className="wbte-ewb-card" style={ { marginTop: '20px' } }>
				<h3 className="wbte-ewb-card__title">
					{ __( 'Order Items', 'wt-eu-withdrawal-button' ) }
				</h3>
				{ items.length > 0 ? (
					<div className="wbte-ewb-table-wrap">
						<table className="wbte-ewb-table widefat striped">
						<thead>
							<tr>
								<th>{ __( 'Product', 'wt-eu-withdrawal-button' ) }</th>
								<th>{ __( 'Quantity', 'wt-eu-withdrawal-button' ) }</th>
								<th>{ __( 'Line Total', 'wt-eu-withdrawal-button' ) }</th>
								{ isPartialRequest && (
									<th>{ __( 'Withdrawal', 'wt-eu-withdrawal-button' ) }</th>
								) }
							</tr>
						</thead>
						<tbody>
							{ items.map( ( item, index ) => {
								const withdrawalBadge = getWithdrawalItemBadge( item );

								return (
								<tr
									key={ item.line_item_id || item.product_id || index }
									className={ getWithdrawalRowClass( item ) }
								>
									<td>
										<span className="wbte-ewb-item-name">{ item.name || item.product_name || '—' }</span>
									</td>
									<td>{ formatItemQuantity( item ) }</td>
									<td>{ formatItemTotal( item ) }</td>
									{ isPartialRequest && (
										<td>
											<span className={ withdrawalBadge.className }>
												{ withdrawalBadge.label }
											</span>
										</td>
									) }
								</tr>
								);
							} ) }
						</tbody>
					</table>
					</div>
				) : (
					<p className="wbte-ewb-field__desc">
						{ __( 'No order items were found for this request.', 'wt-eu-withdrawal-button' ) }
					</p>
				) }
			</div>

			{ /* Status Timeline */ }
			{ logs.length > 0 && (
				<div className="wbte-ewb-card" style={ { marginTop: '20px' } }>
					<h3 className="wbte-ewb-card__title">
						{ __( 'Status Timeline', 'wt-eu-withdrawal-button' ) }
					</h3>
					<StatusTimeline logs={ logs } />
				</div>
			) }

			{ /* Activity Log */ }
			{ logs.length > 0 && (
				<div className="wbte-ewb-card" style={ { marginTop: '20px' } }>
					<h3 className="wbte-ewb-card__title">
						{ __( 'Activity Log', 'wt-eu-withdrawal-button' ) }
					</h3>
					<ActivityLog logs={ logs } />
				</div>
			) }

			{ /* Reject Modal */ }
			{ showRejectModal && (
				<Modal
					title={ __( 'Reject Request', 'wt-eu-withdrawal-button' ) }
					onRequestClose={ () => {
						setShowRejectModal( false );
						setRejectNote( '' );
					} }
				>
					<TextareaControl
						label={ __( 'Rejection Reason', 'wt-eu-withdrawal-button' ) }
						help={ __( 'A reason is required when rejecting a request.', 'wt-eu-withdrawal-button' ) }
						value={ rejectNote }
						onChange={ setRejectNote }
						rows={ 4 }
					/>
					<div style={ { display: 'flex', gap: '8px', justifyContent: 'flex-end', marginTop: '16px' } }>
						<Button
							variant="tertiary"
							onClick={ () => {
								setShowRejectModal( false );
								setRejectNote( '' );
							} }
						>
							{ __( 'Cancel', 'wt-eu-withdrawal-button' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ handleRejectSubmit }
							disabled={ ! rejectNote.trim() || actionLoading }
							isBusy={ actionLoading }
						>
							{ __( 'Reject Request', 'wt-eu-withdrawal-button' ) }
						</Button>
					</div>
				</Modal>
			) }

			{ loading && (
				<div className="wbte-ewb-loading-overlay">
					<Spinner />
				</div>
			) }
		</div>
	);
};

export default RequestDetail;
