/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import edit from './edit';
import metadata from './block.json';

/**
 * Register the Event Directory block.
 */
registerBlockType( metadata.name, {
	edit,
} );
