# Contributing to Polski for WooCommerce

Thanks for helping improve Polski for WooCommerce. This is the free, GPLv2 plugin
published at https://wordpress.org/plugins/polski/.

## Getting support vs contributing

- **Need help using the plugin?** Use the [WordPress.org support forum](https://wordpress.org/support/plugin/polski/)
  - it is the primary, official support channel.
- **Found a bug or have a feature idea?** Open a GitHub Issue using the templates.
- **Want to discuss / ask the maintainers?** Use GitHub Discussions.

## Reporting a bug

Open an issue with the **Bug report** template and include:
- Plugin version, WordPress version, WooCommerce version, PHP version
- Active plugins and the active theme
- Exact steps to reproduce, expected vs actual result

Please test on a staging site first - never debug on production.

## Suggesting a feature

Open an issue with the **Feature request** template. Describe the problem first,
then the proposed solution. Polish/EU compliance context (which regulation it maps
to) helps us prioritise.

## Pull requests

1. Branch off `main` - never push to `main` directly.
2. Keep changes focused; one logical change per PR.
3. The plugin must stay green on all gates before merge:
   - `vendor/bin/phpcs` (WPCS ruleset `phpcs.xml.dist`)
   - `php -d memory_limit=2G vendor/bin/phpstan analyse --memory-limit=2G`
   - **WordPress Plugin Check** (run before every release)
4. Add/update unit tests and documentation for new behaviour.
5. Do not add claims that the plugin guarantees legal compliance - it provides tools,
   not legal advice.

## Code style

Follow the existing code: PSR-4 under `src/`, WordPress Coding Standards, escaping and
nonce/capability checks on all input/output. Match the surrounding file's conventions.

## Security

Do not open public issues for security vulnerabilities. See [SECURITY.md](SECURITY.md).
