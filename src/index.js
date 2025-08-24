/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import Edit from './edit';
import save from './save';
import metadata from '../build/block.json'; // Adjust path if block.json is in src
// If your block.json is in the src folder and copied to build, you might import from './block.json'
// But since we created block.json directly in build, we refer to it there.

/**
 * Styles
 */
import './style.scss'; // For general styles (frontend and editor)
import './editor.scss'; // For editor-only styles (already imported in edit.js, but can be here too for structure)

/**
 * Register the block
 */
registerBlockType( metadata.name, {
    /**
     * @see ./edit.js
     */
    edit: Edit,

    /**
     * @see ./save.js
     */
    save, // equivalent to save: save
} );
