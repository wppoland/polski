<?php
defined('ABSPATH') || exit;
/**
 * DSA illegal content report form.
 *
 * This template can be overridden by copying it to yourtheme/polski/forms/dsa-report.php.
 *
 * @var array<string, mixed> $settings DSA module settings.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$reasons = [
    'illegal_content' => __('Nielegalne treści', 'polski'),
    'illegal_product' => __('Nielegalny produkt', 'polski'),
    'misleading_ad'   => __('Wprowadzająca w błąd reklama', 'polski'),
    'other'           => __('Other', 'polski'),
];
?>
<div class="polski-dsa-report-form">

    <?php if (isset($_GET['polski_dsa_sent'])) : ?>
        <div class="polski-dsa-report-form__success">
            <p><?php echo esc_html__('Dziękujemy! Twoje zgłoszenie zostało wysłane i zostanie rozpatrzone.', 'polski'); ?></p>
        </div>
    <?php endif; ?>

    <h3><?php echo esc_html($settings['form_title'] ?? __('Zgłoś nielegalne treści (DSA)', 'polski')); ?></h3>

    <p class="polski-dsa-report-form__intro">
        <?php echo esc_html($settings['form_intro'] ?? __('Wypełnij poniższy formularz, aby zgłosić treści, które uważasz za nielegalne zgodnie z Aktem o usługach cyfrowych (DSA).', 'polski')); ?>
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

        <p class="polski-dsa-report-form__field">
            <label for="polski-dsa-content-url"><?php echo esc_html__('URL zgłaszanej treści', 'polski'); ?> <abbr class="required" title="<?php echo esc_attr__('wymagane', 'polski'); ?>">*</abbr></label>
            <input type="url" id="polski-dsa-content-url" name="content_url" required>
        </p>

        <p class="polski-dsa-report-form__field">
            <label for="polski-dsa-reason"><?php echo esc_html__('Powód zgłoszenia', 'polski'); ?> <abbr class="required" title="<?php echo esc_attr__('wymagane', 'polski'); ?>">*</abbr></label>
            <select id="polski-dsa-reason" name="reason" required>
                <option value=""><?php echo esc_html__('— Wybierz powód —', 'polski'); ?></option>
                <?php foreach ($reasons as $reasonValue => $reasonLabel) : ?>
                    <option value="<?php echo esc_attr($reasonValue); ?>"><?php echo esc_html($reasonLabel); ?></option>
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
