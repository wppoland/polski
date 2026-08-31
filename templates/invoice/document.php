<?php
/**
 * The invoice document, laid out for reading on screen and for printing.
 *
 * The free plugin does not bundle a PDF engine: a usable one is tens of
 * megabytes, which has no business in a plugin downloaded from WordPress.org.
 * Every browser prints to PDF, so the document is styled for print instead and
 * the Print button opens that dialog.
 *
 * @package Polski
 *
 * @var \Polski\Invoice\Invoice $invoice
 */

defined('ABSPATH') || exit;

$polski_doc      = $invoice->snapshot;
$polski_seller   = is_array($polski_doc['seller'] ?? null) ? $polski_doc['seller'] : [];
$polski_buyer    = is_array($polski_doc['buyer'] ?? null) ? $polski_doc['buyer'] : [];
$polski_lines    = is_array($polski_doc['lines'] ?? null) ? $polski_doc['lines'] : [];
$polski_vat      = is_array($polski_doc['vat'] ?? null) ? $polski_doc['vat'] : [];
$polski_payment  = is_array($polski_doc['payment'] ?? null) ? $polski_doc['payment'] : [];
$polski_currency = (string) ($polski_payment['currency'] ?? 'PLN');

$polski_money = static function (float $amount) use ($polski_currency): string {
    return number_format_i18n($amount, 2) . ' ' . $polski_currency;
};
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html(sprintf(
        /* translators: %s: invoice number */
        __('Invoice %s', 'polski'),
        $invoice->number,
    )); ?></title>
	<?php wp_print_styles('polski-invoice'); ?>
</head>
<body class="polski-invoice">
	<div class="polski-invoice__sheet">
		<header class="polski-invoice__head">
			<div>
				<h1 class="polski-invoice__title"><?php esc_html_e('VAT invoice', 'polski'); ?></h1>
				<p class="polski-invoice__number"><?php echo esc_html($invoice->number); ?></p>
			</div>
			<dl class="polski-invoice__dates">
				<div>
					<dt><?php esc_html_e('Issued', 'polski'); ?></dt>
					<dd><?php echo esc_html(mysql2date(get_option('date_format'), $invoice->issuedAt)); ?></dd>
				</div>
				<?php if (null !== $invoice->soldAt) : ?>
					<div>
						<dt><?php esc_html_e('Date of sale', 'polski'); ?></dt>
						<dd><?php echo esc_html(mysql2date(get_option('date_format'), $invoice->soldAt)); ?></dd>
					</div>
				<?php endif; ?>
				<?php if (null !== $invoice->dueAt) : ?>
					<div>
						<dt><?php esc_html_e('Payment due', 'polski'); ?></dt>
						<dd><?php echo esc_html(mysql2date(get_option('date_format'), $invoice->dueAt)); ?></dd>
					</div>
				<?php endif; ?>
			</dl>
		</header>

		<div class="polski-invoice__parties">
			<section>
				<h2><?php esc_html_e('Seller', 'polski'); ?></h2>
				<p class="polski-invoice__name"><?php echo esc_html((string) ($polski_seller['name'] ?? '')); ?></p>
				<p><?php echo nl2br(esc_html((string) ($polski_seller['address'] ?? ''))); ?></p>
				<?php if ('' !== (string) ($polski_seller['nip'] ?? '')) : ?>
					<p><?php echo esc_html(sprintf(
                        /* translators: %s: VAT identification number */
                        __('VAT ID: %s', 'polski'),
                        (string) $polski_seller['nip'],
                    )); ?></p>
				<?php endif; ?>
			</section>

			<section>
				<h2><?php esc_html_e('Buyer', 'polski'); ?></h2>
				<p class="polski-invoice__name"><?php echo esc_html((string) ($polski_buyer['name'] ?? '')); ?></p>
				<p><?php echo nl2br(esc_html((string) ($polski_buyer['address'] ?? ''))); ?></p>
				<?php if ('' !== (string) ($polski_buyer['nip'] ?? '')) : ?>
					<p><?php echo esc_html(sprintf(
                        /* translators: %s: VAT identification number */
                        __('VAT ID: %s', 'polski'),
                        (string) $polski_buyer['nip'],
                    )); ?></p>
				<?php endif; ?>
			</section>
		</div>

		<table class="polski-invoice__lines">
			<thead>
				<tr>
					<th scope="col" class="is-num">#</th>
					<th scope="col"><?php esc_html_e('Description', 'polski'); ?></th>
					<th scope="col" class="is-num"><?php esc_html_e('Qty', 'polski'); ?></th>
					<th scope="col" class="is-num"><?php esc_html_e('Net', 'polski'); ?></th>
					<th scope="col" class="is-num"><?php esc_html_e('VAT', 'polski'); ?></th>
					<th scope="col" class="is-num"><?php esc_html_e('Gross', 'polski'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($polski_lines as $polski_i => $polski_line) : ?>
					<tr>
						<td class="is-num"><?php echo esc_html((string) ((int) $polski_i + 1)); ?></td>
						<td><?php echo esc_html((string) $polski_line['name']); ?></td>
						<td class="is-num"><?php echo esc_html(number_format_i18n((float) $polski_line['quantity'], 0)); ?></td>
						<td class="is-num"><?php echo esc_html($polski_money((float) $polski_line['net'])); ?></td>
						<td class="is-num"><?php echo esc_html(number_format_i18n((float) $polski_line['rate'], 0) . '%'); ?></td>
						<td class="is-num"><?php echo esc_html($polski_money((float) $polski_line['gross'])); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="polski-invoice__foot">
			<table class="polski-invoice__vat">
				<caption><?php esc_html_e('VAT summary', 'polski'); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('Rate', 'polski'); ?></th>
						<th scope="col" class="is-num"><?php esc_html_e('Net', 'polski'); ?></th>
						<th scope="col" class="is-num"><?php esc_html_e('VAT', 'polski'); ?></th>
						<th scope="col" class="is-num"><?php esc_html_e('Gross', 'polski'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($polski_vat as $polski_row) : ?>
						<tr>
							<td><?php echo esc_html(number_format_i18n((float) $polski_row['rate'], 0) . '%'); ?></td>
							<td class="is-num"><?php echo esc_html($polski_money((float) $polski_row['net'])); ?></td>
							<td class="is-num"><?php echo esc_html($polski_money((float) $polski_row['tax'])); ?></td>
							<td class="is-num"><?php echo esc_html($polski_money((float) $polski_row['gross'])); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="polski-invoice__total">
				<span><?php esc_html_e('Total due', 'polski'); ?></span>
				<strong><?php echo esc_html($polski_money(array_sum(array_map(
                    static fn (array $r): float => (float) $r['gross'],
                    $polski_vat,
                )))); ?></strong>
			</div>
		</div>

		<footer class="polski-invoice__meta">
			<?php if ('' !== (string) ($polski_payment['method'] ?? '')) : ?>
				<p><?php echo esc_html(sprintf(
                    /* translators: %s: payment method name */
                    __('Payment method: %s', 'polski'),
                    (string) $polski_payment['method'],
                )); ?></p>
			<?php endif; ?>
			<?php if ('' !== (string) ($polski_seller['bank'] ?? '')) : ?>
				<p><?php echo esc_html(sprintf(
                    /* translators: %s: bank account number */
                    __('Bank account: %s', 'polski'),
                    (string) $polski_seller['bank'],
                )); ?></p>
			<?php endif; ?>
			<p><?php echo esc_html(sprintf(
                /* translators: %s: order number */
                __('Order: %s', 'polski'),
                (string) ($polski_payment['order'] ?? ''),
            )); ?></p>
		</footer>
	</div>

	<p class="polski-invoice__actions">
		<button type="button" onclick="window.print()"><?php esc_html_e('Print or save as PDF', 'polski'); ?></button>
	</p>
</body>
</html>
