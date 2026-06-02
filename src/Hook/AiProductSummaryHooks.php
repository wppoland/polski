<?php

declare(strict_types=1);

namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Service\AiProductSummaryService;
use WC_Product;

/**
 * Surfaces a stored AI product summary into the AI Feed output.
 *
 * Hooks the existing public 'polski/ai_feed/product_facts' filter rather than
 * rewriting ProductMarkdownBuilder: when a summary has been generated and saved
 * by an admin, it is prepended as a fact row; when absent, the fact list is
 * returned untouched and the builder behaves exactly as before (existing
 * description used).
 *
 * Gated behind the 'ai_bridge' module toggle. Storage / generation never happens
 * here - this hook only reads meta that an admin explicitly created.
 */
final class AiProductSummaryHooks implements HasHooks
{
    public function __construct(
        private readonly AiProductSummaryService $service,
    ) {
    }

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled(AiProductSummaryService::MODULE)) {
            return;
        }

        add_filter('polski/ai_feed/product_facts', [$this, 'addSummaryFact'], 5, 2);
    }

    /**
     * @param mixed $facts   Existing list of [label, value] fact rows.
     * @param mixed $product Product being rendered.
     * @return array<int, array{0: string, 1: string}>
     */
    public function addSummaryFact(mixed $facts, mixed $product): array
    {
        $rows = is_array($facts) ? $facts : [];

        if (! $product instanceof WC_Product) {
            return $rows;
        }

        $summary = $this->service->getStored($product);
        if ($summary === '') {
            return $rows;
        }

        array_unshift($rows, [__('Summary', 'polski'), $summary]);

        return $rows;
    }
}
