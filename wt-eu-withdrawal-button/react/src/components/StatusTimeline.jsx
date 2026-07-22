/**
 * Visual timeline of status changes.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import getActorLabel from '../utils/getActorLabel';
import formatWithdrawalDatetime from '../utils/formatWithdrawalDatetime';

/**
 * Filter logs to only include status-change entries.
 *
 * @param {Array} logs All log entries.
 * @return {Array} Status-relevant log entries.
 */
const filterStatusLogs = ( logs ) => {
	return logs.filter(
		( log ) => log.from_status || log.to_status
	);
};

const StatusTimeline = ( { logs } ) => {
	const statusLogs = useMemo( () => filterStatusLogs( logs ), [ logs ] );

	if ( statusLogs.length === 0 ) {
		return (
			<p className="wbte-ewb-timeline__empty">
				{ __( 'No status changes recorded.', 'wt-eu-withdrawal-button' ) }
			</p>
		);
	}

	return (
		<div className="wbte-ewb-timeline">
			{ statusLogs.map( ( log, index ) => (
				<div key={ log.id || index } className="wbte-ewb-timeline__item">
					<div className="wbte-ewb-timeline__marker">
						<span className="wbte-ewb-timeline__dot" />
						{ index < statusLogs.length - 1 && (
							<span className="wbte-ewb-timeline__line" />
						) }
					</div>
					<div className="wbte-ewb-timeline__content">
						<div className="wbte-ewb-timeline__action">
							<strong>{ log.action }</strong>
							{ log.from_status && log.to_status && (
								<span className="wbte-ewb-timeline__transition">
									{ ` ${ log.from_status } → ${ log.to_status }` }
								</span>
							) }
						</div>
						<div className="wbte-ewb-timeline__meta">
							<span className="wbte-ewb-timeline__actor">
								{ getActorLabel( log ) }
							</span>
							<span className="wbte-ewb-timeline__date">
								{ formatWithdrawalDatetime( log ) }
							</span>
						</div>
						{ log.note && (
							<p className="wbte-ewb-timeline__note">{ log.note }</p>
						) }
					</div>
				</div>
			) ) }
		</div>
	);
};

export default StatusTimeline;
