/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	Placeholder,
} from '@wordpress/components';

/**
 * Edit component for the Group Members block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update attributes.
 * @return {JSX.Element} The edit component.
 */
const Edit = ( { attributes, setAttributes } ) => {
	const { limit, showRole } = attributes;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'gatherpress' ) }>
					<RangeControl
						label={ __( 'Members to show', 'gatherpress' ) }
						value={ limit }
						onChange={ ( value ) =>
							setAttributes( { limit: value } )
						}
						min={ 4 }
						max={ 100 }
					/>
					<ToggleControl
						label={ __( 'Show role badge', 'gatherpress' ) }
						checked={ showRole }
						onChange={ ( value ) =>
							setAttributes( { showRole: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<Placeholder
				icon="groups"
				label={ __( 'Group Members', 'gatherpress' ) }
				instructions={ __(
					'Displays a grid of group members with avatars. Configure in the sidebar.',
					'gatherpress'
				) }
			/>
		</div>
	);
};

export default Edit;
