/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

/**
 * Edit component for the Event Feedback block.
 *
 * @return {JSX.Element} The edit component.
 */
const Edit = () => {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Placeholder
				icon="star-filled"
				label={ __( 'Event Feedback', 'gatherpress' ) }
				instructions={ __(
					'Shows a star rating form for past events and displays aggregated feedback. Only visible after the event date.',
					'gatherpress'
				) }
			/>
		</div>
	);
};

export default Edit;
