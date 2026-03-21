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
 * Edit component for the Group Directory block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update attributes.
 * @return {JSX.Element} The edit component.
 */
const Edit = ( { attributes, setAttributes } ) => {
	const { perPage, showSearch, columns } = attributes;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Directory Settings', 'gatherpress' ) }
				>
					<RangeControl
						label={ __( 'Groups per page', 'gatherpress' ) }
						value={ perPage }
						onChange={ ( value ) =>
							setAttributes( { perPage: value } )
						}
						min={ 4 }
						max={ 48 }
					/>
					<RangeControl
						label={ __( 'Columns', 'gatherpress' ) }
						value={ columns }
						onChange={ ( value ) =>
							setAttributes( { columns: value } )
						}
						min={ 1 }
						max={ 4 }
					/>
					<ToggleControl
						label={ __( 'Show search bar', 'gatherpress' ) }
						checked={ showSearch }
						onChange={ ( value ) =>
							setAttributes( { showSearch: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<Placeholder
				icon="groups"
				label={ __( 'Group Directory', 'gatherpress' ) }
				instructions={ __(
					'Displays a searchable directory of all community groups. Configure settings in the sidebar.',
					'gatherpress'
				) }
			/>
		</div>
	);
};

export default Edit;
