<?php
/**
 * Checkout legal checkboxes container.
 *
 * This template can be overridden by copying it to yourtheme/spolszczony/checkout/legal-checkboxes.php.
 *
 * @var list<\Spolszczony\Model\LegalCheckbox> $checkboxes The checkboxes to display.
 *
 * @package Spolszczony/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (empty($checkboxes)) {
    return;
}
?>
<div class="spolszczony-legal-checkboxes">
    <?php foreach ($checkboxes as $checkbox) : ?>
        <?php
        $field_name = 'spolszczony_checkbox_' . $checkbox->id;
        $required = $checkbox->isRequired();
        ?>
        <p class="form-row spolszczony-checkbox spolszczony-checkbox--<?php echo esc_attr($checkbox->id); ?> <?php echo $required ? 'validate-required' : ''; ?>">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input type="checkbox"
                       class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                       name="<?php echo esc_attr($field_name); ?>"
                       id="<?php echo esc_attr($field_name); ?>"
                       value="1"
                       <?php echo $required ? 'required' : ''; ?> />
                <span class="woocommerce-terms-and-conditions-checkbox-text">
                    <?php echo wp_kses($checkbox->label, ['a' => ['href' => [], 'target' => [], 'rel' => []], 'strong' => [], 'em' => []]); ?>
                </span>
                <?php if ($required) : ?>
                    <abbr class="required" title="<?php esc_attr_e('required', 'spolszczony'); ?>">*</abbr>
                <?php endif; ?>
            </label>
        </p>
    <?php endforeach; ?>
</div>
