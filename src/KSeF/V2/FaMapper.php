<?php

declare(strict_types=1);

namespace Polski\KSeF\V2;

use Polski\KSeF\ExchangeRateProvider;
use Polski\KSeF\Model\Invoice;
use Polski\KSeF\Model\InvoiceItem;
use Polski\KSeF\Model\InvoiceType;

defined('ABSPATH') || exit;

/**
 * Builds an FA(3) document that passes schemat_FA(3)_v1-0E.xsd.
 *
 * FA(3) is mandatory for invoices issued from 2026-02-01. Against FA(2) it
 * moved to a new namespace and changed three things this mapper emits: Podmiot2
 * gained the mandatory JST and GV flags, the 0% rate split into "0 KR", "0 WDT"
 * and "0 EX", and P_7 grew from 256 to 512 characters.
 *
 * Written fresh rather than patched onto the KSeF 1.0 mapper, which had never
 * been validated against the schema and could not be: it omitted the mandatory
 * Adnotacje block entirely, wrapped the lines in a FaWiersze element that does
 * not exist, made the seller NIP conditional when the schema requires it, and
 * wrote the VAT rate as "23%" where the enumeration only accepts "23".
 *
 * Two rules the schema enforces that nothing else hints at:
 *
 *  - Fa is one long xsd:sequence, so element order is correctness. Adnotacje
 *    sits between P_15 and RodzajFaktury, and the correction fields come after
 *    RodzajFaktury, not before.
 *  - Every child of Adnotacje is mandatory, including the ones a normal shop
 *    always answers "no" to. There is no "omit when not applicable".
 */
final class FaMapper
{
    private const NAMESPACE_FA = 'http://crd.gov.pl/wzor/2025/06/25/13775/';

    /** Answer "2" means "does not apply", which is what a plain shop sale is. */
    private const NIE = '2';

    /**
     * Where each VAT rate's sales total is reported in the Fa header.
     *
     * P_12 value, net element, tax element. A null tax element means the schema
     * has no tax field for that bucket, which is the case for 0% and "zw". Only
     * which of the zero rates a 0% line is follows from the buyer and the line,
     * see rate(). "zw" is used only for a seller exempt under art. 113, see
     * $exemption. Rates
     * share elements by design: 23 and 22 both land in P_13_1, 8 and 7 in
     * P_13_2, because the statute counts them as one band.
     *
     * A list rather than a map keyed by rate, because PHP would silently turn
     * '23' into an integer key and the declared type would stop matching.
     * Declaration order is the order the xsd:sequence expects.
     *
     * @var list<array{0: string, 1: string, 2: string|null}>
     */
    private const RATE_BUCKETS = [
        ['23', 'P_13_1', 'P_14_1'],
        ['22', 'P_13_1', 'P_14_1'],
        ['8', 'P_13_2', 'P_14_2'],
        ['7', 'P_13_2', 'P_14_2'],
        ['5', 'P_13_3', 'P_14_3'],
        ['0 KR', 'P_13_6_1', null],
        ['0 WDT', 'P_13_6_2', null],
        ['0 EX', 'P_13_6_3', null],
        ['zw', 'P_13_7', null],
        ['np I', 'P_13_8', null],
        ['np II', 'P_13_9', null],
    ];

    /**
     * The TGTU enumeration from schemat_FA(3)_v1-0E.xsd, unchanged from FA(2).
     *
     * @var list<string>
     */
    private const GTU_CODES = [
        'GTU_01', 'GTU_02', 'GTU_03', 'GTU_04', 'GTU_05', 'GTU_06', 'GTU_07',
        'GTU_08', 'GTU_09', 'GTU_10', 'GTU_11', 'GTU_12', 'GTU_13',
    ];

    /** @var \Closure(?int): string */
    private readonly \Closure $gtuLookup;

    /** @var \Closure(string, \DateTimeImmutable): ?float */
    private readonly \Closure $plnRate;

    /** @var \Closure(): string */
    private readonly \Closure $exemption;

    /** @var \Closure(?int): bool */
    private readonly \Closure $isService;

    /** @var \Closure(?int): string */
    private readonly \Closure $lineExemption;

    /** @var \Closure(): bool */
    private readonly \Closure $cashAccounting;

    /** @var \Closure(Invoice): array{paid: ?string, due: ?string, method: string, title: string, account: string, order: string, ordered: ?string} */
    private readonly \Closure $paymentTerms;

    /** @var \Closure(?int): array{sku: string, gtin: string} */
    private readonly \Closure $lineIds;

    /** @var \Closure(): array{krs: string, regon: string, bdo: string} */
    private readonly \Closure $registers;

    /** Bases of the exempt lines (art. 43 and the like) of the document being built, when the seller itself charges VAT. */
    private string $lineBases = '';

    /** Legal basis of the seller's VAT exemption for the document being built, '' when none. */
    private string $exemptionBasis = '';

    /** Buyer of the document being built; decides which zero rate a 0% line is. */
    private ?FaParty $buyer = null;

    /** Whether the document being built sells any goods, which lines without a product follow. */
    private bool $hasGoods = false;

    /**
     * @param (\Closure(?int): string)|null                       $gtuLookup GTU code for a product id; defaults to the product meta.
     * @param (\Closure(string, \DateTimeImmutable): ?float)|null $plnRate   PLN per unit of a currency for a tax point; defaults to the NBP rate the PDF prints.
     * @param (\Closure(): string)|null                          $exemption Legal basis of an art. 113 exemption, '' when the seller charges VAT; defaults to the free plugin's small business switch.
     * @param (\Closure(?int): bool)|null                         $isService Whether a product is a service; defaults to WooCommerce's virtual flag.
     * @param (\Closure(?int): string)|null                       $lineExemption Exemption basis of a product sold at 0%, '' when none; defaults to the free plugin's product field.
     * @param (\Closure(): bool)|null                             $cashAccounting Whether the seller uses cash accounting (P_16); defaults to the free plugin's setting.
     * @param (\Closure(Invoice): array{paid: ?string, due: ?string, method: string, title: string, account: string, order: string, ordered: ?string})|null $paymentTerms Payment and order data for Platnosc and WarunkiTransakcji; defaults to the WooCommerce order and the invoice settings.
     */
    public function __construct(
        private readonly string $systemInfo = 'Polski Pro',
        ?\Closure $gtuLookup = null,
        ?\Closure $plnRate = null,
        ?\Closure $exemption = null,
        ?\Closure $isService = null,
        ?\Closure $lineExemption = null,
        ?\Closure $cashAccounting = null,
        ?\Closure $paymentTerms = null,
        ?\Closure $lineIds = null,
        ?\Closure $registers = null,
    ) {
        $this->lineIds = $lineIds ?? self::productIds(...);
        $this->registers = $registers ?? self::sellerRegisters(...);
        $this->paymentTerms = $paymentTerms ?? self::orderTerms(...);
        $this->lineExemption = $lineExemption ?? self::productExemption(...);
        $this->cashAccounting = $cashAccounting ?? self::cashAccounting(...);
        $this->gtuLookup = $gtuLookup ?? static function (?int $productId): string {
            if ($productId === null || $productId <= 0 || ! function_exists('wc_get_product')) {
                return '';
            }

            $product = wc_get_product($productId);

            // Entered by hand in the free plugin or assigned by a tax rule
            // here; both write the same meta key.
            return $product instanceof \WC_Product ? (string) $product->get_meta(\Polski\Invoice\Gtu::META, true) : '';
        };

        $this->plnRate = $plnRate ?? static function (string $currency, \DateTimeImmutable $taxPoint): ?float {
            if (! class_exists(ExchangeRateProvider::class) || ! function_exists('wp_remote_get')) {
                return null;
            }

            // Same lookup as InvoiceGenerator, so the PDF and the KSeF document
            // state the same rate (cached per currency and day).
            return (new ExchangeRateProvider())->plnRate($currency, $taxPoint)['rate'] ?? null;
        };

        $this->exemption = $exemption ?? self::smallBusinessBasis(...);

        // ponytail: virtual means service. Right for downloads (an ebook is an
        // electronically supplied service to the VAT act) and for anything a
        // shop does not ship; wrong only for a service product left
        // non-virtual. Add a per-product switch when a shop hits that.
        $this->isService = $isService ?? static function (?int $productId): bool {
            if ($productId === null || $productId <= 0 || ! function_exists('wc_get_product')) {
                return false;
            }

            $product = wc_get_product($productId);

            return $product instanceof \WC_Product && $product->is_virtual();
        };
    }

    /** The exemption basis Polski stores on a product (or its parent), '' when none: the line is then "zw", not "0 KR". */
    public static function productExemption(?int $productId): string
    {
        if ($productId === null || $productId <= 0 || ! function_exists('wc_get_product')) {
            return '';
        }

        $product = wc_get_product($productId);
        if (! $product instanceof \WC_Product) {
            return '';
        }
        $basis = trim((string) $product->get_meta('_polski_vat_exemption', true));
        if ($basis === '' && $product->get_parent_id() > 0) {
            $parent = wc_get_product($product->get_parent_id());
            $basis = $parent instanceof \WC_Product ? trim((string) $parent->get_meta('_polski_vat_exemption', true)) : '';
        }

        return $basis;
    }

    /** Cash accounting (art. 21 ustawy o VAT), from the free plugin's setting: P_16 and the "metoda kasowa" line (art. 106e ust. 1 pkt 16). */
    public static function cashAccounting(): bool
    {
        $general = function_exists('get_option') ? get_option('polski_general', []) : [];

        return is_array($general) && ! empty($general['cash_accounting']);
    }

    /**
     * Legal basis of the seller's art. 113 exemption, from the free plugin's
     * small business switch; '' when the shop charges VAT. Shared with the PDF,
     * so the paper invoice and the KSeF document never disagree about it.
     */
    public static function smallBusinessBasis(): string
    {
        if (! function_exists('get_option')) {
            return '';
        }

        $general = get_option('polski_general', []);

        if (! is_array($general) || empty($general['small_business'])) {
            return '';
        }

        // The shop's own exemption text when it has written one; the free
        // plugin ships an English sentence, which is no basis to file.
        $taxes = get_option('polski_taxes', []);
        $notice = is_array($taxes) ? trim((string) ($taxes['vat_exempt_notice'] ?? '')) : '';

        return $notice !== '' && $notice !== 'Exempt from VAT based on Art. 113 par. 1 of the VAT Act'
            ? $notice
            : 'art. 113 ust. 1 ustawy o VAT';
    }

    /**
     * @param Invoice|null $corrected   The invoice being corrected, required when $invoice is a Korygujaca.
     * @param FaParty|null $buyerBefore The buyer as the corrected invoice named it, written as Podmiot2K on a buyer correction.
     *
     * @throws \InvalidArgumentException When the invoice cannot be represented as an FA(3) document.
     */
    public function toXml(Invoice $invoice, FaParty $seller, FaParty $buyer, ?Invoice $corrected = null, ?FaParty $buyerBefore = null): string
    {
        if (! $invoice->type->isAccountingDocument()) {
            throw new \InvalidArgumentException(
                esc_html(sprintf('%s documents are not eligible for KSeF submission.', $invoice->type->label()))
            );
        }

        $isCorrection = $invoice->type === InvoiceType::Korygujaca;
        $this->prepare($invoice, $buyer);

        if ($isCorrection && $corrected === null) {
            throw new \InvalidArgumentException(
                esc_html('A corrective invoice needs the invoice it corrects; DaneFaKorygowanej is mandatory.')
            );
        }

        if (! $seller->hasPolishNip()) {
            throw new \InvalidArgumentException(
                esc_html('The seller NIP is missing, malformed or fails the NIP checksum; FA(3) requires a valid one on Podmiot1.')
            );
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NAMESPACE_FA, 'Faktura');
        $dom->appendChild($root);

        $this->appendNaglowek($dom, $root);
        $this->appendPodmiot1($dom, $root, $seller);
        $this->appendPodmiot2($dom, $root, $buyer);

        $fa = $this->child($dom, $root, 'Fa');
        $this->child($dom, $fa, 'KodWaluty', $invoice->currency);
        $this->child($dom, $fa, 'P_1', $invoice->issuedAt->format('Y-m-d'));
        $this->child($dom, $fa, 'P_2', $invoice->number);

        // Date of supply, only where it differs from the issue date: the
        // schema's own wording for P_6 (art. 106e ust. 1 pkt 6).
        if ($invoice->soldAt !== null && $invoice->soldAt->format('Y-m-d') !== $invoice->issuedAt->format('Y-m-d')) {
            $this->child($dom, $fa, 'P_6', $invoice->soldAt->format('Y-m-d'));
        }

        // Art. 106e ust. 11: a foreign-currency invoice also states its VAT in
        // PLN. A reduction converts at the rate used originally (art. 31a ust.
        // 2a), so a correction looks up the corrected invoice's tax point.
        // ponytail: an in-plus correction whose cause arose later should use
        // the rate before the correction (ust. 2b); the invoice cannot say which.
        // Without a rate the W fields stay out and FaValidator refuses the document.
        $exchangeRate = null;

        if (strtoupper($invoice->currency) !== 'PLN') {
            $exchangeRate = ($this->plnRate)($invoice->currency, ($corrected ?? $invoice)->issuedAt);
            $exchangeRate = $exchangeRate !== null && $exchangeRate > 0.0 ? $exchangeRate : null;
        }

        // Header totals are always the difference, also when the lines are
        // listed before and after.
        $this->appendRateTotals($dom, $fa, $invoice->differenceItems(), $exchangeRate);

        $this->child($dom, $fa, 'P_15', $this->amount($invoice->grossTotal));
        $this->appendAdnotacje($dom, $fa, $invoice->items, self::splitPayment($invoice));
        $this->child($dom, $fa, 'RodzajFaktury', $isCorrection ? 'KOR' : 'VAT');

        if ($corrected !== null) {
            $this->appendKorekta($dom, $fa, $invoice, $corrected, $buyerBefore);
        }

        if ($corrected !== null && $invoice->correctsByState()) {
            $this->appendStateLines($dom, $fa, $invoice, $corrected, $exchangeRate);
        } else {
            foreach ($invoice->items as $index => $item) {
                $this->appendWiersz($dom, $fa, $item, $index + 1, $corrected, $exchangeRate);
            }
        }

        // What a buyer's software reads to pay: not in art. 106e, but the KSeF XML is the invoice it receives.
        $terms = ($this->paymentTerms)($invoice);
        if (! $isCorrection) {
            $this->appendPlatnosc($dom, $fa, $terms);
        }
        if ($terms['order'] !== '') {
            $zamowienia = $this->child($dom, $this->child($dom, $fa, 'WarunkiTransakcji'), 'Zamowienia');
            if ($terms['ordered'] !== null) {
                $this->child($dom, $zamowienia, 'DataZamowienia', $terms['ordered']);
            }
            $this->child($dom, $zamowienia, 'NrZamowienia', $this->truncate($terms['order'], 256));
        }

        $this->appendStopka($dom, $root);

        return $dom->saveXML() ?: '';
    }

    /** The seller's registers (KRS, REGON, BDO) in Stopka, when any is valid. */
    private function appendStopka(\DOMDocument $dom, \DOMElement $root): void
    {
        $r = ($this->registers)();
        $krs = preg_match('/^\d{10}$/', $r['krs']) ? $r['krs'] : '';
        $regon = preg_match('/^(\d{9}|\d{14})$/', $r['regon']) ? $r['regon'] : '';
        $bdo = preg_match('/^\d{1,9}$/', $r['bdo']) ? $r['bdo'] : '';
        if ($krs === '' && $regon === '' && $bdo === '') {
            return;
        }

        $rejestry = $this->child($dom, $this->child($dom, $root, 'Stopka'), 'Rejestry');
        foreach (['KRS' => $krs, 'REGON' => $regon, 'BDO' => $bdo] as $name => $value) {
            if ($value !== '') {
                $this->child($dom, $rejestry, $name, $value);
            }
        }
    }

    /**
     * SKU and GTIN of a product (a variation falls back to its parent's): the
     * WooCommerce GTIN field, else the one Polski stores.
     *
     * @return array{sku: string, gtin: string}
     */
    public static function productIds(?int $productId): array
    {
        $product = $productId !== null && $productId > 0 && function_exists('wc_get_product') ? wc_get_product($productId) : null;
        if (! $product instanceof \WC_Product) {
            return ['sku' => '', 'gtin' => ''];
        }
        // WooCommerce's own GTIN field (stored as _global_unique_id since 9.2; Polski supports 8.0), else Polski's.
        $gtin = trim((string) get_post_meta($product->get_id(), '_global_unique_id', true));
        if ($gtin === '') {
            $gtin = trim((string) $product->get_meta('_polski_gtin', true));
        }

        return ['sku' => trim((string) $product->get_sku()), 'gtin' => preg_replace('/\D/', '', $gtin) ?? ''];
    }

    /**
     * KRS, REGON and BDO from the company details (KRS through the polski/ksef/registers filter only).
     *
     * @return array{krs: string, regon: string, bdo: string}
     */
    public static function sellerRegisters(): array
    {
        $general = function_exists('get_option') ? get_option('polski_general', []) : [];
        $general = is_array($general) ? $general : [];
        $registers = [
            'krs' => '',
            'regon' => preg_replace('/\D/', '', (string) ($general['company_regon'] ?? '')) ?? '',
            'bdo' => preg_replace('/\D/', '', (string) ($general['bdo_number'] ?? '')) ?? '',
        ];

        /**
         * Filters the seller registers printed in the KSeF document footer.
         *
         * @param array{krs: string, regon: string, bdo: string} $registers
         */
        $filtered = function_exists('apply_filters') ? apply_filters('polski/ksef/registers', $registers) : $registers;

        return is_array($filtered) ? array_map('strval', $filtered + $registers) : $registers;
    }

    /** TFormaPlatnosci by gateway id prefix; anything else goes as PlatnoscInna with the method title. */
    private const PAYMENT_FORMS = ['cod' => '1', 'stripe' => '2', 'cheque' => '4', 'bacs' => '6'];

    /** @param array{paid: ?string, due: ?string, method: string, title: string, account: string, order: string, ordered: ?string} $terms */
    private function appendPlatnosc(\DOMDocument $dom, \DOMElement $fa, array $terms): void
    {
        $form = '';
        foreach (self::PAYMENT_FORMS as $prefix => $code) {
            if (str_starts_with($terms['method'], $prefix)) {
                $form = $code;
                break;
            }
        }
        $account = (string) preg_replace('/\s+/', '', $terms['account']);
        $account = $terms['paid'] === null && strlen($account) >= 10 && strlen($account) <= 34 ? $account : '';

        if ($terms['paid'] === null && $terms['due'] === null && $form === '' && $terms['title'] === '' && $account === '') {
            return;
        }

        $platnosc = $this->child($dom, $fa, 'Platnosc');
        if ($terms['paid'] !== null) {
            $this->child($dom, $platnosc, 'Zaplacono', '1');
            $this->child($dom, $platnosc, 'DataZaplaty', $terms['paid']);
        } elseif ($terms['due'] !== null) {
            $this->child($dom, $this->child($dom, $platnosc, 'TerminPlatnosci'), 'Termin', $terms['due']);
        }
        if ($form !== '') {
            $this->child($dom, $platnosc, 'FormaPlatnosci', $form);
        } elseif ($terms['title'] !== '') {
            $this->child($dom, $platnosc, 'PlatnoscInna', '1');
            $this->child($dom, $platnosc, 'OpisPlatnosci', $this->truncate($terms['title'], 256));
        }
        if ($account !== '') {
            $this->child($dom, $this->child($dom, $platnosc, 'RachunekBankowy'), 'NrRB', $account);
        }
    }

    /**
     * Payment and order data from the WooCommerce order and the invoice
     * settings (the due date and account the PDF prints).
     *
     * @return array{paid: ?string, due: ?string, method: string, title: string, account: string, order: string, ordered: ?string}
     */
    public static function orderTerms(Invoice $invoice): array
    {
        $terms = ['paid' => null, 'due' => null, 'method' => '', 'title' => '', 'account' => '', 'order' => '', 'ordered' => null];
        $order = function_exists('wc_get_order') && $invoice->orderId > 0 ? wc_get_order($invoice->orderId) : null;
        if (! $order instanceof \WC_Order) {
            return $terms;
        }

        $settings = get_option('polski_pro_invoices', []);
        $settings = is_array($settings) ? $settings : [];
        $days = (int) ($settings['payment_deadline_days'] ?? 0);
        $paid = $order->get_date_paid();

        return [
            'paid' => $paid !== null ? $paid->date('Y-m-d') : null,
            'due' => $days > 0 ? $invoice->issuedAt->modify('+' . $days . ' days')->format('Y-m-d') : null,
            'method' => (string) $order->get_payment_method(),
            'title' => (string) $order->get_payment_method_title(),
            'account' => (string) ($settings['seller_bank_account'] ?? ''),
            'order' => (string) $order->get_order_number(),
            'ordered' => $order->get_date_created()?->date('Y-m-d'),
        ];
    }

    private function appendNaglowek(\DOMDocument $dom, \DOMElement $root): void
    {
        $naglowek = $this->child($dom, $root, 'Naglowek');

        $kod = $this->child($dom, $naglowek, 'KodFormularza', 'FA');
        $kod->setAttribute('kodSystemowy', 'FA (3)');
        $kod->setAttribute('wersjaSchemy', '1-0E');

        $this->child($dom, $naglowek, 'WariantFormularza', '3');
        // TDataCzas is bounded by two timezoned instants, so an offsetless
        // stamp compares indeterminately against them. Always send UTC.
        $this->child(
            $dom,
            $naglowek,
            'DataWytworzeniaFa',
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z')
        );
        $this->child($dom, $naglowek, 'SystemInfo', $this->truncate($this->systemInfo, 256));
    }

    private function appendPodmiot1(\DOMDocument $dom, \DOMElement $root, FaParty $seller): void
    {
        $podmiot = $this->child($dom, $root, 'Podmiot1');

        $dane = $this->child($dom, $podmiot, 'DaneIdentyfikacyjne');
        $this->child($dom, $dane, 'NIP', $seller->nip);
        $this->child($dom, $dane, 'Nazwa', $this->truncate($seller->name, 512));

        $this->appendAdres($dom, $podmiot, $seller);
    }

    private function appendPodmiot2(\DOMDocument $dom, \DOMElement $root, FaParty $buyer): void
    {
        $podmiot = $this->child($dom, $root, 'Podmiot2');

        $this->appendBuyerData($dom, $podmiot, $buyer);

        // Both mandatory since FA(3). "1" would route the invoice to a local
        // government sub-unit or a VAT group member through Podmiot3, which a
        // shop order never does.
        $this->child($dom, $podmiot, 'JST', self::NIE);
        $this->child($dom, $podmiot, 'GV', self::NIE);
    }

    /** DaneIdentyfikacyjne and Adres of a buyer, shared by Podmiot2 and Podmiot2K. */
    private function appendBuyerData(\DOMDocument $dom, \DOMElement $podmiot, FaParty $buyer): void
    {
        $dane = $this->child($dom, $podmiot, 'DaneIdentyfikacyjne');

        // Written whenever it has the shape of a NIP, checksum or not, so a
        // mistyped one reaches FaValidator and is refused there instead of
        // quietly turning a business buyer into a consumer.
        if ($buyer->claimsPolishNip()) {
            $this->child($dom, $dane, 'NIP', $buyer->nip);
        } elseif (($euVat = $buyer->euVat()) !== null) {
            // Art. 106e ust. 1 pkt 24: WDT and art. 28b services name the
            // buyer by its EU VAT number.
            $this->child($dom, $dane, 'KodUE', $euVat[0]);
            $this->child($dom, $dane, 'NrVatUE', $euVat[1]);
        } elseif (trim($buyer->nip) !== '' && strtoupper($buyer->countryCode) !== 'PL') {
            // Any other foreign tax id (a US EIN, a Swiss UID) goes out as
            // typed, with the country that issued it, not as "no id".
            $this->child($dom, $dane, 'KodKraju', strtoupper($buyer->countryCode));
            $this->child($dom, $dane, 'NrID', mb_substr(trim($buyer->nip), 0, 50));
        } else {
            // A consumer has no tax id and the schema wants that said out loud
            // rather than left out; an omitted choice branch is a hard failure.
            $this->child($dom, $dane, 'BrakID', '1');
        }

        $this->child($dom, $dane, 'Nazwa', $this->truncate($buyer->name, 512));

        $this->appendAdres($dom, $podmiot, $buyer);
    }

    private function appendAdres(\DOMDocument $dom, \DOMElement $parent, FaParty $party): void
    {
        $adres = $this->child($dom, $parent, 'Adres');
        $this->child($dom, $adres, 'KodKraju', strtoupper($party->countryCode));
        $this->child($dom, $adres, 'AdresL1', $this->truncate($party->addressL1, 512));

        if (trim($party->addressL2) !== '') {
            $this->child($dom, $adres, 'AdresL2', $this->truncate($party->addressL2, 512));
        }
    }

    /**
     * Net and tax totals per rate, in the order the sequence declares them.
     *
     * @param list<InvoiceItem> $items
     * @param float|null        $exchangeRate PLN per unit on a foreign-currency invoice; adds P_14_xW.
     */
    private function appendRateTotals(\DOMDocument $dom, \DOMElement $fa, array $items, ?float $exchangeRate = null): void
    {
        // Accumulate straight into the element names, so two rates sharing a
        // bucket add up instead of writing the element twice, which the
        // sequence forbids.
        $net = [];
        $vat = [];

        foreach ($items as $item) {
            [$netElement, $vatElement] = $this->bucket($this->rate($item), $item->name);

            $net[$netElement] = ($net[$netElement] ?? 0.0) + $item->netPrice;

            if ($vatElement !== null) {
                $vat[$vatElement] = ($vat[$vatElement] ?? 0.0) + $item->vatAmount;
            }
        }

        foreach (self::RATE_BUCKETS as [, $netElement, $vatElement]) {
            if (! isset($net[$netElement])) {
                continue;
            }

            $this->child($dom, $fa, $netElement, $this->amount($net[$netElement]));

            if ($vatElement !== null && isset($vat[$vatElement])) {
                $this->child($dom, $fa, $vatElement, $this->amount($vat[$vatElement]));

                // P_14_1W to P_14_4W follow their P_14_x in the same sequence.
                if ($exchangeRate !== null) {
                    $this->child($dom, $fa, $vatElement . 'W', $this->amount(round($vat[$vatElement] * $exchangeRate, 2)));
                }
            }

            unset($net[$netElement]);
        }
    }

    /**
     * @return array{0: string, 1: string|null} Net element, tax element.
     *
     * @throws \InvalidArgumentException When the rate is not one FA(3) knows.
     */
    private function bucket(string $rate, string $lineName): array
    {
        foreach (self::RATE_BUCKETS as [$candidate, $netElement, $vatElement]) {
            if ($candidate === $rate) {
                return [$netElement, $vatElement];
            }
        }

        throw new \InvalidArgumentException(
            esc_html(sprintf('VAT rate %s%% has no FA(3) equivalent on line "%s".', $rate, $lineName))
        );
    }

    /**
     * Split payment (art. 108a ust. 1a): a business buyer, more than 15 000 zł
     * gross in PLN, and a line whose product the shop marked as annex 15 in
     * Polski (product meta _polski_mpp). Filter polski/pro/ksef/split_payment.
     */
    public static function splitPayment(Invoice $invoice): bool
    {
        $applies = false;
        if (($invoice->nipBuyer ?? '') !== '' && strtoupper($invoice->currency) === 'PLN' && $invoice->grossTotal > 15000.0 && function_exists('wc_get_product')) {
            foreach ($invoice->items as $item) {
                $product = $item->productId ? wc_get_product($item->productId) : null;
                if (! $product instanceof \WC_Product) {
                    continue;
                }
                $parent = $product->get_parent_id() > 0 ? wc_get_product($product->get_parent_id()) : null;
                if ($product->get_meta('_polski_mpp', true) === 'yes' || ($parent instanceof \WC_Product && $parent->get_meta('_polski_mpp', true) === 'yes')) {
                    $applies = true;
                    break;
                }
            }
        }

        return function_exists('apply_filters') ? (bool) apply_filters('polski/pro/ksef/split_payment', $applies, $invoice) : $applies;
    }

    /**
     * The eight mandatory declarations, every one of them answered.
     *
     * All negative, which is what an ordinary WooCommerce sale is, except
     * Zwolnienie for a seller exempt under art. 113, which states P_19 and the
     * basis in P_19A, and P_18 when a line is an art. 28b service to an EU
     * business ("np II"), where the buyer accounts for the VAT, and P_18A for
     * split payment (see splitPayment()).
     *
     * @param list<InvoiceItem> $items
     */
    private function appendAdnotacje(\DOMDocument $dom, \DOMElement $fa, array $items, bool $splitPayment = false): void
    {
        $reverseCharge = false;
        foreach ($items as $item) {
            $reverseCharge = $reverseCharge || $this->rate($item) === 'np II';
        }

        $adnotacje = $this->child($dom, $fa, 'Adnotacje');

        $this->child($dom, $adnotacje, 'P_16', ($this->cashAccounting)() ? '1' : self::NIE); // Metoda kasowa.
        $this->child($dom, $adnotacje, 'P_17', self::NIE); // Samofakturowanie.
        $this->child($dom, $adnotacje, 'P_18', $reverseCharge ? '1' : self::NIE); // Odwrotne obciazenie.
        $this->child($dom, $adnotacje, 'P_18A', $splitPayment ? '1' : self::NIE); // Mechanizm podzielonej platnosci.

        $zwolnienie = $this->child($dom, $adnotacje, 'Zwolnienie');
        $basis = $this->exemptionBasis !== '' ? $this->exemptionBasis : $this->lineBases;
        if ($basis !== '') {
            $this->child($dom, $zwolnienie, 'P_19', '1');
            // P_19A: a provision of the act; P_19B: of the directive (art. 106e ust. 1 pkt 19 lit. a and b).
            $this->child($dom, $zwolnienie, stripos($basis, 'dyrektyw') !== false ? 'P_19B' : 'P_19A', $this->truncate($basis, 256));
        } else {
            $this->child($dom, $zwolnienie, 'P_19N', '1'); // Nic zwolnionego z podatku.
        }

        $transport = $this->child($dom, $adnotacje, 'NoweSrodkiTransportu');
        $this->child($dom, $transport, 'P_22N', '1'); // Zadnych nowych srodkow transportu.

        $this->child($dom, $adnotacje, 'P_23', self::NIE); // Procedura uproszczona trojstronna.

        $marza = $this->child($dom, $adnotacje, 'PMarzy');
        $this->child($dom, $marza, 'P_PMarzyN', '1'); // Brak procedury marzy.
    }

    private function appendKorekta(\DOMDocument $dom, \DOMElement $fa, Invoice $invoice, Invoice $corrected, ?FaParty $buyerBefore = null): void
    {
        if ($invoice->correctionReason !== null && trim($invoice->correctionReason) !== '') {
            $this->child($dom, $fa, 'PrzyczynaKorekty', $this->truncate($invoice->correctionReason, 256));
        }

        // An in-minus correction lowers the tax base in the period the
        // correction is issued (art. 29a ust. 13 ustawy o VAT), whatever the
        // cause. An in-plus one lands in the original period when its cause
        // already existed at the sale (ust. 17), which the invoice cannot say,
        // so TypKorekty, optional, is left out rather than guessed, unless
        // the merchant chose it.
        if ($invoice->correctionType !== null) {
            $this->child($dom, $fa, 'TypKorekty', (string) $invoice->correctionType);
        } elseif ($invoice->netTotal < 0.0 && $invoice->vatTotal <= 0.0) {
            // A rate rise at the same gross lowers the net but raises the
            // VAT; that is no in-minus correction and gets no default either.
            $this->child($dom, $fa, 'TypKorekty', '2');
        }

        $dane = $this->child($dom, $fa, 'DaneFaKorygowanej');
        $this->child($dom, $dane, 'DataWystFaKorygowanej', $corrected->issuedAt->format('Y-m-d'));
        $this->child($dom, $dane, 'NrFaKorygowanej', $corrected->number);

        // ksef_reference holds whatever the last submission wrote, which for a
        // queued invoice is the session reference, not a KSeF number. Only a
        // value matching TNumerKSeF may be presented as one.
        $ksefNumber = (string) ($corrected->ksefReference ?? '');

        if (VerificationLink::isKsefNumber($ksefNumber)) {
            $this->child($dom, $dane, 'NrKSeF', '1');
            $this->child($dom, $dane, 'NrKSeFFaKorygowanej', $ksefNumber);
        } else {
            $this->child($dom, $dane, 'NrKSeFN', '1');
        }

        // The buyer as the corrected invoice named it. OkresFaKorygowanej,
        // NrFaKorygowany and Podmiot1K, which sit between, are never written.
        if ($buyerBefore !== null) {
            $this->appendBuyerData($dom, $this->child($dom, $fa, 'Podmiot2K'), $buyerBefore);
        }
    }

    /**
     * A correction by state: each line of the corrected invoice marked
     * StanPrzed, followed by the line after the correction, numbered on.
     * The method the Ministry asks for when a rate or a description changes.
     * On a date correction the line before carries the old date of supply.
     */
    private function appendStateLines(\DOMDocument $dom, \DOMElement $fa, Invoice $invoice, Invoice $corrected, ?float $exchangeRate): void
    {
        $dateBefore = $invoice->correctionKind === 'date' ? ($corrected->soldAt ?? $corrected->issuedAt)->format('Y-m-d') : null;
        $number = 0;

        for ($i = 0, $n = max(count($corrected->items), count($invoice->items)); $i < $n; $i++) {
            if (isset($corrected->items[$i])) {
                $this->appendWiersz($dom, $fa, $corrected->items[$i], ++$number, null, $exchangeRate, $dateBefore, true);
            }

            if (isset($invoice->items[$i])) {
                $this->appendWiersz($dom, $fa, $invoice->items[$i], ++$number, null, $exchangeRate);
            }
        }
    }

    /**
     * @param Invoice|null $corrected    Set on a correction, whose lines are written by difference.
     * @param float|null   $exchangeRate Set on a foreign-currency invoice, written as KursWaluty.
     * @param string|null  $supplyDate   Written as P_6A, the line's own date of supply.
     * @param bool         $before       The line as it was before a correction (StanPrzed).
     */
    private function appendWiersz(\DOMDocument $dom, \DOMElement $fa, InvoiceItem $item, int $number, ?Invoice $corrected = null, ?float $exchangeRate = null, ?string $supplyDate = null, bool $before = false): void
    {
        $wiersz = $this->child($dom, $fa, 'FaWiersz');

        $this->child($dom, $wiersz, 'NrWierszaFa', (string) $number);

        if ($supplyDate !== null) {
            $this->child($dom, $wiersz, 'P_6A', $supplyDate);
        }
        $this->child($dom, $wiersz, 'P_7', $this->truncate($item->name, 512));

        // The shop's own product code and the barcode, so the buyer's software can match the line.
        $ids = ($this->lineIds)($item->productId);
        if ($ids['sku'] !== '') {
            $this->child($dom, $wiersz, 'Indeks', $this->truncate($ids['sku'], 50));
        }
        if (preg_match('/^\d{8,14}$/', $ids['gtin'])) {
            $this->child($dom, $wiersz, 'GTIN', $ids['gtin']);
        }

        if (trim($item->unit) !== '') {
            $this->child($dom, $wiersz, 'P_8A', $this->truncate($item->unit, 256));
        }

        // netPrice is the line total, not the unit price, so P_9A divides back out.
        $quantity = $item->quantity;
        $unitPrice = $quantity > 0.0 ? $item->netPrice / $quantity : $item->netPrice;

        if ($corrected !== null) {
            [$quantity, $unitPrice] = $this->differenceLine($item, $corrected);
        }

        $this->child($dom, $wiersz, 'P_8B', number_format($quantity, 6, '.', ''));
        $this->child($dom, $wiersz, 'P_9A', $this->amount($unitPrice));
        $this->child($dom, $wiersz, 'P_11', $this->amount($item->netPrice));
        $this->child($dom, $wiersz, 'P_12', $this->rate($item));

        // GTU, then KursWaluty, is the order the FaWiersz sequence declares
        // after P_12. TGTU is an enumeration, so an unknown code is dropped
        // rather than sent: KSeF would reject the whole document for it.
        $gtu = ($this->gtuLookup)($item->productId);

        if (in_array($gtu, self::GTU_CODES, true)) {
            $this->child($dom, $wiersz, 'GTU', $gtu);
        }

        if ($exchangeRate !== null) {
            $this->child($dom, $wiersz, 'KursWaluty', number_format($exchangeRate, 6, '.', ''));
        }

        if ($before) {
            $this->child($dom, $wiersz, 'StanPrzed', '1');
        }
    }

    /**
     * P_8B and P_9A of a correction line written by difference, so that P_8B
     * times P_9A is still the signed P_11.
     *
     * The information sheet shows two shapes. Goods coming back (Przyklad 23,
     * method one) change the quantity at the original price: P_8B -1, P_9A 90,
     * P_11 -90. A price cut (sample file Przyklad 3) keeps the quantity and
     * signs the price: P_8B 1, P_9A -162.60. A stored line cannot say which
     * one a refund was, so it is read off the corrected invoice: when the
     * line's unit price equals the original line's, units went back.
     * ponytail: matched by product id or name plus rate; a refund that returns
     * units at a changed price comes out as a price cut, which still sums right.
     *
     * @return array{0: float, 1: float} P_8B, P_9A.
     */
    private function differenceLine(InvoiceItem $item, Invoice $corrected): array
    {
        $quantity = abs($item->quantity);

        if ($quantity <= 0.0) {
            return [$item->quantity, $item->netPrice];
        }

        $unitPrice = abs($item->netPrice) / $quantity;
        $sign = $item->netPrice < 0.0 ? -1.0 : 1.0;

        foreach ($corrected->items as $original) {
            $sameLine = $item->productId !== null && $original->productId !== null
                ? $item->productId === $original->productId
                : $item->name === $original->name;

            if (! $sameLine || $original->quantity <= 0.0 || abs($original->vatRate - $item->vatRate) >= 0.005) {
                continue;
            }

            if (abs(round($unitPrice, 2) - round(abs($original->netPrice) / $original->quantity, 2)) < 0.005) {
                return [$sign * $quantity, $unitPrice];
            }
        }

        return [$quantity, $sign * $unitPrice];
    }

    /**
     * The TStawkaPodatku each line would be filed with, null where the line
     * cannot be filed. The PDF reads this so paper and KSeF name the same rate.
     *
     * @return list<?string>
     */
    public function lineRates(Invoice $invoice, FaParty $buyer): array
    {
        $this->prepare($invoice, $buyer);
        $rates = [];

        foreach ($invoice->items as $item) {
            try {
                $rates[] = $this->rate($item);
            } catch (\InvalidArgumentException) {
                $rates[] = null;
            }
        }

        return $rates;
    }

    /** Per-document state rate() and zeroRate() read. */
    private function prepare(Invoice $invoice, FaParty $buyer): void
    {
        $this->exemptionBasis = ($this->exemption)();
        $this->buyer = $buyer;
        $bases = [];
        foreach ($invoice->items as $item) {
            $basis = abs($item->vatRate) < 0.005 ? ($this->lineExemption)($item->productId) : '';
            if ($basis !== '') {
                $bases[$basis] = true;
            }
        }
        $this->lineBases = implode('; ', array_keys($bases));
        $this->hasGoods = false;
        foreach ($invoice->items as $item) {
            if ($item->productId !== null && $item->productId > 0 && ! ($this->isService)($item->productId)) {
                $this->hasGoods = true;
                break;
            }
        }
    }

    /**
     * The VAT rate as TStawkaPodatku spells it: a bare number, no percent sign,
     * and a plain 0 qualified: "zw" when the seller is exempt under art. 113,
     * otherwise by the buyer and the line, see zeroRate().
     *
     * @throws \InvalidArgumentException When the rate is not one FA(3) knows.
     */
    private function rate(InvoiceItem $item): string
    {
        $rate = rtrim(rtrim(number_format($item->vatRate, 2, '.', ''), '0'), '.');
        $zero = $rate === '' || $rate === '-0' || $rate === '0';

        if ($this->exemptionBasis !== '') {
            if (! $zero) {
                throw new \InvalidArgumentException(
                    esc_html(sprintf('The seller is VAT exempt (%s) but line "%s" charges %s%% VAT.', $this->exemptionBasis, $item->name, $rate))
                );
            }

            return 'zw';
        }

        $rate = $zero ? (($this->lineExemption)($item->productId) !== '' ? 'zw' : $this->zeroRate($item)) : $rate;

        $this->bucket($rate, $item->name);

        return $rate;
    }

    /**
     * Which zero rate a 0% line is.
     *
     * A buyer in Poland: "0 KR". In another member state: goods to a buyer
     * with an EU VAT number are WDT (art. 42), a service to one is art. 28b
     * ("np II"); a consumer there owes Polish VAT or OSS, never 0%, so that
     * is refused. Outside the EU: goods are an export, a service is outside
     * the territory ("np I"). A line without a product (shipping, a fee)
     * follows the goods on the invoice. ponytail: an export at 0% also needs
     * the customs proof of exit (art. 41 ust. 6); that stays the shop's job.
     *
     * @throws \InvalidArgumentException For a 0% sale to an EU consumer.
     */
    private function zeroRate(InvoiceItem $item): string
    {
        $buyer = $this->buyer;
        $country = $buyer !== null ? strtoupper($buyer->countryCode) : 'PL';

        if ($buyer === null || $country === 'PL' || $country === '') {
            return '0 KR';
        }

        $service = $item->productId !== null && $item->productId > 0
            ? ($this->isService)($item->productId)
            : ! $this->hasGoods;

        if ($buyer->inOtherEuCountry()) {
            if ($buyer->euVat() === null) {
                throw new \InvalidArgumentException(
                    esc_html(sprintf('Line "%s" is at 0%% but the buyer in %s has no EU VAT number: a sale to an EU consumer carries Polish VAT or OSS, not 0%%.', $item->name, $country))
                );
            }

            return $service ? 'np II' : '0 WDT';
        }

        return $service ? 'np I' : '0 EX';
    }

    /** TKwotowy: two decimals, a plain dot, no thousands separator. */
    private function amount(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return $formatted === '-0.00' ? '0.00' : $formatted;
    }

    private function truncate(string $value, int $limit): string
    {
        $collapsed = trim((string) preg_replace('/\s+/u', ' ', $value));

        return mb_substr($collapsed, 0, $limit);
    }

    private function child(\DOMDocument $dom, \DOMElement $parent, string $name, ?string $value = null): \DOMElement
    {
        $element = $value === null ? $dom->createElement($name) : $dom->createElement($name, htmlspecialchars($value, ENT_XML1));
        $parent->appendChild($element);

        return $element;
    }
}
