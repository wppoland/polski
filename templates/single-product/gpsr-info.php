<?php
/**
 * Single product GPSR (General Product Safety Regulation) information.
 *
 * @var array<string, string> $polski_data     GPSR field values.
 * @var array<string, mixed>  $polski_settings GPSR module settings.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$polski_labels = [
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
            <?php foreach ($polski_data as $polski_key => $polski_value) : ?>
                <?php if ($polski_value !== '') : ?>
                    <dt class="polski-gpsr-info__term"><?php echo esc_html($polski_labels[$polski_key] ?? $polski_key); ?></dt>
                    <dd class="polski-gpsr-info__description"><?php echo esc_html($polski_value); ?></dd>
                <?php endif; ?>
            <?php endforeach; ?>
        </dl>
    </details>
</div>
