<?php
/**
 * Checkout legal checkboxes container.
 *
 * This template can be overridden by copying it to yourtheme/polski/checkout/legal-checkboxes.php.
 *
 * @var list<\Polski\Model\LegalCheckbox> $checkboxes The checkboxes to display.
 * @var \Polski\Enum\CheckboxContext       $context    The display context.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
if (empty($checkboxes)) {
    return;
}
?>
<div class="polski-legal-checkboxes">
    <?php foreach ($checkboxes as $checkbox) : ?>
        <?php
        $field_name = $checkbox->getFieldName();
        $html_id = $checkbox->getHtmlId();
        $required = $checkbox->isRequired();
        $extra_classes = $checkbox->htmlClasses !== '' ? ' ' . esc_attr($checkbox->htmlClasses) : '';
        $extra_style = $checkbox->htmlStyle !== '' ? ' style="' . esc_attr($checkbox->htmlStyle) . '"' : '';

        /**
         * Fires before a legal checkbox is rendered.
         *
         * @param \Polski\Model\LegalCheckbox   $checkbox The checkbox.
         * @param \Polski\Enum\CheckboxContext   $context  The display context.
         */
        do_action('polski/checkboxes/before_render', $checkbox, $context);
        ?>
        <p class="form-row polski-checkbox polski-checkbox--<?php echo esc_attr($checkbox->id); ?><?php echo $extra_classes; ?> <?php echo $required ? 'validate-required' : ''; ?>"<?php echo $extra_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" for="<?php echo esc_attr($html_id); ?>">
                <?php if (! $checkbox->hideInput) : ?>
                    <input type="checkbox"
                           class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                           name="<?php echo esc_attr($field_name); ?>"
                           id="<?php echo esc_attr($html_id); ?>"
                           value="1"
                           <?php echo $required ? 'required' : ''; ?> />
                <?php endif; ?>
                <span class="woocommerce-terms-and-conditions-checkbox-text">
                    <?php echo wp_kses($checkbox->label, ['a' => ['href' => [], 'target' => [], 'rel' => [], 'class' => []], 'strong' => [], 'em' => [], 'br' => []]); ?>
                </span>
                <?php if ($required && ! $checkbox->hideInput) : ?>
                    <abbr class="required" title="<?php esc_attr_e('wymagane', 'polski'); ?>">*</abbr>
                <?php endif; ?>
            </label>
            <?php // Hidden field for visibility detection during AJAX fragment refresh. ?>
            <input type="hidden"
                   name="<?php echo esc_attr($field_name); ?>-field"
                   value="1" />
        </p>
        <?php
        /**
         * Fires after a legal checkbox is rendered.
         *
         * @param \Polski\Model\LegalCheckbox   $checkbox The checkbox.
         * @param \Polski\Enum\CheckboxContext   $context  The display context.
         */
        do_action('polski/checkboxes/after_render', $checkbox, $context);
        ?>
    <?php endforeach; ?>
</div>
