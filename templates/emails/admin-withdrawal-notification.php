<?php
/**
 * Admin notification email for a new withdrawal declaration (HTML).
 *
 * @var \WC_Order|null                       $order
 * @var \Polski\Model\WithdrawalRequest|null $request
 * @var string                              $email_heading
 * @var string                              $additional_content
 * @var bool                                $sent_to_admin
 * @var bool                                $plain_text
 * @var \WC_Email|null                      $email
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

$declaration_id = sprintf('POL-WD-%06d', $request->id);
$filed_at = wp_date((string) get_option('date_format') . ' H:i', $request->requestedAt->getTimestamp());
$currency = $order->get_currency();

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email header hook.
do_action('woocommerce_email_header', $email_heading, $email);
?>

<p>
    <?php
    printf(
        /* translators: %s = declaration id */
        esc_html__('A new withdrawal declaration %s has been registered in your store.', 'polski'),
        '<strong>' . esc_html($declaration_id) . '</strong>',
    );
    ?>
</p>

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
            <td>
                #<?php echo esc_html((string) $order->get_order_number()); ?>
                <?php if (method_exists($order, 'get_edit_order_url')) : ?>
                    (<a href="<?php echo esc_url($order->get_edit_order_url()); ?>"><?php esc_html_e('View order in admin', 'polski'); ?></a>)
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th align="left"><?php esc_html_e('Buyer', 'polski'); ?></th>
            <td>
                <?php echo esc_html(trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name())); ?><br />
                <a href="mailto:<?php echo esc_attr((string) $order->get_billing_email()); ?>"><?php echo esc_html((string) $order->get_billing_email()); ?></a>
                <?php if (method_exists($order, 'get_billing_phone') && $order->get_billing_phone()) : ?>
                    <br /><?php echo esc_html((string) $order->get_billing_phone()); ?>
                <?php endif; ?>
            </td>
        </tr>
    </tbody>
</table>

<?php if ($request->reason) : ?>
    <p>
        <strong><?php esc_html_e('Reason provided by customer:', 'polski'); ?></strong><br />
        <?php echo esc_html($request->reason); ?>
    </p>
<?php endif; ?>

<h3><?php esc_html_e('Items covered by this declaration', 'polski'); ?></h3>

<table cellspacing="0" cellpadding="6" border="1" style="border-collapse: collapse; width: 100%; margin-bottom: 20px;">
    <thead>
        <tr>
            <th align="left"><?php esc_html_e('Product', 'polski'); ?></th>
            <th align="right"><?php esc_html_e('Qty', 'polski'); ?></th>
            <th align="right"><?php esc_html_e('Line total', 'polski'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php
    $polski_lines = \Polski\Email\WithdrawalConfirmationEmail::declaredLines($order, $request);
    foreach ($polski_lines as $polski_line) :
        $item = $polski_line['item'];
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
            <td align="right"><?php echo esc_html((string) wc_stock_amount($polski_line['quantity'])); ?></td>
            <td align="right"><?php echo wp_kses_post(wc_price($polski_line['total'], ['currency' => $currency])); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th align="right" colspan="2"><?php esc_html_e('Total', 'polski'); ?></th>
            <th align="right"><?php echo wp_kses_post(wc_price(array_sum(array_column($polski_lines, 'total')), ['currency' => $currency])); ?></th>
        </tr>
    </tfoot>
</table>

<?php if ($additional_content) : ?>
    <p><?php echo wp_kses_post($additional_content); ?></p>
<?php endif; ?>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core footer hook.
do_action('woocommerce_email_footer', $email);
