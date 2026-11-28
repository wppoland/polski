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
// An empty VAT summary means the margin scheme: no net or VAT figures anywhere.
$polski_show_vat = $polski_vat !== [];
// A corrective invoice (art. 106j) names the invoice it corrects and why.
$polski_correction = is_array($polski_doc['correction'] ?? null) ? $polski_doc['correction'] : null;

// KSeF number and QR codes once the invoice went to KSeF (or was issued offline).
$polski_ksef = null;
if (class_exists(\Polski\KSeF\Repository\InvoiceRepository::class) && \Polski\Plugin::instance()->container()->has(\Polski\KSeF\Repository\InvoiceRepository::class)) {
    $polski_ksef = \Polski\Plugin::instance()->container()->get(\Polski\KSeF\Repository\InvoiceRepository::class)->findById($invoice->id);
}
$polski_ksef_codes = $polski_ksef !== null ? \Polski\KSeF\V2\VerificationLink::codes($polski_ksef) : [];
$polski_corrected_ksef = '';
if ($polski_ksef !== null && $polski_ksef->originalInvoiceId !== null) {
    $polski_corrected_ksef = (string) (\Polski\Plugin::instance()->container()->get(\Polski\KSeF\Repository\InvoiceRepository::class)->findById($polski_ksef->originalInvoiceId)?->ksefReference ?? '');
}

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
				<h1 class="polski-invoice__title"><?php echo null !== $polski_correction ? esc_html__('Corrective invoice', 'polski') : esc_html__('VAT invoice', 'polski'); ?></h1>
				<p class="polski-invoice__number"><?php echo esc_html($invoice->number); ?></p>
				<?php if (null !== $polski_correction) : ?>
					<p class="polski-invoice__corrects"><?php echo esc_html(sprintf(
                        /* translators: 1: corrected invoice number, 2: its issue date */
                        __('Corrects invoice %1$s of %2$s', 'polski'),
                        (string) ($polski_correction['of'] ?? ''),
                        (string) ($polski_correction['issued'] ?? ''),
                    )); ?><?php if (\Polski\KSeF\V2\VerificationLink::isKsefNumber($polski_corrected_ksef)) : ?><br><?php echo esc_html(sprintf(
                        /* translators: %s: KSeF number of the corrected invoice */
                        __('KSeF number of the corrected invoice: %s', 'polski'),
                        $polski_corrected_ksef,
                    )); ?><?php endif; ?><br><?php echo esc_html(sprintf(
                        /* translators: %s: reason for the correction */
                        __('Reason: %s', 'polski'),
                        (string) ($polski_correction['reason'] ?? ''),
                    )); ?></p>
				<?php endif; ?>
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
					<?php if ($polski_show_vat) : ?>
						<th scope="col" class="is-num"><?php esc_html_e('Net', 'polski'); ?></th>
						<th scope="col" class="is-num"><?php esc_html_e('VAT', 'polski'); ?></th>
						<th scope="col" class="is-num"><?php esc_html_e('Gross', 'polski'); ?></th>
					<?php else : ?>
						<th scope="col" class="is-num"><?php esc_html_e('Amount', 'polski'); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($polski_lines as $polski_i => $polski_line) : ?>
					<tr>
						<td class="is-num"><?php echo esc_html((string) ((int) $polski_i + 1)); ?></td>
						<td>
							<?php echo esc_html((string) $polski_line['name']); ?>
							<?php if ('' !== (string) ($polski_line['gtu'] ?? '')) : ?>
								<span class="polski-invoice__gtu"><?php echo esc_html((string) $polski_line['gtu']); ?></span>
							<?php endif; ?>
						</td>
						<td class="is-num"><?php echo esc_html(rtrim(rtrim(number_format_i18n((float) $polski_line['quantity'], 3), '0'), ',.') . ' ' . (string) ($polski_line['unit'] ?? 'szt.')); ?></td>
						<?php if ($polski_show_vat) : ?>
							<td class="is-num"><?php echo esc_html($polski_money((float) $polski_line['net'])); ?></td>
							<td class="is-num"><?php echo esc_html('' !== (string) ($polski_line['exemption'] ?? '') ? 'zw' : number_format_i18n((float) $polski_line['rate'], 0) . '%'); ?></td>
						<?php endif; ?>
						<td class="is-num"><?php echo esc_html($polski_money((float) $polski_line['gross'])); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="polski-invoice__foot">
			<?php if ($polski_show_vat) : ?>
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
							<td><?php echo esc_html(! empty($polski_row['exempt']) ? 'zw' : number_format_i18n((float) $polski_row['rate'], 0) . '%'); ?></td>
							<td class="is-num"><?php echo esc_html($polski_money((float) $polski_row['net'])); ?></td>
							<td class="is-num"><?php echo esc_html($polski_money((float) $polski_row['tax'])); ?></td>
							<td class="is-num"><?php echo esc_html($polski_money((float) $polski_row['gross'])); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>

			<?php foreach ((array) ($polski_doc['annotations'] ?? []) as $polski_annotation) : ?>
				<p class="polski-invoice__annotation"><?php echo esc_html((string) $polski_annotation); ?></p>
			<?php endforeach; ?>

			<div class="polski-invoice__total">
				<span><?php echo null !== $polski_correction ? esc_html__('Correction amount', 'polski') : esc_html__('Total due', 'polski'); ?></span>
				<strong><?php
                // Sum the lines, not the VAT breakdown. Under the margin scheme
                // the breakdown is empty by law, and summing it would print a
                // total due of zero on a real invoice.
                echo esc_html($polski_money(array_sum(array_map(
                    static fn (array $r): float => (float) ($r['gross'] ?? 0),
                    $polski_lines,
                )))); ?></strong>
			</div>
		</div>

		<?php if ($polski_ksef_codes !== []) : ?>
			<div class="polski-invoice__ksef">
				<?php foreach ($polski_ksef_codes as $polski_code) : ?>
					<figure style="display:inline-block;margin:0 16px 0 0;text-align:center;">
						<div style="width:<?php echo esc_attr((string) $polski_code['size']); ?>mm;height:<?php echo esc_attr((string) $polski_code['size']); ?>mm;">
							<?php echo wp_kses(\Polski\KSeF\V2\VerificationLink::svg($polski_code['url']), ['svg' => ['xmlns' => true, 'class' => true, 'viewbox' => true, 'preserveaspectratio' => true], 'path' => ['class' => true, 'fill' => true, 'd' => true]]); ?>
						</div>
						<figcaption style="font-size:9px;"><?php echo esc_html($polski_code['label']); ?></figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

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
