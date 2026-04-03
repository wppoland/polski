<?php

declare(strict_types=1);

/**
 * @var \Polski\Service\SubscriptionService $service
 * @var list<\Polski\Model\Subscription>     $subscriptions
 */
?>
<section class="polski-account-subscriptions">
    <?php if ((bool) ($service->getSettings()['show_account_title'] ?? true)) : ?>
        <h2><?php echo esc_html((string) ($service->getSettings()['account_title'] ?? __('Subskrypcje', 'polski'))); ?></h2>
    <?php endif; ?>

    <?php if ((string) ($service->getSettings()['account_intro_text'] ?? '') !== '') : ?>
        <div class="polski-account-subscriptions__intro">
            <?php echo wpautop(wp_kses_post((string) ($service->getSettings()['account_intro_text'] ?? ''))); ?>
        </div>
    <?php endif; ?>

    <?php if ($subscriptions === []) : ?>
        <p><?php echo esc_html((string) ($service->getSettings()['empty_text'] ?? __('Brak aktywnych subskrypcji.', 'polski'))); ?></p>
    <?php else : ?>
        <table class="shop_table shop_table_responsive">
            <thead>
                <tr>
                    <?php if ((bool) ($service->getSettings()['show_product_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($service->getSettings()['column_product'] ?? __('Produkt', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($service->getSettings()['show_cycle_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($service->getSettings()['column_cycle'] ?? __('Cykl', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($service->getSettings()['show_amount_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($service->getSettings()['column_amount'] ?? __('Kwota', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($service->getSettings()['show_status_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($service->getSettings()['column_status'] ?? __('Status', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($service->getSettings()['show_next_payment_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($service->getSettings()['column_next_payment'] ?? __('Następne odnowienie', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($service->getSettings()['show_actions_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($service->getSettings()['column_actions'] ?? __('Akcje', 'polski'))); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscriptions as $subscription) : ?>
                    <tr>
                        <?php if ((bool) ($service->getSettings()['show_product_column'] ?? true)) : ?>
                            <td><?php echo esc_html($subscription->productName); ?></td>
                        <?php endif; ?>
                        <?php if ((bool) ($service->getSettings()['show_cycle_column'] ?? true)) : ?>
                            <td><?php echo esc_html($service->getHumanInterval($subscription->intervalCount, $subscription->intervalPeriod)); ?></td>
                        <?php endif; ?>
                        <?php if ((bool) ($service->getSettings()['show_amount_column'] ?? true)) : ?>
                            <td><?php echo wp_kses_post(\Polski\Util\Formatter::price($subscription->recurringAmount * $subscription->quantity)); ?></td>
                        <?php endif; ?>
                        <?php if ((bool) ($service->getSettings()['show_status_column'] ?? true)) : ?>
                            <td><?php echo esc_html($service->getStatusLabel($subscription)); ?></td>
                        <?php endif; ?>
                        <?php if ((bool) ($service->getSettings()['show_next_payment_column'] ?? true)) : ?>
                            <td><?php echo esc_html($subscription->nextPaymentAt ? wp_date($service->getDateFormat(), $subscription->nextPaymentAt->getTimestamp()) : ''); ?></td>
                        <?php endif; ?>
                        <?php if ((bool) ($service->getSettings()['show_actions_column'] ?? true)) : ?>
                            <td>
                                <?php if ($subscription->status === 'cancelled') : ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['polski_subscription_action' => 'reactivate', 'subscription_id' => $subscription->id]), 'polski_subscription_reactivate_' . $subscription->id)); ?>"><?php echo esc_html((string) ($service->getSettings()['reactivate_button_text'] ?? __('Wznów', 'polski'))); ?></a>
                                <?php elseif ($service->getSettings()['allow_cancellation'] ?? true) : ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['polski_subscription_action' => 'cancel', 'subscription_id' => $subscription->id]), 'polski_subscription_cancel_' . $subscription->id)); ?>"><?php echo esc_html((string) ($service->getSettings()['cancel_button_text'] ?? __('Anuluj', 'polski'))); ?></a>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
