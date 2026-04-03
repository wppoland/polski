<?php

declare(strict_types=1);

/**
 * @var \Polski\Service\GiftCardService $service
 * @var list<\Polski\Model\GiftCard>    $cards
 */
?>
<section class="polski-account-gift-cards">
    <?php if ((bool) ($service->getSettings()['show_account_title'] ?? true)) : ?>
        <h2><?php echo esc_html((string) ($service->getSettings()['account_title'] ?? __('Karty podarunkowe', 'polski'))); ?></h2>
    <?php endif; ?>

    <?php if ((string) ($service->getSettings()['account_intro_text'] ?? '') !== '') : ?>
        <div class="polski-account-gift-cards__intro">
            <?php echo wpautop(wp_kses_post((string) ($service->getSettings()['account_intro_text'] ?? ''))); ?>
        </div>
    <?php endif; ?>

    <?php if ($cards === []) : ?>
        <p><?php echo esc_html((string) ($service->getSettings()['empty_text'] ?? __('Brak kart podarunkowych przypisanych do Twojego konta.', 'polski'))); ?></p>
    <?php else : ?>
        <table class="shop_table shop_table_responsive">
            <thead>
                <tr>
                    <th><?php echo esc_html((string) ($service->getSettings()['column_code'] ?? __('Kod', 'polski'))); ?></th>
                    <?php if ((bool) ($service->getSettings()['show_balance_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($service->getSettings()['column_balance'] ?? __('Saldo', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($service->getSettings()['show_recipient_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($service->getSettings()['column_recipient'] ?? __('Odbiorca', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($service->getSettings()['show_status_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($service->getSettings()['column_status'] ?? __('Status', 'polski'))); ?></th>
                    <?php endif; ?>
                    <?php if ((bool) ($service->getSettings()['show_expiry_column'] ?? true)) : ?>
                        <th><?php echo esc_html((string) ($service->getSettings()['column_expiry'] ?? __('Ważna do', 'polski'))); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cards as $card) : ?>
                    <tr>
                        <td><?php echo esc_html($card->code); ?></td>
                        <?php if ((bool) ($service->getSettings()['show_balance_column'] ?? true)) : ?>
                            <td><?php echo wp_kses_post(\Polski\Util\Formatter::price($card->balance, $card->currency)); ?></td>
                        <?php endif; ?>
                        <?php if ((bool) ($service->getSettings()['show_recipient_column'] ?? true)) : ?>
                            <td>
                                <?php
                                $recipient = $card->recipientName;

                                if ((bool) ($service->getSettings()['show_recipient_email_in_account'] ?? true) && $card->recipientEmail !== '') {
                                    $recipient .= ' (' . $card->recipientEmail . ')';
                                }

                                echo esc_html($recipient);
                                ?>
                            </td>
                        <?php endif; ?>
                        <?php if ((bool) ($service->getSettings()['show_status_column'] ?? true)) : ?>
                            <td><?php echo esc_html($service->getStatusLabel($card)); ?></td>
                        <?php endif; ?>
                        <?php if ((bool) ($service->getSettings()['show_expiry_column'] ?? true)) : ?>
                            <td><?php echo esc_html($card->expiresAt ? wp_date($service->getDateFormat(), $card->expiresAt->getTimestamp()) : ''); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
