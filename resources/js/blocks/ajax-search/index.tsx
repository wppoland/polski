import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { PanelBody, RangeControl, TextControl, ToggleControl } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

type Attributes = {
    placeholder: string;
    searchLabel: string;
    resultsLabel: string;
    submitButtonText: string;
    showSubmitButton: boolean;
    minChars: number;
    limit: number;
};

registerBlockType(metadata.name, {
    ...metadata,
    edit: ({ attributes, setAttributes }: { attributes: Attributes; setAttributes: (value: Partial<Attributes>) => void }) => {
        const blockProps = useBlockProps();

        return (
            <Fragment>
                <InspectorControls>
                    <PanelBody title={__('Search settings', 'polski')}>
                        <TextControl
                            label={__('Placeholder', 'polski')}
                            value={attributes.placeholder}
                            onChange={(placeholder) => setAttributes({ placeholder })}
                        />
                        <TextControl
                            label={__('Search label', 'polski')}
                            value={attributes.searchLabel}
                            onChange={(searchLabel) => setAttributes({ searchLabel })}
                        />
                        <TextControl
                            label={__('Results label', 'polski')}
                            value={attributes.resultsLabel}
                            onChange={(resultsLabel) => setAttributes({ resultsLabel })}
                        />
                        <ToggleControl
                            label={__('Show submit button', 'polski')}
                            checked={attributes.showSubmitButton}
                            onChange={(showSubmitButton) => setAttributes({ showSubmitButton })}
                        />
                        {attributes.showSubmitButton && (
                            <TextControl
                                label={__('Submit button text', 'polski')}
                                value={attributes.submitButtonText}
                                onChange={(submitButtonText) => setAttributes({ submitButtonText })}
                            />
                        )}
                        <RangeControl
                            label={__('Minimum characters', 'polski')}
                            value={attributes.minChars}
                            onChange={(minChars) => setAttributes({ minChars: minChars ?? 2 })}
                            min={1}
                            max={6}
                        />
                        <RangeControl
                            label={__('Results limit', 'polski')}
                            value={attributes.limit}
                            onChange={(limit) => setAttributes({ limit: limit ?? 6 })}
                            min={1}
                            max={20}
                        />
                    </PanelBody>
                </InspectorControls>
                <div {...blockProps}>
                    <strong>{__('Polski AJAX Search', 'polski')}</strong>
                    <p>{__('Dynamic product search form rendered on the frontend.', 'polski')}</p>
                </div>
            </Fragment>
        );
    },
    save: () => null,
});
