<?php

declare(strict_types=1);

namespace Spolszczony\Contract;

/**
 * Strategy interface for modifying how prices are displayed.
 */
interface PriceModifier
{
    /**
     * Modify the price HTML for a product.
     *
     * @param string       $priceHtml The current price HTML.
     * @param \WC_Product  $product   The product.
     * @return string Modified price HTML.
     */
    public function modify(string $priceHtml, \WC_Product $product): string;
}
