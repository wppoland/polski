<?php

declare(strict_types=1);

/**
 * Minimal stubs for the WooCommerce Store API (Blocks) classes the plugin
 * integrates with. The php-stubs/woocommerce-stubs package does not ship the
 * Automattic\WooCommerce\StoreApi namespace, so PHPStan reports these as
 * unknown classes in CI (which installs stubs only). Locally the dev vendor has
 * full WooCommerce, so each declaration is guarded with class_exists to avoid a
 * "cannot declare class, already in use" fatal. Analysis-only; never loaded at runtime.
 */

namespace Automattic\WooCommerce\StoreApi {
    if (! \class_exists('Automattic\WooCommerce\StoreApi\StoreApi')) {
        class StoreApi
        {
            /** @return mixed */
            public static function container()
            {
                return null;
            }
        }
    }

    if (! \class_exists('Automattic\WooCommerce\StoreApi\SchemaController')) {
        class SchemaController
        {
            /** @return mixed */
            public function get(string $name, int $version = 1)
            {
                return null;
            }
        }
    }
}

namespace Automattic\WooCommerce\StoreApi\Exceptions {
    if (! \class_exists('Automattic\WooCommerce\StoreApi\Exceptions\RouteException')) {
        class RouteException extends \Exception
        {
            /** @param array<string, mixed> $additional_data */
            public function __construct(
                string $error_code = '',
                string $message = '',
                int $http_status_code = 400,
                array $additional_data = []
            ) {
                parent::__construct($message, $http_status_code);
            }
        }
    }
}

namespace Automattic\WooCommerce\StoreApi\Schemas\V1 {
    if (! \class_exists('Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema')) {
        class CheckoutSchema
        {
            const IDENTIFIER = 'checkout';
        }
    }

    if (! \class_exists('Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema')) {
        class ProductSchema
        {
            const IDENTIFIER = 'product';
        }
    }
}
