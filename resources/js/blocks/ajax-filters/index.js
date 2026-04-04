import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType(metadata.name, {
    ...metadata,
    edit: ({ attributes, setAttributes }) => {
        const blockProps = useBlockProps();

        return (
            <Fragment>
                <InspectorControls>
                    <PanelBody title={__('Filter settings', 'polski')}>
                        <TextControl
                            label={__('Title', 'polski')}
                            value={attributes.title}
                            onChange={(title) => setAttributes({ title })}
                        />
                        <ToggleControl
                            label={__('Show title', 'polski')}
                            checked={attributes.showTitle}
                            onChange={(showTitle) => setAttributes({ showTitle })}
                        />
                        <ToggleControl
                            label={__('Show categories', 'polski')}
                            checked={attributes.showCategories}
                            onChange={(showCategories) => setAttributes({ showCategories })}
                        />
                        <ToggleControl
                            label={__('Show brands', 'polski')}
                            checked={attributes.showBrands}
                            onChange={(showBrands) => setAttributes({ showBrands })}
                        />
                        <ToggleControl
                            label={__('Show price', 'polski')}
                            checked={attributes.showPrice}
                            onChange={(showPrice) => setAttributes({ showPrice })}
                        />
                        <ToggleControl
                            label={__('Show stock', 'polski')}
                            checked={attributes.showStock}
                            onChange={(showStock) => setAttributes({ showStock })}
                        />
                        <ToggleControl
                            label={__('Show sale', 'polski')}
                            checked={attributes.showSale}
                            onChange={(showSale) => setAttributes({ showSale })}
                        />
                        <ToggleControl
                            label={__('Show attributes', 'polski')}
                            checked={attributes.showAttributes}
                            onChange={(showAttributes) => setAttributes({ showAttributes })}
                        />
                    </PanelBody>
                </InspectorControls>
                <div {...blockProps}>
                    <strong>{__('Polski AJAX Filters', 'polski')}</strong>
                    <p>{__('Dynamic product filters rendered on the frontend.', 'polski')}</p>
                </div>
            </Fragment>
        );
    },
    save: () => null,
});
