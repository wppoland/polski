<?php

declare(strict_types=1);
namespace Polski\Enum;

defined('ABSPATH') || exit;

/**
 * Consent categories used by the Consent Manager banner.
 *
 * `Necessary` is always granted and cannot be switched off. The other
 * categories map onto Google Consent Mode v2 signals and gate any scripts
 * or tags that opt in via the consent-gating contract.
 */
enum ConsentCategory: string
{
    case Necessary = 'necessary';
    case Analytics = 'analytics';
    case Marketing = 'marketing';
    case Preferences = 'preferences';

    /**
     * Categories the visitor can opt into (everything except always-on necessary).
     *
     * @return list<self>
     */
    public static function optional(): array
    {
        return [self::Analytics, self::Marketing, self::Preferences];
    }

    public function label(): string
    {
        return match ($this) {
            self::Necessary => __('Necessary', 'polski'),
            self::Analytics => __('Analytics', 'polski'),
            self::Marketing => __('Marketing', 'polski'),
            self::Preferences => __('Preferences', 'polski'),
        };
    }
}
