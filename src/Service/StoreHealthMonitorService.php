<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Plugin;

/**
 * Continuous, passive monitoring of store-side operational health.
 *
 * Unlike SiteAuditService (on-demand compliance checks) and
 * SecurityIncidentService (manual incident log), this service runs on a
 * schedule and watches operational/revenue signals: front-end fatal errors,
 * the checkout failure rate, and a sales anomaly ("traffic but no orders").
 *
 * Detection is passive: it observes real WooCommerce events and order
 * history. It never places synthetic orders, so it cannot create fake orders
 * or charge cards. The trade-off is that payment problems are detected once a
 * real customer hits them, not proactively.
 */
final class StoreHealthMonitorService implements HasHooks
{
    public const MODULE = 'store_health';

    private const SETTINGS_OPTION = 'polski_store_health';
    private const STATE_OPTION = 'polski_store_health_state';
    private const COUNTERS_OPTION = 'polski_store_health_counters';
    private const FATAL_OPTION = 'polski_store_health_last_fatal';
    private const CRON_HOOK = 'polski_store_health_check';
    private const CRON_INTERVAL = 'polski_five_minutes';
    private const SALES_GUARD_TRANSIENT = 'polski_store_health_sales_checked';
    private const CAPABILITY = 'manage_woocommerce';

    private const STATUS_OK = 'ok';
    private const STATUS_DEGRADED = 'degraded';
    private const STATUS_DOWN = 'down';

    /** Fatal errors are considered active within this window (seconds). */
    private const FATAL_WINDOW = 900;

    public function registerHooks(): void
    {
        add_filter('cron_schedules', [$this, 'registerCronInterval']);
        add_action('init', [$this, 'ensureCron']);
        add_action(self::CRON_HOOK, [$this, 'runCheck']);
        add_action('admin_post_polski_store_health_recheck', [$this, 'handleManualRecheck']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        if (! $this->isEnabled()) {
            return;
        }

        add_action('woocommerce_checkout_order_processed', [$this, 'recordCheckoutSuccess']);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'recordCheckoutSuccess']);
        add_action('woocommerce_order_status_failed', [$this, 'recordCheckoutFailure']);
        add_action('shutdown', [$this, 'captureFatalError']);
        add_action('admin_notices', [$this, 'maybeRenderAdminNotice']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled(self::MODULE);
    }

    /**
     * @param array<string, array{interval: int, display: string}> $schedules
     * @return array<string, array{interval: int, display: string}>
     */
    public function registerCronInterval(array $schedules): array
    {
        if (! isset($schedules[self::CRON_INTERVAL])) {
            $schedules[self::CRON_INTERVAL] = [
                'interval' => 300,
                'display' => __('Every 5 minutes (Polski store health)', 'polski'),
            ];
        }

        return $schedules;
    }

    public function ensureCron(): void
    {
        $scheduled = wp_next_scheduled(self::CRON_HOOK);

        if (! $this->isEnabled()) {
            if ($scheduled !== false) {
                wp_unschedule_event($scheduled, self::CRON_HOOK);
            }

            return;
        }

        if ($scheduled === false) {
            wp_schedule_event(time() + 300, self::CRON_INTERVAL, self::CRON_HOOK);
        }
    }

    public function recordCheckoutSuccess(): void
    {
        $this->incrementCounter('ok');
    }

    public function recordCheckoutFailure(): void
    {
        $this->incrementCounter('failed');
    }

    /**
     * Records the last fatal error on a front-end request so the cron run can
     * surface it. Admin and cron fatals are ignored to keep the focus on the
     * customer-facing storefront.
     */
    public function captureFatalError(): void
    {
        if (is_admin() || wp_doing_cron()) {
            return;
        }

        $error = error_get_last();

        if (! is_array($error) || ! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        update_option(self::FATAL_OPTION, [
            'message' => sanitize_text_field($error['message']),
            'file' => sanitize_text_field($error['file']),
            'line' => $error['line'],
            'at' => time(),
        ], false);
    }

    /**
     * Main scheduled evaluation: run every sensor, fold into an overall status,
     * persist, and alert on a worsening transition.
     */
    public function runCheck(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $sensors = [
            'fatal' => $this->evaluateFatal(),
            'payments' => $this->evaluatePayments(),
            'sales' => $this->evaluateSales(),
        ];

        $overall = self::STATUS_OK;

        foreach ($sensors as $sensor) {
            if ($sensor['status'] === self::STATUS_DOWN) {
                $overall = self::STATUS_DOWN;
                break;
            }

            if ($sensor['status'] === self::STATUS_DEGRADED) {
                $overall = self::STATUS_DEGRADED;
            }
        }

        $previous = $this->getState();
        $previousStatus = (string) ($previous['status'] ?? self::STATUS_OK);

        $state = [
            'status' => $overall,
            'sensors' => $sensors,
            'updated_at' => current_time('mysql', true),
            'last_alert_at' => (string) ($previous['last_alert_at'] ?? ''),
        ];

        if ($this->shouldAlert($overall, $previousStatus, (string) ($previous['last_alert_at'] ?? ''))) {
            $this->dispatchAlerts($overall, $sensors);
            $state['last_alert_at'] = current_time('mysql', true);
        }

        update_option(self::STATE_OPTION, $state, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        $state = get_option(self::STATE_OPTION, []);

        return is_array($state) ? $state : [];
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('polski/v1', '/store-health', [
            'methods' => 'GET',
            'callback' => function () {
                return rest_ensure_response($this->getState());
            },
            'permission_callback' => static fn (): bool => current_user_can(self::CAPABILITY),
        ]);
    }

    public function handleManualRecheck(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'polski'));
        }

        check_admin_referer('polski_store_health_recheck', '_polski_store_health_nonce');

        $this->runCheck();

        wp_safe_redirect(add_query_arg(
            ['page' => 'polski', 'tab' => 'reports', 'view' => 'health', 'rechecked' => '1'],
            admin_url('admin.php'),
        ));
        exit;
    }

    public function maybeRenderAdminNotice(): void
    {
        if (! $this->isEnabled() || ! current_user_can(self::CAPABILITY)) {
            return;
        }

        $state = $this->getState();
        $status = (string) ($state['status'] ?? self::STATUS_OK);

        if ($status === self::STATUS_OK) {
            return;
        }

        $class = $status === self::STATUS_DOWN ? 'notice-error' : 'notice-warning';
        $url = add_query_arg(
            ['page' => 'polski', 'tab' => 'reports', 'view' => 'health'],
            admin_url('admin.php'),
        );

        echo '<div class="notice ' . esc_attr($class) . '"><p><strong>' . esc_html__('Polski: store health', 'polski') . '</strong> - '
            . esc_html($this->labelForStatus($status)) . '. '
            . '<a href="' . esc_url($url) . '">' . esc_html__('View details', 'polski') . '</a></p></div>';
    }

    public function renderPage(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'polski'));
        }

        $state = $this->getState();
        $status = (string) ($state['status'] ?? self::STATUS_OK);
        $sensors = is_array($state['sensors'] ?? null) ? $state['sensors'] : [];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Store health', 'polski') . '</h1>';
        echo '<p>' . esc_html__('Continuous, passive monitoring of front-end fatal errors, the checkout failure rate, and sales anomalies. Checks run every 5 minutes. No synthetic orders are placed.', 'polski') . '</p>';

        echo '<p style="font-size:15px;"><strong>' . esc_html__('Overall status:', 'polski') . '</strong> '
            . '<span style="display:inline-block;padding:2px 10px;border-radius:3px;color:#fff;background:' . esc_attr($this->colorForStatus($status)) . ';">'
            . esc_html($this->labelForStatus($status)) . '</span>';

        if (! empty($state['updated_at'])) {
            echo ' <span style="color:#666;">' . esc_html(sprintf(
                /* translators: %s: last check timestamp */
                __('Last check: %s UTC', 'polski'),
                (string) $state['updated_at'],
            )) . '</span>';
        }

        echo '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0 20px;">';
        wp_nonce_field('polski_store_health_recheck', '_polski_store_health_nonce');
        echo '<input type="hidden" name="action" value="polski_store_health_recheck" />';
        submit_button(__('Run check now', 'polski'), 'secondary', 'submit', false);
        echo '</form>';

        if ($sensors === []) {
            echo '<p>' . esc_html__('No check has run yet. Use "Run check now" to evaluate immediately.', 'polski') . '</p>';
        } else {
            echo '<table class="widefat striped" style="max-width:760px;">';
            echo '<thead><tr><th>' . esc_html__('Sensor', 'polski') . '</th><th>' . esc_html__('Status', 'polski') . '</th><th>' . esc_html__('Detail', 'polski') . '</th></tr></thead><tbody>';

            foreach (['fatal', 'payments', 'sales'] as $key) {
                $sensor = is_array($sensors[$key] ?? null) ? $sensors[$key] : [];
                $sensorStatus = (string) ($sensor['status'] ?? self::STATUS_OK);
                echo '<tr>';
                echo '<td><strong>' . esc_html($this->labelForSensor($key)) . '</strong></td>';
                echo '<td><span style="color:' . esc_attr($this->colorForStatus($sensorStatus)) . ';">' . esc_html($this->labelForStatus($sensorStatus)) . '</span></td>';
                echo '<td>' . esc_html((string) ($sensor['detail'] ?? '')) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /**
     * @return array{status: string, detail: string}
     */
    private function evaluateFatal(): array
    {
        $fatal = get_option(self::FATAL_OPTION, []);

        if (! is_array($fatal) || empty($fatal['at'])) {
            return ['status' => self::STATUS_OK, 'detail' => __('No recent front-end fatal errors.', 'polski')];
        }

        if ((time() - (int) $fatal['at']) > self::FATAL_WINDOW) {
            return ['status' => self::STATUS_OK, 'detail' => __('No fatal errors in the last 15 minutes.', 'polski')];
        }

        return [
            'status' => self::STATUS_DOWN,
            'detail' => sprintf(
                /* translators: 1: error message, 2: file, 3: line */
                __('Fatal error: %1$s (%2$s:%3$d)', 'polski'),
                (string) ($fatal['message'] ?? ''),
                basename((string) ($fatal['file'] ?? '')),
                (int) ($fatal['line'] ?? 0),
            ),
        ];
    }

    /**
     * @return array{status: string, detail: string}
     */
    private function evaluatePayments(): array
    {
        $counters = $this->readCounters();
        $ok = $this->sumRecent($counters['ok'] ?? []);
        $failed = $this->sumRecent($counters['failed'] ?? []);
        $total = $ok + $failed;
        $minSample = $this->settingInt('payments_min_sample', 5);

        if ($total < $minSample) {
            return [
                'status' => self::STATUS_OK,
                'detail' => sprintf(
                    /* translators: 1: observed checkout count, 2: minimum sample size */
                    __('Insufficient data (%1$d/%2$d checkouts in the last 2h).', 'polski'),
                    $total,
                    $minSample,
                ),
            ];
        }

        $rate = $failed / $total;
        $threshold = $this->settingInt('payments_fail_percent', 30) / 100;

        if ($rate >= $threshold) {
            return [
                'status' => $rate >= ($threshold * 1.5) ? self::STATUS_DOWN : self::STATUS_DEGRADED,
                'detail' => sprintf(
                    /* translators: 1: failed count, 2: total count, 3: failure percentage */
                    __('%1$d of %2$d checkouts failed (%3$d%%) in the last 2h.', 'polski'),
                    $failed,
                    $total,
                    (int) round($rate * 100),
                ),
            ];
        }

        return [
            'status' => self::STATUS_OK,
            'detail' => sprintf(
                /* translators: 1: failed count, 2: total count */
                __('%1$d of %2$d checkouts failed in the last 2h.', 'polski'),
                $failed,
                $total,
            ),
        ];
    }

    /**
     * Sales anomaly: compares the previous full hour against the typical order
     * count for the same weekday and hour over the past 8 weeks. Runs at most
     * once per clock hour to keep the order queries cheap.
     *
     * @return array{status: string, detail: string}
     */
    private function evaluateSales(): array
    {
        if (! function_exists('wc_get_orders')) {
            return ['status' => self::STATUS_OK, 'detail' => __('WooCommerce not active.', 'polski')];
        }

        $cached = get_transient(self::SALES_GUARD_TRANSIENT);

        if (is_array($cached) && isset($cached['status'], $cached['detail'])) {
            return [
                'status' => (string) $cached['status'],
                'detail' => (string) $cached['detail'],
            ];
        }

        $timezone = wp_timezone();
        $now = new \DateTimeImmutable('now', $timezone);
        $hourEnd = $now->setTime((int) $now->format('G'), 0, 0);
        $hourStart = $hourEnd->modify('-1 hour');

        $actual = $this->countOrdersBetween($hourStart, $hourEnd);

        $samples = [];

        for ($week = 1; $week <= 8; $week++) {
            $pastStart = $hourStart->modify('-' . $week . ' week');
            $pastEnd = $hourEnd->modify('-' . $week . ' week');
            $samples[] = $this->countOrdersBetween($pastStart, $pastEnd);
        }

        $expected = array_sum($samples) / count($samples);
        $minExpected = $this->settingInt('sales_min_expected', 3);

        if ($expected >= $minExpected && $actual === 0) {
            $result = [
                'status' => self::STATUS_DOWN,
                'detail' => sprintf(
                    /* translators: 1: expected order count */
                    __('No orders in the last full hour, but ~%1$.1f are typical for this time. Traffic may not be converting.', 'polski'),
                    $expected,
                ),
            ];
        } else {
            $result = [
                'status' => self::STATUS_OK,
                'detail' => sprintf(
                    /* translators: 1: actual orders, 2: expected orders */
                    __('%1$d order(s) last hour (typical ~%2$.1f).', 'polski'),
                    $actual,
                    $expected,
                ),
            ];
        }

        set_transient(self::SALES_GUARD_TRANSIENT, $result, HOUR_IN_SECONDS);

        return $result;
    }

    private function countOrdersBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $query = wc_get_orders([
            'limit' => 1,
            'paginate' => true,
            'return' => 'ids',
            'status' => ['wc-processing', 'wc-completed', 'wc-on-hold'],
            'date_created' => $start->getTimestamp() . '...' . $end->getTimestamp(),
        ]);

        return is_object($query) && isset($query->total) ? (int) $query->total : 0;
    }

    private function shouldAlert(string $current, string $previous, string $lastAlertAt): bool
    {
        if ($current === self::STATUS_OK) {
            return false;
        }

        // Always alert when the status worsens.
        if ($this->severityRank($current) > $this->severityRank($previous)) {
            return true;
        }

        // Otherwise re-alert only after the cooldown, so an ongoing outage is
        // not silenced indefinitely but does not spam every 5 minutes.
        if ($lastAlertAt === '') {
            return true;
        }

        $cooldown = $this->settingInt('cooldown_minutes', 60) * MINUTE_IN_SECONDS;
        $last = strtotime($lastAlertAt . ' UTC');

        return $last === false || (time() - $last) >= $cooldown;
    }

    /**
     * @param array<string, array{status: string, detail: string}> $sensors
     */
    private function dispatchAlerts(string $status, array $sensors): void
    {
        $lines = [];

        foreach ($sensors as $key => $sensor) {
            if (($sensor['status'] ?? self::STATUS_OK) === self::STATUS_OK) {
                continue;
            }

            $lines[] = sprintf('- %s: %s', $this->labelForSensor((string) $key), (string) ($sensor['detail'] ?? ''));
        }

        $body = $this->labelForStatus($status) . "\n\n" . implode("\n", $lines);
        $subject = sprintf(
            /* translators: 1: site name, 2: status label */
            __('[%1$s] Store health: %2$s', 'polski'),
            wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
            $this->labelForStatus($status),
        );

        $email = $this->settingString('alert_email', (string) get_option('admin_email'));

        if (is_email($email)) {
            wp_mail($email, $subject, $body);
        }

        $webhook = $this->settingString('webhook_url', '');

        if ($webhook !== '' && wp_http_validate_url($webhook)) {
            wp_remote_post($webhook, [
                'timeout' => 5,
                'blocking' => false,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => (string) wp_json_encode(['text' => $subject . "\n" . $body]),
            ]);
        }

        $this->logIncident($status, $lines);
    }

    /**
     * On a hard outage, record an entry in the security incident log so the
     * event has an audit trail alongside manually logged incidents.
     *
     * @param list<string> $lines
     */
    private function logIncident(string $status, array $lines): void
    {
        if ($status !== self::STATUS_DOWN) {
            return;
        }

        if (! ModulesPage::isModuleEnabled('security_incidents')) {
            return;
        }

        $container = Plugin::instance()->container();

        if (! $container->has(SecurityIncidentService::class)) {
            return;
        }

        /** @var SecurityIncidentService $incidents */
        $incidents = $container->get(SecurityIncidentService::class);
        $incidents->createIncident([
            'type' => 'availability',
            'severity' => 'critical',
            'title' => __('Store health check reported an outage', 'polski'),
            'affected_area' => __('Storefront / checkout', 'polski'),
            'notes' => implode("\n", $lines),
            'status' => 'open',
        ]);
    }

    /**
     * @param 'ok'|'failed' $bucket
     */
    private function incrementCounter(string $bucket): void
    {
        $counters = $this->readCounters();
        $hourKey = gmdate('Y-m-d-H');
        $counters[$bucket][$hourKey] = (int) ($counters[$bucket][$hourKey] ?? 0) + 1;
        update_option(self::COUNTERS_OPTION, $this->pruneCounters($counters), false);
    }

    /**
     * @return array{ok: array<string, int>, failed: array<string, int>}
     */
    private function readCounters(): array
    {
        $counters = get_option(self::COUNTERS_OPTION, []);
        $counters = is_array($counters) ? $counters : [];

        return [
            'ok' => is_array($counters['ok'] ?? null) ? $counters['ok'] : [],
            'failed' => is_array($counters['failed'] ?? null) ? $counters['failed'] : [],
        ];
    }

    /**
     * @param array{ok: array<string, int>, failed: array<string, int>} $counters
     * @return array{ok: array<string, int>, failed: array<string, int>}
     */
    private function pruneCounters(array $counters): array
    {
        $keep = [gmdate('Y-m-d-H'), gmdate('Y-m-d-H', time() - HOUR_IN_SECONDS)];

        foreach (['ok', 'failed'] as $bucket) {
            foreach (array_keys($counters[$bucket]) as $hourKey) {
                if (! in_array($hourKey, $keep, true)) {
                    unset($counters[$bucket][$hourKey]);
                }
            }
        }

        return $counters;
    }

    /**
     * @param array<string, int> $bucket
     */
    private function sumRecent(array $bucket): int
    {
        $keep = [gmdate('Y-m-d-H'), gmdate('Y-m-d-H', time() - HOUR_IN_SECONDS)];
        $sum = 0;

        foreach ($keep as $hourKey) {
            $sum += (int) ($bucket[$hourKey] ?? 0);
        }

        return $sum;
    }

    private function settingInt(string $key, int $default): int
    {
        $settings = get_option(self::SETTINGS_OPTION, []);
        $settings = is_array($settings) ? $settings : [];

        return isset($settings[$key]) && is_numeric($settings[$key]) ? (int) $settings[$key] : $default;
    }

    private function settingString(string $key, string $default): string
    {
        $settings = get_option(self::SETTINGS_OPTION, []);
        $settings = is_array($settings) ? $settings : [];

        return isset($settings[$key]) && is_string($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
    }

    private function severityRank(string $status): int
    {
        return match ($status) {
            self::STATUS_DOWN => 2,
            self::STATUS_DEGRADED => 1,
            default => 0,
        };
    }

    private function labelForStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_DOWN => __('Down', 'polski'),
            self::STATUS_DEGRADED => __('Degraded', 'polski'),
            default => __('OK', 'polski'),
        };
    }

    private function colorForStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_DOWN => '#d63638',
            self::STATUS_DEGRADED => '#dba617',
            default => '#00a32a',
        };
    }

    private function labelForSensor(string $key): string
    {
        return match ($key) {
            'fatal' => __('Fatal errors (front-end)', 'polski'),
            'payments' => __('Checkout / payments', 'polski'),
            'sales' => __('Sales anomaly', 'polski'),
            default => $key,
        };
    }
}
