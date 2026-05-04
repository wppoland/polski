<?php
/**
 * DSA illegal content report form.
 *
 * This template can be overridden by copying it to yourtheme/polski/forms/dsa-report.php.
 *
 * @var array<string, mixed> $polski_settings   DSA module settings.
 * @var string               $polski_prefill_url Optional URL to lock the report to.
 * @var string               $polski_prefill_label Optional human-readable label of the reported item.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$polski_reasons = [
    'illegal_content' => __('Nielegalne treści', 'polski'),
    'illegal_product' => __('Nielegalny produkt', 'polski'),
    'misleading_ad'   => __('Wprowadzająca w błąd reklama', 'polski'),
    'other'           => __('Inne', 'polski'),
];

$polski_prefill_url = isset($polski_prefill_url) ? (string) $polski_prefill_url : '';
$polski_prefill_label = isset($polski_prefill_label) ? (string) $polski_prefill_label : '';
?>
<div class="polski-dsa-report-form">

    <?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only success flag. ?>
    <?php if (isset($_GET['polski_dsa_sent'])) : ?>
        <div class="polski-dsa-report-form__success">
            <p><?php echo esc_html__('Dziękujemy! Twoje zgłoszenie zostało wysłane i zostanie rozpatrzone.', 'polski'); ?></p>
        </div>
    <?php endif; ?>
    <?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

    <h3><?php echo esc_html($polski_settings['form_title'] ?? __('Zgłoś nielegalne treści (DSA)', 'polski')); ?></h3>

    <p class="polski-dsa-report-form__intro">
        <?php echo esc_html($polski_settings['form_intro'] ?? __('Wypełnij poniższy formularz, aby zgłosić treści, które uważasz za nielegalne zgodnie z Aktem o usługach cyfrowych (DSA).', 'polski')); ?>
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="polski_dsa_report">
        <?php wp_nonce_field('polski_dsa_report', '_polski_dsa_nonce'); ?>

        <p class="polski-dsa-report-form__field">
            <label for="polski-dsa-reporter-name"><?php echo esc_html__('Imię i nazwisko', 'polski'); ?> <abbr class="required" title="<?php echo esc_attr__('wymagane', 'polski'); ?>">*</abbr></label>
            <input type="text" id="polski-dsa-reporter-name" name="reporter_name" required>
        </p>

        <p class="polski-dsa-report-form__field">
            <label for="polski-dsa-reporter-email"><?php echo esc_html__('Adres e-mail', 'polski'); ?> <abbr class="required" title="<?php echo esc_attr__('wymagane', 'polski'); ?>">*</abbr></label>
            <input type="email" id="polski-dsa-reporter-email" name="reporter_email" required>
        </p>

        <?php if ($polski_prefill_url !== '') : ?>
            <p class="polski-dsa-report-form__prefilled">
                <?php
                if ($polski_prefill_label !== '') {
                    printf(
                        /* translators: 1: human label, 2: URL */
                        esc_html__('Zgłaszasz: %1$s (%2$s)', 'polski'),
                        '<strong>' . esc_html($polski_prefill_label) . '</strong>',
                        '<a href="' . esc_url($polski_prefill_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($polski_prefill_url) . '</a>',
                    );
                } else {
                    printf(
                        /* translators: %s: URL */
                        esc_html__('Zgłaszasz: %s', 'polski'),
                        '<a href="' . esc_url($polski_prefill_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($polski_prefill_url) . '</a>',
                    );
                }
                ?>
            </p>
            <input type="hidden" name="content_url" value="<?php echo esc_url($polski_prefill_url); ?>">
        <?php else : ?>
        <p class="polski-dsa-report-form__field">
            <label for="polski-dsa-content-url"><?php echo esc_html__('URL zgłaszanej treści', 'polski'); ?> <abbr class="required" title="<?php echo esc_attr__('wymagane', 'polski'); ?>">*</abbr></label>
            <input type="url" id="polski-dsa-content-url" name="content_url" required>
        </p>
        <?php endif; ?>

        <p class="polski-dsa-report-form__field">
            <label for="polski-dsa-reason"><?php echo esc_html__('Powód zgłoszenia', 'polski'); ?> <abbr class="required" title="<?php echo esc_attr__('wymagane', 'polski'); ?>">*</abbr></label>
            <select id="polski-dsa-reason" name="reason" required>
                <option value=""><?php echo esc_html__('— Wybierz powód —', 'polski'); ?></option>
                <?php foreach ($polski_reasons as $polski_reasonValue => $polski_reasonLabel) : ?>
                    <option value="<?php echo esc_attr($polski_reasonValue); ?>"><?php echo esc_html($polski_reasonLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p class="polski-dsa-report-form__field">
            <label for="polski-dsa-description"><?php echo esc_html__('Opis', 'polski'); ?> <abbr class="required" title="<?php echo esc_attr__('wymagane', 'polski'); ?>">*</abbr></label>
            <textarea id="polski-dsa-description" name="description" rows="5" required></textarea>
        </p>

        <p class="polski-dsa-report-form__submit">
            <button type="submit" class="button"><?php echo esc_html__('Wyślij zgłoszenie', 'polski'); ?></button>
        </p>
    </form>
</div>
