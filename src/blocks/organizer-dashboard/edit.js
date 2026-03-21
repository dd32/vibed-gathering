/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

/**
 * Edit component for the Organizer Dashboard block.
 *
 * @return {JSX.Element} The edit component.
 */
const Edit = () => {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Placeholder
				icon="dashboard"
				label={ __( 'Organizer Dashboard', 'gatherpress' ) }
				instructions={ __(
					'Displays group metrics, upcoming events, recent RSVPs, and quick actions for organizers. Only visible to organizers on the front-end.',
					'gatherpress'
				) }
			/>
		</div>
	);
};

export default Edit;
