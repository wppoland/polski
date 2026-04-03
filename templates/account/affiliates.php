<?php

declare(strict_types=1);

/**
 * @var \Polski\Service\AffiliateService $service
 * @var \Polski\Model\Affiliate           $affiliate
 * @var list<\Polski\Model\AffiliateReferral> $referrals
 * @var array{referrals:int,revenue:float,commission:float} $stats
 * @var string                                 $referral_url
 */
?>
<section class="polski-account-affiliates">
    <?php $settings = $service->getSettings(); ?>

    <?php if ((bool) ($settings['show_dashboard_title'] ?? true)) : ?>
        <h2><?php echo esc_html((string) ($settings['dashboard_title'] ?? __('Panel partnera', 'polski'))); ?></h2>
    <?php endif; ?>

    <?php if ((bool) ($settings['show_dashboard_intro'] ?? true) && (string) ($settings['dashboard_intro_text'] ?? '') !== '') : ?>
        <div class="polski-account-affiliates__intro">
            <?php echo wpautop(wp_kses_post((string) ($settings['dashboard_intro_text'] ?? ''))); ?>
        </div>
    <?php endif; ?>

    <?php if ((bool) ($settings['show_referral_link'] ?? true)) : ?>
        <p><strong><?php echo esc_html((string) ($settings['referral_link_label'] ?? __('Twój link partnerski', 'polski'))); ?>:</strong></p>
        <p><input type="text" readonly="readonly" value="<?php echo esc_attr($referral_url); ?>" class="regular-text code" /></p>
    <?php endif; ?>

    <?php if ((bool) ($settings['show_stats'] ?? true)) : ?>
        <div class="polski-affiliates__stats">
            <p><?php echo esc_html(str_replace('{count}', (string) ((int) $stats['referrals']), (string) ($settings['stats_referrals_label'] ?? __('Polecenia: {count}', 'polski')))); ?></p>
            <p><?php echo esc_html(str_replace('{amount}', wp_strip_all_tags($service->formatAmount((float) $stats['revenue'])), (string) ($settings['stats_revenue_label'] ?? __('Sprzedaż: {amount}', 'polski')))); ?></p>
            <p><?php echo esc_html(str_replace('{amount}', wp_strip_all_tags($service->formatAmount((float) $stats['commission'])), (string) ($settings['stats_commission_label'] ?? __('Prowizja: {amount}', 'polski')))); ?></p>
        </div>
    <?php endif; ?>

    <?php if ((bool) ($settings['show_table'] ?? true) && $referrals === []) : ?>
        <p><?php echo esc_html((string) ($settings['empty_text'] ?? __('Brak poleceń przypisanych do Twojego konta.', 'polski'))); ?></p>
    <?php elseif ((bool) ($settings['show_table'] ?? true)) : ?>
        <table class="shop_table shop_table_responsive">
            <thead>
                <tr>
                    <?php if ((bool) ($settings['show_order_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($settings['column_order'] ?? __('Zamówienie', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($settings['show_customer_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($settings['column_customer'] ?? __('Klient', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($settings['show_value_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($settings['column_value'] ?? __('Wartość', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($settings['show_commission_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($settings['column_commission'] ?? __('Prowizja', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($settings['show_status_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($settings['column_status'] ?? __('Status', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($settings['show_date_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($settings['column_date'] ?? __('Data', 'polski'))); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($referrals as $referral) : ?>
                    <tr>
                        <?php if ((bool) ($settings['show_order_column'] ?? true)) : ?>
                            <td><?php echo esc_html((string) ($settings['order_prefix'] ?? '#') . (string) $referral->orderId); ?></td>
                        <?php endif; ?>
                        <?php if ((bool) ($settings['show_customer_column'] ?? true)) : ?>
                            <td><?php echo esc_html($referral->customerEmail); ?></td>
                        <?php endif; ?>
                        <?php if ((bool) ($settings['show_value_column'] ?? true)) : ?>
                            <td><?php echo wp_kses_post($service->formatAmount($referral->orderTotal)); ?></td>
                        <?php endif; ?>
                        <?php if ((bool) ($settings['show_commission_column'] ?? true)) : ?>
                            <td><?php echo wp_kses_post($service->formatAmount($referral->commissionAmount)); ?></td>
                        <?php endif; ?>
                        <?php if ((bool) ($settings['show_status_column'] ?? true)) : ?>
                            <td><?php echo esc_html($referral->status); ?></td>
                        <?php endif; ?>
                        <?php if ((bool) ($settings['show_date_column'] ?? true)) : ?>
                            <td><?php echo esc_html(wp_date($service->getDateFormat(), $referral->createdAt->getTimestamp())); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
