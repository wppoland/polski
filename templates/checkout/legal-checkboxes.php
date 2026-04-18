<?php
/**
 * Checkout legal checkboxes container.
 *
 * This template can be overridden by copying it to yourtheme/polski/checkout/legal-checkboxes.php.
 *
 * @var list<\Polski\Model\LegalCheckbox> $polski_checkboxes The checkboxes to display.
 * @var \Polski\Enum\CheckboxContext       $polski_context    The display context.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
if (empty($polski_checkboxes)) {
    return;
}
?>
<div class="polski-legal-checkboxes">
    <?php foreach ($polski_checkboxes as $polski_checkbox) : ?>
        <?php
        $polski_field_name = $polski_checkbox->getFieldName();
        $polski_html_id = $polski_checkbox->getHtmlId();
        $polski_required = $polski_checkbox->isRequired();
        $polski_extra_classes = $polski_checkbox->htmlClasses !== '' ? ' ' . esc_attr($polski_checkbox->htmlClasses) : '';
        $polski_extra_style = $polski_checkbox->htmlStyle !== '' ? ' style="' . esc_attr($polski_checkbox->htmlStyle) . '"' : '';

        /**
         * Fires before a legal checkbox is rendered.
         *
         * @param \Polski\Model\LegalCheckbox   $polski_checkbox The checkbox.
         * @param \Polski\Enum\CheckboxContext   $polski_context  The display context.
         */
        do_action('polski/checkboxes/before_render', $polski_checkbox, $polski_context);
        ?>
        <p class="form-row polski-checkbox polski-checkbox--<?php echo esc_attr($polski_checkbox->id); ?><?php echo $polski_extra_classes; ?> <?php echo $polski_required ? 'validate-required' : ''; ?>"<?php echo $polski_extra_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" for="<?php echo esc_attr($polski_html_id); ?>">
                <?php if (! $polski_checkbox->hideInput) : ?>
                    <input type="checkbox"
                           class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                           name="<?php echo esc_attr($polski_field_name); ?>"
                           id="<?php echo esc_attr($polski_html_id); ?>"
                           value="1"
                           <?php echo $polski_required ? 'required' : ''; ?> />
                <?php endif; ?>
                <span class="woocommerce-terms-and-conditions-checkbox-text">
                    <?php echo wp_kses($polski_checkbox->label, ['a' => ['href' => [], 'target' => [], 'rel' => [], 'class' => []], 'strong' => [], 'em' => [], 'br' => []]); ?>
                </span>
                <?php if ($polski_required && ! $polski_checkbox->hideInput) : ?>
                    <abbr class="required" title="<?php esc_attr_e('required', 'polski'); ?>">*</abbr>
                <?php endif; ?>
            </label>
            <?php // Hidden field for visibility detection during AJAX fragment refresh. ?>
            <input type="hidden"
                   name="<?php echo esc_attr($polski_field_name); ?>-field"
                   value="1" />
        </p>
        <?php
        /**
         * Fires after a legal checkbox is rendered.
         *
         * @param \Polski\Model\LegalCheckbox   $polski_checkbox The checkbox.
         * @param \Polski\Enum\CheckboxContext   $polski_context  The display context.
         */
        do_action('polski/checkboxes/after_render', $polski_checkbox, $polski_context);
        ?>
    <?php endforeach; ?>
</div>
