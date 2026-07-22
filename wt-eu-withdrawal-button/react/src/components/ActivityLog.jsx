/**
 * Full activity log table component.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import getActorLabel from '../utils/getActorLabel';
import formatWithdrawalDatetime from '../utils/formatWithdrawalDatetime';

const ActivityLog = ( { logs } ) => {
	// Sort logs with most recent first.
	const sortedLogs = useMemo( () => {
		return [ ...logs ].sort( ( a, b ) => {
			const dateA = new Date( a.created_at || 0 );
			const dateB = new Date( b.created_at || 0 );
			return dateB - dateA;
		} );
	}, [ logs ] );

	if ( sortedLogs.length === 0 ) {
		return (
			<p>{ __( 'No activity recorded.', 'wt-eu-withdrawal-button' ) }</p>
		);
	}

	return (
		<div className="wbte-ewb-table-wrap">
			<table className="wbte-ewb-table widefat striped">
			<thead>
				<tr>
					<th>{ __( 'Date', 'wt-eu-withdrawal-button' ) }</th>
					<th>{ __( 'Actor', 'wt-eu-withdrawal-button' ) }</th>
					<th>{ __( 'Action', 'wt-eu-withdrawal-button' ) }</th>
					<th>{ __( 'Note', 'wt-eu-withdrawal-button' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ sortedLogs.map( ( log, index ) => (
					<tr key={ log.id || index }>
						<td>{ formatWithdrawalDatetime( log ) }</td>
						<td>{ getActorLabel( log ) }</td>
						<td>{ log.action }</td>
						<td>{ log.note || '—' }</td>
					</tr>
				) ) }
			</tbody>
		</table>
		</div>
	);
};

export default ActivityLog;
