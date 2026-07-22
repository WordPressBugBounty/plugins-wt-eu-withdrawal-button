/**
 * Review request banner for the admin dashboard.
 *
 * @package Wbte_Eu_Withdrawal_Button
 */

import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fetchReviewBanner, updateReviewBanner } from '../services/api';

const REVIEW_URL = 'https://wordpress.org/support/plugin/wt-eu-withdrawal-button/reviews/#new-post';

const ReviewBanner = () => {
	const [ visible, setVisible ] = useState( false );
	const [ milestone, setMilestone ] = useState( '' );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		let mounted = true;

		const loadBanner = async () => {
			try {
				const state = await fetchReviewBanner();

				if ( ! mounted ) {
					return;
				}

				setVisible( Boolean( state?.show ) );
				setMilestone( state?.milestone || '' );
			} catch {
				if ( mounted ) {
					setVisible( false );
				}
			} finally {
				if ( mounted ) {
					setLoading( false );
				}
			}
		};

		loadBanner();

		return () => {
			mounted = false;
		};
	}, [] );

	const handleAction = async ( action ) => {
		setVisible( false );

		try {
			await updateReviewBanner( action );
		} catch {
			// Keep hidden after optimistic update even if the request fails.
		}
	};

	const handleReviewClick = ( event ) => {
		event.preventDefault();
		handleAction( 'review' );
		window.open( REVIEW_URL, '_blank', 'noopener,noreferrer' );
	};

	if ( loading || ! visible ) {
		return null;
	}

	const message =
		milestone === 'withdrawals'
			? __( 'You\'ve received 10+ withdrawal requests. Leave a review for WebToffee EU Withdrawal Button!', 'wt-eu-withdrawal-button' )
			: __( 'You\'ve been using WebToffee EU Withdrawal Button for a week. Leave a review!', 'wt-eu-withdrawal-button' );

	return (
		<div className="wbte-ewb-review-banner" role="region" aria-label={ __( 'Review request', 'wt-eu-withdrawal-button' ) }>
			<div className="wbte-ewb-review-banner__main">
				<div className="wbte-ewb-review-banner__icon" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 2l2.9 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l7.1-1.01L12 2z" />
					</svg>
				</div>
				<div className="wbte-ewb-review-banner__content">
					<p className="wbte-ewb-review-banner__text">{ message }</p>
				</div>
			</div>
			<div className="wbte-ewb-review-banner__actions">
				<a
					className="wbte-ewb-review-banner__link"
					href={ REVIEW_URL }
					target="_blank"
					rel="noopener noreferrer"
					onClick={ handleReviewClick }
				>
					{ __( 'Leave a review', 'wt-eu-withdrawal-button' ) }
				</a>
				<button
					type="button"
					className="wbte-ewb-review-banner__later"
					onClick={ () => handleAction( 'later' ) }
				>
					{ __( 'Remind me later', 'wt-eu-withdrawal-button' ) }
				</button>
				<button
					type="button"
					className="wbte-ewb-review-banner__dismiss"
					onClick={ () => handleAction( 'dismiss' ) }
					aria-label={ __( 'Dismiss review request', 'wt-eu-withdrawal-button' ) }
				>
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
		</div>
	);
};

export default ReviewBanner;
