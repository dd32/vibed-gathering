/**
 * Front-end interactivity for the Join Group Button block.
 *
 * Handles join/leave group actions via the GatherPress REST API
 * without requiring a full page reload.
 */

/**
 * Initialize all join group buttons on the page.
 */
function initJoinGroupButtons() {
	const buttons = document.querySelectorAll(
		'.wp-block-gatherpress-join-group-button [data-action]'
	);

	buttons.forEach((button) => {
		button.addEventListener('click', handleButtonClick);
	});
}

/**
 * Handle a join/leave button click.
 *
 * @param {Event} event The click event.
 */
async function handleButtonClick(event) {
	const button = event.currentTarget;
	const action = button.getAttribute('data-action');
	const wrapper = button.closest(
		'.wp-block-gatherpress-join-group-button'
	);
	const blogId = wrapper?.getAttribute('data-blog-id');

	if (!blogId || !action) {
		return;
	}

	// Disable button and show loading state.
	button.disabled = true;
	const originalText = button.textContent;
	button.textContent =
		action === 'join'
			? wp.i18n.__('Joining...', 'gatherpress')
			: wp.i18n.__('Leaving...', 'gatherpress');

	try {
		// Get a fresh nonce.
		const nonceResponse = await wp.apiFetch({
			path: '/gatherpress/v1/event/nonce',
		});

		const endpoint =
			action === 'join'
				? '/gatherpress/v1/group/join'
				: '/gatherpress/v1/group/leave';

		const response = await wp.apiFetch({
			path: endpoint,
			method: 'POST',
			data: { blog_id: parseInt(blogId, 10) },
			headers: {
				'X-WP-Nonce': nonceResponse.nonce,
			},
		});

		if (response.success) {
			// Reload to show updated state.
			window.location.reload();
		} else {
			throw new Error(response.message || 'Unknown error');
		}
	} catch (error) {
		// Restore button state on error.
		button.disabled = false;
		button.textContent = originalText;

		// Show error briefly.
		const errorEl = document.createElement('span');
		errorEl.className =
			'wp-block-gatherpress-join-group-button__error';
		errorEl.textContent = error.message || 'Something went wrong.';
		errorEl.setAttribute('role', 'alert');
		wrapper.appendChild(errorEl);

		setTimeout(() => errorEl.remove(), 5000);
	}
}

// Initialize when DOM is ready.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initJoinGroupButtons);
} else {
	initJoinGroupButtons();
}
