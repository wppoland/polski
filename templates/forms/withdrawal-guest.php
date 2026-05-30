<?php
/**
 * Authenticated guest withdrawal form (revealed after redeeming the magic-link token).
 *
 * Shows the order summary so the visitor can verify what they are about to
 * withdraw - critical for cognitive accessibility and error prevention
 * (WCAG 3.3.4: review before final submission).
 *
 * @var \WC_Order $polski_order
 * @var string    $polski_token
 * @var string    $polski_email
 * @var string    $polski_nonce
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$polski_currency = $polski_order->get_currency();
$polski_order_date = $polski_order->get_date_created();
?>
<section
    class="polski-withdrawal-guest-form"
    aria-labelledby="polski-withdrawal-guest-title"
    lang="pl"
    style="max-width: 65ch;"
>
    <h2 id="polski-withdrawal-guest-title">
        <?php
        printf(
            /* translators: %s = order number */
            esc_html__('Odstąpienie od umowy - zamówienie #%s', 'polski'),
            esc_html($polski_order->get_order_number()),
        );
        ?>
    </h2>

    <p>
        <?php
        printf(
            /* translators: %s = email address */
            esc_html__('Składasz oświadczenie na rzecz adresu: %s', 'polski'),
            '<strong>' . esc_html($polski_email) . '</strong>',
        );
        ?>
    </p>

    <h3><?php esc_html_e('Pozycje objęte odstąpieniem', 'polski'); ?></h3>
    <p style="color:#475569;">
        <?php esc_html_e('Złożenie tego oświadczenia obejmuje całe poniższe zamówienie. Jeżeli chcesz odstąpić tylko od części pozycji, skontaktuj się ze sklepem lub złóż osobne oświadczenie po zalogowaniu się na konto.', 'polski'); ?>
    </p>

    <table class="shop_table" style="width: 100%;">
        <caption class="screen-reader-text" style="position:absolute;left:-9999px;">
            <?php esc_html_e('Pozycje zamówienia objęte oświadczeniem.', 'polski'); ?>
        </caption>
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Produkt', 'polski'); ?></th>
                <th scope="col"><?php esc_html_e('Ilość', 'polski'); ?></th>
                <th scope="col"><?php esc_html_e('Wartość', 'polski'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($polski_order->get_items() as $polski_item) :
                if (! $polski_item instanceof \WC_Order_Item_Product) {
                    continue;
                }
                $polski_product = $polski_item->get_product();
                $polski_attrs = '';
                if ($polski_product instanceof \WC_Product && $polski_product->is_type('variation')) {
                    $polski_attrs = wc_get_formatted_variation($polski_product, true, true, false);
                }
                ?>
                <tr>
                    <td data-label="<?php esc_attr_e('Produkt', 'polski'); ?>">
                        <?php echo esc_html((string) $polski_item->get_name()); ?>
                        <?php if ($polski_attrs !== '') : ?>
                            <br><span style="color:#475569;"><?php echo esc_html($polski_attrs); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Ilość', 'polski'); ?>">
                        <?php echo esc_html((string) $polski_item->get_quantity()); ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Wartość', 'polski'); ?>">
                        <?php echo wp_kses_post(wc_price((float) $polski_item->get_total(), ['currency' => $polski_currency])); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" scope="row" style="text-align: right;">
                    <?php esc_html_e('Wartość zamówienia:', 'polski'); ?>
                </th>
                <td>
                    <strong><?php echo wp_kses_post(wc_price((float) $polski_order->get_total(), ['currency' => $polski_currency])); ?></strong>
                </td>
            </tr>
            <?php if ($polski_order_date !== null) : ?>
                <tr>
                    <th colspan="2" scope="row" style="text-align: right;">
                        <?php esc_html_e('Data zamówienia:', 'polski'); ?>
                    </th>
                    <td><?php echo esc_html($polski_order_date->date_i18n(get_option('date_format'))); ?></td>
                </tr>
            <?php endif; ?>
        </tfoot>
    </table>

    <p style="color:#475569;">
        <?php esc_html_e('Po wysłaniu formularza otrzymasz e-mail potwierdzający z numerem deklaracji oraz podsumowaniem zamówienia.', 'polski'); ?>
    </p>

    <form method="post" action="" novalidate>
        <p>
            <label for="polski_withdrawal_reason">
                <?php esc_html_e('Powód odstąpienia (opcjonalnie)', 'polski'); ?>
            </label>
            <textarea
                id="polski_withdrawal_reason"
                name="polski_withdrawal_reason"
                rows="4"
                style="width: 100%; max-width: 60ch;"
                aria-describedby="polski_withdrawal_reason_help"
            ></textarea>
            <small id="polski_withdrawal_reason_help" style="display:block; color:#475569;">
                <?php esc_html_e('Powód nie jest wymagany - odstąpienie nie wymaga uzasadnienia.', 'polski'); ?>
            </small>
        </p>

        <p style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="polski_guest_nonce" value="<?php echo esc_attr($polski_nonce); ?>">
            <button
                type="submit"
                name="polski_guest_submit"
                value="1"
                class="button button-primary"
            >
                <?php esc_html_e('Złóż oświadczenie i wyślij potwierdzenie na e-mail', 'polski'); ?>
            </button>
        </p>
    </form>
</section>
