/**
 * Front-end interactivity for the Join Group Button block.
 *
 * Handles join/leave group actions via the GatherPress REST API
 * without requiring a full page reload. Uses native fetch() since
 * this runs as a viewScriptModule where wp.apiFetch is unavailable.
 */

/**
 * Initialize all join group buttons on the page.
 */
function initJoinGroupButtons() {
	const buttons = document.querySelectorAll(
		'.wp-block-gatherpress-join-group-button [data-action]'
	);

	buttons.forEach( ( button ) => {
		button.addEventListener( 'click', handleButtonClick );
	} );
}

/**
 * Handle a join/leave button click.
 *
 * @param {Event} event The click event.
 */
async function handleButtonClick( event ) {
	const button = event.currentTarget;
	const action = button.getAttribute( 'data-action' );
	const wrapper = button.closest(
		'.wp-block-gatherpress-join-group-button'
	);
	const blogId = wrapper?.getAttribute( 'data-blog-id' );

	if ( ! blogId || ! action ) {
		return;
	}

	// Read translated strings from data attributes.
	const i18nJoining = wrapper?.dataset.i18nJoining || 'Joining...';
	const i18nLeaving = wrapper?.dataset.i18nLeaving || 'Leaving...';
	const i18nConfirm =
		wrapper?.dataset.i18nConfirmLeave ||
		'Are you sure you want to leave this group?';
	const i18nError =
		wrapper?.dataset.i18nError || 'Something went wrong.';

	// Confirm leave action.
	if ( 'leave' === action ) {
		// eslint-disable-next-line no-alert -- Intentional UX confirmation before destructive action.
		const confirmed = window.confirm( i18nConfirm );
		if ( ! confirmed ) {
			return;
		}
	}

	// Disable button and show loading state.
	button.disabled = true;
	const originalText = button.textContent;
	button.textContent = 'join' === action ? i18nJoining : i18nLeaving;

	try {
		const endpoint =
			'join' === action
				? '/wp-json/gatherpress/v1/group/join'
				: '/wp-json/gatherpress/v1/group/leave';

		const response = await fetch( endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': getRestNonce(),
			},
			body: JSON.stringify( { blog_id: parseInt( blogId, 10 ) } ),
		} );

		const data = await response.json();

		if ( data.success ) {
			// Reload to show updated state.
			window.location.reload();
		} else {
			throw new Error( data.message || 'Action failed.' );
		}
	} catch ( error ) {
		// Restore button state on error.
		button.disabled = false;
		button.textContent = originalText;

		// Show error briefly.
		const errorEl = document.createElement( 'span' );
		errorEl.className =
			'wp-block-gatherpress-join-group-button__error';
		errorEl.textContent = error.message || i18nError;
		errorEl.setAttribute( 'role', 'alert' );
		wrapper.appendChild( errorEl );

		setTimeout( () => errorEl.remove(), 5000 );
	}
}

/**
 * Get the WordPress REST nonce from the page.
 *
 * @return {string} The nonce value.
 */
function getRestNonce() {
	// Try wpApiSettings (enqueued by WordPress when user is logged in).
	if ( window.wpApiSettings?.nonce ) {
		return window.wpApiSettings.nonce;
	}

	// Fallback: look for a nonce in a meta tag or hidden input.
	const nonceEl = document.querySelector( '#_wpnonce, [name="_wpnonce"]' );
	if ( nonceEl ) {
		return nonceEl.value || nonceEl.getAttribute( 'content' ) || '';
	}

	return '';
}

// Initialize when DOM is ready.
if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', initJoinGroupButtons );
} else {
	initJoinGroupButtons();
}
