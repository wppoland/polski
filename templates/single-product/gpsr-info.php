<?php
/**
 * Single product GPSR (General Product Safety Regulation) information.
 *
 * Two groups, not one list: who is answerable for the product, and how to use
 * it safely. A group with no data is not printed at all, so a shop that only
 * fills in the manufacturer does not ship an empty "Safety" heading.
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
    'manufacturer_contact' => __('Manufacturer contact', 'polski'),
    'responsible_person'   => __('Responsible person', 'polski'),
    'responsible_address'  => __('Responsible person address', 'polski'),
    'responsible_contact'  => __('Responsible person contact', 'polski'),
    'importer_name'        => __('Importer', 'polski'),
    'importer_address'     => __('Importer address', 'polski'),
    'importer_contact'     => __('Importer contact', 'polski'),
    'product_identifier'   => __('Product identifier', 'polski'),
    'safety_warnings'      => __('Safety warnings', 'polski'),
    'instructions'         => __('Safety instructions', 'polski'),
];

$polski_groups = [
    'responsibility' => [
        'title' => __('Product responsibility', 'polski'),
        'keys'  => [
            'manufacturer_name',
            'manufacturer_address',
            'manufacturer_contact',
            'responsible_person',
            'responsible_address',
            'responsible_contact',
            'importer_name',
            'importer_address',
            'importer_contact',
        ],
    ],
    'safety' => [
        'title' => __('Safety information', 'polski'),
        'keys'  => ['product_identifier', 'safety_warnings', 'instructions'],
    ],
];

// Keys the plugin does not know about (a filter, an add-on) still get printed,
// so extending $polski_data never silently drops a value.
$polski_known = array_merge(...array_column($polski_groups, 'keys'));
$polski_extra = array_diff(array_keys($polski_data), $polski_known);

if ($polski_extra !== []) {
    $polski_groups['safety']['keys'] = array_merge($polski_groups['safety']['keys'], $polski_extra);
}

$polski_filled = [];

foreach ($polski_groups as $polski_id => $polski_group) {
    $polski_rows = [];

    foreach ($polski_group['keys'] as $polski_key) {
        if (trim((string) ($polski_data[$polski_key] ?? '')) !== '') {
            $polski_rows[$polski_key] = (string) $polski_data[$polski_key];
        }
    }

    if ($polski_rows !== []) {
        $polski_filled[$polski_id] = ['title' => $polski_group['title'], 'rows' => $polski_rows];
    }
}

if ($polski_filled === []) {
    return;
}

// There is no polski_gpsr entry in config/defaults.php, so this fallback is what
// supplies the default the modules screen advertises.
$polski_mode = ($polski_settings['display_mode'] ?? 'accordion') === 'section' ? 'section' : 'accordion';
$polski_heading = trim((string) ($polski_settings['section_title'] ?? ''));
$polski_heading = $polski_heading !== '' ? $polski_heading : __('Product safety (GPSR)', 'polski');

// One group filled means the wrapper heading already says everything the inner
// heading would, so the inner one is dropped rather than repeated.
$polski_show_group_titles = count($polski_filled) > 1;
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

        <?php foreach ($polski_filled as $polski_id => $polski_group) : ?>
            <div class="polski-gpsr-info__group polski-gpsr-info__group--<?php echo esc_attr($polski_id); ?>">
                <?php if ($polski_show_group_titles) : ?>
                    <h3 class="polski-gpsr-info__group-title"><?php echo esc_html($polski_group['title']); ?></h3>
                <?php endif; ?>
                <dl class="polski-gpsr-info__list">
                    <?php foreach ($polski_group['rows'] as $polski_key => $polski_value) : ?>
                        <dt class="polski-gpsr-info__term"><?php echo esc_html($polski_labels[$polski_key] ?? $polski_key); ?></dt>
                        <dd class="polski-gpsr-info__description">
                            <?php
                            if (is_email($polski_value)) {
                                printf('<a href="%s">%s</a>', esc_url('mailto:' . $polski_value), esc_html($polski_value));
                            } elseif (preg_match('#^https?://#i', $polski_value) === 1) {
                                printf(
                                    '<a href="%s" rel="nofollow noopener external">%s</a>',
                                    esc_url($polski_value),
                                    esc_html($polski_value)
                                );
                            } else {
                                echo esc_html($polski_value);
                            }
                            ?>
                        </dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        <?php endforeach; ?>
    <?php if ($polski_mode === 'accordion') : ?>
    </details>
    <?php endif; ?>
</div>
