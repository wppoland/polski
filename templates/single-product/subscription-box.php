<?php

declare(strict_types=1);

/**
 * @var \Polski\Service\SubscriptionService $service
 * @var array<string, mixed>                      $config
 */
?>
<div class="polski-subscription-box">
    <?php if ((bool) ($service->getSettings()['show_section_title'] ?? true)) : ?>
        <h4><?php echo esc_html((string) ($service->getSettings()['section_title'] ?? __('Subskrypcja produktu', 'polski'))); ?></h4>
    <?php endif; ?>
    <?php if ((bool) ($service->getSettings()['show_section_intro'] ?? true) && ! empty($service->getSettings()['section_intro'])) : ?>
        <p><?php echo esc_html((string) $service->getSettings()['section_intro']); ?></p>
    <?php endif; ?>
    <p><?php echo esc_html($service->getHumanInterval((int) ($config['interval_count'] ?? 1), (string) ($config['interval_period'] ?? 'month'))); ?></p>
    <?php if ((float) ($config['signup_fee'] ?? 0) > 0) : ?>
        <p><?php echo esc_html($service->getSignupFeeText((float) $config['signup_fee'])); ?></p>
    <?php endif; ?>
    <?php if ((int) ($config['trial_days'] ?? 0) > 0) : ?>
        <p><?php echo esc_html($service->getTrialText((int) $config['trial_days'])); ?></p>
    <?php endif; ?>
</div>
