<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Model\Affiliate;
use Polski\Repository\AffiliateRepository;
use Polski\Util\Formatter;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Lightweight affiliate and referral tracking.
 */
final class AffiliateService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_affiliates';
    private const ENDPOINT = 'polski-affiliates';
    private const COOKIE = 'polski_affiliate_token';

    public function __construct(
        private readonly AffiliateRepository $repository,
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerEndpoint']);
        add_action('init', [$this, 'captureReferral']);
        add_filter('woocommerce_account_menu_items', [$this, 'addAccountMenuItem']);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [$this, 'renderAccountPage']);
        add_action('woocommerce_checkout_create_order', [$this, 'storeReferralOnOrder'], 15, 2);
        add_action('woocommerce_order_status_processing', [$this, 'registerReferralForOrder']);
        add_action('woocommerce_order_status_completed', [$this, 'registerReferralForOrder']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('affiliates');
    }

    public function registerEndpoint(): void
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public function captureReferral(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $param = sanitize_key((string) ($this->getSettings()['referral_param'] ?? 'poleca'));
        $token = sanitize_text_field((string) wp_unslash($_GET[$param] ?? ''));

        if ($token === '') {
            return;
        }

        $affiliate = $this->repository->findAffiliateByToken($token);

        if (! $affiliate instanceof Affiliate || $affiliate->status !== 'active') {
            return;
        }

        if (is_user_logged_in() && (int) get_current_user_id() === $affiliate->userId) {
            return;
        }

        $days = max(1, (int) ($this->getSettings()['cookie_days'] ?? 30));
        wc_setcookie(self::COOKIE, $token, time() + ($days * DAY_IN_SECONDS));
    }

    /**
     * @param array<string, string> $items
     * @return array<string, string>
     */
    public function addAccountMenuItem(array $items): array
    {
        if (! $this->isEnabled() || ! ($this->getSettings()['show_in_account'] ?? true) || ! is_user_logged_in()) {
            return $items;
        }

        $logout = $items['customer-logout'] ?? null;
        unset($items['customer-logout']);

        $items[self::ENDPOINT] = (string) ($this->getSettings()['account_label'] ?? __('Program partnerski', 'polski'));

        if ($logout !== null) {
            $items['customer-logout'] = $logout;
        }

        return $items;
    }

    public function renderAccountPage(): void
    {
        echo $this->renderDashboard();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function storeReferralOnOrder(\WC_Order $order, array $data): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $token = sanitize_text_field((string) ($_COOKIE[self::COOKIE] ?? ''));

        if ($token === '') {
            return;
        }

        $affiliate = $this->repository->findAffiliateByToken($token);

        if (! $affiliate instanceof Affiliate || $affiliate->status !== 'active') {
            return;
        }

        if ($order->get_user_id() > 0 && (int) $order->get_user_id() === $affiliate->userId) {
            return;
        }

        $order->update_meta_data('_polski_affiliate_token', $token);
        $order->update_meta_data('_polski_affiliate_id', $affiliate->id);
    }

    public function registerReferralForOrder(int $orderId): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        $affiliateId = (int) $order->get_meta('_polski_affiliate_id', true);

        if ($affiliateId <= 0 || $this->repository->referralExists($affiliateId, $orderId)) {
            return;
        }

        $allowedStatuses = array_filter(array_map('trim', explode(',', (string) ($this->getSettings()['pending_statuses'] ?? 'processing,completed'))));

        if (! in_array((string) $order->get_status(), $allowedStatuses, true)) {
            return;
        }

        $minimum = max(0.0, (float) ($this->getSettings()['minimum_order_total'] ?? 0));
        $orderTotal = (float) $order->get_total();

        if ($orderTotal < $minimum) {
            return;
        }

        $commissionPercent = max(0.0, (float) ($this->getSettings()['commission_percent'] ?? 5));
        $commission = $orderTotal * ($commissionPercent / 100);

        $this->repository->createReferral(
            $affiliateId,
            $orderId,
            (string) $order->get_billing_email(),
            $orderTotal,
            $commission,
            (string) $order->get_status(),
        );
    }

    public function renderDashboard(): string
    {
        $affiliate = $this->getOrCreateAffiliate();

        if (! $affiliate instanceof Affiliate) {
            return '<p>' . esc_html((string) ($this->getSettings()['login_required_text'] ?? __('Zaloguj się, aby korzystać z programu partnerskiego.', 'polski'))) . '</p>';
        }

        $referrals = $this->repository->findReferralsByAffiliate($affiliate->id);
        $stats = $this->repository->getStats($affiliate->id);

        return $this->templateLoader->render('account/affiliates', [
            'service' => $this,
            'affiliate' => $affiliate,
            'referrals' => $referrals,
            'stats' => $stats,
            'referral_url' => $this->getReferralUrl($affiliate),
        ]);
    }

    public function getReferralUrl(Affiliate $affiliate): string
    {
        $param = sanitize_key((string) ($this->getSettings()['referral_param'] ?? 'poleca'));
        return add_query_arg($param, $affiliate->token, home_url('/'));
    }

    public function getOrCreateAffiliate(): ?Affiliate
    {
        if (! is_user_logged_in()) {
            return null;
        }

        $userId = (int) get_current_user_id();
        $affiliate = $this->repository->findAffiliateByUserId($userId);

        if ($affiliate instanceof Affiliate) {
            return $affiliate;
        }

        $token = strtolower(wp_generate_password(12, false, false));
        $this->repository->createAffiliate($userId, $token);

        return $this->repository->findAffiliateByUserId($userId);
    }

    public function formatAmount(float $amount): string
    {
        return Formatter::price($amount);
    }

    public function getDateFormat(): string
    {
        return (string) ($this->getSettings()['date_format'] ?? 'd.m.Y');
    }
}
