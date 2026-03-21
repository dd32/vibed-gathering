/**
 * Front-end interactivity for the Group Application Form block.
 *
 * Handles form submission via native fetch() since this runs as a
 * viewScriptModule where wp.apiFetch is unavailable.
 */

/**
 * Initialize the application form.
 */
function initApplicationForm() {
	const form = document.querySelector('[data-gp-application-form]');
	if (!form) {
		return;
	}

	form.addEventListener('submit', handleFormSubmit);
}

/**
 * Handle form submission.
 *
 * @param {Event} event The submit event.
 */
async function handleFormSubmit(event) {
	event.preventDefault();

	const form = event.currentTarget;
	const submitBtn = form.querySelector(
		'.wp-block-gatherpress-application-form__submit-btn'
	);
	const messageEl = form.querySelector(
		'.wp-block-gatherpress-application-form__message'
	);

	// Gather form data.
	const formData = new FormData(form);
	const data = {
		group_name: formData.get('group_name'),
		description: formData.get('description'),
		city: formData.get('city'),
		country: formData.get('country'),
		frequency: formData.get('frequency'),
		organizer_info: formData.get('organizer_info') || '',
	};

	// Show loading state.
	submitBtn.disabled = true;
	submitBtn.textContent = 'Submitting...';
	messageEl.hidden = true;

	try {
		// Get nonce from the form's hidden field.
		const nonce = formData.get('_wpnonce') || '';

		const response = await fetch(
			'/wp-json/gatherpress/v1/group-application/submit',
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify(data),
			}
		);

		const result = await response.json();

		if (response.ok && result.success !== false) {
			// Show success message.
			messageEl.className =
				'wp-block-gatherpress-application-form__message wp-block-gatherpress-application-form__message--success';
			messageEl.textContent =
				result.message ||
				'Your application has been submitted!';
			messageEl.hidden = false;

			// Disable the form.
			form.querySelectorAll(
				'input, textarea, select, button'
			).forEach((el) => {
				el.disabled = true;
			});
		} else {
			throw new Error(
				result.message || 'Submission failed.'
			);
		}
	} catch (error) {
		// Show error message.
		messageEl.className =
			'wp-block-gatherpress-application-form__message wp-block-gatherpress-application-form__message--error';
		messageEl.textContent =
			error.message || 'Something went wrong.';
		messageEl.hidden = false;

		// Restore button.
		submitBtn.disabled = false;
		submitBtn.textContent = 'Submit Application';
	}
}

// Initialize when DOM is ready.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initApplicationForm);
} else {
	initApplicationForm();
}
