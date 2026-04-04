import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { PanelBody, RangeControl, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
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
                    <PanelBody title={__('Slider settings', 'polski')}>
                        <TextControl
                            label={__('Title', 'polski')}
                            value={attributes.title}
                            onChange={(title) => setAttributes({ title })}
                        />
                        <SelectControl
                            label={__('Source', 'polski')}
                            value={attributes.source}
                            options={[
                                { label: __('Related products', 'polski'), value: 'related' },
                                { label: __('Upsell products', 'polski'), value: 'upsell' },
                                { label: __('Sale products', 'polski'), value: 'sale' },
                                { label: __('Featured products', 'polski'), value: 'featured' },
                            ]}
                            onChange={(source) => setAttributes({ source })}
                        />
                        <TextControl
                            label={__('Product ID', 'polski')}
                            type="number"
                            value={String(attributes.productId)}
                            help={__('Optional for related and upsell sliders outside product templates.', 'polski')}
                            onChange={(value) => setAttributes({ productId: Number(value) || 0 })}
                        />
                        <RangeControl
                            label={__('Product limit', 'polski')}
                            value={attributes.limit}
                            onChange={(limit) => setAttributes({ limit: limit ?? 8 })}
                            min={1}
                            max={12}
                        />
                        <ToggleControl
                            label={__('Show title', 'polski')}
                            checked={attributes.showTitle}
                            onChange={(showTitle) => setAttributes({ showTitle })}
                        />
                        <ToggleControl
                            label={__('Show price', 'polski')}
                            checked={attributes.showPrice}
                            onChange={(showPrice) => setAttributes({ showPrice })}
                        />
                        <ToggleControl
                            label={__('Show add to cart', 'polski')}
                            checked={attributes.showAddToCart}
                            onChange={(showAddToCart) => setAttributes({ showAddToCart })}
                        />
                    </PanelBody>
                </InspectorControls>
                <div {...blockProps}>
                    <strong>{__('Polski Product Slider', 'polski')}</strong>
                    <p>{__('Dynamic merchandising slider rendered on the frontend.', 'polski')}</p>
                </div>
            </Fragment>
        );
    },
    save: () => null,
});
