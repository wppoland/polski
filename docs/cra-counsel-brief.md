# CRA Readiness — Counsel Hand-off Brief

**Product:** Polski for WooCommerce (free) and Polski PRO (commercial add-on)
**Maker:** WPPoland
**Prepared for:** external legal review
**Status:** internal working document — not published, not distributed in the plugin

---

## 0. Purpose and disclaimer

This brief gathers, in one place, the facts a legal adviser needs to assess the
plugin's position under **Regulation (EU) 2024/2847 (Cyber Resilience Act, "CRA")**.

It describes what the software does and how it is built and distributed. **It is not
a statement of legal compliance**, and it does not assert that the CRA applies, or
does not apply, to this product. Those determinations — applicability, manufacturer
status, conformity-assessment route, reporting obligations — are for counsel to make.
Section 6 lists the open questions we are asking counsel to decide.

Where this brief cites a practice, the supporting code or document is named so it can
be verified independently.

---

## 1. Product and distribution

| | Free edition | PRO edition |
|---|---|---|
| Name | Polski for WooCommerce | Polski PRO |
| Licence | GPLv2-or-later | Commercial |
| Channel | WordPress.org plugin directory | Freemius (paid) |
| Source | Public, human-readable (`github.com/wppoland/polski`) | Private |
| Price | Free | Paid subscription |
| Updates | WordPress.org auto-update (integrity-checked) | Freemius update API |

The free edition is GPL software distributed without charge through the official
WordPress.org repository. The PRO edition is a paid, commercially-distributed add-on.
This free/commercial split is material to the CRA's free-and-open-source-software
treatment (see 6.2).

The product is a **WordPress/WooCommerce plugin** — a software component that runs
inside a host CMS on the user's own server. It is not a standalone device and ships
no hardware.

---

## 2. Who places it on the market

- The **free** edition is published by the WordPress.org contributor account
  `motylanogha` (WPPoland) under the GPL, at no charge.
- The **PRO** edition is sold by WPPoland via Freemius (merchant of record).

Counsel to determine whether, and for which edition, WPPoland is a "manufacturer"
or other economic operator within the meaning of the CRA, and the effect of the
Freemius merchant-of-record arrangement on that question.

---

## 3. Security practices mapped to CRA Annex I

The following are **already in place**. They are presented as evidence of current
engineering practice, mapped to the structure of CRA Annex I, not as a conformity
claim.

### 3.1 Annex I, Part I — security properties of the product

| Practice | Evidence |
|---|---|
| Secure-by-default coding: input sanitisation, output escaping, capability and nonce checks | Enforced by PHP_CodeSniffer (WordPress Coding Standards) + WordPress Plugin Check on every change; `SECURITY.md` |
| Minimised data collection; no hidden/undisclosed data; documented external services | `readme.txt` (external-services disclosure); `SECURITY.md` |
| Request-rate limiting and anti-spoofing on the sensitive guest flow | `src/Service/GuestWithdrawalService.php`, `src/Util/ClientIp.php` (trusted-proxy-aware client IP; forged forwarding headers ignored by default) |
| Personal-data minimisation in logs (IP anonymisation) | Consent log IPv6 anonymisation; `src/Repository/ConsentLogRepository.php` |
| Clean removal of data on uninstall (no orphaned data) | `uninstall.php` (drops plugin tables, options, post/order meta, taxonomy terms) |
| Versioned, reversible schema changes | `src/Migrator.php` |

### 3.2 Annex I, Part II — vulnerability handling

| Requirement (paraphrased) | Practice | Evidence |
|---|---|---|
| Coordinated vulnerability disclosure | Private reporting to `security@wppoland.com`; fixes prepared before public disclosure | `SECURITY.md` |
| Security updates delivered without delay | Releases via WordPress.org standard update mechanism (free); Freemius (PRO) | `SECURITY.md`; release scripts |
| Supported-version policy | Security fixes provided for the latest public release | `SECURITY.md` |
| Software identifiable / inventory | Plugin version is declared in the header and changelog; an SBOM tool is shipped in the admin (Reports & Tools > SBOM) | `polski.php`, `changelog.txt`, SBOM admin page |
| Source available for audit | Free edition source is public and human-readable | `github.com/wppoland/polski` |

### 3.3 User-facing information (Annex II direction)

- `readme.txt` documents purpose, scope, external services contacted, and an explicit
  non-advice / non-compliance statement.
- The plugin states throughout that it provides tools and templates, not legal advice,
  and does not guarantee compliance.

---

## 4. Incident-reporting readiness (CRA Article 14)

The plugin ships an **incident register** designed around the CRA Article 14 reporting
cadence, so that an in-scope manufacturer would have the tooling to track and meet the
deadlines. It does **not** auto-report to any authority; it is an internal record.

- Admin screen: Reports & Tools > CRA incidents (`src/Admin/CRAIncidentsPage.php`).
- Domain model: `src/CRA/` — `IncidentService`, `Incident`, repository, and enums.
- Incident kinds (`IncidentKind`): **actively-exploited vulnerability**, **security
  incident**, **near miss** — mapping to the Article 14(1)/(2) categories.
- Severity (`Severity`): critical / high / medium / low.
- Status lifecycle (`IncidentStatus`): open -> notified -> under investigation ->
  resolved / false-positive.
- The model is documented against the Article 14 timeline: **24h early warning,
  72h notification, 14-day final report** (see the doc comment in
  `src/CRA/Enum/IncidentStatus.php`).

**For counsel:** confirm whether the Article 14 reporting duty applies here at all,
to whom (ENISA / national CSIRT single reporting platform), and whether an internal
register is sufficient or a registered reporting channel is required.

---

## 5. Evidence index

| Area | File(s) |
|---|---|
| Security policy / CVD / CRA readiness narrative | `SECURITY.md` |
| Incident register (Article 14 tooling) | `src/Admin/CRAIncidentsPage.php`, `src/CRA/**` |
| Readiness service | `src/Service/CRAReadinessService.php` |
| Client-IP hardening | `src/Util/ClientIp.php`, `src/Service/GuestWithdrawalService.php` |
| Consent-log anonymisation | `src/Repository/ConsentLogRepository.php` |
| Data removal on uninstall | `uninstall.php` |
| Schema migrations | `src/Migrator.php` |
| External-services + non-advice disclosure | `readme.txt` |

---

## 6. Open questions for counsel

These are the decisions we need from legal review. We are **not** taking a position on
any of them in this document.

1. **Applicability.** Is a WordPress/WooCommerce plugin a "product with digital
   elements made available on the market in the course of a commercial activity"
   within the CRA, given it runs inside a host CMS on the user's own server?
2. **Free / open-source treatment.** Does the CRA's treatment of free and open-source
   software exempt the **free** GPL edition (published at no charge), and does the
   **PRO** paid edition change that analysis — and if so, only for PRO?
3. **Manufacturer determination.** For each edition, is WPPoland a CRA "manufacturer",
   and what is the effect of Freemius acting as merchant of record for PRO?
4. **Classification.** If in scope, is the product default-class or does any function
   pull it into a higher class (Annex III/important or critical), affecting the
   conformity-assessment route?
5. **Conformity assessment / declaration / CE.** If in scope, which conformity-
   assessment procedure applies, is an EU declaration of conformity required, and how
   does CE marking apply to software delivered as a download?
6. **Article 14 reporting.** Does the active-exploitation / severe-incident reporting
   duty apply, to which authority, on what trigger, and is the shipped internal
   register adequate or must a formal reporting channel be registered?
7. **Support-period / EOL obligations.** What security-update support period must be
   stated, and how should end-of-support be communicated to users?
8. **Timeline.** Which CRA obligations apply from which date for this product, given
   the staged application of the Regulation.

---

*Maintainers: keep section 3-5 in sync with the code. Update `SECURITY.md` first; this
brief summarises it for legal review.*
