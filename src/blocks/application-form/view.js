/**
 * Front-end interactivity for the Group Application Form block.
 *
 * Handles form submission via the GatherPress REST API.
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
	submitBtn.textContent = wp.i18n.__('Submitting...', 'gatherpress');
	messageEl.hidden = true;

	try {
		const response = await wp.apiFetch({
			path: '/gatherpress/v1/group-application/submit',
			method: 'POST',
			data,
		});

		// Show success message.
		messageEl.className =
			'wp-block-gatherpress-application-form__message wp-block-gatherpress-application-form__message--success';
		messageEl.textContent =
			response.message ||
			wp.i18n.__(
				'Your application has been submitted!',
				'gatherpress'
			);
		messageEl.hidden = false;

		// Disable the form.
		form.querySelectorAll('input, textarea, select, button').forEach(
			(el) => {
				el.disabled = true;
			}
		);
	} catch (error) {
		// Show error message.
		messageEl.className =
			'wp-block-gatherpress-application-form__message wp-block-gatherpress-application-form__message--error';
		messageEl.textContent =
			error.message ||
			wp.i18n.__('Something went wrong.', 'gatherpress');
		messageEl.hidden = false;

		// Restore button.
		submitBtn.disabled = false;
		submitBtn.textContent = wp.i18n.__(
			'Submit Application',
			'gatherpress'
		);
	}
}

// Initialize when DOM is ready.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initApplicationForm);
} else {
	initApplicationForm();
}
