/**
 * Editor side of the server-rendered Polski blocks (BDO number, business
 * info, copyright notice and the rest registered from blocks/).
 *
 * Those blocks only had a PHP render callback, so the block editor never
 * listed them. This registers each one client-side with a live server
 * preview and a sidebar field per scalar attribute. The list comes from PHP
 * (window.polskiServerBlocks), so a new block.json needs no JS change.
 */
(function (wp) {
    if (!wp || !wp.blocks || !wp.serverSideRender) {
        return;
    }

    var el = wp.element.createElement;
    var ServerSideRender = wp.serverSideRender;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var ToggleControl = wp.components.ToggleControl;
    var TextControl = wp.components.TextControl;

    // Attributes core adds to every block; they have their own UI.
    var core = ['className', 'align', 'anchor', 'style', 'lock', 'metadata', 'backgroundColor', 'textColor', 'fontSize'];

    function label(key) {
        var text = key.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/_/g, ' ');
        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    function control(key, type, props) {
        var value = props.attributes[key];
        var set = function (next) {
            var change = {};
            change[key] = next;
            props.setAttributes(change);
        };

        if (type === 'boolean') {
            return el(ToggleControl, { key: key, label: label(key), checked: !!value, onChange: set, __nextHasNoMarginBottom: true });
        }

        if (type === 'string' || type === 'number' || type === 'integer') {
            return el(TextControl, {
                key: key,
                label: label(key),
                type: type === 'string' ? 'text' : 'number',
                value: value === undefined || value === null ? '' : String(value),
                onChange: function (next) {
                    set(type === 'string' ? next : (next === '' ? undefined : Number(next)));
                },
                __nextHasNoMarginBottom: true,
            });
        }

        return null;
    }

    (window.polskiServerBlocks || []).forEach(function (def) {
        if (wp.blocks.getBlockType(def.name)) {
            return;
        }

        var attributes = def.attributes || {};

        wp.blocks.registerBlockType(def.name, {
            apiVersion: 3,
            title: def.title,
            description: def.description,
            icon: def.icon || 'admin-generic',
            category: def.category || 'polski',
            attributes: attributes,
            supports: def.supports || {},
            edit: function (props) {
                var controls = Object.keys(attributes)
                    .filter(function (key) { return core.indexOf(key) === -1; })
                    .map(function (key) { return control(key, attributes[key].type, props); })
                    .filter(Boolean);

                return el(
                    'div',
                    useBlockProps(),
                    controls.length ? el(InspectorControls, null, el(PanelBody, { title: def.title }, controls)) : null,
                    el(ServerSideRender, { block: def.name, attributes: props.attributes })
                );
            },
            save: function () {
                return null;
            },
        });
    });
})(window.wp);
