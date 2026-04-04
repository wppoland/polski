<?php
defined('ABSPATH') || exit;
/**
 * Consumer withdrawal (return) request form.
 *
 * This template can be overridden by copying it to yourtheme/polski/forms/withdrawal-form.php.
 *
 * @var \WC_Order               $order  The order to withdraw.
 * @var array<string, array>    $fields Form fields.
 * @var string                  $action_url Form action URL.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$settings = get_option('polski_withdrawal', []);
$settings = is_array($settings) ? $settings : [];
$introText = (string) ($settings['form_intro_text'] ?? __('Składasz wniosek o odstąpienie dla zamówienia #{order_number} z dnia {order_date}.', 'polski'));
$introText = str_replace(
    ['{order_number}', '{order_date}'],
    [$order->get_order_number(), $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format')) : ''],
    $introText,
);
?>
<div class="polski-withdrawal-form">
    <h3><?php echo esc_html((string) ($settings['form_title'] ?? __('Wniosek o odstąpienie od umowy', 'polski'))); ?></h3>

    <p class="polski-withdrawal-form__info">
        <?php echo esc_html($introText); ?>
    </p>

    <p class="polski-withdrawal-form__notice">
        <?php echo esc_html((string) ($settings['legal_notice_text'] ?? __('Zgodnie z polskim prawem masz 14 dni na odstąpienie od umowy bez podawania przyczyny.', 'polski'))); ?>
    </p>

    <form method="post" action="<?php echo esc_url($action_url); ?>">
        <?php wp_nonce_field('polski_withdrawal_' . $order->get_id()); ?>
        <input type="hidden" name="polski_withdrawal" value="<?php echo esc_attr((string) $order->get_id()); ?>" />

        <h4><?php echo esc_html((string) ($settings['items_heading'] ?? __('Pozycje zamówienia', 'polski'))); ?></h4>
        <table class="polski-withdrawal-form__items shop_table">
            <thead>
                <tr>
                    <th><?php echo esc_html((string) ($settings['column_product'] ?? __('Produkt', 'polski'))); ?></th>
                    <th><?php echo esc_html((string) ($settings['column_quantity'] ?? __('Ilość', 'polski'))); ?></th>
                    <th><?php echo esc_html((string) ($settings['column_price'] ?? __('Cena', 'polski'))); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order->get_items() as $item) : ?>
                    <?php if (! $item instanceof WC_Order_Item_Product) { continue; } ?>
                    <?php
                    $product = $item->get_product();
                    $exempt = $product instanceof WC_Product && $product->get_meta('_polski_withdrawal_exempt', true) === 'yes';
                    ?>
                    <tr class="<?php echo $exempt ? 'polski-withdrawal-form__item--exempt' : ''; ?>">
                        <td>
                            <?php echo esc_html($item->get_name()); ?>
                            <?php if ($exempt) : ?>
                                <br><small><?php echo esc_html((string) ($settings['exempt_notice_text'] ?? __('(Produkt wyłączony z prawa odstąpienia)', 'polski'))); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html((string) $item->get_quantity()); ?></td>
                        <td><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php foreach ($fields as $key => $field) : ?>
            <p class="form-row form-row-wide">
                <label for="polski_withdrawal_<?php echo esc_attr($key); ?>">
                    <?php echo esc_html($field['label']); ?>
                </label>
                <?php if (($field['type'] ?? 'text') === 'textarea') : ?>
                    <textarea
                        name="polski_withdrawal_<?php echo esc_attr($key); ?>"
                        id="polski_withdrawal_<?php echo esc_attr($key); ?>"
                        class="input-text"
                        rows="4"
                        <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>
                    ></textarea>
                <?php else : ?>
                    <input
                        type="<?php echo esc_attr($field['type'] ?? 'text'); ?>"
                        name="polski_withdrawal_<?php echo esc_attr($key); ?>"
                        id="polski_withdrawal_<?php echo esc_attr($key); ?>"
                        class="input-text"
                        <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>
                    />
                <?php endif; ?>
            </p>
        <?php endforeach; ?>

        <p class="form-row">
            <button type="submit" class="button alt">
                <?php echo esc_html((string) ($settings['submit_button_text'] ?? __('Wyślij wniosek o odstąpienie', 'polski'))); ?>
            </button>
        </p>
    </form>
</div>
