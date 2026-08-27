<?php
/**
 * Withdrawal confirmation email (HTML).
 *
 * Designed to serve as the buyer's record of their withdrawal declaration on a
 * durable medium: it captures a frozen snapshot of the order at the time the
 * request was filed (line items, totals, declaration ID, timestamp).
 *
 * @var \WC_Order|null                     $order
 * @var \Polski\Model\WithdrawalRequest|null $request
 * @var string                            $email_heading
 * @var string                            $additional_content
 * @var bool                              $sent_to_admin
 * @var bool                              $plain_text
 * @var \WC_Email|null                    $email
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$order = $order ?? $polski_order ?? null;
$request = $request ?? $polski_request ?? null;
$email_heading = $email_heading ?? $polski_email_heading ?? '';
$additional_content = $additional_content ?? $polski_additional_content ?? '';
$email = $email ?? $polski_email ?? null;

if (! $order instanceof \WC_Order || ! $request instanceof \Polski\Model\WithdrawalRequest) {
    return;
}

$polski_settings = get_option('polski_withdrawal', []);
$polski_settings = is_array($polski_settings) ? $polski_settings : [];

$greeting = str_replace(
    '{name}',
    (string) $order->get_billing_first_name(),
    (string) ($polski_settings['email_greeting'] ?? __('Hello {name},', 'polski')),
);
$intro = str_replace(
    '{order_number}',
    (string) $order->get_order_number(),
    (string) ($polski_settings['email_intro_text'] ?? __('Your withdrawal declaration for order #{order_number} has been registered.', 'polski')),
);

$declaration_id = sprintf('POL-WD-%06d', $request->id);
$filed_at = wp_date((string) get_option('date_format') . ' H:i', $request->requestedAt->getTimestamp());
$currency = $order->get_currency();

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email header hook for template integration.
do_action('woocommerce_email_header', $email_heading, $email);
?>

<p><?php echo esc_html($greeting); ?></p>

<p><?php echo esc_html($intro); ?></p>

<table cellspacing="0" cellpadding="6" border="1" style="border-collapse: collapse; width: 100%; margin: 16px 0;">
    <tbody>
        <tr>
            <th align="left" width="40%"><?php esc_html_e('Declaration ID', 'polski'); ?></th>
            <td><strong><?php echo esc_html($declaration_id); ?></strong></td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Filed at', 'polski'); ?></th>
            <td><?php echo esc_html($filed_at); ?></td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Order', 'polski'); ?></th>
            <td>#<?php echo esc_html((string) $order->get_order_number()); ?></td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Order date', 'polski'); ?></th>
            <td><?php
                $order_date = $order->get_date_created();
                echo esc_html($order_date instanceof \WC_DateTime ? wp_date((string) get_option('date_format'), $order_date->getTimestamp()) : '');
            ?></td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Buyer', 'polski'); ?></th>
            <td>
                <?php echo esc_html(trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name())); ?><br />
                <?php echo esc_html((string) $order->get_billing_email()); ?>
            </td>
        </tr>
    </tbody>
</table>

<?php if ($request->reason) : ?>
    <p>
        <strong><?php echo esc_html((string) ($polski_settings['email_reason_label'] ?? __('Your reason', 'polski'))); ?>:</strong><br />
        <?php echo esc_html($request->reason); ?>
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
    <?php foreach ($order->get_items() as $item) :
        if (! $item instanceof \WC_Order_Item_Product) {
            continue;
        }
        $product = $item->get_product();
        $attrs = '';
        if ($product instanceof \WC_Product && $product->is_type('variation')) {
            $attrs = wc_get_formatted_variation($product, true, true, false);
        }
        ?>
        <tr>
            <td>
                <?php echo esc_html((string) $item->get_name()); ?>
                <?php if ($attrs !== '') : ?>
                    <br /><small><?php echo esc_html($attrs); ?></small>
                <?php endif; ?>
            </td>
            <td align="right"><?php echo esc_html((string) $item->get_quantity()); ?></td>
            <td align="right"><?php echo wp_kses_post(wc_price((float) $item->get_total(), ['currency' => $currency])); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th align="right" colspan="2"><?php esc_html_e('Order total', 'polski'); ?></th>
            <th align="right"><?php echo wp_kses_post(wc_price((float) $order->get_total(), ['currency' => $currency])); ?></th>
        </tr>
    </tfoot>
</table>

<p>
    <?php echo esc_html((string) ($polski_settings['email_return_instruction'] ?? __('Send the goods back to the address below within 14 days of filing the declaration:', 'polski'))); ?>
</p>

<p>
    <?php echo wp_kses_post((string) get_option('woocommerce_store_address', '')); ?><br />
    <?php echo wp_kses_post((string) get_option('woocommerce_store_address_2', '')); ?><br />
    <?php echo wp_kses_post((string) get_option('woocommerce_store_postcode', '') . ' ' . (string) get_option('woocommerce_store_city', '')); ?>
</p>

<p style="font-size: smaller; color: #555;">
    <?php
    echo esc_html((string) ($polski_settings['email_durable_medium_notice'] ?? __(
        'Keep this message as proof that the declaration was filed. It holds everything the declaration needs: the number, the date and time it was filed, and a summary of the order.',
        'polski',
    )));
    ?>
</p>

<?php if ($additional_content) : ?>
    <p><?php echo wp_kses_post($additional_content); ?></p>
<?php endif; ?>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email footer hook for template integration.
do_action('woocommerce_email_footer', $email);
