<?php

/**
 * Run: php tests/block-loop-once-check.php
 *
 * Loop shopmarks (unit price, Omnibus, delivery time, brand) and loop buttons
 * reach a product card by two paths: the classic loop hook and a filter on the
 * card's price/button block. WooCommerce fires the classic hook inside a
 * Product Collection only on shop/taxonomy archives, for a collection that
 * inherits the template query, with its compatibility layer on. Every card
 * must get the output from exactly one path.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../src/Util/BlockLoop.php';

use Polski\Util\BlockLoop;

// [context, isArchive, isInherited, layerDisabled, classic hook fires in the card]
$cases = [
    ['TT5 shop grid (inherited)', true, true, false, true],
    ['TT5 homepage collection', false, false, false, false],
    ['Inherited flag outside an archive', false, true, false, false],
    ['Custom collection on the shop page', true, false, false, false],
    ['Shop grid, compatibility layer off', true, true, true, false],
    ['Related products on a product page', false, false, false, false],
];

foreach ($cases as [$label, $archive, $inherited, $disabled, $classicFires]) {
    $blockRenders = ! BlockLoop::covers($archive, $inherited, $disabled);
    $paths = (int) $classicFires + (int) $blockRenders;
    assert($paths === 1, sprintf('%s: rendered %d times, expected exactly once', $label, $paths));
}

// Buttons: the first path per product wins, per feature.
assert(BlockLoop::claim('wishlist', 7) === true, 'first wishlist claim for product 7 must win');
assert(BlockLoop::claim('wishlist', 7) === false, 'second wishlist claim for product 7 must lose');
assert(BlockLoop::claim('compare', 7) === true, 'compare must not be blocked by wishlist');
assert(BlockLoop::claim('wishlist', 8) === true, 'another product must not be blocked');

// Badges go inside the positioned image wrapper, not before it.
assert(BlockLoop::prependInside('<div class="img"><a></a></div>', 'B') === '<div class="img">B<a></a></div>', 'badge must land inside the wrapper div');
assert(BlockLoop::prependInside('<figure></figure>', 'B') === 'B<figure></figure>', 'no wrapper div: prepend');
assert(BlockLoop::prependInside('<div></div>', '') === '<div></div>', 'nothing to add: unchanged');

echo "block-loop-once-check: OK\n";
