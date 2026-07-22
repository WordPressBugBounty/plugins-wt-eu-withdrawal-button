/**
 * Admin order meta box interactions.
 *
 * Handles Approve / Reject button clicks via AJAX to the REST API
 * and updates the meta box UI accordingly.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

/* global jQuery, wbte_ewb_admin_order_params */
( function ( $ ) {
	'use strict';

	var params = typeof wbte_ewb_admin_order_params !== 'undefined' ? wbte_ewb_admin_order_params : {};

	if ( ! params.rest_url || ! params.nonce ) {
		return;
	}

	/**
	 * Prompt for a required rejection note.
	 *
	 * @return {string|null} Note text, or null when cancelled/invalid.
	 */
	function promptRejectNote() {
		var note = window.prompt( params.i18n.reject_note_prompt, '' ); // eslint-disable-line no-alert

		if ( null === note ) {
			return null;
		}

		note = note.trim();

		if ( ! note ) {
			window.alert( params.i18n.reject_note_required ); // eslint-disable-line no-alert
			return null;
		}

		return note;
	}

	/**
	 * Send an action (approve / reject) to the REST API.
	 *
	 * @param {jQuery}  $button   The clicked button element.
	 * @param {string}  action    Either 'approve' or 'reject'.
	 * @param {number}  requestId The withdrawal request ID.
	 */
	function processAction( $button, action, requestId ) {
		var confirmMsg = 'approve' === action
			? params.i18n.confirm_approve
			: params.i18n.confirm_reject;
		var payload = {};
		var note;

		if ( 'reject' === action ) {
			note = promptRejectNote();

			if ( null === note ) {
				return;
			}

			payload.note = note;
		}

		if ( ! window.confirm( confirmMsg ) ) { // eslint-disable-line no-alert
			return;
		}

		var $actions = $button.closest( '.wbte-ewb-metabox-actions' );
		var $buttons = $actions.find( '.button' );

		// Set loading state.
		$buttons.addClass( 'wbte-ewb-loading' ).prop( 'disabled', true );
		$button.text( params.i18n.processing );

		$.ajax( {
			url: params.rest_url + 'requests/' + requestId + '/' + action,
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify( payload ),
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', params.nonce );
			},
			success: function () {
				// Reload the page to reflect updated state.
				window.location.reload();
			},
			error: function ( jqXHR ) {
				var message = params.i18n.error;

				if ( jqXHR.responseJSON && jqXHR.responseJSON.message ) {
					message = jqXHR.responseJSON.message;
				}

				window.alert( message ); // eslint-disable-line no-alert

				// Reset button state.
				$buttons.removeClass( 'wbte-ewb-loading' ).prop( 'disabled', false );
				$button.text( 'approve' === action ? params.i18n.approve : params.i18n.reject );
			}
		} );
	}

	/**
	 * Hide the metabox review prompt and persist the action via REST.
	 *
	 * @param {jQuery} $banner Review prompt container.
	 * @param {string} action  dismiss, review, or later.
	 */
	function processReviewAction( $banner, action ) {
		$banner.slideUp( 200 );

		$.ajax( {
			url: params.rest_url + 'review-banner',
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify( { action: action } ),
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', params.nonce );
			},
		} );
	}

	$( document ).ready( function () {
		// Approve button.
		$( document ).on( 'click', '.wbte-ewb-approve-btn', function ( e ) {
			e.preventDefault();
			var $btn = $( this );
			processAction( $btn, 'approve', $btn.data( 'request-id' ) );
		} );

		// Reject button.
		$( document ).on( 'click', '.wbte-ewb-reject-btn', function ( e ) {
			e.preventDefault();
			var $btn = $( this );
			processAction( $btn, 'reject', $btn.data( 'request-id' ) );
		} );

		// Metabox review prompt actions.
		$( document ).on( 'click', '.wbte-ewb-metabox-review__dismiss', function ( e ) {
			e.preventDefault();
			processReviewAction( $( this ).closest( '.wbte-ewb-metabox-review' ), 'dismiss' );
		} );

		$( document ).on( 'click', '.wbte-ewb-metabox-review__later', function ( e ) {
			e.preventDefault();
			processReviewAction( $( this ).closest( '.wbte-ewb-metabox-review' ), 'later' );
		} );

		$( document ).on( 'click', '.wbte-ewb-metabox-review__link', function () {
			processReviewAction( $( this ).closest( '.wbte-ewb-metabox-review' ), 'review' );
		} );
	} );

} )( jQuery );
