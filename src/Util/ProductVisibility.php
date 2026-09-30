<?php

declare(strict_types=1);

namespace Polski\Util;

defined('ABSPATH') || exit;

/**
 * Whether the current visitor may see a product's public data.
 *
 * wc_get_product() resolves any status, so every storefront handler that takes
 * a product id from the request has to ask this before rendering or storing it.
 */
final class ProductVisibility
{
    public static function canView(int $productId): bool
    {
        $post = get_post($productId);

        if (! $post instanceof \WP_Post || ! in_array($post->post_type, ['product', 'product_variation'], true)) {
            return false;
        }

        if ($post->post_type === 'product_variation' && ! self::canView((int) $post->post_parent)) {
            return false;
        }

        return ($post->post_status === 'publish' || current_user_can('read_post', $post->ID))
            && ! post_password_required($post);
    }
}
