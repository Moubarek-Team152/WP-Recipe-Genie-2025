// build/index.js (Simulated build output)
(function (blocks, blockEditor, components, i18n, element) {
    const { __ } = i18n;
    const { useBlockProps, InspectorControls } = blockEditor;
    const { PanelBody, TextControl } = components;
    const { Fragment, createElement } = element;

    // --- Edit Function (derived from src/edit.js) ---
    function Edit( { attributes, setAttributes } ) {
        // Note: useBlockProps() adds necessary class names and attributes to the block's wrapper.
        // For apiVersion 2+, it should be applied to the root element returned by Edit.
        const blockProps = useBlockProps(); 
        const { postTitle, focusKeyword, relatedKeyword, internalLink, externalLink } = attributes;

        return createElement( Fragment, null, // Use Fragment to return multiple top-level elements
            createElement( InspectorControls, null, // InspectorControls for the sidebar
                createElement( PanelBody, { title: __( 'Recipe Details', 'recipe-generator' ) },
                    createElement( TextControl, {
                        label: __( 'Post Title', 'recipe-generator' ),
                        value: postTitle,
                        onChange: ( val ) => setAttributes( { postTitle: val } ),
                        help: __( 'The main title for your recipe post.', 'recipe-generator' )
                    }),
                    createElement( TextControl, {
                        label: __( 'Focus Keyword', 'recipe-generator' ),
                        value: focusKeyword,
                        onChange: ( val ) => setAttributes( { focusKeyword: val } ),
                        help: __( 'The primary keyword for SEO.', 'recipe-generator' )
                    }),
                    createElement( TextControl, {
                        label: __( 'Related Keyword', 'recipe-generator' ),
                        value: relatedKeyword,
                        onChange: ( val ) => setAttributes( { relatedKeyword: val } ),
                        help: __( 'A secondary keyword.', 'recipe-generator' )
                    }),
                    createElement( TextControl, {
                        label: __( 'Internal Link', 'recipe-generator' ),
                        value: internalLink,
                        type: 'url',
                        onChange: ( val ) => setAttributes( { internalLink: val } ),
                        help: __( 'URL for the focus keyword (your site).', 'recipe-generator' )
                    }),
                    createElement( TextControl, {
                        label: __( 'External Link', 'recipe-generator' ),
                        value: externalLink,
                        type: 'url',
                        onChange: ( val ) => setAttributes( { externalLink: val } ),
                        help: __( 'URL for the related keyword (external site).', 'recipe-generator' )
                    })
                )
            ),
            // Main block content visible in the editor
            createElement( 'div', { ...blockProps }, // Apply blockProps to the main wrapper div
                createElement( 'div', { className: 'recipe-generator-editor-preview' },
                    createElement( 'h4', null, __( 'Recipe Generator Configuration', 'recipe-generator' ) ),
                    createElement( 'p', null, __( 'Enter the details in the sidebar. The recipe will be generated on the frontend when the post is viewed.', 'recipe-generator' ) ),
                    postTitle && createElement( 'p', null, createElement('strong', null, __('Title:', 'recipe-generator')), ` ${postTitle}` ),
                    focusKeyword && createElement( 'p', null, createElement('strong', null, __('Focus:', 'recipe-generator')), ` ${focusKeyword}` ),
                    relatedKeyword && createElement( 'p', null, createElement('strong', null, __('Related:', 'recipe-generator')), ` ${relatedKeyword}` ),
                    createElement( 'p', null, createElement( 'em', null, __( 'A preview of the generated content is not shown here. Save and view the page.', 'recipe-generator' ) ) )
                )
            )
        );
    }

    // --- Save Function (derived from src/save.js) ---
    function save() {
        // For dynamic blocks rendered server-side, the save function should return null.
        return null;
    }

    // --- Block Registration ---
    // 'recipe-generator/block' is the name from block.json.
    // WordPress merges attributes and other metadata from block.json automatically
    // when register_block_type is used with the block.json path in PHP.
    blocks.registerBlockType( 'recipe-generator/block', {
        edit: Edit,
        save: save,
        // title, icon, category, attributes etc. are supplied from block.json by PHP's register_block_type.
    } );

})(
    window.wp.blocks,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n,
    window.wp.element
);
