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
    'manufacturer_name'    => __('Manufacturer', 'polski'),
    'manufacturer_address' => __('Manufacturer address', 'polski'),
    'importer_name'        => __('Importer', 'polski'),
    'importer_address'     => __('Importer address', 'polski'),
    'responsible_person'   => __('Responsible person', 'polski'),
    'product_identifier'   => __('Product identifier', 'polski'),
    'safety_warnings'      => __('Safety warnings', 'polski'),
    'instructions'         => __('Safety instructions', 'polski'),
];

// There is no polski_gpsr entry in config/defaults.php, so this fallback is what
// supplies the default the modules screen advertises.
$polski_mode = ($polski_settings['display_mode'] ?? 'accordion') === 'section' ? 'section' : 'accordion';
$polski_heading = trim((string) ($polski_settings['section_title'] ?? ''));
$polski_heading = $polski_heading !== '' ? $polski_heading : __('Product safety (GPSR)', 'polski');
?>
<div class="polski-gpsr-info">
    <?php if ($polski_mode === 'accordion') : ?>
    <details class="polski-gpsr-info__details">
        <summary class="polski-gpsr-info__summary">
            <?php echo esc_html($polski_heading); ?>
        </summary>
    <?php else : ?>
        <h2 class="polski-gpsr-info__title"><?php echo esc_html($polski_heading); ?></h2>
    <?php endif; ?>
        <dl class="polski-gpsr-info__list">
            <?php foreach ($polski_data as $polski_key => $polski_value) : ?>
                <?php if ($polski_value !== '') : ?>
                    <dt class="polski-gpsr-info__term"><?php echo esc_html($polski_labels[$polski_key] ?? $polski_key); ?></dt>
                    <dd class="polski-gpsr-info__description"><?php echo esc_html($polski_value); ?></dd>
                <?php endif; ?>
            <?php endforeach; ?>
        </dl>
    <?php if ($polski_mode === 'accordion') : ?>
    </details>
    <?php endif; ?>
</div>
