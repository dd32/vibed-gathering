/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

/**
 * Edit component for the Application Form block.
 *
 * @return {JSX.Element} The edit component.
 */
const Edit = () => {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Placeholder
				icon="clipboard"
				label={ __( 'Group Application Form', 'gatherpress' ) }
				instructions={ __(
					'Displays a form for users to apply to create a new community group. Only shown to logged-in users on the main site.',
					'gatherpress'
				) }
			/>
		</div>
	);
};

export default Edit;
