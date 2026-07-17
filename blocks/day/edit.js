import {__} from '@wordpress/i18n';
import {useBlockProps, InspectorControls} from '@wordpress/block-editor';
import {PanelBody, SelectControl, TextControl, ToggleControl} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit({attributes, setAttributes}) {
    const {locale, title, showDate} = attributes;

    return (
        <>
            <InspectorControls>
                <PanelBody
                    title={__('Settings', 'kalenda')}
                    initialOpen={true}
                >
                    <SelectControl
                        label={__('Language', 'kalenda')}
                        value={locale}
                        options={[
                            {label: 'Dutch', value: 'nl'},
                            {label: 'English', value: 'en'},
                            {label: 'Français', value: 'fr'},
                            {label: 'Italiano', value: 'it'},
                            {label: 'Latin', value: 'la'}
                        ]}
                        onChange={(value) =>
                            setAttributes({locale: value})
                        }
                    />
                    <TextControl
                        label={__('Heading', 'kalenda')}
                        value={title}
                        onChange={(value) => setAttributes({title: value})}
                    />
                    <ToggleControl
                        label={__('Show date', 'kalenda')}
                        checked={showDate}
                        onChange={(value) => setAttributes({showDate: value})}
                    />
                    <SelectControl
                        label={ __( 'Style', 'kalenda' ) }
                        value={ attributes.style }
                        options={ [
                            { label: __( 'Default', 'kalenda' ), value: 'default' },
                            { label: __( 'Minimal', 'kalenda' ), value: 'minimal' }
                        ] }
                        onChange={ ( style ) => setAttributes( { style } ) }
                    />
                </PanelBody>
            </InspectorControls>

            <div {...useBlockProps()}>
                <ServerSideRender
                    block="kalenda/day"
                    attributes={attributes}
                />
            </div>
        </>
    );
}
