<?php

declare(strict_types=1);
namespace Polski;

defined('ABSPATH') || exit;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;

/**
 * Main plugin class. Singleton that wires the DI container and boots all services.
 */
final class Plugin
{
    private static ?self $instance = null;
    private Container $container;
    private bool $booted = false;
    private bool $textDomainLoaded = false;

    private function __construct()
    {
        $this->container = new Container();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Boot the plugin: wire services, register hooks, fire events.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        // Store self in container for cross-references.
        $this->container->instance(self::class, $this);

        if (did_action('init')) {
            $this->loadTextDomain();
        }

        // Load service definitions.
        $this->loadServiceDefinitions();

        // Register all hook subscribers.
        $this->registerHookSubscribers();

        $this->syncInstalledVersion();
        /**
         * Fires after Polski is fully booted.
         *
         * @param Plugin $plugin The plugin instance.
         */
        do_action('polski/booted', $this);
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function version(): string
    {
        return VERSION;
    }

    /**
     * Absolute path to the plugin directory (with optional relative path appended).
     */
    public function path(string $relative = ''): string
    {
        return PLUGIN_DIR . ($relative !== '' ? '/' . ltrim($relative, '/') : '');
    }

    /**
     * URL to the plugin directory (with optional relative path appended).
     */
    public function url(string $relative = ''): string
    {
        return plugins_url($relative, PLUGIN_FILE);
    }

    /**
     * Load service definitions from config/services.php.
     */
    private function loadServiceDefinitions(): void
    {
        $servicesFile = $this->path('config/services.php');

        if (file_exists($servicesFile)) {
            $register = require $servicesFile;

            if (is_callable($register)) {
                $register($this->container);
            }
        }
    }

    /**
     * Resolve and register hook subscribers defined in config/hooks.php.
     */
    private function registerHookSubscribers(): void
    {
        $hooksFile = $this->path('config/hooks.php');

        if (! file_exists($hooksFile)) {
            return;
        }

        /** @var list<class-string<HasHooks>> $hookClasses */
        $hookClasses = require $hooksFile;

        foreach ($hookClasses as $className) {
            if (! $this->container->has($className)) {
                continue;
            }

            $service = $this->container->get($className);

            if ($service instanceof Bootable) {
                $service->boot();
            }

            if ($service instanceof HasHooks) {
                $service->registerHooks();
            }
        }
    }

    /**
     * Load plugin text domain for translations.
     */
    public function loadTextDomain(): void
    {
        if ($this->textDomainLoaded || ! did_action('init')) {
            return;
        }

        $this->textDomainLoaded = true;

        load_plugin_textdomain(
            'polski',
            false,
            dirname(plugin_basename(PLUGIN_FILE)) . '/languages',
        );
    }

    private function syncInstalledVersion(): void
    {
        if (get_option('polski_version') !== VERSION) {
            update_option('polski_version', VERSION);
        }
    }

    /**
     * Prevent cloning.
     */
    private function __clone()
    {
    }
}
