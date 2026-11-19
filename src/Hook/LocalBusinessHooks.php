<?php

declare(strict_types=1);

namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Service\LocalBusinessSchema;
use Polski\Service\MerchantPolicySchema;

/**
 * Publishes the LocalBusiness node and the organization-level return policy.
 *
 * With Yoast SEO the data goes into Yoast's own graph: the business merges
 * into the #organization node (one organization, linked by @id from WebSite
 * and WebPage), or joins the graph as its own node when Yoast describes a
 * person. Without Yoast the node is printed on its own in the footer.
 *
 * Google recommends the standard return policy on the Organization, with
 * offer-level policies as overrides, so the policy rides the organization
 * node wherever Yoast prints it.
 *
 * existing-checked: StructuredDataHooks - it only filters WooCommerce's
 * product node; the organization lives in Yoast's graph or nowhere, so it
 * needs separate hooks.
 */
final class LocalBusinessHooks implements HasHooks
{
    private bool $printed = false;

    public function __construct(
        private readonly LocalBusinessSchema $business,
        private readonly MerchantPolicySchema $merchant,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('wpseo_schema_graph', [$this, 'filterYoastGraph'], 20);
        add_action('wp_footer', [$this, 'printStandalone'], 20);
    }

    /**
     * @param mixed $graph Yoast's graph: a list of nodes.
     * @return mixed
     */
    public function filterYoastGraph(mixed $graph): mixed
    {
        if (! is_array($graph)) {
            return $graph;
        }

        $business = $this->shouldOutput() ? $this->withPolicy($this->business->build()) : null;
        $policy = $this->merchant->returnPolicy();
        $organizationId = home_url('/') . '#organization';

        foreach ($graph as $i => $node) {
            if (! is_array($node) || ($node['@id'] ?? '') !== $organizationId) {
                continue;
            }

            if ($business !== null) {
                $node = LocalBusinessSchema::mergeInto($node, $business);
                $this->printed = true;
            } elseif ($policy !== null && ! isset($node['hasMerchantReturnPolicy'])) {
                $node['hasMerchantReturnPolicy'] = $policy;
            }

            $graph[$i] = $node;
        }

        // Yoast set to describe a person: the business is a node of its own.
        if ($business !== null && ! $this->printed) {
            $graph[] = $business;
            $this->printed = true;
        }

        return $graph;
    }

    /**
     * No SEO plugin graph took the node: print it as its own script.
     */
    public function printStandalone(): void
    {
        if ($this->printed || ! $this->shouldOutput()) {
            return;
        }

        $node = $this->withPolicy($this->business->build());

        if ($node === null) {
            return;
        }

        $this->printed = true;

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG, so no markup can break out of the script element.
        echo '<script type="application/ld+json">' . wp_json_encode(['@context' => 'https://schema.org'] + $node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>' . "\n";
    }

    /**
     * The business node with the shop's return policy, which Google reads from the organization.
     *
     * @param array<string, mixed>|null $node
     * @return array<string, mixed>|null
     */
    private function withPolicy(?array $node): ?array
    {
        $policy = $this->merchant->returnPolicy();

        if ($node !== null && $policy !== null) {
            $node['hasMerchantReturnPolicy'] = $policy;
        }

        return $node;
    }

    private function shouldOutput(): bool
    {
        if (! ModulesPage::isModuleEnabled('local_business')) {
            return false;
        }

        /**
         * Whether the LocalBusiness node goes on the current page. Front page by default.
         *
         * @param bool $output
         */
        return (bool) apply_filters('polski/local_business/output', is_front_page());
    }
}
