<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Enum\LegalPageType;
use Polski\Enum\WithdrawalStatus;
use Polski\PageCompliance\PageComplianceService;
use Polski\Repository\WithdrawalRepository;

/**
 * Exposes the withdrawal module via the WordPress Abilities API (WP 6.9+).
 *
 * Each ability is a stable, namespaced contract - a structured way for the
 * Site Editor, the command palette, MCP servers, and AI assistants to invoke
 * the same operations the human admin / customer would.
 *
 * On WordPress < 6.9 this service no-ops gracefully (the API is detected at
 * runtime; nothing else in the plugin depends on it).
 *
 * Categories:
 *   - polski/withdrawal - consumer right of withdrawal flow.
 *   - polski/legal      - generated legal documents (Annex I(A), I(B), etc.).
 */
final class AbilitiesService implements HasHooks
{
    public function __construct(
        private readonly WithdrawalService $withdrawal,
        private readonly WithdrawalExemptionService $exemption,
        private readonly AnnexGeneratorService $annex,
        private readonly WithdrawalRepository $repository,
        private readonly LegalPageService $legalPages,
        private readonly PageComplianceService $pageCompliance,
    ) {
    }

    public function registerHooks(): void
    {
        if (! function_exists('wp_register_ability')) {
            // WP < 6.9 or Abilities API not loaded. No-op.
            return;
        }

        add_action('wp_abilities_api_categories_init', [$this, 'registerCategories']);
        add_action('wp_abilities_api_init', [$this, 'registerAbilities']);
    }

    public function registerCategories(): void
    {
        if (! function_exists('wp_register_ability_category')) {
            return;
        }

        wp_register_ability_category('polski-withdrawal', [
            'label' => __('Polski - withdrawal', 'polski'),
            'description' => __('Consumer right of withdrawal (14-day return) operations.', 'polski'),
        ]);

        wp_register_ability_category('polski-legal', [
            'label' => __('Polski - legal documents', 'polski'),
            'description' => __('Generated consumer-law documents (Annex I(A), I(B), etc.).', 'polski'),
        ]);

        wp_register_ability_category('polski-compliance', [
            'label' => __('Polski - compliance checks', 'polski'),
            'description' => __('Static page / cookie banner / accessibility heuristics for Polish & EU consumer law.', 'polski'),
        ]);

        wp_register_ability_category('polski-shop', [
            'label' => __('Polski - shop identification', 'polski'),
            'description' => __('Business identification (name, NIP, REGON, address, contact) and legal pages registry.', 'polski'),
        ]);
    }

    public function registerAbilities(): void
    {
        if (! function_exists('wp_register_ability')) {
            return;
        }

        $this->registerEligibility();
        $this->registerGetRemainingItems();
        $this->registerGetDeadline();
        $this->registerCreate();
        $this->registerConfirm();
        $this->registerComplete();
        $this->registerReject();
        $this->registerList();
        $this->registerAnnexInfo();
        $this->registerAnnexForm();
        $this->registerGetExemptionReason();

        // Compliance + shop identification surface (analogous to how Yoast exposes
        // SEO operations as abilities - discoverable from the command palette and
        // callable by AI agents).
        $this->registerCompliancePrivacy();
        $this->registerComplianceTerms();
        $this->registerComplianceCookieBanner();
        $this->registerBusinessInfo();
        $this->registerLegalPagesStatus();
    }

    private function registerEligibility(): void
    {
        wp_register_ability('polski/withdrawal-is-eligible', [
            'label' => __('Check withdrawal eligibility', 'polski'),
            'description' => __('Returns whether an order can still be withdrawn from (within window, not all-exempt, items remaining).', 'polski'),
            'category' => 'polski-withdrawal',
            'input_schema' => [
                'type' => 'object',
                'required' => ['order_id'],
                'properties' => [
                    'order_id' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'eligible' => ['type' => 'boolean'],
                    'deadline' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                ],
            ],
            'execute_callback' => function (array $input): array {
                $order = wc_get_order((int) ($input['order_id'] ?? 0));
                if (! $order instanceof \WC_Order) {
                    return ['eligible' => false, 'deadline' => null];
                }
                $deadline = $this->withdrawal->getDeadline($order);
                return [
                    'eligible' => $this->withdrawal->isEligible($order),
                    'deadline' => $deadline?->format('c'),
                ];
            },
            'permission_callback' => [$this, 'canReadOrders'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerGetRemainingItems(): void
    {
        wp_register_ability('polski/withdrawal-get-remaining-items', [
            'label' => __('Get remaining withdrawable items', 'polski'),
            'description' => __('Lists order line items that can still be withdrawn (qty not yet returned).', 'polski'),
            'category' => 'polski-withdrawal',
            'input_schema' => [
                'type' => 'object',
                'required' => ['order_id'],
                'properties' => [
                    'order_id' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'order_item_id' => ['type' => 'integer'],
                                'product_id' => ['type' => 'integer'],
                                'variation_id' => ['type' => ['integer', 'null']],
                                'name' => ['type' => 'string'],
                                'attributes' => ['type' => 'string'],
                                'sku' => ['type' => 'string'],
                                'quantity_total' => ['type' => 'number'],
                                'quantity_remaining' => ['type' => 'number'],
                                'line_total' => ['type' => 'number'],
                                'currency' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            'execute_callback' => function (array $input): array {
                $order = wc_get_order((int) ($input['order_id'] ?? 0));
                if (! $order instanceof \WC_Order) {
                    return ['items' => []];
                }
                return ['items' => $this->withdrawal->getRemainingItems($order)];
            },
            'permission_callback' => [$this, 'canReadOrders'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerGetDeadline(): void
    {
        wp_register_ability('polski/withdrawal-get-deadline', [
            'label' => __('Get withdrawal deadline', 'polski'),
            'description' => __('Returns the last moment the consumer can still file a withdrawal for the given order.', 'polski'),
            'category' => 'polski-withdrawal',
            'input_schema' => [
                'type' => 'object',
                'required' => ['order_id'],
                'properties' => [
                    'order_id' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'deadline' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'days_left' => ['type' => ['integer', 'null']],
                ],
            ],
            'execute_callback' => function (array $input): array {
                $order = wc_get_order((int) ($input['order_id'] ?? 0));
                if (! $order instanceof \WC_Order) {
                    return ['deadline' => null, 'days_left' => null];
                }
                $deadline = $this->withdrawal->getDeadline($order);
                if ($deadline === null) {
                    return ['deadline' => null, 'days_left' => null];
                }
                $now = new \WC_DateTime('now', $deadline->getTimezone());
                $daysLeft = (int) max(0, ceil(($deadline->getTimestamp() - $now->getTimestamp()) / 86400));
                return [
                    'deadline' => $deadline->format('c'),
                    'days_left' => $daysLeft,
                ];
            },
            'permission_callback' => [$this, 'canReadOrders'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerCreate(): void
    {
        wp_register_ability('polski/withdrawal-create', [
            'label' => __('Create a withdrawal request', 'polski'),
            'description' => __('Files a withdrawal request on behalf of the current user for the given order (and optional item selection).', 'polski'),
            'category' => 'polski-withdrawal',
            'input_schema' => [
                'type' => 'object',
                'required' => ['order_id'],
                'properties' => [
                    'order_id' => ['type' => 'integer', 'minimum' => 1],
                    'reason' => ['type' => ['string', 'null']],
                    'items' => [
                        'type' => ['object', 'null'],
                        'description' => 'Map of order_item_id => qty to withdraw. Omit for entire-order withdrawal.',
                        'additionalProperties' => ['type' => 'number', 'minimum' => 0],
                    ],
                ],
            ],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'declaration_id' => ['type' => ['integer', 'null']],
                    'status' => ['type' => ['string', 'null']],
                ],
            ],
            'execute_callback' => function (array $input): array {
                $orderId = (int) ($input['order_id'] ?? 0);
                $reason = isset($input['reason']) ? (string) $input['reason'] : null;
                $items = isset($input['items']) && is_array($input['items'])
                    ? array_map('floatval', $input['items'])
                    : null;

                $request = $this->withdrawal->createRequest($orderId, $reason, $items);

                if ($request === null) {
                    return ['declaration_id' => null, 'status' => null];
                }

                return [
                    'declaration_id' => $request->id,
                    'status' => $request->status->value,
                ];
            },
            'permission_callback' => function (array $input): bool {
                $orderId = (int) ($input['order_id'] ?? 0);
                $order = wc_get_order($orderId);
                if (! $order instanceof \WC_Order) {
                    return false;
                }
                // Customer may withdraw their own order; admin may withdraw any.
                return current_user_can('manage_woocommerce')
                    || ($order->get_customer_id() === get_current_user_id() && get_current_user_id() > 0);
            },
            'meta' => ['show_in_rest' => true],
        ]);
    }

    private function registerConfirm(): void
    {
        wp_register_ability('polski/withdrawal-confirm', [
            'label' => __('Confirm a withdrawal request', 'polski'),
            'description' => __('Moves a requested withdrawal to the confirmed state (admin action).', 'polski'),
            'category' => 'polski-withdrawal',
            'input_schema' => [
                'type' => 'object',
                'required' => ['declaration_id'],
                'properties' => [
                    'declaration_id' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'output_schema' => ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']]],
            'execute_callback' => fn (array $input): array => [
                'ok' => $this->withdrawal->confirm((int) ($input['declaration_id'] ?? 0)),
            ],
            'permission_callback' => [$this, 'canManageOrders'],
            'meta' => ['show_in_rest' => true],
        ]);
    }

    private function registerComplete(): void
    {
        wp_register_ability('polski/withdrawal-complete', [
            'label' => __('Complete a withdrawal request', 'polski'),
            'description' => __('Marks a confirmed withdrawal as completed (after the refund has been processed).', 'polski'),
            'category' => 'polski-withdrawal',
            'input_schema' => [
                'type' => 'object',
                'required' => ['declaration_id'],
                'properties' => [
                    'declaration_id' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
            'output_schema' => ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']]],
            'execute_callback' => fn (array $input): array => [
                'ok' => $this->withdrawal->complete((int) ($input['declaration_id'] ?? 0)),
            ],
            'permission_callback' => [$this, 'canManageOrders'],
            'meta' => ['show_in_rest' => true],
        ]);
    }

    private function registerReject(): void
    {
        wp_register_ability('polski/withdrawal-reject', [
            'label' => __('Reject a withdrawal request', 'polski'),
            'description' => __('Rejects a withdrawal request with an audit-trailed reason.', 'polski'),
            'category' => 'polski-withdrawal',
            'input_schema' => [
                'type' => 'object',
                'required' => ['declaration_id'],
                'properties' => [
                    'declaration_id' => ['type' => 'integer', 'minimum' => 1],
                    'reason' => ['type' => ['string', 'null']],
                ],
            ],
            'output_schema' => ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']]],
            'execute_callback' => fn (array $input): array => [
                'ok' => $this->withdrawal->reject(
                    (int) ($input['declaration_id'] ?? 0),
                    isset($input['reason']) ? (string) $input['reason'] : null,
                ),
            ],
            'permission_callback' => [$this, 'canManageOrders'],
            'meta' => ['show_in_rest' => true],
        ]);
    }

    private function registerList(): void
    {
        wp_register_ability('polski/withdrawal-list', [
            'label' => __('List withdrawal requests', 'polski'),
            'description' => __('Returns withdrawal requests, optionally filtered by status.', 'polski'),
            'category' => 'polski-withdrawal',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => ['string', 'null'], 'enum' => ['requested', 'confirmed', 'completed', 'rejected', null]],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                ],
            ],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'items' => ['type' => 'object'],
                    ],
                ],
            ],
            'execute_callback' => function (array $input): array {
                $status = isset($input['status']) && $input['status'] !== null
                    ? WithdrawalStatus::tryFrom((string) $input['status'])
                    : null;
                $rows = $this->repository->findAll(
                    (int) ($input['limit'] ?? 50),
                    (int) ($input['offset'] ?? 0),
                    $status,
                );
                return ['items' => array_map(static fn ($r) => $r->toArray(), $rows)];
            },
            'permission_callback' => [$this, 'canManageOrders'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerAnnexInfo(): void
    {
        wp_register_ability('polski/annex-generate-info', [
            'label' => __('Generate Annex I(A) - withdrawal information', 'polski'),
            'description' => __('Renders Annex I(A) (information about the right of withdrawal) with the merchant data filled in.', 'polski'),
            'category' => 'polski-legal',
            'input_schema' => ['type' => 'object', 'properties' => []],
            'output_schema' => ['type' => 'object', 'properties' => ['html' => ['type' => 'string']]],
            'execute_callback' => fn (): array => ['html' => $this->annex->getInfoHtml()],
            'permission_callback' => '__return_true',
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerAnnexForm(): void
    {
        wp_register_ability('polski/annex-generate-form', [
            'label' => __('Generate Annex I(B) - withdrawal form template', 'polski'),
            'description' => __('Renders Annex I(B) (the model withdrawal form template) with the merchant data filled in.', 'polski'),
            'category' => 'polski-legal',
            'input_schema' => ['type' => 'object', 'properties' => []],
            'output_schema' => ['type' => 'object', 'properties' => ['html' => ['type' => 'string']]],
            'execute_callback' => fn (): array => ['html' => $this->annex->getFormHtml()],
            'permission_callback' => '__return_true',
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerGetExemptionReason(): void
    {
        wp_register_ability('polski/withdrawal-get-exemption-reason', [
            'label' => __('Get exemption reason for a product', 'polski'),
            'description' => __('Returns the Art. 38 exemption reason (if any) for a given product, considering both product- and category-level settings.', 'polski'),
            'category' => 'polski-withdrawal',
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
                    'exempt' => ['type' => 'boolean'],
                    'reason' => ['type' => 'string'],
                ],
            ],
            'execute_callback' => function (array $input): array {
                $product = wc_get_product((int) ($input['product_id'] ?? 0));
                if (! $product instanceof \WC_Product) {
                    return ['exempt' => false, 'reason' => ''];
                }
                return [
                    'exempt' => $this->exemption->isProductExempt($product),
                    'reason' => $this->exemption->getProductExemptionReason($product),
                ];
            },
            'permission_callback' => '__return_true',
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerCompliancePrivacy(): void
    {
        wp_register_ability('polski/compliance-check-privacy', [
            'label' => __('Audit Privacy Policy page', 'polski'),
            'description' => __('Runs the RODO Art. 13 heuristic rules against the configured Privacy Policy page and returns a CheckReport (pass/fail + score).', 'polski'),
            'category' => 'polski-compliance',
            'input_schema' => ['type' => 'object', 'properties' => []],
            'output_schema' => ['type' => 'object'],
            'execute_callback' => fn (): array => $this->pageCompliance->check(LegalPageType::Privacy)->toArray(),
            'permission_callback' => [$this, 'canManageOptions'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerComplianceTerms(): void
    {
        wp_register_ability('polski/compliance-check-terms', [
            'label' => __('Audit Terms (Regulamin) page', 'polski'),
            'description' => __('Runs UŚUDE + Ustawa o prawach konsumenta rules against the configured Terms page.', 'polski'),
            'category' => 'polski-compliance',
            'input_schema' => ['type' => 'object', 'properties' => []],
            'output_schema' => ['type' => 'object'],
            'execute_callback' => fn (): array => $this->pageCompliance->check(LegalPageType::Terms)->toArray(),
            'permission_callback' => [$this, 'canManageOptions'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerComplianceCookieBanner(): void
    {
        wp_register_ability('polski/compliance-check-cookie-banner', [
            'label' => __('Audit cookie banner', 'polski'),
            'description' => __('Fetches the home page and runs active-consent rules (presence, Accept/Reject parity, granular categories, etc.).', 'polski'),
            'category' => 'polski-compliance',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'url' => ['type' => ['string', 'null'], 'format' => 'uri'],
                ],
            ],
            'output_schema' => ['type' => 'object'],
            'execute_callback' => function (array $input): array {
                $url = isset($input['url']) ? esc_url_raw((string) $input['url']) : null;
                return $this->pageCompliance->checkCookieBanner($url !== '' ? $url : null)->toArray();
            },
            'permission_callback' => [$this, 'canManageOptions'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerBusinessInfo(): void
    {
        wp_register_ability('polski/shop-get-business-info', [
            'label' => __('Get shop business identification', 'polski'),
            'description' => __('Returns the merchant data (name, NIP, REGON, address, contact) captured by the setup wizard, with fallbacks to WooCommerce store options.', 'polski'),
            'category' => 'polski-shop',
            'input_schema' => ['type' => 'object', 'properties' => []],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'address' => ['type' => 'string'],
                    'nip' => ['type' => 'string'],
                    'regon' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                    'phone' => ['type' => 'string'],
                ],
            ],
            'execute_callback' => function (): array {
                $general = get_option('polski_general', []);
                $general = is_array($general) ? $general : [];

                return [
                    'name' => trim((string) ($general['company_name'] ?? get_bloginfo('name'))),
                    'address' => trim((string) ($general['company_address'] ?? '')),
                    'nip' => trim((string) ($general['company_nip'] ?? '')),
                    'regon' => trim((string) ($general['company_regon'] ?? '')),
                    'email' => trim((string) ($general['company_email'] ?? get_option('admin_email', ''))),
                    'phone' => trim((string) ($general['company_phone'] ?? '')),
                ];
            },
            'permission_callback' => '__return_true',
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    private function registerLegalPagesStatus(): void
    {
        wp_register_ability('polski/shop-legal-pages-status', [
            'label' => __('Get legal pages configuration status', 'polski'),
            'description' => __('Returns which legal pages (Terms, Privacy, Returns/Withdrawal, Complaints) are configured and published.', 'polski'),
            'category' => 'polski-shop',
            'input_schema' => ['type' => 'object', 'properties' => []],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'pages' => [
                        'type' => 'object',
                        'additionalProperties' => ['type' => 'boolean'],
                    ],
                ],
            ],
            'execute_callback' => fn (): array => [
                'pages' => $this->legalPages->getConfigurationStatus(),
            ],
            'permission_callback' => [$this, 'canManageOptions'],
            'meta' => ['show_in_rest' => true, 'readonly' => true],
        ]);
    }

    public function canManageOrders(): bool
    {
        return current_user_can('manage_woocommerce');
    }

    public function canManageOptions(): bool
    {
        return current_user_can('manage_options') || current_user_can('manage_woocommerce');
    }

    public function canReadOrders(): bool
    {
        return current_user_can('manage_woocommerce') || get_current_user_id() > 0;
    }
}
