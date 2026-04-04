<?php

declare(strict_types=1);
namespace Polski\Util;

defined('ABSPATH') || exit;

use const Polski\PLUGIN_DIR;

/**
 * Provides cached access to a WordPress option merged with defaults.
 *
 * Classes using this trait must define:
 *   private const OPTION = 'polski_...';
 *
 * Loaded once on first access, then served from cache.
 *
 * Usage:
 *   use SettingsCacheable;
 *   private const OPTION = 'polski_filters';
 *   // Then call $this->getSettings() anywhere.
 */
trait SettingsCacheable
{
    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    /**
     * Get merged settings (saved values over defaults).
     *
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $defaults = require PLUGIN_DIR . '/config/defaults.php';
        $defaultSettings = is_array($defaults[static::OPTION] ?? null) ? $defaults[static::OPTION] : [];

        $saved = get_option(static::OPTION, []);
        $saved = is_array($saved) ? $saved : [];

        $this->settings = wp_parse_args($saved, $defaultSettings);

        return $this->settings;
    }

    /**
     * Get a single setting value with optional default.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->getSettings()[$key] ?? $default;
    }

    /**
     * Clear the settings cache (e.g., after saving new values).
     */
    public function clearSettingsCache(): void
    {
        $this->settings = null;
    }
}
