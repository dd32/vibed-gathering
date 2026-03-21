/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

/**
 * Edit component for the Join Group Button block.
 *
 * @return {JSX.Element} The edit component.
 */
const Edit = () => {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Placeholder
				icon="admin-users"
				label={ __( 'Join Group Button', 'gatherpress' ) }
				instructions={ __(
					'Displays a join/leave button for the current group. Shown on the front-end only.',
					'gatherpress'
				) }
			/>
		</div>
	);
};

export default Edit;
