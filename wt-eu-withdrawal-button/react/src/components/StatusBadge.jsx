/**
 * Status badge component.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { __ } from '@wordpress/i18n';

const STATUS_STYLES = {
	pending: {
		backgroundColor: '#fcf0e3',
		color: '#9a6700',
		border: '1px solid #f0c674',
	},
	approved: {
		backgroundColor: '#e6f4ea',
		color: '#1a7f37',
		border: '1px solid #a3d9a5',
	},
	rejected: {
		backgroundColor: '#fde8e8',
		color: '#c93c37',
		border: '1px solid #f5a5a5',
	},
};

const STATUS_LABELS = {
	pending: () => __( 'Pending', 'wt-eu-withdrawal-button' ),
	approved: () => __( 'Approved', 'wt-eu-withdrawal-button' ),
	rejected: () => __( 'Rejected', 'wt-eu-withdrawal-button' ),
};

const StatusBadge = ( { status } ) => {
	const style = STATUS_STYLES[ status ] || STATUS_STYLES.pending;
	const getLabel = STATUS_LABELS[ status ] || STATUS_LABELS.pending;

	const badgeStyle = {
		...style,
		display: 'inline-block',
		padding: '2px 10px',
		borderRadius: '12px',
		fontSize: '12px',
		fontWeight: '600',
		lineHeight: '20px',
		textTransform: 'capitalize',
	};

	return (
		<span className={ `wbte-ewb-badge wbte-ewb-badge--${ status }` } style={ badgeStyle }>
			{ getLabel() }
		</span>
	);
};

export default StatusBadge;
