<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Repository\WaitlistRepository;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;
use WPPoland\StorefrontKit\Waitlist\WaitlistEngine;

/**
 * Waitlist signups and back-in-stock notifications.
 */
final class WaitlistService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_waitlist';

    private readonly WaitlistEngine $engine;

    public function __construct(
        private readonly WaitlistRepository $repository,
        private readonly TemplateLoader $templateLoader,
    ) {
        $this->engine = new WaitlistEngine(
            repository: $this->repository,
            ajaxAction: 'polski_waitlist_subscribe',
            nonceAction: 'polski_waitlist',
            scriptObjectName: 'polskiWaitlist',
            assetHandle: 'polski-waitlist',
            styleUrl: \Polski\Plugin::instance()->url('assets/css/waitlist.css'),
            scriptUrl: \Polski\Plugin::instance()->url('assets/js/waitlist.js'),
            version: \Polski\VERSION,
            templateName: 'single-product/waitlist-form',
            defaultMessages: [
                'generic_error' => __('Something went wrong. Please try again.', 'polski'),
                'product_not_found' => __('Product not found.', 'polski'),
                'disabled' => __('Waitlist is unavailable for this product.', 'polski'),
                'invalid_email' => __('Provide a valid email address.', 'polski'),
                'privacy_error' => __('You must accept the consent for email contact.', 'polski'),
                'login_required' => __('Login to join the waitlist.', 'polski'),
                'success' => __('Thank you. You have been added to the waitlist.', 'polski'),
                'notify_subject' => __('Product back in stock - {product_name}', 'polski'),
                'notify_intro' => __('Product {product_name} is back in stock.', 'polski'),
                'notify_outro' => __('If you no longer wish to receive these messages, simply ignore this email.', 'polski'),
            ],
            isEnabled: fn (): bool => $this->isEnabled(),
            settings: fn (): array => $this->getSettings(),
            renderTemplate: function (string $template, array $data): void {
                $this->templateLoader->include($template, $data);
            },
        );
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        $this->engine->registerHooks();
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('waitlist');
    }
}
