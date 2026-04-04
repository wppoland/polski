<?php
defined('ABSPATH') || exit;
/**
 * Single product GPSR (General Product Safety Regulation) information.
 *
 * @var array<string, string> $data     GPSR field values.
 * @var array<string, mixed>  $settings GPSR module settings.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$labels = [
    'manufacturer_name'    => __('Producent', 'polski'),
    'manufacturer_address' => __('Adres producenta', 'polski'),
    'importer_name'        => __('Importer', 'polski'),
    'importer_address'     => __('Adres importera', 'polski'),
    'responsible_person'   => __('Osoba odpowiedzialna', 'polski'),
    'product_identifier'   => __('Identyfikator produktu', 'polski'),
    'safety_warnings'      => __('Ostrzeżenia dotyczące bezpieczeństwa', 'polski'),
    'instructions'         => __('Instrukcje bezpieczeństwa', 'polski'),
];
?>
<div class="polski-gpsr-info">
    <details class="polski-gpsr-info__details">
        <summary class="polski-gpsr-info__summary">
            <?php esc_html_e('Bezpieczeństwo produktu (GPSR)', 'polski'); ?>
        </summary>
        <dl class="polski-gpsr-info__list">
            <?php foreach ($data as $key => $value) : ?>
                <?php if ($value !== '') : ?>
                    <dt class="polski-gpsr-info__term"><?php echo esc_html($labels[$key] ?? $key); ?></dt>
                    <dd class="polski-gpsr-info__description"><?php echo esc_html($value); ?></dd>
                <?php endif; ?>
            <?php endforeach; ?>
        </dl>
    </details>
</div>
