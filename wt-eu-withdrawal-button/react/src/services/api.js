/**
 * API service module for withdrawal request endpoints.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const API_BASE = 'wbte-ewb/v1';

/**
 * Build a clean query object by stripping empty values.
 *
 * @param {Object} params Raw query parameters.
 * @return {Object} Cleaned parameters.
 */
const cleanParams = ( params ) => {
	const cleaned = {};
	Object.entries( params || {} ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			cleaned[ key ] = value;
		}
	} );
	return cleaned;
};

/**
 * Fetch a paginated list of withdrawal requests.
 *
 * @param {Object} params Query parameters (page, per_page, status, search, date_from, date_to).
 * @return {Promise<Object>} Parsed response with { data, total, totalPages }.
 */
export const fetchRequests = async ( params = {} ) => {
	const path = addQueryArgs( `${ API_BASE }/requests`, cleanParams( params ) );
	const response = await apiFetch( {
		path,
		parse: false,
	} );

	const body = await response.json();
	const total = parseInt( response.headers.get( 'X-WP-Total' ) || '0', 10 );
	const totalPages = parseInt( response.headers.get( 'X-WP-TotalPages' ) || '0', 10 );

	return {
		data: body.data || [],
		total,
		totalPages,
	};
};

/**
 * Fetch a single withdrawal request by ID.
 *
 * @param {number} id Request ID.
 * @return {Promise<Object>} Parsed response body.
 */
export const fetchRequest = async ( id ) => {
	const response = await apiFetch( {
		path: `${ API_BASE }/requests/${ id }`,
	} );
	return response;
};

/**
 * Approve a withdrawal request.
 *
 * @param {number} id   Request ID.
 * @param {string} note Optional approval note.
 * @return {Promise<Object>} Parsed response body.
 */
export const approveRequest = async ( id, note = '' ) => {
	const response = await apiFetch( {
		path: `${ API_BASE }/requests/${ id }/approve`,
		method: 'POST',
		data: { note },
	} );
	return response;
};

/**
 * Reject a withdrawal request.
 *
 * @param {number} id   Request ID.
 * @param {string} note Rejection reason (required).
 * @return {Promise<Object>} Parsed response body.
 */
export const rejectRequest = async ( id, note ) => {
	const response = await apiFetch( {
		path: `${ API_BASE }/requests/${ id }/reject`,
		method: 'POST',
		data: { note },
	} );
	return response;
};

/**
 * Fetch dashboard statistics for withdrawal requests.
 *
 * @return {Promise<Object>} Stats data { open, open_last_7d, open_prev_7d, approved_30d, approved_prev_30d }.
 */
export const fetchStats = async () => {
	const response = await apiFetch( {
		path: `${ API_BASE }/requests/stats`,
	} );
	return response;
};

/**
 * Fetch plugin settings.
 *
 * @return {Promise<Object>} Parsed response body.
 */
export const fetchSettings = async () => {
	const response = await apiFetch( {
		path: `${ API_BASE }/settings`,
	} );
	return response;
};

/**
 * Update plugin settings.
 *
 * @param {Object} data Settings data.
 * @return {Promise<Object>} Parsed response body.
 */
export const updateSettings = async ( data ) => {
	const response = await apiFetch( {
		path: `${ API_BASE }/settings`,
		method: 'POST',
		data,
	} );
	return response;
};

/**
 * Fetch review banner state.
 *
 * @return {Promise<Object>} Banner state { show, milestone, status }.
 */
export const fetchReviewBanner = async () => {
	const response = await apiFetch( {
		path: `${ API_BASE }/review-banner`,
	} );

	return response?.data || response;
};

/**
 * Update review banner state.
 *
 * @param {string} action One of dismiss, review, or later.
 * @return {Promise<Object>} Updated banner state.
 */
export const updateReviewBanner = async ( action ) => {
	const response = await apiFetch( {
		path: `${ API_BASE }/review-banner`,
		method: 'POST',
		data: { action },
	} );

	return response?.data || response;
};
