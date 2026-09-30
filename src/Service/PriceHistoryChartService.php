<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Model\OmnibusPrice;
use Polski\Repository\OmnibusPriceRepository;

/**
 * Price History Chart - visual price transparency for Omnibus Directive.
 *
 * Renders an inline SVG sparkline showing 30/90/180-day price trends
 * on product pages. Uses existing OmnibusPriceRepository data.
 * No external JS libraries - pure SVG for maximum performance.
 */
final class PriceHistoryChartService implements HasHooks
{
    private const OPTION = 'polski_price_history';

    public function __construct(
        private readonly OmnibusPriceRepository $priceRepository,
    ) {
    }

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('price_history_chart')) {
            return;
        }

        add_action('woocommerce_single_product_summary', [$this, 'renderChart'], 15);

        // History is kept per variation, so a variable product shows the chart of
        // the variation the shopper picks, inside the price WooCommerce swaps in.
        add_filter('woocommerce_available_variation', [$this, 'addVariationChart'], 20, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return wp_parse_args(
            get_option(self::OPTION, []),
            [
                'days' => 30,
                'height' => 60,
                'color' => '#0369a1',
                'fill_color' => '#e0f2fe',
                'show_min_max' => true,
            ],
        );
    }

    /**
     * Render the price history sparkline on the product page.
     */
    public function renderChart(): void
    {
        global $product;

        // A variable product has no history of its own; its variations do.
        if (! $product instanceof \WC_Product || $product->is_type('variable')) {
            return;
        }

        echo $this->getChartHtml($product); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in getChartHtml().
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addVariationChart(array $data, \WC_Product $product, \WC_Product $variation): array
    {
        $chart = $this->getChartHtml($variation);

        if ($chart !== '') {
            $data['price_html'] = (string) ($data['price_html'] ?? '') . $chart;
        }

        return $data;
    }

    /**
     * The chart for one product or variation, empty when there is nothing to show.
     */
    private function getChartHtml(\WC_Product $product): string
    {
        $settings = $this->getSettings();
        $days = max(7, min(365, (int) $settings['days']));
        $history = $this->priceRepository->findHistory($product->get_id(), $days);

        if (count($history) < 2) {
            return '';
        }

        // History comes newest first; the line reads left to right, oldest to now.
        // Amounts are converted gross or net as WooCommerce displays the price.
        $prices = array_map(
            static fn (OmnibusPrice $row) => (float) wc_get_price_to_display($product, ['price' => $row->effectivePrice()]),
            array_reverse($history),
        );

        // The dot marks the price on sale right now, so the line must end there
        // even when no row was recorded for it (a scheduled sale that started).
        $currentPrice = (float) wc_get_price_to_display($product);
        if ($currentPrice > 0 && abs(end($prices) - $currentPrice) > 0.00001) {
            $prices[] = $currentPrice;
        }

        $minPrice = min(...$prices);
        $maxPrice = max(...$prices);

        // Don't show if price never changed.
        if ($minPrice === $maxPrice) {
            return '';
        }

        $height = max(40, min(120, (int) $settings['height']));
        $width = 280;
        $color = sanitize_hex_color($settings['color'] ?? '#0369a1') ?: '#0369a1';
        $fillColor = sanitize_hex_color($settings['fill_color'] ?? '#e0f2fe') ?: '#e0f2fe';

        $svg = $this->buildSparklineSvg($prices, $width, $height, $color, $fillColor);

        ob_start();

        echo '<div class="polski-price-history" style="margin:12px 0;padding:12px 16px;background:#f8fafc;border-radius:8px;max-width:320px">';

        printf(
            '<div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:6px">%s</div>',
            esc_html(sprintf(
                /* translators: %d: number of days */
                __('Price history (%d days)', 'polski'),
                $days,
            )),
        );

        echo wp_kses($svg, $this->inlineSvgAllowedTags());

        if ($settings['show_min_max']) {
            printf(
                '<div style="display:flex;justify-content:space-between;font-size:11px;color:#94a3b8;margin-top:4px"><span>%s: %s</span><span>%s: %s</span></div>',
                esc_html__('Lowest', 'polski'),
                wp_kses_post(wc_price($minPrice)),
                esc_html__('Highest', 'polski'),
                wp_kses_post(wc_price($maxPrice)),
            );
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * Build an inline SVG sparkline from price data points.
     *
     * @param list<float> $prices
     */
    private function buildSparklineSvg(array $prices, int $width, int $height, string $lineColor, string $fillColor): string
    {
        $count = count($prices);

        if ($count < 2) {
            return '';
        }

        $min = min(...$prices);
        $max = max(...$prices);
        $range = $max - $min;

        if ($range <= 0) {
            return '';
        }

        $padding = 2;
        $innerWidth = $width - ($padding * 2);
        $innerHeight = $height - ($padding * 2);

        // Build polyline points.
        $points = [];

        for ($i = 0; $i < $count; $i++) {
            $x = $padding + ($i / ($count - 1)) * $innerWidth;
            $y = $padding + $innerHeight - (($prices[$i] - $min) / $range * $innerHeight);
            $points[] = round($x, 1) . ',' . round($y, 1);
        }

        $polyline = implode(' ', $points);

        // Build fill polygon (closed shape under the line).
        $fillPoints = $polyline;
        $fillPoints .= ' ' . round($padding + $innerWidth, 1) . ',' . ($height - $padding);
        $fillPoints .= ' ' . $padding . ',' . ($height - $padding);

        return sprintf(
            '<svg width="%d" height="%d" viewBox="0 0 %d %d" xmlns="http://www.w3.org/2000/svg" style="display:block">'
            . '<polygon points="%s" fill="%s" opacity="0.3"/>'
            . '<polyline points="%s" fill="none" stroke="%s" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>'
            . '<circle cx="%s" cy="%s" r="3" fill="%s"/>'
            . '</svg>',
            $width,
            $height,
            $width,
            $height,
            esc_attr($fillPoints),
            esc_attr($fillColor),
            esc_attr($polyline),
            esc_attr($lineColor),
            // Current price dot (last point).
            round($padding + $innerWidth, 1),
            round($padding + $innerHeight - (($prices[$count - 1] - $min) / $range * $innerHeight), 1),
            esc_attr($lineColor),
        );
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function inlineSvgAllowedTags(): array
    {
        return [
            'svg' => [
                'width' => true,
                'height' => true,
                'viewbox' => true,
                'xmlns' => true,
                'style' => true,
            ],
            'polygon' => [
                'points' => true,
                'fill' => true,
                'opacity' => true,
            ],
            'polyline' => [
                'points' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-linejoin' => true,
                'stroke-linecap' => true,
            ],
            'circle' => [
                'cx' => true,
                'cy' => true,
                'r' => true,
                'fill' => true,
            ],
        ];
    }
}
