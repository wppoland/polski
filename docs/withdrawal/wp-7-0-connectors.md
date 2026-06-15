# WordPress 7.0 Connectors API - Polski integration sketch

This document plans how the Polski plugin adopts the new
[Connectors API](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/)
landing in WordPress 7.0 (stable expected mid-2026).

## What changes for end users

Today's flow for a shop using Polski Pro AI features (description rewrites,
content audits, etc.):

1. Open WooCommerce > Settings > Polski Pro > AI
2. Paste an OpenAI / Anthropic / Gemini API key into a Polski-specific field
3. Each module that wants AI looks up that field

With WP 7.0 Connectors API:

1. Site admin opens **Settings > Connectors** (a core admin screen) once
2. Enters the API key in the connector tile for their provider - stored in
   `connectors_ai_<provider>_api_key` option, or read from a system-wide
   environment variable / PHP constant
3. Every plugin that needs an AI provider reads the same source through
   `wp_get_connector( 'anthropic' )` - no per-plugin field

Polski-specific UI for credentials goes away. The site admin manages all
their AI providers in one place.

## Polski services that should consume Connectors

| Service | Provider |
|---|---|
| `Polski\Pro\Service\AiDescriptionService` | Anthropic (default) or OpenAI fallback |
| `Polski\Service\PageComplianceService` (auto-fix suggestions if added) | Any text-gen LLM |
| Withdrawal annex translator (future) | Any text-gen LLM |
| KSeF XML schema fixer (future) | Same provider |

For now only `AiDescriptionService` is shipped; the rest are aspirational.

## Registration

We do not _register_ a connector - Anthropic/OpenAI/Gemini providers are
auto-discovered when the WP AI Client picks them up. We only _consume_ the
registry. Pseudo-code:

```php
final class PolskiAiClient
{
    public function send(string $prompt): ?string
    {
        if (! function_exists('wp_get_connector')) {
            // WP < 7.0 - fall back to the legacy Polski Pro field.
            return $this->legacyClient->send($prompt);
        }

        $connector = wp_get_connector(
            (string) apply_filters('polski_pro/ai/preferred_connector', 'anthropic')
        );

        if ($connector === null) {
            return null; // No provider configured.
        }

        $apiKey = $this->resolveCredentials($connector);
        if ($apiKey === '') {
            return null;
        }

        // Provider-specific transport stays inside the WP AI Client.
        return wp_ai_request($connector['name'], $prompt, ['api_key' => $apiKey]);
    }

    private function resolveCredentials(array $connector): string
    {
        if (! isset($connector['authentication']['setting_name'])) {
            return '';
        }
        $optionName = (string) $connector['authentication']['setting_name'];
        return (string) get_option($optionName, '');
    }
}
```

## Overriding the default connector (advanced)

If a shop wants to swap the default Anthropic description with a sharper
fork ("Anthropic + Polish system prompt"), they can override it during
`wp_connectors_init`:

```php
add_action('wp_connectors_init', function (WP_Connector_Registry $registry): void {
    if (! $registry->is_registered('anthropic')) {
        return;
    }
    $connector = $registry->unregister('anthropic');
    $connector['description'] = __(
        'Anthropic Claude - Polski preset (Polish system prompt, PL fallback)',
        'polski-pro',
    );
    $registry->register('anthropic', $connector);
});
```

We won't ship this; it's an extensibility example for shop owners.

## Authentication priority

Inherited from the core registry:

1. Environment variable (e.g. `ANTHROPIC_API_KEY`)
2. PHP constant of the same name (`define('ANTHROPIC_API_KEY', '...')`)
3. Database option (`connectors_ai_anthropic_api_key`)

This solves the long-standing issue with secrets-in-DB on shared hosting:
ops teams can set the env var and the key never reaches `wp_options`.

## Migration plan

1. **Polski Pro 1.15.0** - add `PolskiAiClient` with feature detection
   (`function_exists('wp_get_connector')`) and a one-time admin notice that
   tells the user "WP 7.0 detected - manage AI keys in Settings > Connectors".
2. **Polski Pro 1.16.0** - read both legacy field AND Connectors, prefer
   Connectors when set; emit deprecation warning in `WP_DEBUG`.
3. **Polski Pro 2.0.0** - drop legacy field UI; legacy reads still work
   for sites pinned to WP 6.9.
4. **Polski Pro 3.0.0** - require WP 7.0; drop legacy entirely.

## Tests we'll add

- Unit: `PolskiAiClient` falls back to legacy when `function_exists` returns
  false (mock the global).
- Unit: priority order - env → constant → option (use `putenv` + `define`).
- E2E: with WP 7.0 wp-env profile, set the option and assert
  `polski_pro/ai/preferred_connector` filter is honoured.

## Open questions

- WP 7.0 stable release date - currently in RC4 (April 2026); GA expected
  May 2026. Until then we feature-detect.
- AI Client transport - does it abstract the HTTP layer (so we don't ship
  Anthropic SDK ourselves), or do we still call `wp_remote_post()`?
- Per-locale system prompts - connector metadata doesn't carry locale; we
  apply the Polish system prompt client-side.

## Not in scope

- Registering Polski's _own_ abilities as connectors. Connectors are AI
  provider abstractions, not the Abilities API. Polski's 20 abilities
  already live under `wp-abilities/v1/` (WP 6.9+) and don't change.
