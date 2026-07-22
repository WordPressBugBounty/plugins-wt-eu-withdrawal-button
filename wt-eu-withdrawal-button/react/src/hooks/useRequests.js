/**
 * Custom hook for managing the withdrawal requests list.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useState, useCallback } from '@wordpress/element';
import { fetchRequests } from '../services/api';

/**
 * Hook that manages requests list state, pagination, and data fetching.
 *
 * @return {Object} Requests state and helpers.
 */
const useRequests = () => {
	const [ requests, setRequests ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ totalPages, setTotalPages ] = useState( 0 );
	const [ currentPage, setCurrentPage ] = useState( 1 );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const fetchData = useCallback( async ( params = {} ) => {
		setLoading( true );
		setError( null );

		try {
			const result = await fetchRequests( params );
			setRequests( result.data );
			setTotal( result.total );
			setTotalPages( result.totalPages );
		} catch ( err ) {
			setError( err.message || 'An error occurred while fetching requests.' );
			setRequests( [] );
			setTotal( 0 );
			setTotalPages( 0 );
		} finally {
			setLoading( false );
		}
	}, [] );

	return {
		requests,
		total,
		totalPages,
		loading,
		error,
		fetchData,
		currentPage,
		setCurrentPage,
	};
};

export default useRequests;
