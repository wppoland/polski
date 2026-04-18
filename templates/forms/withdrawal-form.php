<?php
/**
 * Consumer withdrawal (return) request form.
 *
 * This template can be overridden by copying it to yourtheme/polski/forms/withdrawal-form.php.
 *
 * @var \WC_Order               $polski_order  The order to withdraw.
 * @var array<string, array>    $polski_fields Form fields.
 * @var string                  $polski_action_url Form action URL.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$polski_settings = get_option('polski_withdrawal', []);
$polski_settings = is_array($polski_settings) ? $polski_settings : [];
$polski_introText = (string) ($polski_settings['form_intro_text'] ?? __('Składasz wniosek o odstąpienie dla zamówienia #{order_number} z dnia {order_date}.', 'polski'));
$polski_introText = str_replace(
    ['{order_number}', '{order_date}'],
    [$polski_order->get_order_number(), $polski_order->get_date_created() ? $polski_order->get_date_created()->date_i18n(get_option('date_format')) : ''],
    $polski_introText,
);
?>
<div class="polski-withdrawal-form">
    <h3><?php echo esc_html((string) ($polski_settings['form_title'] ?? __('Wniosek o odstąpienie od umowy', 'polski'))); ?></h3>

    <p class="polski-withdrawal-form__info">
        <?php echo esc_html($polski_introText); ?>
    </p>

    <p class="polski-withdrawal-form__notice">
        <?php echo esc_html((string) ($polski_settings['legal_notice_text'] ?? __('Zgodnie z polskim prawem masz 14 dni na odstąpienie od umowy bez podawania przyczyny.', 'polski'))); ?>
    </p>

    <form method="post" action="<?php echo esc_url($polski_action_url); ?>">
        <?php wp_nonce_field('polski_withdrawal_' . $polski_order->get_id()); ?>
        <input type="hidden" name="polski_withdrawal" value="<?php echo esc_attr((string) $polski_order->get_id()); ?>" />

        <h4><?php echo esc_html((string) ($polski_settings['items_heading'] ?? __('Pozycje zamówienia', 'polski'))); ?></h4>
        <table class="polski-withdrawal-form__items shop_table">
            <thead>
                <tr>
                    <th><?php echo esc_html((string) ($polski_settings['column_product'] ?? __('Produkt', 'polski'))); ?></th>
                    <th><?php echo esc_html((string) ($polski_settings['column_quantity'] ?? __('Ilość', 'polski'))); ?></th>
                    <th><?php echo esc_html((string) ($polski_settings['column_price'] ?? __('Cena', 'polski'))); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($polski_order->get_items() as $polski_item) : ?>
                    <?php if (! $polski_item instanceof WC_Order_Item_Product) { continue; } ?>
                    <?php
                    $polski_product = $polski_item->get_product();
                    $polski_exempt = $polski_product instanceof WC_Product && $polski_product->get_meta('_polski_withdrawal_exempt', true) === 'yes';
                    ?>
                    <tr class="<?php echo $polski_exempt ? 'polski-withdrawal-form__item--exempt' : ''; ?>">
                        <td>
                            <?php echo esc_html($polski_item->get_name()); ?>
                            <?php if ($polski_exempt) : ?>
                                <br><small><?php echo esc_html((string) ($polski_settings['exempt_notice_text'] ?? __('(Produkt wyłączony z prawa odstąpienia)', 'polski'))); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html((string) $polski_item->get_quantity()); ?></td>
                        <td><?php echo wp_kses_post($polski_order->get_formatted_line_subtotal($polski_item)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php foreach ($polski_fields as $polski_key => $polski_field) : ?>
            <p class="form-row form-row-wide">
                <label for="polski_withdrawal_<?php echo esc_attr($polski_key); ?>">
                    <?php echo esc_html($polski_field['label']); ?>
                </label>
                <?php if (($polski_field['type'] ?? 'text') === 'textarea') : ?>
                    <textarea
                        name="polski_withdrawal_<?php echo esc_attr($polski_key); ?>"
                        id="polski_withdrawal_<?php echo esc_attr($polski_key); ?>"
                        class="input-text"
                        rows="4"
                        <?php echo ($polski_field['required'] ?? false) ? 'required' : ''; ?>
                    ></textarea>
                <?php else : ?>
                    <input
                        type="<?php echo esc_attr($polski_field['type'] ?? 'text'); ?>"
                        name="polski_withdrawal_<?php echo esc_attr($polski_key); ?>"
                        id="polski_withdrawal_<?php echo esc_attr($polski_key); ?>"
                        class="input-text"
                        <?php echo ($polski_field['required'] ?? false) ? 'required' : ''; ?>
                    />
                <?php endif; ?>
            </p>
        <?php endforeach; ?>

        <p class="form-row">
            <button type="submit" class="button alt">
                <?php echo esc_html((string) ($polski_settings['submit_button_text'] ?? __('Wyślij wniosek o odstąpienie', 'polski'))); ?>
            </button>
        </p>
    </form>
</div>
