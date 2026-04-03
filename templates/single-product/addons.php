<?php
/**
 * Product add-ons form fields.
 *
 * @var list<array{index:int,type:string,label:string,description:string,placeholder:string,price:float,required:bool,max_length:int,options:array<string,float>}> $add_ons
 * @var array<string, mixed>                                                                                                                     $settings
 * @var string                                                                                                                                   $section_title
 * @var string                                                                                                                                   $section_intro
 * @var \WC_Product                                                                                                                               $product
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$showDescriptions = (bool) ($settings['show_descriptions'] ?? true);
$showPriceInline = (bool) ($settings['show_price_inline'] ?? true);
$fieldStyle = (string) ($settings['field_style'] ?? 'boxed');
$requiredBadgeText = (string) ($settings['required_badge_text'] ?? __('Wymagane', 'polski'));
$optionalBadgeText = (string) ($settings['optional_badge_text'] ?? __('Opcjonalne', 'polski'));
$selectPlaceholder = (string) ($settings['select_placeholder'] ?? __('Wybierz opcję', 'polski'));
$textPlaceholder = (string) ($settings['text_placeholder'] ?? __('Wpisz wartość', 'polski'));
$textareaPlaceholder = (string) ($settings['textarea_placeholder'] ?? __('Wpisz szczegóły', 'polski'));
$textareaRows = max(2, (int) ($settings['textarea_rows'] ?? 3));
$pricePrefix = (string) ($settings['price_prefix'] ?? '+');
?>
<div class="polski-addons polski-addons--<?php echo esc_attr($fieldStyle); ?>">
    <?php if ($section_title !== '') : ?>
        <h3><?php echo esc_html($section_title); ?></h3>
    <?php endif; ?>
    <?php if ($section_intro !== '') : ?>
        <p><?php echo esc_html($section_intro); ?></p>
    <?php endif; ?>

    <?php foreach ($add_ons as $addOn) : ?>
        <?php
        $fieldKey = 'polski_addon_' . $addOn['index'];
        $required = $addOn['required'];
        $priceLabel = $addOn['price'] > 0 && $showPriceInline
            ? $pricePrefix . wp_strip_all_tags(wc_price($addOn['price']))
            : '';
        $placeholder = $addOn['placeholder'] !== ''
            ? $addOn['placeholder']
            : ($addOn['type'] === 'textarea' ? $textareaPlaceholder : $textPlaceholder);
        ?>
        <div class="polski-addon-field polski-addon-field--<?php echo esc_attr($addOn['type']); ?>">
            <div class="polski-addon-field__header">
                <div class="polski-addon-field__title-wrap">
                    <strong class="polski-addon-field__title"><?php echo esc_html($addOn['label']); ?></strong>
                    <span class="polski-addon-field__badge">
                        <?php echo esc_html($required ? $requiredBadgeText : $optionalBadgeText); ?>
                    </span>
                </div>
                <?php if ($priceLabel !== '') : ?>
                    <span class="polski-addon-field__price"><?php echo esc_html($priceLabel); ?></span>
                <?php endif; ?>
            </div>

            <?php if ($showDescriptions && $addOn['description'] !== '') : ?>
                <p class="polski-addon-field__description"><?php echo esc_html($addOn['description']); ?></p>
            <?php endif; ?>

            <?php if ($addOn['type'] === 'checkbox') : ?>
                <label class="polski-addon-field__checkbox">
                    <input type="checkbox" name="<?php echo esc_attr($fieldKey); ?>" value="1" <?php checked((string) wp_unslash($_POST[$fieldKey] ?? ''), '1'); ?> <?php echo $required ? 'required' : ''; ?> />
                    <span><?php echo esc_html($addOn['label']); ?></span>
                </label>
            <?php elseif ($addOn['type'] === 'select') : ?>
                <select name="<?php echo esc_attr($fieldKey); ?>" id="<?php echo esc_attr($fieldKey); ?>" <?php echo $required ? 'required' : ''; ?>>
                    <option value=""><?php echo esc_html($addOn['placeholder'] !== '' ? $addOn['placeholder'] : $selectPlaceholder); ?></option>
                    <?php foreach ($addOn['options'] as $optionLabel => $optionPrice) : ?>
                        <option value="<?php echo esc_attr($optionLabel); ?>" <?php selected((string) wp_unslash($_POST[$fieldKey] ?? ''), $optionLabel); ?>>
                            <?php
                            $optionPriceLabel = $optionPrice > 0 && $showPriceInline
                                ? ' (' . $pricePrefix . wp_strip_all_tags(wc_price($optionPrice)) . ')'
                                : '';
                            echo esc_html($optionLabel . $optionPriceLabel);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($addOn['type'] === 'textarea') : ?>
                <textarea
                    name="<?php echo esc_attr($fieldKey); ?>"
                    id="<?php echo esc_attr($fieldKey); ?>"
                    rows="<?php echo esc_attr((string) $textareaRows); ?>"
                    placeholder="<?php echo esc_attr($placeholder); ?>"
                    <?php echo $required ? 'required' : ''; ?>
                    <?php echo $addOn['max_length'] > 0 ? 'maxlength="' . esc_attr((string) $addOn['max_length']) . '"' : ''; ?>
                ><?php echo esc_textarea((string) wp_unslash($_POST[$fieldKey] ?? '')); ?></textarea>
            <?php else : ?>
                <input
                    type="text"
                    name="<?php echo esc_attr($fieldKey); ?>"
                    id="<?php echo esc_attr($fieldKey); ?>"
                    value="<?php echo esc_attr((string) wp_unslash($_POST[$fieldKey] ?? '')); ?>"
                    placeholder="<?php echo esc_attr($placeholder); ?>"
                    <?php echo $required ? 'required' : ''; ?>
                    <?php echo $addOn['max_length'] > 0 ? 'maxlength="' . esc_attr((string) $addOn['max_length']) . '"' : ''; ?>
                />
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
