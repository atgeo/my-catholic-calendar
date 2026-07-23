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
                    title={__('Settings', 'my-catholic-calendar')}
                    initialOpen={true}
                >
                    <SelectControl
                        label={__('Language', 'my-catholic-calendar')}
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
                        label={__('Heading', 'my-catholic-calendar')}
                        value={title}
                        onChange={(value) => setAttributes({title: value})}
                    />
                    <ToggleControl
                        label={__('Show date', 'my-catholic-calendar')}
                        checked={showDate}
                        onChange={(value) => setAttributes({showDate: value})}
                    />
                    <SelectControl
                        label={ __( 'Style', 'my-catholic-calendar' ) }
                        value={ attributes.style }
                        options={ [
                            { label: __( 'Default', 'my-catholic-calendar' ), value: 'default' },
                            { label: __( 'Minimal', 'my-catholic-calendar' ), value: 'minimal' }
                        ] }
                        onChange={ ( style ) => setAttributes( { style } ) }
                    />
                </PanelBody>
            </InspectorControls>

            <div {...useBlockProps()}>
                <ServerSideRender
                    block="my-catholic-calendar/day"
                    attributes={attributes}
                />
            </div>
        </>
    );
}
