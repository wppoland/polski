import Block from './block.js';
import metadata from './block.json';

const { registerCheckoutBlock } = window.wc.blocksCheckout;

registerCheckoutBlock({
    metadata,
    component: Block,
});
