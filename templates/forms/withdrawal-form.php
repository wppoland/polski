<?php
/**
 * Two-step consumer withdrawal form. Step 1: select items + qty. Step 2: reason
 * + confirm. Accessibility-hardened: every input has a visible label, qty fields
 * are spinbuttons with explicit min/max, helper text is reachable through
 * aria-describedby, and the submit button states the outcome (not the action).
 *
 * Override by copying to yourtheme/polski/forms/withdrawal-form.php.
 *
 * @var \WC_Order                  $polski_order
 * @var list<array<string, mixed>> $polski_remaining_items
 * @var string                     $polski_submit_nonce
 * @var string                     $polski_form_action
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$polski_settings = get_option('polski_withdrawal', []);
$polski_settings = is_array($polski_settings) ? $polski_settings : [];
$polski_intro_text = (string) ($polski_settings['form_intro_text'] ?? __('Składasz wniosek o odstąpienie dla zamówienia #{order_number} z dnia {order_date}.', 'polski'));
$polski_order_date = $polski_order->get_date_created();
$polski_intro_text = str_replace(
    ['{order_number}', '{order_date}'],
    [
        (string) $polski_order->get_order_number(),
        $polski_order_date !== null ? $polski_order_date->date_i18n(get_option('date_format')) : '',
    ],
    $polski_intro_text,
);
?>
<section
    class="polski-withdrawal-form"
    aria-labelledby="polski-withdrawal-form-title"
    lang="pl"
    style="max-width: 80ch;"
>
    <h2 id="polski-withdrawal-form-title">
        <?php echo esc_html((string) ($polski_settings['form_title'] ?? __('Wniosek o odstąpienie od umowy', 'polski'))); ?>
    </h2>

    <p class="polski-withdrawal-form__info"><?php echo esc_html($polski_intro_text); ?></p>

    <?php if ($polski_remaining_items === []) : ?>
        <p role="status">
            <?php esc_html_e('Wszystkie pozycje z tego zamówienia zostały już objęte odstąpieniem lub nie kwalifikują się do zwrotu.', 'polski'); ?>
        </p>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url($polski_form_action); ?>" novalidate>
            <fieldset>
                <legend>
                    <h3 style="display:inline; margin:0;"><?php esc_html_e('Krok 1. Wybierz pozycje do odstąpienia', 'polski'); ?></h3>
                </legend>

                <p style="color:#475569;">
                    <?php esc_html_e('Wpisz liczbę sztuk, które chcesz objąć odstąpieniem. Pole „Pozostało" pokazuje maksymalną liczbę, którą można jeszcze odstąpić w tej pozycji.', 'polski'); ?>
                </p>

                <p class="polski-withdrawal-form__quick-actions" style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem;">
                    <button type="button" data-polski-select="all" class="button">
                        <?php esc_html_e('Wybierz wszystkie pozycje', 'polski'); ?>
                    </button>
                    <button type="button" data-polski-select="none" class="button">
                        <?php esc_html_e('Wyczyść wybór', 'polski'); ?>
                    </button>
                </p>

                <table class="shop_table polski-withdrawal-items" style="width: 100%;">
                    <caption class="screen-reader-text" style="position:absolute;left:-9999px;">
                        <?php esc_html_e('Pozycje zamówienia dostępne do odstąpienia.', 'polski'); ?>
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Pozycja', 'polski'); ?></th>
                            <th scope="col"><?php esc_html_e('Pozostało', 'polski'); ?></th>
                            <th scope="col"><?php esc_html_e('Liczba sztuk do zwrotu', 'polski'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($polski_remaining_items as $polski_item) :
                        $polski_field_id = 'polski_qty_' . (int) $polski_item['order_item_id'];
                        $polski_help_id = $polski_field_id . '_help';
                        $polski_is_exempt = ! empty($polski_item['is_exempt']);
                        $polski_exempt_reason = $polski_is_exempt ? (string) ($polski_item['exempt_reason'] ?? '') : '';
                    ?>
                        <tr<?php echo $polski_is_exempt ? ' class="polski-withdrawal-item--exempt" aria-disabled="true"' : ''; ?>>
                            <th scope="row" style="text-align: left;<?php echo $polski_is_exempt ? ' opacity:0.65;' : ''; ?>" data-label="<?php esc_attr_e('Pozycja', 'polski'); ?>">
                                <label for="<?php echo esc_attr($polski_field_id); ?>">
                                    <strong><?php echo esc_html((string) $polski_item['name']); ?></strong>
                                </label>
                                <?php if (! empty($polski_item['attributes'])) : ?>
                                    <br><span style="color:#475569;"><?php echo esc_html((string) $polski_item['attributes']); ?></span>
                                <?php endif; ?>
                                <?php if (! empty($polski_item['sku'])) : ?>
                                    <br><span style="color:#475569;"><?php esc_html_e('SKU:', 'polski'); ?> <?php echo esc_html((string) $polski_item['sku']); ?></span>
                                <?php endif; ?>
                                <?php if ($polski_is_exempt) : ?>
                                    <br>
                                    <span class="polski-withdrawal-item__exempt-badge" style="display:inline-block;margin-top:0.25rem;padding:0.15rem 0.45rem;border-radius:0.25rem;background:#fef3c7;color:#92400e;font-size:0.85em;">
                                        <?php esc_html_e('Wyłączone ze zwrotu', 'polski'); ?><?php if ($polski_exempt_reason !== '') : ?>: <?php echo esc_html($polski_exempt_reason); ?><?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </th>
                            <td data-label="<?php esc_attr_e('Pozostało', 'polski'); ?>"<?php echo $polski_is_exempt ? ' style="opacity:0.65;"' : ''; ?>>
                                <span aria-label="<?php esc_attr_e('Pozostało do zwrotu', 'polski'); ?>">
                                    <?php echo esc_html((string) $polski_item['quantity_remaining']); ?> / <?php echo esc_html((string) $polski_item['quantity_total']); ?>
                                </span>
                            </td>
                            <td data-label="<?php esc_attr_e('Liczba sztuk do zwrotu', 'polski'); ?>">
                                <?php if ($polski_is_exempt) : ?>
                                    <span aria-hidden="true" style="color:#94a3b8;">&mdash;</span>
                                    <span class="screen-reader-text" style="position:absolute;left:-9999px;">
                                        <?php esc_html_e('Ta pozycja jest wyłączona ze zwrotu i nie może być wybrana.', 'polski'); ?>
                                    </span>
                                <?php else : ?>
                                    <input
                                        type="number"
                                        id="<?php echo esc_attr($polski_field_id); ?>"
                                        min="0"
                                        max="<?php echo esc_attr((string) $polski_item['quantity_remaining']); ?>"
                                        step="1"
                                        name="polski_items[<?php echo esc_attr((string) $polski_item['order_item_id']); ?>]"
                                        value="<?php echo esc_attr((string) $polski_item['quantity_remaining']); ?>"
                                        aria-describedby="<?php echo esc_attr($polski_help_id); ?>"
                                        inputmode="numeric"
                                        style="width: 5rem;"
                                    >
                                    <small id="<?php echo esc_attr($polski_help_id); ?>" class="screen-reader-text" style="position:absolute;left:-9999px;">
                                        <?php
                                        printf(
                                            /* translators: 1: product name, 2: max qty */
                                            esc_html__('Maksymalna liczba sztuk dostępnych do zwrotu dla pozycji „%1$s" wynosi %2$s.', 'polski'),
                                            esc_html((string) $polski_item['name']),
                                            esc_html((string) $polski_item['quantity_remaining']),
                                        );
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </fieldset>

            <fieldset style="margin-top: 1.5rem;">
                <legend>
                    <h3 style="display:inline; margin:0;"><?php esc_html_e('Krok 2. Powód i potwierdzenie', 'polski'); ?></h3>
                </legend>

                <p>
                    <label for="polski_withdrawal_reason">
                        <?php esc_html_e('Powód odstąpienia (opcjonalnie)', 'polski'); ?>
                    </label>
                    <textarea
                        id="polski_withdrawal_reason"
                        name="polski_withdrawal_reason"
                        rows="3"
                        style="width: 100%; max-width: 60ch;"
                        aria-describedby="polski_withdrawal_reason_help"
                    ></textarea>
                    <small id="polski_withdrawal_reason_help" style="display:block; color:#475569;">
                        <?php esc_html_e('Powód nie jest wymagany - odstąpienie nie wymaga uzasadnienia.', 'polski'); ?>
                    </small>
                </p>

                <p style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="polski_submit_nonce" value="<?php echo esc_attr($polski_submit_nonce); ?>">
                    <button
                        type="submit"
                        class="button button-primary"
                        name="polski_submit_withdrawal"
                        value="1"
                    >
                        <?php esc_html_e('Złóż oświadczenie i wyślij potwierdzenie na e-mail', 'polski'); ?>
                    </button>
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="polski-withdrawal-cancel" style="color: #475569;">
                        <?php esc_html_e('Anuluj i wróć do listy zamówień', 'polski'); ?>
                    </a>
                </p>
            </fieldset>
        </form>
    <?php endif; ?>
</section>
