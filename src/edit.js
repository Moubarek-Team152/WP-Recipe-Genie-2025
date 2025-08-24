import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import './editor.scss'; // We'll create this later if needed, or use index.css from build.

export default function Edit( { attributes, setAttributes } ) {
    const blockProps = useBlockProps();
    const { postTitle, focusKeyword, relatedKeyword, internalLink, externalLink } = attributes;

    return (
        <div { ...blockProps }>
            <InspectorControls>
                <PanelBody title={ __( 'Recipe Details', 'recipe-generator' ) }>
                    <TextControl
                        label={ __( 'Post Title', 'recipe-generator' ) }
                        value={ postTitle }
                        onChange={ ( val ) => setAttributes( { postTitle: val } ) }
                        help={ __( 'The main title for your recipe post.', 'recipe-generator' ) }
                    />
                    <TextControl
                        label={ __( 'Focus Keyword', 'recipe-generator' ) }
                        value={ focusKeyword }
                        onChange={ ( val ) => setAttributes( { focusKeyword: val } ) }
                        help={ __( 'The primary keyword for SEO.', 'recipe-generator' ) }
                    />
                    <TextControl
                        label={ __( 'Related Keyword', 'recipe-generator' ) }
                        value={ relatedKeyword }
                        onChange={ ( val ) => setAttributes( { relatedKeyword: val } ) }
                        help={ __( 'A secondary keyword.', 'recipe-generator' ) }
                    />
                    <TextControl
                        label={ __( 'Internal Link', 'recipe-generator' ) }
                        value={ internalLink }
                        type="url"
                        onChange={ ( val ) => setAttributes( { internalLink: val } ) }
                        help={ __( 'URL for the focus keyword (your site).', 'recipe-generator' ) }
                    />
                    <TextControl
                        label={ __( 'External Link', 'recipe-generator' ) }
                        value={ externalLink }
                        type="url"
                        onChange={ ( val ) => setAttributes( { externalLink: val } ) }
                        help={ __( 'URL for the related keyword (external site).', 'recipe-generator' ) }
                    />
                </PanelBody>
            </InspectorControls>
            <div className="recipe-generator-editor-preview">
                <h4>{ __( 'Recipe Generator Configuration', 'recipe-generator' ) }</h4>
                <p>{ __( 'Enter the details above. The recipe will be generated on the frontend.', 'recipe-generator' ) }</p>
                { postTitle && <p><strong>{__('Title:', 'recipe-generator')}</strong> {postTitle}</p> }
                { focusKeyword && <p><strong>{__('Focus:', 'recipe-generator')}</strong> {focusKeyword}</p> }
                { relatedKeyword && <p><strong>{__('Related:', 'recipe-generator')}</strong> {relatedKeyword}</p> }
                <p><em>{ __( 'Preview of generated content is not available in the editor for this block. View the page to see the output.', 'recipe-generator' ) }</em></p>
                 {/* 
                    Alternatively, to show a live preview (which makes an API call on every change):
                    <ServerSideRender 
                        block="recipe-generator/block"
                        attributes={ attributes }
                    /> 
                    Be cautious with this due to API usage costs and performance.
                 */}
            </div>
        </div>
    );
}
