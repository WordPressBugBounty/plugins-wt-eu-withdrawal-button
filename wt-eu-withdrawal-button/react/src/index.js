/**
 * Entry point for the Consent Withdrawal admin dashboard.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import App from './App';
import './style.css';

// Set up nonce middleware for authenticated REST requests.
apiFetch.use( apiFetch.createNonceMiddleware( wbteEwbAdmin.nonce ) );
apiFetch.use( apiFetch.createRootURLMiddleware( wbteEwbAdmin.rest_url ) );

const container = document.getElementById( 'wbte-ewb-admin-root' );

if ( container ) {
	const root = createRoot( container );
	root.render( <App /> );
}
