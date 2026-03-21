/**
 * Front-end interactivity for the Event Feedback block.
 *
 * Handles star rating selection and feedback form submission.
 */

function initFeedbackForm() {
	const form = document.querySelector('[data-gp-feedback-form]');
	if (!form) {
		return;
	}

	// Star rating selection.
	const starBtns = form.querySelectorAll(
		'.wp-block-gatherpress-event-feedback__star-btn'
	);
	const ratingInput = form.querySelector('input[name="rating"]');

	starBtns.forEach((btn) => {
		btn.addEventListener('click', () => {
			const rating = btn.getAttribute('data-rating');
			ratingInput.value = rating;

			// Update visual state.
			starBtns.forEach((b) => {
				const r = parseInt(b.getAttribute('data-rating'), 10);
				b.classList.toggle('selected', r <= parseInt(rating, 10));
			});
		});
	});

	// Form submission.
	form.addEventListener('submit', async (event) => {
		event.preventDefault();

		const submitBtn = form.querySelector(
			'.wp-block-gatherpress-event-feedback__submit'
		);
		const messageEl = form.querySelector(
			'.wp-block-gatherpress-event-feedback__message'
		);
		const wrapper = form.closest(
			'.wp-block-gatherpress-event-feedback'
		);
		const eventId = wrapper?.getAttribute('data-event-id');

		if (!ratingInput.value) {
			messageEl.className =
				'wp-block-gatherpress-event-feedback__message wp-block-gatherpress-event-feedback__message--error';
			messageEl.textContent = 'Please select a star rating.';
			messageEl.hidden = false;
			return;
		}

		submitBtn.disabled = true;
		submitBtn.textContent = 'Submitting...';
		messageEl.hidden = true;

		try {
			const formData = new FormData(form);
			const nonce = formData.get('_wpnonce') || '';

			const response = await fetch(
				'/wp-json/gatherpress/v1/event/feedback',
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
					},
					body: JSON.stringify({
						post_id: parseInt(eventId, 10),
						rating: parseInt(ratingInput.value, 10),
						comment: formData.get('comment') || '',
					}),
				}
			);

			const data = await response.json();

			if (response.ok) {
				messageEl.className =
					'wp-block-gatherpress-event-feedback__message wp-block-gatherpress-event-feedback__message--success';
				messageEl.textContent =
					data.message || 'Thank you for your feedback!';
				messageEl.hidden = false;
				form.querySelectorAll(
					'input, textarea, button'
				).forEach((el) => {
					el.disabled = true;
				});
			} else {
				throw new Error(data.message || 'Submission failed.');
			}
		} catch (error) {
			messageEl.className =
				'wp-block-gatherpress-event-feedback__message wp-block-gatherpress-event-feedback__message--error';
			messageEl.textContent =
				error.message || 'Something went wrong.';
			messageEl.hidden = false;
			submitBtn.disabled = false;
			submitBtn.textContent = 'Submit Feedback';
		}
	});
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initFeedbackForm);
} else {
	initFeedbackForm();
}
