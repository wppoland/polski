<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;

/**
 * Energy label next to the price for online distance selling.
 *
 * Regulation (EU) 2017/1369 art. 5(1)(a) makes the dealer show the label
 * online too; the delegated act for each product group sets how. Delegated
 * Regulation (EU) 2019/2014 Annex VIII is the pattern used here: an arrow in
 * the colour of the class, next to the price, that opens the label (a new tab
 * is one of the allowed ways), plus a "Product information sheet" link.
 *
 * Nothing renders without a class and a label: art. 6(d) forbids anything
 * that mimics the label on products no delegated act covers.
 */
final class EnergyLabelService
{
    /** Every class letter a delegated act uses, best first. */
    public const CLASSES = ['A+++', 'A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G'];

    /** The ranges in use: the rescaled A to G, and the older ones still in force for some groups. */
    public const SCALES = ['A-G' => ['A', 'G'], 'A+++-D' => ['A+++', 'D'], 'A++-E' => ['A++', 'E'], 'A+-F' => ['A+', 'F']];

    /**
     * Arrow colours top to bottom, CMYK as printed in Delegated Regulation
     * (EU) 2019/2014 Annex III (A 100,0,100,0 ... G 0,100,100,0).
     */
    private const CMYK = [[100, 0, 100, 0], [70, 0, 100, 0], [30, 0, 100, 0], [0, 0, 100, 0], [0, 30, 100, 0], [0, 70, 100, 0], [0, 100, 100, 0]];

    public function getArrowHtml(\WC_Product $product): string
    {
        if (! ModulesPage::isModuleEnabled('energy_label')) {
            return '';
        }

        $data = $this->getData($product);
        $range = $this->range($data['scale']);
        $position = array_search($data['class'], $range, true);

        if ($position === false || $data['label_url'] === '') {
            return '';
        }

        $html = sprintf(
            '<span class="polski-energy"><a class="polski-energy__arrow" href="%1$s" target="_blank" rel="noopener" style="--polski-energy-color:%2$s" aria-label="%3$s"><span class="polski-energy__range" aria-hidden="true"><span>%4$s</span><span>%5$s</span></span><span class="polski-energy__class">%6$s</span></a>',
            esc_url($data['label_url']),
            esc_attr($this->colour((int) $position, count($range))),
            /* translators: 1: energy efficiency class, e.g. B. 2: best class of the scale. 3: worst class of the scale. */
            esc_attr(sprintf(__('Energy efficiency class %1$s (scale %2$s to %3$s), opens the energy label', 'polski'), $data['class'], $range[0], end($range))),
            esc_html($range[0]),
            esc_html((string) end($range)),
            esc_html($data['class']),
        );

        if ($data['sheet_url'] !== '') {
            $html .= sprintf(
                ' <a class="polski-energy__sheet" href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url($data['sheet_url']),
                // Annex VIII point 4 wants this wording on the link.
                esc_html__('Product information sheet', 'polski'),
            );
        }

        return (string) apply_filters('polski/energy_label/html', $html . '</span>', $product, $data);
    }

    /**
     * @return array{class: string, scale: string, label_url: string, sheet_url: string}
     */
    public function getData(\WC_Product $product): array
    {
        $ownClass = (string) $product->get_meta('_polski_energy_class', true);

        // A variation with its own class set (different from the parent, e.g.
        // a size with a different energy rating) uses its own fields. A
        // variation with none set falls back to the parent's label.
        $source = ($product->is_type('variation') && $ownClass === '')
            ? (wc_get_product($product->get_parent_id()) ?: $product)
            : $product;

        return [
            'class' => $source === $product ? $ownClass : (string) $source->get_meta('_polski_energy_class', true),
            'scale' => (string) $source->get_meta('_polski_energy_scale', true) ?: 'A-G',
            'label_url' => (string) $source->get_meta('_polski_energy_label_url', true),
            'sheet_url' => (string) $source->get_meta('_polski_energy_sheet_url', true),
        ];
    }

    /**
     * @return list<string>
     */
    public function range(string $scale): array
    {
        [$top, $bottom] = self::SCALES[$scale] ?? self::SCALES['A-G'];
        $from = (int) array_search($top, self::CLASSES, true);

        return array_slice(self::CLASSES, $from, (int) array_search($bottom, self::CLASSES, true) - $from + 1);
    }

    /**
     * Colour by position in the scale: every range in use has seven classes,
     * dark green first, red last. CMYK goes to sRGB by the plain formula.
     */
    public function colour(int $position, int $steps): string
    {
        $index = $steps > 1 ? (int) round($position * 6 / ($steps - 1)) : 0;
        [$c, $m, $y, $k] = self::CMYK[max(0, min(6, $index))];
        $channel = static fn (int $ink): int => (int) round(255 * (1 - $ink / 100) * (1 - $k / 100));

        return sprintf('#%02x%02x%02x', $channel($c), $channel($m), $channel($y));
    }
}
