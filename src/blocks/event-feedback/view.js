/**
 * Front-end interactivity for the Event Feedback block.
 *
 * Handles star rating selection with hover preview, and feedback
 * form submission via native fetch().
 */

function initFeedbackForm() {
	const form = document.querySelector( '[data-gp-feedback-form]' );
	if ( ! form ) {
		return;
	}

	// Read i18n strings from data attributes.
	const i18n = {
		selectRating:
			form.dataset.i18nSelectRating || 'Please select a star rating.',
		submitting: form.dataset.i18nSubmitting || 'Submitting...',
		submit: form.dataset.i18nSubmit || 'Submit Feedback',
		success:
			form.dataset.i18nSuccess || 'Thank you for your feedback!',
		error: form.dataset.i18nError || 'Something went wrong.',
	};

	const starBtns = form.querySelectorAll(
		'.wp-block-gatherpress-event-feedback__star-btn'
	);
	const ratingInput = form.querySelector( 'input[name="rating"]' );
	let currentRating = 0;

	// Star rating click handler.
	starBtns.forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			currentRating = parseInt(
				btn.getAttribute( 'data-rating' ),
				10
			);
			ratingInput.value = currentRating;
			updateStarDisplay( starBtns, currentRating );
		} );
	} );

	// Star rating hover preview.
	starBtns.forEach( ( btn ) => {
		btn.addEventListener( 'mouseenter', () => {
			const hoverRating = parseInt(
				btn.getAttribute( 'data-rating' ),
				10
			);
			updateStarDisplay( starBtns, hoverRating );
		} );
	} );

	// Restore to current rating on mouse leave.
	const starSelect = form.querySelector(
		'.wp-block-gatherpress-event-feedback__star-select'
	);
	if ( starSelect ) {
		starSelect.addEventListener( 'mouseleave', () => {
			updateStarDisplay( starBtns, currentRating );
		} );
	}

	// Form submission.
	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		const messageEl = form.querySelector(
			'.wp-block-gatherpress-event-feedback__message'
		);

		if ( ! ratingInput.value ) {
			showMessage( messageEl, i18n.selectRating, 'error' );
			return;
		}

		const submitBtn = form.querySelector(
			'.wp-block-gatherpress-event-feedback__submit'
		);
		const wrapper = form.closest(
			'.wp-block-gatherpress-event-feedback'
		);
		const eventId = wrapper?.getAttribute( 'data-event-id' );

		submitBtn.disabled = true;
		submitBtn.textContent = i18n.submitting;
		messageEl.hidden = true;

		try {
			const formData = new FormData( form );
			const nonce = formData.get( '_wpnonce' ) || '';

			const response = await fetch(
				'/wp-json/gatherpress/v1/event/feedback',
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
					},
					body: JSON.stringify( {
						post_id: parseInt( eventId, 10 ),
						rating: parseInt( ratingInput.value, 10 ),
						comment: formData.get( 'comment' ) || '',
					} ),
				}
			);

			const data = await response.json();

			if ( response.ok ) {
				showMessage(
					messageEl,
					data.message || i18n.success,
					'success'
				);
				form.querySelectorAll(
					'input, textarea, button'
				).forEach( ( el ) => {
					el.disabled = true;
				} );
			} else {
				throw new Error( data.message || i18n.error );
			}
		} catch ( error ) {
			showMessage( messageEl, error.message || i18n.error, 'error' );
			submitBtn.disabled = false;
			submitBtn.textContent = i18n.submit;
		}
	} );
}

/**
 * Update the visual display of star buttons.
 *
 * @param {NodeList} buttons The star buttons.
 * @param {number}   rating  The rating to display (1-5, or 0 for none).
 */
function updateStarDisplay( buttons, rating ) {
	buttons.forEach( ( btn ) => {
		const r = parseInt( btn.getAttribute( 'data-rating' ), 10 );
		btn.classList.toggle( 'selected', r <= rating );
	} );
}

/**
 * Show a message in the feedback form.
 *
 * @param {Element} el   The message element.
 * @param {string}  text The message text.
 * @param {string}  type 'success' or 'error'.
 */
function showMessage( el, text, type ) {
	el.className = `wp-block-gatherpress-event-feedback__message wp-block-gatherpress-event-feedback__message--${ type }`;
	el.textContent = text;
	el.hidden = false;
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', initFeedbackForm );
} else {
	initFeedbackForm();
}
