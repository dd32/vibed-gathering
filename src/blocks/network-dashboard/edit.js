/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

/**
 * Edit component for the Network Dashboard block.
 *
 * @return {JSX.Element} The edit component.
 */
const Edit = () => {
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<Placeholder
				icon="admin-multisite"
				label={__('Network Dashboard', 'gatherpress')}
				instructions={__(
					'Shows network-wide metrics, pending applications, at-risk/dormant groups, and recent activity. Only visible to super admins.',
					'gatherpress'
				)}
			/>
		</div>
	);
};

export default Edit;
