// src/save.js

/**
 * The save function defines the way in which the different attributes should be combined
 * into the final markup, which is then serialized by the block editor into `post_content`.
 *
 * Since this block is dynamically rendered with PHP through `render_callback`,
 * this function returns `null`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 *
 * @return {null} Null, as the block is rendered server-side.
 */
export default function save() {
    return null;
}
