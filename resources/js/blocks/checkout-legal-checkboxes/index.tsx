/**
 * WooCommerce Checkout Block integration for Polski legal checkboxes.
 *
 * Registers an inner block in the WC Checkout Block that renders
 * legal checkboxes and sends their state via Store API extensions.
 */

import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';
import { __ } from '@wordpress/i18n';
import Block from './block';
import metadata from './block.json';

registerCheckoutBlock({
    metadata,
    component: Block,
});
