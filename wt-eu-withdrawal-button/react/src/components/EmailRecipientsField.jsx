/**
 * Email recipient chips with add/remove controls.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * @param {string} email Email address.
 * @return {boolean}
 */
const isValidEmail = ( email ) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email );

/**
 * @param {Object}   props
 * @param {string[]} props.value    Selected email addresses.
 * @param {Function} props.onChange Change handler.
 */
const EmailRecipientsField = ( { value = [], onChange } ) => {
	const [ input, setInput ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const recipients = Array.isArray( value ) ? value.filter( Boolean ) : [];

	const addEmail = () => {
		const email = input.trim();

		if ( ! email ) {
			return;
		}

		if ( ! isValidEmail( email ) ) {
			setError( __( 'Please enter a valid email address.', 'wt-eu-withdrawal-button' ) );
			return;
		}

		if ( recipients.includes( email ) ) {
			setError( __( 'This email address is already added.', 'wt-eu-withdrawal-button' ) );
			return;
		}

		setError( '' );
		onChange( [ ...recipients, email ] );
		setInput( '' );
	};

	const removeEmail = ( email ) => {
		setError( '' );
		onChange( recipients.filter( ( item ) => item !== email ) );
	};

	const handleKeyDown = ( event ) => {
		if ( event.key === 'Enter' ) {
			event.preventDefault();
			addEmail();
		}
	};

	return (
		<div className="wbte-ewb-email-recipients">
			<div className="wbte-ewb-email-recipients__chips">
				{ recipients.map( ( email ) => (
					<span key={ email } className="wbte-ewb-email-recipients__chip">
						<span className="wbte-ewb-email-recipients__chip-label">{ email }</span>
						<button
							type="button"
							className="wbte-ewb-email-recipients__chip-remove"
							onClick={ () => removeEmail( email ) }
							aria-label={ __( 'Remove email', 'wt-eu-withdrawal-button' ) }
						>
							&times;
						</button>
					</span>
				) ) }
			</div>

			{ recipients.length === 0 && (
				<p className="wbte-ewb-email-recipients__error">
					{ __( 'Add at least one recipient to save settings.', 'wt-eu-withdrawal-button' ) }
				</p>
			) }

			<div className="wbte-ewb-email-recipients__add">
				<input
					type="email"
					className="wbte-ewb-input wbte-ewb-email-recipients__add-input"
					value={ input }
					placeholder={ __( 'Enter email address…', 'wt-eu-withdrawal-button' ) }
					onChange={ ( event ) => {
						setInput( event.target.value );
						if ( error ) {
							setError( '' );
						}
					} }
					onKeyDown={ handleKeyDown }
				/>
				<button
					type="button"
					className="wbte-ewb-btn wbte-ewb-btn--primary wbte-ewb-email-recipients__add-btn"
					onClick={ addEmail }
				>
					{ __( 'Add', 'wt-eu-withdrawal-button' ) }
				</button>
			</div>

			{ error && <p className="wbte-ewb-email-recipients__error">{ error }</p> }
		</div>
	);
};

export default EmailRecipientsField;
