<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Enum\LegalPageType;
use Polski\PageCompliance\PageComplianceService;

/**
 * AI Bridge - commerce ability provider.
 *
 * Exposes a focused, READ-ONLY surface of the shop's commerce / compliance data
 * via the WordPress Abilities API (WP 6.9+) so the Site Editor command palette,
 * MCP servers, and AI assistants can read the same facts a human admin would.
 *
 * Every ability:
 *   - has a stable, namespaced id under `polski/`;
 *   - belongs to the `polski/commerce` category;
 *   - is marked `meta.readonly = true` and `meta.show_in_rest = true`;
 *   - is gated by a `manage_woocommerce` capability check.
 *
 * Each ability is wired to a real plugin service - it never re-implements the
 * underlying logic:
 *   - get_omnibus_history  -> OmnibusService
 *   - get_gpsr_data        -> GPSRService
 *   - get_compliance_status-> PageComplianceService
 *   - get_store_health     -> StoreHealthMonitorService
 *   - get_product_facts    -> the `polski/ai_feed/product_facts` filter (AI Feed)
 *
 * On WordPress < 6.9 (no Abilities API) and when the AI Bridge module is
 * disabled, this service no-ops gracefully.
 */
final class CommerceAbilitiesService implements HasHooks
{
    public const MODULE = 'ai_bridge';

    private const CATEGORY = 'polski/commerce';

    private const CAPABILITY = 'manage_woocommerce';

    public function __construct(
        private readonly OmnibusService $omnibus,
        private readonly GPSRService $gpsr,
        private readonly PageComplianceService $pageCompliance,
        private readonly StoreHealthMonitorService $storeHealth,
    ) {
    }

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled(self::MODULE)) {
            return;
        }

        if (! function_exists('wp_register_ability')) {
            // WP < 6.9 or Abilities API not loaded. No-op.
            return;
        }

        add_action('wp_abilities_api_categories_init', [$this, 'registerCategory']);
        add_action('wp_abilities_api_init', [$this, 'registerAbilities']);
    }

    public function registerCategory(): void
    {
        if (! function_exists('wp_register_ability_category')) {
            return;
        }

        wp_register_ability_category(self::CATEGORY, [
            'label' => __('Polski - commerce data', 'polski'),
            'description' => __('Read-only access to product pricing history, product safety data, store health, and configured-page checks.', 'polski'),
        ]);
    }

    public function registerAbilities(): void
    {
        if (! function_exists('wp_register_ability')) {
            return;
        }

        $this->registerOmnibusHistory();
        $this->registerGpsrData();
        $this->registerComplianceStatus();
        $this->registerStoreHealth();
        $this->registerProductFacts();
    }

    private function registerOmnibusHistory(): void
    {
        wp_register_ability('polski/get-omnibus-history', [
            'label' => __('Get lowest-price (Omnibus) history', 'polski'),
            'description' => __('Returns the tracked price history and the lowest recorded price for a product, as used for the Omnibus Directive display.', 'polski'),
            'category' => self::CATEGORY,
            'input_schema' => [
                'type' => 'object',
                'required' => ['product_id'],
                'properties' => [
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer'],
                    'currency' => ['type' => 'string'],
                    'on_sale' => ['type' => 'boolean'],
                    'lowest_price' => ['type' => ['number', 'null']],
                    'lowest_recorded_at' => ['type' => ['string', 'null']],
                    'history' => [
                        'type' => 'array',
                        'items' => ['type' => 'object'],
                    ],
                ],
            ],
            'execute_callback' => function (array $input): array {
                $productId = (int) ($input['product_id'] ?? 0);
                $product = wc_get_product($productId);
                if (! $product instanceof \WC_Product) {
                    return [
                        'product_id' => $productId,
                        'currency' => get_woocommerce_currency(),
                        'on_sale' => false,
                        'lowest_price' => null,
                        'lowest_recorded_at' => null,
                        'history' => [],
                    ];
                }

                $lowest = $this->omnibus->getLowestPrice($productId);

                return [
                    'product_id' => $productId,
                    'currency' => $lowest !== null ? $lowest->currency : get_woocommerce_currency(),
                    'on_sale' => $this->omnibus->isOnSale($productId),
                    'lowest_price' => $lowest !== null ? $lowest->effectivePrice() : null,
                    'lowest_recorded_at' => $lowest !== null ? $lowest->recordedAt->format('c') : null,
                    'history' => $this->omnibus->getPriceHistory($productId),
                ];
            },
            'permission_callback' => [$this, 'canRead'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerGpsrData(): void
    {
        wp_register_ability('polski/get-gpsr-data', [
            'label' => __('Get product safety (GPSR) data', 'polski'),
            'description' => __('Returns the General Product Safety Regulation data captured for a product: manufacturer, importer, responsible person, identifier, warnings, and instructions.', 'polski'),
            'category' => self::CATEGORY,
            'input_schema' => [
                'type' => 'object',
                'required' => ['product_id'],
                'properties' => [
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'manufacturer_name' => ['type' => 'string'],
                    'manufacturer_address' => ['type' => 'string'],
                    'importer_name' => ['type' => 'string'],
                    'importer_address' => ['type' => 'string'],
                    'responsible_person' => ['type' => 'string'],
                    'product_identifier' => ['type' => 'string'],
                    'safety_warnings' => ['type' => 'string'],
                    'instructions' => ['type' => 'string'],
                ],
            ],
            'execute_callback' => function (array $input): array {
                $product = wc_get_product((int) ($input['product_id'] ?? 0));
                if (! $product instanceof \WC_Product) {
                    return [
                        'manufacturer_name' => '',
                        'manufacturer_address' => '',
                        'importer_name' => '',
                        'importer_address' => '',
                        'responsible_person' => '',
                        'product_identifier' => '',
                        'safety_warnings' => '',
                        'instructions' => '',
                    ];
                }

                return $this->gpsr->getGPSRData($product);
            },
            'permission_callback' => [$this, 'canRead'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerComplianceStatus(): void
    {
        wp_register_ability('polski/get-compliance-status', [
            'label' => __('Get configured-page check status', 'polski'),
            'description' => __('Runs the heuristic content checks for the configured legal pages (Terms, Privacy, Returns, Complaints) and returns a per-page report with a score.', 'polski'),
            'category' => self::CATEGORY,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'page_type' => [
                        'type' => ['string', 'null'],
                        'enum' => ['terms', 'privacy', 'returns', 'complaints', null],
                        'description' => 'Restrict to a single page type; omit to check all.',
                    ],
                ],
            ],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'reports' => [
                        'type' => 'object',
                        'additionalProperties' => ['type' => 'object'],
                    ],
                ],
            ],
            'execute_callback' => function (array $input): array {
                $only = isset($input['page_type'])
                    ? LegalPageType::tryFrom((string) $input['page_type'])
                    : null;

                $types = $only !== null ? [$only] : LegalPageType::cases();
                $reports = [];

                foreach ($types as $type) {
                    $reports[$type->value] = $this->pageCompliance->check($type)->toArray();
                }

                return ['reports' => $reports];
            },
            'permission_callback' => [$this, 'canRead'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerStoreHealth(): void
    {
        wp_register_ability('polski/get-store-health', [
            'label' => __('Get store health status', 'polski'),
            'description' => __('Returns the latest store health snapshot (overall status plus the fatal-error, payments, and sales sensors).', 'polski'),
            'category' => self::CATEGORY,
            'input_schema' => ['type' => 'object', 'properties' => []],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => ['string', 'null']],
                    'sensors' => ['type' => ['object', 'null']],
                    'updated_at' => ['type' => ['string', 'null']],
                ],
            ],
            'execute_callback' => function (): array {
                $state = $this->storeHealth->getState();

                return [
                    'status' => isset($state['status']) ? (string) $state['status'] : null,
                    'sensors' => isset($state['sensors']) && is_array($state['sensors']) ? $state['sensors'] : null,
                    'updated_at' => isset($state['updated_at']) ? (string) $state['updated_at'] : null,
                ];
            },
            'permission_callback' => [$this, 'canRead'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerProductFacts(): void
    {
        wp_register_ability('polski/get-product-facts', [
            'label' => __('Get product facts', 'polski'),
            'description' => __('Returns the structured product fact list (label/value pairs) that the AI Feed surfaces for this product - SKU, GTIN, price, categories, delivery time, and more.', 'polski'),
            'category' => self::CATEGORY,
            'input_schema' => [
                'type' => 'object',
                'required' => ['product_id'],
                'properties' => [
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer'],
                    'facts' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'label' => ['type' => 'string'],
                                'value' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            'execute_callback' => function (array $input): array {
                $productId = (int) ($input['product_id'] ?? 0);
                $product = wc_get_product($productId);
                if (! $product instanceof \WC_Product) {
                    return ['product_id' => $productId, 'facts' => []];
                }

                // Reuse the exact fact set the AI Feed exposes via its public
                // filter (ProductMarkdownBuilder seeds the same hook), instead
                // of re-deriving the data here.
                $rows = apply_filters('polski/ai_feed/product_facts', [], $product);
                $facts = [];

                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        if (! is_array($row) || ! isset($row[0], $row[1])) {
                            continue;
                        }
                        $facts[] = [
                            'label' => (string) $row[0],
                            'value' => (string) $row[1],
                        ];
                    }
                }

                return ['product_id' => $productId, 'facts' => $facts];
            },
            'permission_callback' => [$this, 'canRead'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    public function canRead(): bool
    {
        return current_user_can(self::CAPABILITY);
    }
}
