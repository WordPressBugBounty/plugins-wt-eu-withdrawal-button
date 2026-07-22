/**
 * Display a withdrawal timestamp using the server-formatted value.
 *
 * @param {Object} item        Request or log object from the REST API.
 * @param {string} field       Raw datetime field name.
 * @param {boolean} dateOnly   Whether to return date-only formatting.
 * @return {string} Formatted datetime for display.
 */
const formatWithdrawalDatetime = ( item, field = 'created_at', dateOnly = false ) => {
	if ( ! item ) {
		return '\u2014';
	}

	const formattedKey = dateOnly ? `${ field }_date_formatted` : `${ field }_formatted`;

	if ( item[ formattedKey ] ) {
		return item[ formattedKey ];
	}

	if ( item[ field ] ) {
		return item[ field ];
	}

	return '\u2014';
};

export default formatWithdrawalDatetime;
