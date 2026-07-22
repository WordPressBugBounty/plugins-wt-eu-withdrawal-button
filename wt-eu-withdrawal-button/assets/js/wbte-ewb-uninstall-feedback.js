( function ( $ ) {
	'use strict';

	$( function () {
		var params = window.wbteEwbUninstallFeedback || {};
		var modal = $( '#wbte-ewb-uninstall-modal' );
		var deactivateLink = '';

		if ( ! modal.length ) {
			return;
		}

		$( '#the-list' ).on( 'click', 'a.wbte-ewb-deactivate-link', function ( e ) {
			e.preventDefault();
			modal.addClass( 'is-active' ).attr( 'aria-hidden', 'false' );
			deactivateLink = $( this ).attr( 'href' );
			modal.find( 'a.wbte-ewb-uninstall-skip' ).attr( 'href', deactivateLink );
			modal.find( 'input[type="radio"]:checked' ).prop( 'checked', false );
			modal.find( '.reason-input' ).remove();
			modal.find( '.wbte-ewb-uninstall-sub-reasons' ).hide();
		} );

		modal.on( 'click', 'button.wbte-ewb-uninstall-cancel', function ( e ) {
			e.preventDefault();
			modal.removeClass( 'is-active' ).attr( 'aria-hidden', 'true' );
		} );

		modal.on( 'click', 'input[type="radio"]', function () {
			var reasonId = $( this ).val();
			var parent = $( this ).parents( 'li:first' );
			var inputType = parent.data( 'type' );

			modal.find( '.reason-input' ).remove();

			if ( 'wbte-ewb-selected-reason' === $( this ).attr( 'name' ) ) {
				modal.find( '.wbte-ewb-uninstall-sub-reasons' ).hide();
			}

			if ( 'main_reason' === inputType ) {
				modal.find( '.wbte-ewb-uninstall-sub-reasons[data-parent="' + reasonId + '"]' ).show();
				modal.find( '.wbte-ewb-uninstall-sub-reasons[data-parent="' + reasonId + '"] input[type="radio"]:checked' ).trigger( 'click' );
				return;
			}

			var inputPlaceholder = parent.data( 'placeholder' );
			var reasonInputHtml = '<div class="reason-input">' +
				( ( 'text' === inputType ) ? '<input type="text" class="input-text" size="40" />' : '<textarea rows="5" cols="45"></textarea>' ) +
				'</div>';

			if ( inputType ) {
				parent.append( $( reasonInputHtml ) );
				parent.find( 'input, textarea' ).attr( 'placeholder', inputPlaceholder ).focus();
			}
		} );

		modal.on( 'click', '#wbte-ewb-contact-me-checkbox', function () {
			var emailWrap = $( '#wbte-ewb-email-field-wrap' );
			if ( $( this ).is( ':checked' ) ) {
				emailWrap.prop( 'hidden', false ).show();
			} else {
				emailWrap.prop( 'hidden', true ).hide();
				$( '#wbte-ewb-contact-email' ).val( '' );
				$( '#wbte-ewb-email-error' ).prop( 'hidden', true ).hide();
			}
		} );

		modal.on( 'click', 'button.wbte-ewb-uninstall-submit', function ( e ) {
			e.preventDefault();

			var button = $( this );
			if ( button.hasClass( 'disabled' ) ) {
				return;
			}

			var reasonId = 'none';
			var reasonInfo = '';
			var $radio = $( 'input[type="radio"][name="wbte-ewb-selected-reason"]:checked', modal );

			if ( $radio.length > 0 ) {
				reasonId = $radio.val();
				var $selectedReason = $radio.parents( 'li:first' );

				if ( 'main_reason' === $selectedReason.attr( 'data-type' ) ) {
					var subReason = $selectedReason.find( '.wbte-ewb-uninstall-sub-reasons' );
					var subReasonInput = subReason.find( 'input[type="radio"][name="wbte-ewb-selected-sub-reason"]:checked' );

					if ( subReasonInput.length > 0 ) {
						reasonId += ' | ' + subReasonInput.val();
						var subReasonInfoInput = subReasonInput.parents( 'li:first' ).find( 'textarea, input[type="text"]' );
						if ( subReasonInfoInput.length > 0 ) {
							reasonInfo = ( subReasonInfoInput.val() || '' ).trim();
						}
					}
				} else {
					var reasonInfoInput = $selectedReason.find( 'textarea, input[type="text"]' );
					if ( reasonInfoInput.length > 0 ) {
						reasonInfo = ( reasonInfoInput.val() || '' ).trim();
					}
				}
			}

			var emailCheckbox = $( '#wbte-ewb-contact-me-checkbox' );
			var emailField = $( '#wbte-ewb-contact-email' );
			var emailError = $( '#wbte-ewb-email-error' );
			emailError.prop( 'hidden', true ).hide();

			if ( emailCheckbox.is( ':checked' ) ) {
				var emailVal = emailField.val();
				var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				if ( ! emailVal || ! emailPattern.test( emailVal ) ) {
					emailError.text( params.i18n.invalid_email || 'Please enter a valid email address.' ).prop( 'hidden', false ).show();
					emailField.focus();
					return;
				}
			}

			$.ajax( {
				url: params.ajaxurl,
				type: 'POST',
				data: {
					action: 'wbte_ewb_submit_uninstall_reason',
					_wpnonce: params.nonce,
					reason_id: reasonId,
					reason_info: reasonInfo,
					user_email: emailCheckbox.is( ':checked' ) ? emailField.val() : ''
				},
				beforeSend: function () {
					button.addClass( 'disabled' );
					button.text( params.i18n.processing || 'Processing...' );
				},
				complete: function () {
					window.location.href = deactivateLink;
				}
			} );
		} );
	} );
}( jQuery ) );
