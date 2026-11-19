<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Util\SettingsCacheable;

/**
 * "Delivery by <date>" for the product page.
 *
 * An order placed before the cutoff hour on a working day is dispatched that
 * day, otherwise on the next working day. The lead time (per product or
 * variation, per shipping class, or the default) is then counted in working
 * days, and the delivery day itself is always a working day. Blocked weekdays
 * and holidays are non-working days for both dispatch and delivery.
 *
 * With the PRO delivery date picker switched on, its blocked weekdays, blocked
 * dates and earliest date are used, so the line never promises a day the
 * checkout will not let the customer choose.
 *
 * existing-checked: DeliveryTimeService - it shows a free-text delivery time
 * term ("2-3 dni robocze") and has no calendar; the estimate needs dates,
 * a clock and its own settings, and keeps that service unchanged.
 */
final class DeliveryEstimateService
{
    use SettingsCacheable;

    private const OPTION = 'polski_delivery_estimate';

    public const META_LEAD_DAYS = '_polski_delivery_lead_days';

    /** @var \Closure(): \DateTimeImmutable */
    private \Closure $clock;

    /** @var array<int, array{weekdays: list<int>, holidays: list<string>, min_days: int}> Rules per year, built once per request. */
    private array $rules = [];

    /**
     * @param (\Closure(): \DateTimeImmutable)|null $clock Current time in the shop's timezone.
     */
    public function __construct(?\Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable('now', wp_timezone());
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('delivery_estimate');
    }

    /**
     * Delivery date for an order placed at $now, or null when no working day
     * exists in the next year (every weekday blocked).
     *
     * @param list<int>    $blockedWeekdays 0 = Sunday, 6 = Saturday.
     * @param list<string> $holidays        Y-m-d dates.
     */
    public static function estimate(
        \DateTimeImmutable $now,
        string $cutoff,
        int $leadDays,
        array $blockedWeekdays,
        array $holidays,
        int $minCalendarDays = 0,
    ): ?\DateTimeImmutable {
        $isWorkday = static fn (\DateTimeImmutable $d): bool => ! in_array((int) $d->format('w'), $blockedWeekdays, true)
            && ! in_array($d->format('Y-m-d'), $holidays, true);

        $next = static function (\DateTimeImmutable $d) use ($isWorkday): ?\DateTimeImmutable {
            for ($i = 0; $i < 400; $i++) {
                $d = $d->modify('+1 day');
                if ($isWorkday($d)) {
                    return $d;
                }
            }

            return null;
        };

        $day = $now->setTime(0, 0);
        $cutoffAt = self::cutoffAt($day, $cutoff);

        if (! $isWorkday($day) || ($cutoffAt !== null && $now >= $cutoffAt)) {
            $day = $next($day);
        }

        for ($i = 0; $day !== null && $i < max(0, $leadDays); $i++) {
            $day = $next($day);
        }

        if ($day === null) {
            return null;
        }

        $earliest = $now->setTime(0, 0)->modify('+' . max(0, $minCalendarDays) . ' days');

        if ($day < $earliest) {
            $day = $isWorkday($earliest) ? $earliest : $next($earliest);
        }

        return $day;
    }

    /**
     * When the estimate shown at $now stops being true: the next cutoff, or
     * midnight when there is no cutoff.
     */
    public static function expiresAt(\DateTimeImmutable $now, string $cutoff): \DateTimeImmutable
    {
        $cutoffAt = self::cutoffAt($now->setTime(0, 0), $cutoff);

        if ($cutoffAt === null) {
            return $now->setTime(0, 0)->modify('+1 day');
        }

        return $now < $cutoffAt ? $cutoffAt : $cutoffAt->modify('+1 day');
    }

    /**
     * Polish statutory public holidays (ustawa o dniach wolnych od pracy),
     * including Christmas Eve from 2025.
     *
     * @return list<string> Y-m-d dates.
     */
    public static function polishHolidays(int $year): array
    {
        // Anonymous Gregorian algorithm for Easter Sunday.
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $h = (19 * $a + $b - intdiv($b, 4) - intdiv(8 * $b + 13, 25) + 15) % 30;
        $l = (32 + 2 * ($b % 4) + 2 * intdiv($c, 4) - $h - $c % 4) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        $easter = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));

        $dates = ['01-01', '01-06', '05-01', '05-03', '08-15', '11-01', '11-11', '12-25', '12-26'];
        if ($year >= 2025) {
            $dates[] = '12-24';
        }

        $list = array_map(static fn (string $md): string => $year . '-' . $md, $dates);

        foreach ([0, 1, 49, 60] as $offset) { // Easter, Easter Monday, Pentecost, Corpus Christi.
            $list[] = $easter->modify('+' . $offset . ' days')->format('Y-m-d');
        }

        sort($list);

        return $list;
    }

    /**
     * @return list<int>
     */
    public static function parseWeekdays(mixed $raw): array
    {
        $list = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $d): int => (int) trim((string) $d) % 7, array_filter($list, static fn (mixed $d): bool => is_numeric(trim((string) $d)))),
            static fn (int $d): bool => $d >= 0,
        )));
    }

    /**
     * @return list<string>
     */
    public static function parseDates(mixed $raw): array
    {
        $list = is_array($raw) ? $raw : preg_split('/[\s,;]+/', (string) $raw);

        return array_values(array_filter(
            array_map(static fn (mixed $d): string => trim((string) $d), $list ?: []),
            static fn (string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d),
        ));
    }

    /**
     * "slug: days" lines, one shipping class per line.
     *
     * @return array<string, int>
     */
    public static function parseClassLeadDays(string $raw): array
    {
        $map = [];

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            if (preg_match('/^\s*([^:=]+?)\s*[:=]\s*(\d+)\s*$/', $line, $m)) {
                $map[sanitize_title($m[1])] = (int) $m[2];
            }
        }

        return $map;
    }

    /**
     * Working days from dispatch to delivery for a product or variation.
     */
    public function leadDaysFor(\WC_Product $product): int
    {
        $parent = $product->get_parent_id() > 0 ? wc_get_product($product->get_parent_id()) : null;

        foreach ([$product, $parent] as $source) {
            if (! $source instanceof \WC_Product) {
                continue;
            }

            $own = $source->get_meta(self::META_LEAD_DAYS, true);
            if (is_numeric($own)) {
                return max(0, (int) $own);
            }
        }

        $byClass = self::parseClassLeadDays((string) $this->getSetting('class_lead_days', ''));
        $class = $product->get_shipping_class();

        if ($class !== '' && isset($byClass[$class])) {
            return $byClass[$class];
        }

        return max(0, (int) $this->getSetting('default_lead_days', 2));
    }

    public function getEstimate(\WC_Product $product): ?\DateTimeImmutable
    {
        if (! $this->isEnabled() || ! $product->is_purchasable() || $product->get_stock_status() !== 'instock') {
            return null;
        }

        $now = ($this->clock)();
        $cutoff = (string) $this->getSetting('cutoff_time', '14:00');
        $rules = $this->rules((int) $now->format('Y'));

        $date = self::estimate($now, $cutoff, $this->leadDaysFor($product), $rules['weekdays'], $rules['holidays'], $rules['min_days']);

        /**
         * Filters the estimated delivery date of a product. Return null to hide the line.
         *
         * @param \DateTimeImmutable|null $date
         * @param \WC_Product             $product
         */
        $date = apply_filters('polski/delivery_estimate/date', $date, $product);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    /**
     * The sentence inside the line: escaped text around a <time> element.
     */
    public function getInnerHtml(\WC_Product $product): string
    {
        $date = $this->getEstimate($product);

        if ($date === null) {
            return '';
        }

        $format = (string) $this->getSetting('display_format', '');
        $format = str_contains($format, '{date}') ? $format : __('Delivery by {date}', 'polski');
        [$before, $after] = explode('{date}', $format, 2);
        $label = wp_date((string) $this->getSetting('date_format', 'l, j F'), $date->getTimestamp(), $date->getTimezone());

        if (! is_string($label)) {
            return '';
        }

        return esc_html($before)
            . '<time datetime="' . esc_attr($date->format('Y-m-d')) . '">'
            . esc_html($label)
            . '</time>'
            . esc_html($after);
    }

    /**
     * The line under the price. The element is printed even when empty so a
     * variation that has an estimate fills reserved space instead of pushing
     * the add-to-cart button down.
     */
    public function getHtml(\WC_Product $product): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        // The expiry is printed even without an estimate: a cached page still
        // carries variation estimates that go stale at the same cutoff.
        $expires = self::expiresAt(($this->clock)(), (string) $this->getSetting('cutoff_time', '14:00'));

        return sprintf(
            '<p class="polski-delivery-estimate" data-polski-estimate="%d" data-expires="%d" aria-live="polite" style="min-height:1.5em">%s</p>',
            $product->get_id(),
            $expires->getTimestamp(),
            $this->getInnerHtml($product),
        );
    }

    /**
     * Blocked weekdays, holidays and the earliest calendar day.
     *
     * @return array{weekdays: list<int>, holidays: list<string>, min_days: int}
     */
    private function rules(int $year): array
    {
        if (isset($this->rules[$year])) {
            return $this->rules[$year];
        }

        // The module flag outlives a deactivated PRO, so PRO must be loaded too.
        $pro = defined('Polski\\Pro\\VERSION') && ModulesPage::isModuleEnabled('delivery_date')
            ? get_option('polski_delivery_date', [])
            : null;

        if (is_array($pro)) {
            $weekdays = self::parseWeekdays($pro['blocked_weekdays'] ?? [0]);
            $holidays = self::parseDates($pro['blocked_dates'] ?? '');
            $minDays = max(0, (int) ($pro['min_days'] ?? 1));
        } else {
            $weekdays = self::parseWeekdays($this->getSetting('blocked_weekdays', '6,0'));
            $holidays = self::parseDates($this->getSetting('holidays', ''));
            $minDays = 0;
        }

        if ($this->getSetting('polish_holidays', true)) {
            $holidays = array_merge($holidays, self::polishHolidays($year), self::polishHolidays($year + 1));
        }

        return $this->rules[$year] = ['weekdays' => $weekdays, 'holidays' => $holidays, 'min_days' => $minDays];
    }

    private static function cutoffAt(\DateTimeImmutable $day, string $cutoff): ?\DateTimeImmutable
    {
        if (! preg_match('/^(\d{1,2})(?::(\d{2}))?$/', trim($cutoff), $m) || (int) $m[1] > 23) {
            return null;
        }

        return $day->setTime((int) $m[1], (int) ($m[2] ?? 0));
    }
}
