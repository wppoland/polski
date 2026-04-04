<?php

declare(strict_types=1);
namespace Polski\Admin;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use const Polski\VERSION;
use const Polski\PLUGIN_FILE;

/**
 * Handles deactivation feedback modal and data persistence.
 */
final class DeactivationHandler implements HasHooks
{
    private const OPTION_NAME = 'polski_deactivation_feedback';
    private const NONCE_ACTION = 'polski_deactivation_feedback_nonce';
    private const AJAX_ACTION = 'polski_submit_deactivation_feedback';
    private const MAX_ENTRIES = 100;

    public function registerHooks(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'handleSubmitFeedback']);

        // Footer modal container.
        add_action('admin_footer-plugins.php', [$this, 'renderModalAndData']);
    }

    /**
     * Enqueue assets only on the plugins page.
     */
    public function enqueueScripts(string $hook): void
    {
        if ('plugins.php' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'polski-deactivation-feedback',
            plugins_url('assets/css/deactivation-feedback.css', PLUGIN_FILE),
            [],
            VERSION
        );

        wp_enqueue_script(
            'polski-deactivation-feedback',
            plugins_url('assets/js/deactivation-feedback.js', PLUGIN_FILE),
            ['jquery'],
            VERSION,
            true
        );

        wp_localize_script('polski-deactivation-feedback', 'polskiDeactivation', [
            'plugin_basename' => plugin_basename(PLUGIN_FILE),
            'plugin_row_slug' => 'polski',
            'plugin_name'  => __('Polski for WooCommerce', 'polski'),
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce(self::NONCE_ACTION),
            'ajax_action' => self::AJAX_ACTION,
            'i18n'        => [
                'title'       => __('Before you deactivate Polski', 'polski'),
                'subtitle'    => __('A quick answer helps us improve the plugin and roadmap.', 'polski'),
                'reasonLabel' => __('What is the main reason for deactivation?', 'polski'),
                'reasons'     => [
                    'missing_feature' => __('A feature is missing', 'polski'),
                    'hard_to_use'     => __('It is hard to configure or use', 'polski'),
                    'bug'             => __('I found a bug or conflict', 'polski'),
                    'not_needed'      => __('I do not need it right now', 'polski'),
                    'temporary'       => __('This is only temporary', 'polski'),
                    'other'           => __('Other reason', 'polski'),
                ],
                'improveLabel'    => __('What could we do better?', 'polski'),
                'improvePlaceholder' => __('Tell us what was frustrating, unclear, or not working well.', 'polski'),
                'featureLabel'    => __('What should we add next?', 'polski'),
                'featurePlaceholder' => __('Feature ideas, integrations, reports, builder support, or anything else you miss.', 'polski'),
                'submit'          => __('Send feedback and deactivate', 'polski'),
                'skip'            => __('Deactivate without feedback', 'polski'),
                'close'           => __('Keep plugin active', 'polski'),
                'wait'            => __('Sending...', 'polski'),
                'validation'      => __('Please choose the main reason before sending feedback.', 'polski'),
                'goodbye'         => __('Thank you for using Polski! We are sorry to see you go. If you have any ideas on how to make it better, we would love to hear from you.', 'polski'),
                'githubLabel'     => __('Post new ideas on GitHub', 'polski'),
                'githubUrl'       => 'https://github.com/WPPoland/polski/issues',
            ]
        ]);
    }

    /**
     * Handle the AJAX feedback submission.
     */
    public function handleSubmitFeedback(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (! current_user_can('activate_plugins')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $reason = sanitize_text_field($_POST['reason'] ?? 'other');
        $improvement = sanitize_textarea_field($_POST['improvement'] ?? '');
        $requestedFeature = sanitize_textarea_field($_POST['requested_feature'] ?? '');

        $feedback = get_option(self::OPTION_NAME, []);
        if (! is_array($feedback)) {
            $feedback = [];
        }

        $feedback[] = [
            'timestamp' => current_time('mysql'),
            'plugin'    => 'polski',
            'plugin_name' => 'Polski for WooCommerce',
            'version'   => VERSION,
            'site_url'  => home_url(),
            'user'      => wp_get_current_user()->display_name,
            'user_email' => wp_get_current_user()->user_email,
            'reason'    => $reason,
            'improvement' => $improvement,
            'requested_feature' => $requestedFeature,
        ];

        if (count($feedback) > self::MAX_ENTRIES) {
            $feedback = array_slice($feedback, -self::MAX_ENTRIES);
        }

        update_option(self::OPTION_NAME, $feedback);

        wp_send_json_success();
    }

    /**
     * Render the feedback log in the Admin Hub.
     */
    public function renderFeedbackLog(): void
    {
        $feedback = get_option(self::OPTION_NAME, []);
        if (! is_array($feedback)) {
            $feedback = [];
        }

        $feedback = array_reverse($feedback); // Newest first.

        echo '<h3>' . esc_html__('Deactivation feedback log', 'polski') . '</h3>';

        if (empty($feedback)) {
            echo '<p>' . esc_html__('No feedback to display.', 'polski') . '</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Date', 'polski') . '</th>';
        echo '<th>' . esc_html__('Plugin', 'polski') . '</th>';
        echo '<th>' . esc_html__('Reason', 'polski') . '</th>';
        echo '<th>' . esc_html__('What to improve', 'polski') . '</th>';
        echo '<th>' . esc_html__('What to add', 'polski') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($feedback as $entry) {
            echo '<tr>';
            echo '<td>' . esc_html($entry['timestamp']) . '</td>';
            echo '<td><strong>' . esc_html((string) ($entry['plugin_name'] ?? 'Polski')) . '</strong><br><small>' . esc_html((string) ($entry['version'] ?? '')) . '</small></td>';
            echo '<td><strong>' . esc_html($this->getReasonLabel($entry['reason'])) . '</strong></td>';
            echo '<td>' . nl2br(esc_html((string) ($entry['improvement'] ?? ''))) . '</td>';
            echo '<td>' . nl2br(esc_html((string) ($entry['requested_feature'] ?? ''))) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function getReasonLabel(string $key): string
    {
        $labels = [
            'missing_feature' => __('A feature is missing', 'polski'),
            'hard_to_use'     => __('It is hard to configure or use', 'polski'),
            'bug'             => __('I found a bug or conflict', 'polski'),
            'not_needed'      => __('I do not need it right now', 'polski'),
            'temporary'       => __('This is only temporary', 'polski'),
            'other'           => __('Other reason', 'polski'),
        ];

        return $labels[$key] ?? $key;
    }

    /**
     * Placeholder needed for JS interceptor.
     */
    public function renderModalAndData(): void
    {
        // Actually the modal HTML will be generated by JS for better animation control,
        // but we can put it here if we want a static fallback.
    }
}
