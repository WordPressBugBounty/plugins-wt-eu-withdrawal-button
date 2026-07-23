/**
 * WebToffee Consent Withdrawal — Frontend JS
 *
 * Handles dynamic item loading, form validation, and AJAX submission
 * for both logged-in and guest withdrawal forms.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

/* global jQuery, wbte_ewb_frontend_params */
( function( $ ) {
	'use strict';

	var params  = wbte_ewb_frontend_params || {};
	var i18n    = params.i18n || {};
	var restUrl = params.rest_url || '';
	var nonce   = params.nonce || '';

	/**
	 * Fetch eligible items for a given order from the REST API.
	 *
	 * @param {number|string} orderId      The WooCommerce order ID.
	 * @param {string|null}   orderNumber  Order number (guest flow).
	 * @param {string|null}   email        Customer email (guest flow).
	 */
	/** Track whether items have been validated (for guest flow). */
	var itemsValidated = false;

	function setSubmitEnabled( enabled ) {
		var $btn = $( '#wbte-ewb-submit-btn' );
		if ( enabled ) {
			$btn.prop( 'disabled', false ).removeClass( 'disabled' );
		} else {
			$btn.prop( 'disabled', true ).addClass( 'disabled' );
		}
	}

	function fetchEligibleItems( orderId, orderNumber, email ) {
		var $container = $( '#wbte-ewb-items-container' );
		var $list      = $( '#wbte-ewb-items-list' );
		var $loading   = $( '#wbte-ewb-items-loading' );
		var isGuest    = !! ( orderNumber && email );

		itemsValidated = false;
		if ( isGuest ) {
			setSubmitEnabled( false );
		}

		$list.empty();
		$container.show();
		$loading.show();

		var url = restUrl + 'customer/orders/' + encodeURIComponent( orderId ) + '/eligible-items';

		var ajaxSettings = {
			url: url,
			method: 'GET',
			beforeSend: function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', nonce );
			},
			data: {}
		};

		// Guest requests need order_number + email for permission.
		if ( isGuest ) {
			ajaxSettings.data.order_number = orderNumber;
			ajaxSettings.data.email        = email;
		}

		$.ajax( ajaxSettings )
			.done( function( response ) {
				$loading.hide();

				var items = [];
				if ( response && response.data ) {
					items = response.data;
				} else if ( Array.isArray( response ) ) {
					items = response;
				}

				if ( ! items.length ) {
					$list.html(
						'<p class="wbte-ewb-no-items">' + escHtml( i18n.no_eligible_items || 'No eligible items found.' ) + '</p>'
					);
					return;
				}

				renderItems( items );
				itemsValidated = true;
				setSubmitEnabled( true );
			} )
			.fail( function( xhr ) {
				$loading.hide();
				itemsValidated = false;

				var msg = i18n.submit_error || 'An error occurred.';
				if ( xhr.responseJSON && xhr.responseJSON.message ) {
					msg = xhr.responseJSON.message;
				}

				$list.html( '<p class="wbte-ewb-error-message">' + escHtml( msg ) + '</p>' );

				if ( isGuest ) {
					setSubmitEnabled( false );
				}
			} );
	}

	/**
	 * Format a numeric price with the store currency symbol.
	 *
	 * @param {string|number} price Raw price value.
	 * @return {string} Formatted price with currency symbol.
	 */
	function formatPrice( price ) {
		if ( price === '' || price === null || typeof price === 'undefined' ) {
			return '';
		}

		var num      = parseFloat( price );
		if ( isNaN( num ) ) {
			return escHtml( price );
		}

		var decimals = parseInt( params.currency_decimals, 10 ) || 2;
		var decSep   = params.currency_decimal_sep || '.';
		var thousSep = params.currency_thousand_sep || ',';

		var fixed    = num.toFixed( decimals );
		var parts    = fixed.split( '.' );
		parts[0]     = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, thousSep );
		var formatted = parts.length > 1 ? parts[0] + decSep + parts[1] : parts[0];

		var symbol   = params.currency_symbol || '';
		var position = params.currency_position || 'left';

		switch ( position ) {
			case 'left':
				return symbol + formatted;
			case 'left_space':
				return symbol + ' ' + formatted;
			case 'right':
				return formatted + symbol;
			case 'right_space':
				return formatted + ' ' + symbol;
			default:
				return symbol + formatted;
		}
	}

	/**
	 * Build `<option>` elements for a withdrawal quantity select.
	 *
	 * @param {number} maxQty   Maximum order quantity.
	 * @param {number} selected Selected value.
	 * @return {string} HTML options.
	 */
	function buildQtySelectOptions( maxQty, selected ) {
		var html = '';

		for ( var i = 1; i <= maxQty; i++ ) {
			html += '<option value="' + i + '"' + ( i === selected ? ' selected' : '' ) + '>' + i + '</option>';
		}

		return html;
	}

	/**
	 * Resolve the withdrawal quantity for a checked item row.
	 *
	 * @param {jQuery} $checkbox Item checkbox element.
	 * @return {number} Quantity to withdraw.
	 */
	function getItemWithdrawQty( $checkbox ) {
		var maxQty = parseInt( $checkbox.data( 'max-qty' ), 10 ) || 1;
		var $qtySelect = $checkbox.closest( '.wbte-ewb-item' ).find( '.wbte-ewb-item-qty-select' );

		if ( $qtySelect.length ) {
			var val = parseInt( $qtySelect.val(), 10 );
			if ( isNaN( val ) ) {
				return maxQty;
			}
			return Math.min( Math.max( val, 1 ), maxQty );
		}

		return maxQty;
	}

	/**
	 * Render item checkboxes inside the items list container.
	 *
	 * @param {Array} items Array of item objects from the REST API.
	 */
	function renderItems( items ) {
		var $list           = $( '#wbte-ewb-items-list' );
		var allowPartial    = params.allow_partial_withdrawals === 'yes';
		var $requestType    = $( '#wbte_ewb_request_type' );

		$list.empty();

		$.each( items, function( index, item ) {
			var itemId       = item.line_item_id || item.item_id || item.id || index;
			var name         = item.name || '';
			var maxQty       = parseInt( item.quantity || item.qty || 0, 10 ) || 1;
			var orderedQty   = parseInt( item.ordered_quantity || item.quantity || item.qty || 0, 10 ) || maxQty;
			var price        = item.total || item.price || '';
			var unitPrice    = maxQty > 0 ? parseFloat( price ) / maxQty : parseFloat( price );
			var inputId      = 'wbte-ewb-item-' + itemId;
			var productId    = item.product_id || 0;
			var withdrawable = item.withdrawable !== false;
			var reason       = item.reason || '';
			var qtyMarkup;

			var html;

			if ( ! withdrawable ) {
				// Non-withdrawable: show disabled with reason.
				html = '<div class="wbte-ewb-item wbte-ewb-item--disabled">' +
					'<input type="checkbox" disabled />' +
					'<label class="wbte-ewb-item-name">' + escHtml( name ) + '</label>' +
					'<span class="wbte-ewb-item-qty">&times; ' + escHtml( maxQty ) + '</span>' +
					'<span class="wbte-ewb-item-price">' + formatPrice( price ) + '</span>' +
					'<span class="wbte-ewb-item-reason">' + escHtml( reason ) + '</span>' +
					'</div>';
			} else {
				var checked = ! allowPartial ? ' checked disabled' : ' checked';

				if ( maxQty > 1 && allowPartial ) {
					qtyMarkup =
						'<span class="wbte-ewb-item-qty wbte-ewb-item-qty--select">' +
							'&times; ' +
							'<input type="number" class="wbte-ewb-item-qty-select" name="item_qty[' + escAttr( itemId ) + ']"' +
							' value="' + escAttr( maxQty ) + '" min="1" max="' + escAttr( maxQty ) + '"' +
							' aria-label="' + escAttr( name + ' ' + ( i18n.withdraw_qty || 'quantity' ) ) + '" />' +
						'</span>';
				} else {
					qtyMarkup = '<span class="wbte-ewb-item-qty">&times; ' + escHtml( maxQty ) + '</span>';
				}

				html = '<div class="wbte-ewb-item">' +
					'<input type="checkbox" name="selected_items[]" value="' + escAttr( itemId ) + '"' +
					' data-product-id="' + escAttr( productId ) + '"' +
					' data-max-qty="' + escAttr( maxQty ) + '"' +
					' data-ordered-qty="' + escAttr( orderedQty ) + '"' +
					' data-unit-price="' + escAttr( unitPrice ) + '"' +
					' id="' + escAttr( inputId ) + '"' + checked + ' />' +
					'<label for="' + escAttr( inputId ) + '" class="wbte-ewb-item-name">' + escHtml( name ) + '</label>' +
					qtyMarkup +
					'<span class="wbte-ewb-item-price">' + formatPrice( price ) + '</span>' +
					'</div>';
			}

			$list.append( html );
		} );

		// Determine request type: if any items are excluded (non-withdrawable),
		// it's always partial even if all eligible items are selected.
		var totalItems    = items.length;
		var excludedCount = $list.find( '.wbte-ewb-item--disabled' ).length;
		var hasExcluded   = excludedCount > 0;

		function syncItemQtySelect( $checkbox ) {
			var $qtySelect = $checkbox.closest( '.wbte-ewb-item' ).find( '.wbte-ewb-item-qty-select' );

			if ( ! $qtySelect.length ) {
				return;
			}

			$qtySelect.prop( 'disabled', ! $checkbox.is( ':checked' ) );
		}

		function updateRequestType() {
			var eligibleChecked = $list.find( 'input[type="checkbox"]:not(:disabled):checked' ).length;
			var isPartial       = hasExcluded || eligibleChecked < totalItems;

			if ( ! isPartial ) {
				$list.find( 'input[type="checkbox"]:not(:disabled):checked' ).each( function() {
					var maxQty      = parseInt( $( this ).data( 'max-qty' ), 10 ) || 1;
					var orderedQty  = parseInt( $( this ).data( 'ordered-qty' ), 10 ) || maxQty;
					var withdrawQty = getItemWithdrawQty( $( this ) );

					if ( withdrawQty < maxQty || maxQty < orderedQty ) {
						isPartial = true;
						return false;
					}
				} );
			}

			$requestType.val( isPartial ? 'partial' : 'full' );
		}

		$list.find( 'input[type="checkbox"]:not(:disabled)' ).each( function() {
			syncItemQtySelect( $( this ) );
		} );

		updateRequestType();

		if ( allowPartial ) {
			$list.off( 'change.wbtecw' ).on( 'change.wbtecw', 'input[type="checkbox"]', function() {
				syncItemQtySelect( $( this ) );
				updateRequestType();
			} );

			$list.off( 'change.wbtecwQty input.wbtecwQty' ).on( 'change.wbtecwQty input.wbtecwQty', '.wbte-ewb-item-qty-select', function() {
				var $input = $( this );
				var max    = parseInt( $input.attr( 'max' ), 10 ) || 1;
				var val    = parseInt( $input.val(), 10 );
				if ( val > max ) { $input.val( max ); val = max; }
				if ( val < 1 )   { $input.val( 1 );   val = 1;   }
				updateRequestType();
				var $item     = $( this ).closest( '.wbte-ewb-item' );
				var $checkbox = $item.find( 'input[type="checkbox"]' );
				var unitPrice = parseFloat( $checkbox.data( 'unit-price' ) ) || 0;
				$item.find( '.wbte-ewb-item-price' ).text( formatPrice( unitPrice * val ) );
			} );
		}
	}

	/**
	 * Validate the form before submission.
	 *
	 * @return {boolean} True if valid, false otherwise.
	 */
	function validateForm() {
		var $form            = $( '#wbte-ewb-withdrawal-form' );
		var isGuestVerified  = $form.closest( '.wbte-ewb-form--guest-verified' ).length > 0;
		var isGuestQueue     = $form.closest( '.wbte-ewb-form--guest' ).length > 0 && ! isGuestVerified;
		var errors           = [];

		// Order selection.
		if ( isGuestQueue || isGuestVerified ) {
			if ( ! ( $( '#wbte_ewb_order_number' ).val() || '' ).trim() ) {
				errors.push( i18n.order_required || 'Please enter your order number.' );
			}
			if ( ! ( $( '#wbte_ewb_guest_email' ).val() || '' ).trim() ) {
				errors.push( i18n.email_required || 'Please enter a valid email address.' );
			}
		} else {
			if ( ! $( '#wbte_ewb_order_id' ).val() ) {
				errors.push( i18n.select_order || 'Please select an order.' );
			}
		}

		// Item selection for logged-in customers and verified guests.
		if ( ! isGuestQueue ) {
			var $items = $( '#wbte-ewb-items-list input[type="checkbox"]:not(:disabled)' );
			if ( $items.length && ! $items.filter( ':checked' ).length ) {
				errors.push( i18n.select_items || 'Please select at least one item.' );
			}

			$items.filter( ':checked' ).each( function() {
				var $checkbox = $( this );
				var maxQty    = parseInt( $checkbox.data( 'max-qty' ), 10 ) || 1;
				var withdrawQty = getItemWithdrawQty( $checkbox );

				if ( withdrawQty < 1 || withdrawQty > maxQty ) {
					errors.push( i18n.invalid_item_qty || 'Please enter a valid quantity for each selected item.' );
					return false;
				}
			} );
		}

		// Reason required on the initial guest queue form and logged-in form.
		if ( ! isGuestVerified && params.reason_required === 'yes' && ! ( $( '#wbte_ewb_reason' ).val() || '' ).trim() ) {
			errors.push( i18n.reason_required || 'Please provide a reason for withdrawal.' );
		}

		if ( errors.length ) {
			showMessages( errors, 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Submit the form via AJAX to the REST API.
	 *
	 * @param {jQuery} $form The form jQuery object.
	 */
	function submitViaAjax( $form ) {
		var $btn = $( '#wbte-ewb-submit-btn' );

		$btn.prop( 'disabled', true ).addClass( 'disabled' );
		showMessages( [ i18n.loading || 'Loading...' ], 'info' );

		// Build the REST API payload.
		var isGuestVerified = $form.closest( '.wbte-ewb-form--guest-verified' ).length > 0;
		var isGuestQueue    = $form.closest( '.wbte-ewb-form--guest' ).length > 0 && ! isGuestVerified;
		var requestType     = $( '#wbte_ewb_request_type' ).val() || 'full';

		var formData = {
			request_type: requestType,
			reason: $( '#wbte_ewb_reason' ).val() || ''
		};

		var submitUrl = restUrl + 'customer/requests';

		if ( isGuestQueue ) {
			submitUrl = restUrl + 'customer/guest-pending-requests';
			formData = {
				order_number: $( '#wbte_ewb_order_number' ).val(),
				email: $( '#wbte_ewb_guest_email' ).val(),
				reason: $( '#wbte_ewb_reason' ).val() || ''
			};
		} else if ( isGuestVerified ) {
			submitUrl = restUrl + 'customer/guest-verified-requests';
			formData = {
				verify_token: $( '#wbte_ewb_guest_token' ).val(),
				request_type: requestType
			};
		} else {
			formData.order_id = parseInt( $( '#wbte_ewb_order_id' ).val(), 10 ) || 0;
		}

		if ( ! isGuestQueue ) {
			// Build items array from checked checkboxes.
			var items = [];
			$( '#wbte-ewb-items-list input[type="checkbox"]:checked' ).each( function() {
				items.push( {
					line_item_id: parseInt( $( this ).val(), 10 ) || 0,
					product_id:   parseInt( $( this ).data( 'product-id' ), 10 ) || 0,
					qty:          getItemWithdrawQty( $( this ) )
				} );
			} );

			if ( items.length ) {
				formData.items = items;
			}
		}

		$.ajax( {
			url: submitUrl,
			method: 'POST',
			beforeSend: function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', nonce );
			},
			contentType: 'application/json',
			data: JSON.stringify( formData )
		} )
			.done( function( response ) {
				if ( isGuestVerified ) {
					var currentParams = new URLSearchParams( window.location.search );
					currentParams.set( 'wbte_ewb_guest_submitted', '1' );
					var successUrl = window.location.pathname + '?' + currentParams.toString();
					window.location.href = successUrl;
					return;
				}

				var msg = isGuestQueue
					? ( i18n.guest_queue_success || 'Thank you. If the details you provided are correct, you will receive a verification email to complete your withdrawal request.' )
					: ( i18n.submit_success || 'Your withdrawal request has been submitted successfully.' );

				if ( response && response.message ) {
					msg = response.message;
				}
				showMessages( [ msg ], 'success' );

				// Reset form.
				$form[ 0 ].reset();

				if ( ! isGuestQueue ) {
					$( '#wbte-ewb-items-container' ).hide();
					$( '#wbte-ewb-items-list' ).empty();
				}
			} )
			.fail( function( xhr ) {
				var msg = i18n.submit_error || 'An error occurred. Please try again.';
				if ( xhr.responseJSON && xhr.responseJSON.message ) {
					msg = xhr.responseJSON.message;
				}
				showMessages( [ msg ], 'error' );
			} )
			.always( function() {
				$btn.prop( 'disabled', false ).removeClass( 'disabled' );
			} );
	}

	/**
	 * Display messages above the form.
	 *
	 * @param {Array}  messages Array of message strings.
	 * @param {string} type     Message type: 'success', 'error', or 'info'.
	 */
	function showMessages( messages, type ) {
		var $form    = $( '#wbte-ewb-withdrawal-form' );
		var $wrapper = $form.closest( '.wbte-ewb-withdrawal-form' );

		// Remove existing messages.
		$wrapper.find( '.wbte-ewb-success-message, .wbte-ewb-error-message, .wbte-ewb-info-message' ).remove();

		var cssClass = 'wbte-ewb-error-message';
		if ( 'success' === type ) {
			cssClass = 'wbte-ewb-success-message';
		} else if ( 'info' === type ) {
			cssClass = 'wbte-ewb-info-message';
		}

		var html = '<div class="' + cssClass + '" role="alert">';
		if ( messages.length === 1 ) {
			html += '<p>' + escHtml( messages[ 0 ] ) + '</p>';
		} else {
			html += '<ul>';
			$.each( messages, function( _, msg ) {
				html += '<li>' + escHtml( msg ) + '</li>';
			} );
			html += '</ul>';
		}
		html += '</div>';

		$form.before( html );

		// Scroll to the message.
		$( 'html, body' ).animate( {
			scrollTop: $wrapper.offset().top - 32
		}, 300 );
	}

	/**
	 * Escape HTML entities for safe insertion.
	 *
	 * @param {*} str The value to escape.
	 * @return {string}
	 */
	function escHtml( str ) {
		if ( str === null || str === undefined ) {
			return '';
		}
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	/**
	 * Escape a value for use in HTML attributes.
	 *
	 * @param {*} str The value to escape.
	 * @return {string}
	 */
	function escAttr( str ) {
		return escHtml( str );
	}

	/** @type {jQuery|null} */
	var $confirmModal   = null;
	var confirmCallback = null;

	/**
	 * Create or return the confirmation modal element.
	 *
	 * @return {jQuery}
	 */
	function getConfirmModal() {
		if ( $confirmModal ) {
			return $confirmModal;
		}

		var html =
			'<div id="wbte-ewb-confirm-modal" class="wbte-ewb-confirm-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="wbte-ewb-confirm-modal-title" aria-describedby="wbte-ewb-confirm-modal-intro" hidden>' +
				'<div class="wbte-ewb-confirm-modal__overlay" tabindex="-1"></div>' +
				'<div class="wbte-ewb-confirm-modal__dialog">' +
					'<div class="wbte-ewb-confirm-modal__header">' +
						'<div class="wbte-ewb-confirm-modal__header-main">' +
							'<div class="wbte-ewb-confirm-modal__icon" aria-hidden="true">' +
								'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
									'<path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
									'<path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" stroke="currentColor" stroke-width="2"/>' +
								'</svg>' +
							'</div>' +
							'<h2 id="wbte-ewb-confirm-modal-title" class="wbte-ewb-confirm-modal__title"></h2>' +
						'</div>' +
					'</div>' +
					'<div class="wbte-ewb-confirm-modal__body">' +
						'<p id="wbte-ewb-confirm-modal-intro" class="wbte-ewb-confirm-modal__intro"></p>' +
						'<div id="wbte-ewb-confirm-modal-review" class="wbte-ewb-confirm-modal__review"></div>' +
					'</div>' +
					'<div class="wbte-ewb-confirm-modal__footer">' +
						'<button type="button" class="wbte-ewb-btn wbte-ewb-btn--secondary" id="wbte-ewb-confirm-cancel"></button>' +
						'<button type="button" class="wbte-ewb-btn wbte-ewb-btn--withdrawal" id="wbte-ewb-confirm-submit"></button>' +
					'</div>' +
				'</div>' +
			'</div>';

		$confirmModal = $( html );
		$( 'body' ).append( $confirmModal );

		$confirmModal.find( '.wbte-ewb-confirm-modal__overlay, #wbte-ewb-confirm-cancel' ).on( 'click', closeConfirmModal );
		$confirmModal.find( '#wbte-ewb-confirm-submit' ).on( 'click', function() {
			if ( confirmCallback ) {
				confirmCallback();
			}
			closeConfirmModal();
		} );

		$( document ).on( 'keydown.wbtecwConfirm', function( e ) {
			if ( 'Escape' === e.key && $confirmModal.hasClass( 'is-active' ) ) {
				closeConfirmModal();
			}
		} );

		return $confirmModal;
	}

	/**
	 * Close the confirmation modal.
	 *
	 * @return {void}
	 */
	function closeConfirmModal() {
		var $modal = getConfirmModal();

		$modal.removeClass( 'is-active' ).attr( 'aria-hidden', 'true' ).prop( 'hidden', true );
		$( 'body' ).removeClass( 'wbte-ewb-confirm-modal-open' );
		confirmCallback = null;
	}

	/**
	 * Get the customer display name from the form fields.
	 *
	 * @return {string}
	 */
	function getCustomerName() {
		var first = ( $( '#wbte_ewb_first_name' ).val() || $( '#wbte_ewb_billing_first_name' ).val() || '' ).trim();
		var last  = ( $( '#wbte_ewb_last_name' ).val() || $( '#wbte_ewb_billing_last_name' ).val() || '' ).trim();

		return ( first + ' ' + last ).trim();
	}

	/**
	 * Build read-only confirmation summary rows from the current form state.
	 *
	 * @param {jQuery} $form The withdrawal form.
	 * @return {Array<{label: string, value: string, isHtml?: boolean}>}
	 */
	function buildConfirmationReview( $form ) {
		var isGuestVerified = $form.closest( '.wbte-ewb-form--guest-verified' ).length > 0;
		var rows            = [];
		var orderText       = '';
		var customerName    = getCustomerName();

		if ( customerName ) {
			rows.push( {
				label: i18n.label_customer || 'Customer',
				value: customerName,
			} );
		}

		if ( isGuestVerified ) {
			orderText = ( $( '#wbte_ewb_order_number' ).val() || '' ).trim();
			if ( orderText ) {
				orderText = '#' + orderText.replace( /^#/, '' );
			}
		} else {
			orderText = ( $( '#wbte_ewb_order_id option:selected' ).text() || '' ).trim();
		}

		if ( orderText ) {
			rows.push( {
				label: i18n.label_order || 'Order',
				value: orderText,
			} );
		}

		var requestType = $( '#wbte_ewb_request_type' ).val() || 'full';
		rows.push( {
			label: i18n.label_request_type || 'Withdrawal type',
			value: 'partial' === requestType
				? ( i18n.request_type_partial || 'Partial withdrawal' )
				: ( i18n.request_type_full || 'Full withdrawal' ),
		} );

		var items = [];
		$( '#wbte-ewb-items-list input[type="checkbox"]:checked' ).each( function() {
			var $checkbox = $( this );
			var name      = $checkbox.closest( '.wbte-ewb-item' ).find( '.wbte-ewb-item-name' ).first().text();
			var qty       = getItemWithdrawQty( $checkbox );

			items.push( escHtml( name ) + ' &times; ' + escHtml( qty ) );
		} );

		if ( items.length ) {
			var itemsHtml = '<ul class="wbte-ewb-confirm-modal__items">';
			$.each( items, function( _, itemText ) {
				var parts    = String( itemText ).split( ' &times; ' );
				var itemName = parts[ 0 ] || '';
				var itemQty  = parts[ 1 ] || '';

				itemsHtml +=
					'<li class="wbte-ewb-confirm-modal__item">' +
						'<span class="wbte-ewb-confirm-modal__item-name">' + itemName + '</span>' +
						'<span class="wbte-ewb-confirm-modal__item-qty">&times; ' + itemQty + '</span>' +
					'</li>';
			} );
			itemsHtml += '</ul>';

			rows.push( {
				label: i18n.label_items || 'Items to withdraw',
				value: itemsHtml,
				isHtml: true,
				isItems: true,
			} );
		}

		var reason = ( $( '#wbte_ewb_reason' ).val() || '' ).trim();
		if ( reason ) {
			rows.push( {
				label: i18n.label_reason || 'Reason for withdrawal',
				value: reason,
				isReason: true,
			} );
		}

		return rows;
	}

	/**
	 * Open the confirmation modal and run a callback when confirmed.
	 *
	 * @param {jQuery}   $form      The withdrawal form.
	 * @param {Function} onConfirm  Callback when the customer confirms withdrawal.
	 * @return {void}
	 */
	function openConfirmModal( $form, onConfirm ) {
		var $modal     = getConfirmModal();
		var reviewHtml = '';

		confirmCallback = onConfirm;

		$modal.find( '#wbte-ewb-confirm-modal-title' ).text( i18n.confirm_title || 'Review your withdrawal request' );
		$modal.find( '#wbte-ewb-confirm-modal-intro' ).text(
			i18n.confirm_intro || 'Please review the details below. To complete your statutory withdrawal, click Confirm withdrawal.'
		);
		$modal.find( '#wbte-ewb-confirm-cancel' ).text( i18n.confirm_cancel || 'Cancel' );
		$modal.find( '#wbte-ewb-confirm-submit' ).text( i18n.confirm_withdrawal || 'Confirm withdrawal' );

		$.each( buildConfirmationReview( $form ), function( _, row ) {
			var fieldClass = 'wbte-ewb-confirm-modal__field';

			if ( row.isItems ) {
				fieldClass += ' wbte-ewb-confirm-modal__field--items';
			} else if ( row.isReason ) {
				fieldClass += ' wbte-ewb-confirm-modal__field--multiline';
			}

			reviewHtml += '<div class="' + fieldClass + '">';
			reviewHtml += '<div class="wbte-ewb-confirm-modal__label">' + escHtml( row.label ) + '</div>';
			reviewHtml += row.isHtml
				? '<div class="wbte-ewb-confirm-modal__value">' + row.value + '</div>'
				: '<div class="wbte-ewb-confirm-modal__value">' + escHtml( row.value ) + '</div>';
			reviewHtml += '</div>';
		} );

		$modal.find( '#wbte-ewb-confirm-modal-review' ).html( reviewHtml );
		$modal.addClass( 'is-active' ).attr( 'aria-hidden', 'false' ).prop( 'hidden', false );
		$( 'body' ).addClass( 'wbte-ewb-confirm-modal-open' );
		$modal.find( '#wbte-ewb-confirm-submit' ).trigger( 'focus' );
	}

	// -------------------------------------------------------
	// DOM-ready bindings
	// -------------------------------------------------------
	$( function() {

		var $loggedInForm      = $( '.wbte-ewb-form--logged-in' );
		var $guestVerifiedForm = $( '.wbte-ewb-form--guest-verified' );

		// Logged-in: order select change -> fetch items.
		$loggedInForm.find( '#wbte_ewb_order_id' ).on( 'change', function() {
			var orderId = $( this ).val();

			if ( ! orderId ) {
				$loggedInForm.find( '#wbte-ewb-items-container' ).hide();
				$loggedInForm.find( '#wbte-ewb-items-list' ).empty();
				return;
			}

			fetchEligibleItems( orderId, null, null );
		} );

		// If an order is pre-selected, load items immediately.
		if ( $loggedInForm.find( '#wbte_ewb_order_id' ).val() ) {
			$loggedInForm.find( '#wbte_ewb_order_id' ).trigger( 'change' );
		}

		// Verified guest: load eligible items immediately.
		if ( $guestVerifiedForm.length ) {
			var verifiedOrderId = $guestVerifiedForm.find( '#wbte_ewb_order_id' ).val();
			var verifiedNumber  = $guestVerifiedForm.find( '#wbte_ewb_order_number' ).val();
			var verifiedEmail   = $guestVerifiedForm.find( '#wbte_ewb_guest_email' ).val();

			if ( verifiedOrderId ) {
				fetchEligibleItems( verifiedOrderId, verifiedNumber, verifiedEmail );
			}
		}

		// Form submit: validate and AJAX submit.
		$( '#wbte-ewb-withdrawal-form' ).on( 'submit', function( e ) {
			e.preventDefault();

			if ( ! validateForm() ) {
				return;
			}

			var $form           = $( this );
			var isGuestQueue    = $form.closest( '.wbte-ewb-form--guest' ).length > 0 && ! $form.closest( '.wbte-ewb-form--guest-verified' ).length;

			if ( isGuestQueue ) {
				submitViaAjax( $form );
				return;
			}

			openConfirmModal( $form, function() {
				submitViaAjax( $form );
			} );
		} );

	} );

} )( jQuery );
