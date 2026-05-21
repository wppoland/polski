<?php

declare(strict_types=1);
namespace Polski\Privacy;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Repository\ConsentLogRepository;
use Polski\Repository\WithdrawalRepository;

/**
 * Wires WordPress core Personal Data Exporters and Erasers for the records that
 * Polski maintains: withdrawal declarations and consent log rows.
 *
 * Withdrawals are kept on disk for accounting and tax retention reasons; the
 * eraser anonymises them (clears email, free-text reason) instead of deleting.
 * Consent log rows have no statutory retention requirement and are fully deleted.
 */
final class WithdrawalPrivacyService implements HasHooks
{
    public function __construct(
        private readonly WithdrawalRepository $withdrawals,
        private readonly ConsentLogRepository $consentLog,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('wp_privacy_personal_data_exporters', [$this, 'registerExporters']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'registerErasers']);
    }

    /**
     * @param array<string, array<string, mixed>> $exporters
     * @return array<string, array<string, mixed>>
     */
    public function registerExporters(array $exporters): array
    {
        $exporters['polski-withdrawals'] = [
            'exporter_friendly_name' => __('Polski Withdrawal Declarations', 'polski'),
            'callback' => [$this, 'exportWithdrawals'],
        ];

        $exporters['polski-consents'] = [
            'exporter_friendly_name' => __('Polski Consent Log', 'polski'),
            'callback' => [$this, 'exportConsents'],
        ];

        return $exporters;
    }

    /**
     * @param array<string, array<string, mixed>> $erasers
     * @return array<string, array<string, mixed>>
     */
    public function registerErasers(array $erasers): array
    {
        $erasers['polski-withdrawals'] = [
            'eraser_friendly_name' => __('Polski Withdrawal Declarations', 'polski'),
            'callback' => [$this, 'eraseWithdrawals'],
        ];

        $erasers['polski-consents'] = [
            'eraser_friendly_name' => __('Polski Consent Log', 'polski'),
            'callback' => [$this, 'eraseConsents'],
        ];

        return $erasers;
    }

    /**
     * @return array{data: list<array<string, mixed>>, done: bool}
     */
    public function exportWithdrawals(string $email, int $page = 1): array
    {
        $items = [];

        $user = get_user_by('email', $email);

        if ($user instanceof \WP_User) {
            foreach ($this->withdrawals->findByCustomer((int) $user->ID, 500) as $row) {
                $items[] = $this->formatWithdrawal($row);
            }
        }

        foreach ($this->withdrawals->findByGuestEmail($email, 500) as $row) {
            $items[] = $this->formatWithdrawal($row);
        }

        return ['data' => $items, 'done' => true];
    }

    /**
     * @return array{data: list<array<string, mixed>>, done: bool}
     */
    public function exportConsents(string $email, int $page = 1): array
    {
        $user = get_user_by('email', $email);

        if (! $user instanceof \WP_User) {
            return ['data' => [], 'done' => true];
        }

        $items = [];

        foreach ($this->consentLog->findByUser((int) $user->ID, 500) as $record) {
            $items[] = [
                'group_id' => 'polski-consents',
                'group_label' => __('Polski Consent Records', 'polski'),
                'item_id' => 'consent-' . (int) $record->id,
                'data' => [
                    ['name' => __('Checkbox', 'polski'), 'value' => (string) $record->checkboxId],
                    ['name' => __('Context', 'polski'), 'value' => (string) $record->context->value],
                    ['name' => __('Consented', 'polski'), 'value' => $record->consented ? __('Yes', 'polski') : __('No', 'polski')],
                    ['name' => __('Recorded at', 'polski'), 'value' => $record->createdAt->format('Y-m-d H:i:s')],
                ],
            ];
        }

        return ['data' => $items, 'done' => true];
    }

    /**
     * @return array{items_removed: int, items_retained: int, messages: list<string>, done: bool}
     */
    public function eraseWithdrawals(string $email, int $page = 1): array
    {
        $retained = 0;
        $messages = [];

        $user = get_user_by('email', $email);

        if ($user instanceof \WP_User) {
            $retained += $this->withdrawals->anonymizeForCustomer((int) $user->ID);
        }

        $retained += $this->withdrawals->anonymizeForGuestEmail($email);

        if ($retained > 0) {
            $messages[] = __('Polski withdrawal declarations were anonymised. The records themselves were retained to comply with statutory accounting retention requirements.', 'polski');
        }

        return [
            'items_removed' => 0,
            'items_retained' => $retained,
            'messages' => $messages,
            'done' => true,
        ];
    }

    /**
     * @return array{items_removed: int, items_retained: int, messages: list<string>, done: bool}
     */
    public function eraseConsents(string $email, int $page = 1): array
    {
        $user = get_user_by('email', $email);

        if (! $user instanceof \WP_User) {
            return ['items_removed' => 0, 'items_retained' => 0, 'messages' => [], 'done' => true];
        }

        $removed = $this->consentLog->deleteByUser((int) $user->ID);

        return [
            'items_removed' => $removed,
            'items_retained' => 0,
            'messages' => [],
            'done' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatWithdrawal(\Polski\Model\WithdrawalRequest $row): array
    {
        return [
            'group_id' => 'polski-withdrawals',
            'group_label' => __('Polski Withdrawal Declarations', 'polski'),
            'item_id' => 'withdrawal-' . $row->id,
            'data' => [
                ['name' => __('Order ID', 'polski'), 'value' => (string) $row->orderId],
                ['name' => __('Status', 'polski'), 'value' => (string) $row->status->value],
                ['name' => __('Channel', 'polski'), 'value' => (string) $row->channel],
                ['name' => __('Filed at', 'polski'), 'value' => $row->requestedAt->format('Y-m-d H:i:s')],
                ['name' => __('Language', 'polski'), 'value' => (string) $row->languageCode],
            ],
        ];
    }
}
