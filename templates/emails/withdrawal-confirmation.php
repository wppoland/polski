<?php
/**
 * Withdrawal confirmation email (HTML).
 *
 * Designed to serve as the buyer's record of their withdrawal declaration on a
 * durable medium: it captures a frozen snapshot of the order at the time the
 * request was filed (line items, totals, declaration ID, timestamp).
 *
 * @var WC_Order                          $polski_order
 * @var \Polski\Model\WithdrawalRequest   $polski_request
 * @var string                            $polski_email_heading
 * @var string                            $polski_additional_content
 * @var bool                              $polski_sent_to_admin
 * @var bool                              $polski_plain_text
 * @var WC_Email                          $polski_email
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$polski_settings = get_option('polski_withdrawal', []);
$polski_settings = is_array($polski_settings) ? $polski_settings : [];

$polski_greeting = str_replace(
    '{name}',
    (string) $polski_order->get_billing_first_name(),
    (string) ($polski_settings['email_greeting'] ?? __('Dzień dobry {name},', 'polski')),
);
$polski_intro = str_replace(
    '{order_number}',
    (string) $polski_order->get_order_number(),
    (string) ($polski_settings['email_intro_text'] ?? __('Twój wniosek o odstąpienie dla zamówienia #{order_number} został zarejestrowany.', 'polski')),
);

$polski_declaration_id = sprintf('POL-WD-%06d', $polski_request->id);
$polski_filed_at = $polski_request->requestedAt->date_i18n(get_option('date_format') . ' H:i');
$polski_currency = $polski_order->get_currency();

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email header hook for template integration.
do_action('woocommerce_email_header', $polski_email_heading, $polski_email);
?>

<p><?php echo esc_html($polski_greeting); ?></p>

<p><?php echo esc_html($polski_intro); ?></p>

<table cellspacing="0" cellpadding="6" border="1" style="border-collapse: collapse; width: 100%; margin: 16px 0;">
    <tbody>
        <tr>
            <th align="left" width="40%"><?php esc_html_e('Declaration ID', 'polski'); ?></th>
            <td><strong><?php echo esc_html($polski_declaration_id); ?></strong></td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Filed at', 'polski'); ?></th>
            <td><?php echo esc_html($polski_filed_at); ?></td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Order', 'polski'); ?></th>
            <td>#<?php echo esc_html((string) $polski_order->get_order_number()); ?></td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Order date', 'polski'); ?></th>
            <td><?php
                $polski_order_date = $polski_order->get_date_created();
                echo esc_html($polski_order_date !== null ? $polski_order_date->date_i18n(get_option('date_format')) : '');
            ?></td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Buyer', 'polski'); ?></th>
            <td>
                <?php echo esc_html(trim($polski_order->get_billing_first_name() . ' ' . $polski_order->get_billing_last_name())); ?><br />
                <?php echo esc_html((string) $polski_order->get_billing_email()); ?>
            </td>
        </tr>
    </tbody>
</table>

<?php if ($polski_request->reason) : ?>
    <p>
        <strong><?php echo esc_html((string) ($polski_settings['email_reason_label'] ?? __('Twój powód', 'polski'))); ?>:</strong><br />
        <?php echo esc_html($polski_request->reason); ?>
    </p>
<?php endif; ?>

<h3><?php esc_html_e('Items covered by this declaration', 'polski'); ?></h3>

<table cellspacing="0" cellpadding="6" border="1" style="border-collapse: collapse; width: 100%;">
    <thead>
        <tr>
            <th align="left"><?php esc_html_e('Product', 'polski'); ?></th>
            <th align="right"><?php esc_html_e('Qty', 'polski'); ?></th>
            <th align="right"><?php esc_html_e('Line total', 'polski'); ?></th>
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
            <td>
                <?php echo esc_html((string) $polski_item->get_name()); ?>
                <?php if ($polski_attrs !== '') : ?>
                    <br /><small><?php echo esc_html($polski_attrs); ?></small>
                <?php endif; ?>
            </td>
            <td align="right"><?php echo esc_html((string) $polski_item->get_quantity()); ?></td>
            <td align="right"><?php echo wp_kses_post(wc_price((float) $polski_item->get_total(), ['currency' => $polski_currency])); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th align="right" colspan="2"><?php esc_html_e('Order total', 'polski'); ?></th>
            <th align="right"><?php echo wp_kses_post(wc_price((float) $polski_order->get_total(), ['currency' => $polski_currency])); ?></th>
        </tr>
    </tfoot>
</table>

<p>
    <?php echo esc_html((string) ($polski_settings['email_return_instruction'] ?? __('Odeślij produkty na poniższy adres w ciągu 14 dni od dnia złożenia oświadczenia:', 'polski'))); ?>
</p>

<p>
    <?php echo wp_kses_post((string) get_option('woocommerce_store_address', '')); ?><br />
    <?php echo wp_kses_post((string) get_option('woocommerce_store_address_2', '')); ?><br />
    <?php echo wp_kses_post((string) get_option('woocommerce_store_postcode', '') . ' ' . (string) get_option('woocommerce_store_city', '')); ?>
</p>

<p style="font-size: smaller; color: #555;">
    <?php
    echo esc_html((string) ($polski_settings['email_durable_medium_notice'] ?? __(
        'Zachowaj tę wiadomość jako potwierdzenie złożenia oświadczenia. Zawiera ona niezbędne dane oświadczenia (numer, datę i czas złożenia, podsumowanie zamówienia).',
        'polski',
    )));
    ?>
</p>

<?php if ($polski_additional_content) : ?>
    <p><?php echo wp_kses_post($polski_additional_content); ?></p>
<?php endif; ?>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email footer hook for template integration.
do_action('woocommerce_email_footer', $polski_email);
