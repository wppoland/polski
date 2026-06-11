# Security Policy

## Supported versions

Security fixes are provided for the latest public free release.

## Reporting a vulnerability

Please do not open public GitHub issues for security reports.

Send the report by email to `security@wppoland.com` with:

- a short summary of the issue
- affected plugin version
- reproduction steps or a proof of concept
- expected impact

We will review the report, confirm whether it is a valid security issue, and prepare a fix.

## Responsible disclosure

Please give us reasonable time to investigate and release a fix before publishing technical details.

## Cyber Resilience Act (CRA) readiness

This section documents the security practices relevant to the EU Cyber Resilience Act. It describes what the plugin does. It is not a statement of legal compliance, and the assessment of your own CRA obligations remains with you and your advisers.

- **Secure update delivery.** Releases are distributed through the official WordPress.org plugin repository, so updates reach users through WordPress' standard, integrity-checked update mechanism.
- **Coordinated vulnerability disclosure.** Security issues can be reported privately to `security@wppoland.com` under the process above, and fixes are prepared before technical details are published.
- **Supported versions.** Security fixes are provided for the latest public free release.
- **Secure-by-default coding.** The code follows WordPress coding and security standards, including input sanitisation, output escaping, and capability and nonce checks, verified with PHP_CodeSniffer (WordPress Coding Standards) and the WordPress Plugin Check.
- **Data transparency.** The plugin does not collect hidden or undisclosed data. Any external services it can contact are documented in `readme.txt`.
- **Source availability.** The plugin source is distributed in readable form and is publicly available, so the code can be reviewed and audited.
