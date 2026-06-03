<?php

declare(strict_types=1);
namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Model\ConsentRecord;
use Polski\Repository\ConsentLogRepository;

/**
 * Read-only admin view of Consent Manager banner decisions, with a CSV export.
 * Only available while the consent_manager module is enabled.
 */
final class ConsentRecordsPage implements HasHooks
{
    private const CAPABILITY = 'manage_woocommerce';
    private const EXPORT_NONCE = 'polski_consent_records_export';
    private const PER_PAGE = 100;

    public function __construct(
        private readonly ConsentLogRepository $consentLog,
    ) {
    }

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('consent_manager')) {
            return;
        }

        // No standalone submenu: the records view is rendered inside the
        // Reports & Tools hub (admin.php?page=polski&tab=reports&view=consent).
        add_action('admin_post_polski_consent_records_export', [$this, 'handleExport']);
    }

    public function render(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            return;
        }

        $paged = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
        $offset = ($paged - 1) * self::PER_PAGE;

        $records = $this->consentLog->findCookieConsents(self::PER_PAGE, $offset);
        $total = $this->consentLog->countCookieConsents();
        $totalPages = (int) ceil($total / self::PER_PAGE);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Consent records', 'polski'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                <input type="hidden" name="action" value="polski_consent_records_export">
                <?php wp_nonce_field(self::EXPORT_NONCE); ?>
                <button type="submit" class="page-title-action"><?php esc_html_e('Export CSV', 'polski'); ?></button>
            </form>
            <hr class="wp-header-end" />

            <p class="description">
                <?php esc_html_e('Each row is one stored consent decision from the banner. These records help you document the choices visitors made; they are not a substitute for legal advice.', 'polski'); ?>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'polski'); ?></th>
                        <th><?php esc_html_e('Category', 'polski'); ?></th>
                        <th><?php esc_html_e('Decision', 'polski'); ?></th>
                        <th><?php esc_html_e('User', 'polski'); ?></th>
                        <th><?php esc_html_e('IP address', 'polski'); ?></th>
                        <th><?php esc_html_e('Wording version', 'polski'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)) : ?>
                        <tr><td colspan="6"><?php esc_html_e('No consent records yet.', 'polski'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($records as $record) : ?>
                            <tr>
                                <td><?php echo esc_html($record->createdAt->format('Y-m-d H:i:s')); ?></td>
                                <td><?php echo esc_html($this->categoryName($record)); ?></td>
                                <td>
                                    <?php echo $record->consented
                                        ? esc_html__('Granted', 'polski')
                                        : esc_html__('Denied', 'polski'); ?>
                                </td>
                                <td><?php echo esc_html($record->userId !== null ? (string) $record->userId : __('Guest', 'polski')); ?></td>
                                <td><?php echo esc_html((string) ($record->ipAddress ?? '')); ?></td>
                                <td><code><?php echo esc_html((string) ($record->consentVersion ?? '')); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post(
                            paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'current' => $paged,
                                'total' => $totalPages,
                            ]) ?? '',
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handleExport(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Sorry, it seems you do not have access to this page.', 'polski'));
        }

        check_admin_referer(self::EXPORT_NONCE);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=polski-consent-records-' . gmdate('Y-m-d-His') . '.csv');

        $handle = fopen('php://output', 'wb');
        if ($handle === false) {
            wp_die(esc_html__('Could not open output stream.', 'polski'));
        }

        fputcsv($handle, ['id', 'created_at', 'category', 'granted', 'user_id', 'ip_address', 'user_agent', 'consent_version']);

        $offset = 0;
        do {
            $records = $this->consentLog->findCookieConsents(self::PER_PAGE, $offset);

            foreach ($records as $record) {
                fputcsv($handle, [
                    (string) $record->id,
                    $record->createdAt->format('c'),
                    $this->categoryKey($record),
                    $record->consented ? '1' : '0',
                    (string) ($record->userId ?? ''),
                    (string) ($record->ipAddress ?? ''),
                    (string) ($record->userAgent ?? ''),
                    (string) ($record->consentVersion ?? ''),
                ]);
            }

            $offset += self::PER_PAGE;
        } while (count($records) === self::PER_PAGE);

        fclose($handle);
        exit;
    }

    private function categoryKey(ConsentRecord $record): string
    {
        return (string) preg_replace('/^cookie_/', '', $record->checkboxId);
    }

    private function categoryName(ConsentRecord $record): string
    {
        $key = $this->categoryKey($record);
        $enum = \Polski\Enum\ConsentCategory::tryFrom($key);

        return $enum !== null ? $enum->label() : $key;
    }
}
