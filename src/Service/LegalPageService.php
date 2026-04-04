<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Enum\LegalPageType;

/**
 * Manages legal pages: auto-creation, retrieval, and email attachment.
 */
final class LegalPageService
{
    /**
     * Auto-create all legal pages if they don't exist yet.
     * Called during setup wizard or on demand.
     */
    public function createDefaultPages(): void
    {
        foreach (LegalPageType::cases() as $type) {
            $this->ensurePageExists($type);
        }
    }

    /**
     * Create a legal page if not already assigned.
     */
    public function ensurePageExists(LegalPageType $type): int
    {
        $existingId = (int) get_option($type->optionKey(), 0);

        if ($existingId > 0 && get_post_status($existingId) === 'publish') {
            return $existingId;
        }

        $pageId = wp_insert_post([
            'post_title' => $this->getDefaultTitle($type),
            'post_content' => $this->getDefaultContent($type),
            'post_status' => 'draft',
            'post_type' => 'page',
            'post_author' => get_current_user_id() ?: 1,
        ]);

        if (is_wp_error($pageId)) {
            return 0;
        }

        update_option($type->optionKey(), $pageId);

        do_action('polski/legal_page/created', $pageId, $type);

        return $pageId;
    }

    /**
     * Get the page ID for a legal page type.
     */
    public function getPageId(LegalPageType $type): int
    {
        return (int) get_option($type->optionKey(), 0);
    }

    /**
     * Get the URL for a legal page.
     */
    public function getPageUrl(LegalPageType $type): string
    {
        $pageId = $this->getPageId($type);

        if ($pageId <= 0) {
            return '';
        }

        return (string) get_permalink($pageId);
    }

    /**
     * Get the content of a legal page (for email attachments).
     */
    public function getPageContent(LegalPageType $type): string
    {
        $pageId = $this->getPageId($type);

        if ($pageId <= 0) {
            return '';
        }

        $post = get_post($pageId);

        if (! $post instanceof \WP_Post) {
            return '';
        }

        return wp_strip_all_tags(apply_filters('the_content', $post->post_content));
    }

    /**
     * Check if all required legal pages are configured.
     *
     * @return array<string, bool> Map of page type => configured status.
     */
    public function getConfigurationStatus(): array
    {
        $status = [];

        foreach (LegalPageType::cases() as $type) {
            $pageId = $this->getPageId($type);
            $status[$type->value] = $pageId > 0 && get_post_status($pageId) === 'publish';
        }

        return $status;
    }

    /**
     * Get legal page content for email attachment.
     *
     * @return array<string, string> Map of page type => plain text content.
     */
    public function getEmailAttachments(): array
    {
        $settings = get_option('polski_emails', []);
        if (! is_array($settings)) {
            $settings = [];
        }

        $attachments = [];

        if ($settings['attach_terms'] ?? true) {
            $content = $this->getPageContent(LegalPageType::Terms);
            if ($content !== '') {
                $attachments['terms'] = $content;
            }
        }

        if ($settings['attach_privacy'] ?? false) {
            $content = $this->getPageContent(LegalPageType::Privacy);
            if ($content !== '') {
                $attachments['privacy'] = $content;
            }
        }

        if ($settings['attach_withdrawal'] ?? true) {
            $content = $this->getPageContent(LegalPageType::Returns);
            if ($content !== '') {
                $attachments['returns'] = $content;
            }
        }

        /**
         * Filter legal page attachments for emails.
         *
         * @param array<string, string> $attachments Page type => content map.
         */
        return (array) apply_filters('polski/email/legal_attachments', $attachments);
    }

    private function getDefaultTitle(LegalPageType $type): string
    {
        return match ($type) {
            LegalPageType::Terms => __('Regulamin', 'polski'),
            LegalPageType::Privacy => __('Polityka prywatności', 'polski'),
            LegalPageType::Returns => __('Prawo odstąpienia od umowy', 'polski'),
            LegalPageType::Complaints => __('Reklamacje', 'polski'),
        };
    }

    private function getDefaultContent(LegalPageType $type): string
    {
        return match ($type) {
            LegalPageType::Terms => '<!-- ' . __('Proszę uzupełnić Regulamin sklepu.', 'polski') . ' -->',
            LegalPageType::Privacy => '<!-- ' . __('Please fill in your Privacy Policy (Polityka prywatności).', 'polski') . ' -->',
            LegalPageType::Returns => '<!-- ' . __('Please fill in your Return and Withdrawal Policy (Prawo odstąpienia od umowy). Consumers have 14 days to withdraw.', 'polski') . ' -->',
            LegalPageType::Complaints => '<!-- ' . __('Proszę uzupełnić procedurę reklamacyjną.', 'polski') . ' -->',
        };
    }
}
