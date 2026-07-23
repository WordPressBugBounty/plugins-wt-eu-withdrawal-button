/**
 * Resolve the display label for an activity log actor.
 *
 * @param {Object} log Activity log entry.
 * @return {string} Actor label.
 */
import { __ } from '@wordpress/i18n';

const getActorLabel = ( log ) => {
	if ( log.actor_type === 'system' ) {
		return log.actor_name || __( 'Auto-approval', 'wt-eu-withdrawal-button' );
	}

	if ( log.actor_name ) {
		return log.actor_name;
	}

	const actorId = Number( log.actor_id );

	if ( actorId > 0 ) {
		return `${ __( 'User', 'wt-eu-withdrawal-button' ) } #${ actorId }`;
	}

	if ( log.actor_type === 'customer' ) {
		return __( 'Guest user', 'wt-eu-withdrawal-button' );
	}

	return __( 'Unknown', 'wt-eu-withdrawal-button' );
};

export default getActorLabel;
