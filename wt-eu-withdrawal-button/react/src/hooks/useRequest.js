/**
 * Custom hook for managing a single withdrawal request.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useState, useCallback } from '@wordpress/element';
import {
	fetchRequest,
	approveRequest,
	rejectRequest,
} from '../services/api';

/**
 * Hook that manages single request state, including approve/reject actions.
 *
 * @return {Object} Request state and action methods.
 */
const useRequest = () => {
	const [ request, setRequest ] = useState( null );
	const [ logs, setLogs ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const fetch = useCallback( async ( id ) => {
		setLoading( true );
		setError( null );

		try {
			const response = await fetchRequest( id );
			const data = response.data || response;
			setRequest( data );
			setLogs( data.logs || [] );
		} catch ( err ) {
			setError( err.message || 'An error occurred while fetching the request.' );
			setRequest( null );
			setLogs( [] );
		} finally {
			setLoading( false );
		}
	}, [] );

	const approve = useCallback( async ( note = '' ) => {
		if ( ! request ) {
			return;
		}

		setLoading( true );
		setError( null );

		try {
			await approveRequest( request.id, note );
			await fetch( request.id );
		} catch ( err ) {
			setError( err.message || 'An error occurred while approving the request.' );
			setLoading( false );
		}
	}, [ request, fetch ] );

	const reject = useCallback( async ( note ) => {
		if ( ! request ) {
			return;
		}

		setLoading( true );
		setError( null );

		try {
			await rejectRequest( request.id, note );
			await fetch( request.id );
		} catch ( err ) {
			setError( err.message || 'An error occurred while rejecting the request.' );
			setLoading( false );
		}
	}, [ request, fetch ] );

	return {
		request,
		logs,
		loading,
		error,
		fetch,
		approve,
		reject,
	};
};

export default useRequest;
