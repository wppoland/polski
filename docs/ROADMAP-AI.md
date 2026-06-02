# ROADMAP-AI.md

AI and agentic direction for Polski for WooCommerce (free) and Polski Pro for WooCommerce (premium).

Built on the WordPress 6.9 Abilities API and the WordPress 7.0 AI Client / AI Connectors stack, with a bring-your-own merchant key model. This document is the planning reference for the AI/agentic layer only. It does not change existing compliance modules except where explicitly noted.

Branding note: this project is WPPoland. Do not write "WP Poland". Do not use the long dash character anywhere in this file or the code/docs it describes.

---

## 1. Vision

Polski becomes AI-native and safe for agentic commerce in a regulated market (Poland and the wider EU).

The thesis:

- Shops in Poland and the EU run on rules: pricing transparency, return rights, business identification, document obligations. Generic AI commerce tooling ignores these rules. Polski already encodes them.
- The next wave of buying and selling is mediated by AI agents (chat assistants, shopping agents, MCP clients, automation runners). Those agents need two things from a store: a machine-readable description of what the store offers and what rules apply, and a safe, governed way to take actions.
- Polski is positioned to be the layer that lets a Polish or EU WooCommerce store expose itself to AI agents in a structured way, and lets the merchant operate their store with AI assistance, without giving up control or sending an agent off to do something irreversible.

What "AI-native" means here:

- The store publishes structured, agent-readable surfaces by default (already shipping: llms.txt manifest, Markdown content negotiation, Abilities API read operations).
- Agentic actions (write operations) are possible but always governed: capability check, dry-run or preview, human approval where it matters, full audit trail, and hard guards on price and pricing-history correctness.
- AI features never require our infrastructure or our API key. The merchant brings their own key to their own provider. Our role is the adapter, the guardrails, and the domain knowledge.

What "safe" means here:

- No autonomous price changes, refunds, or document issuance without an explicit, logged human decision, unless the merchant deliberately opts a specific narrow action into auto mode.
- Pricing-history integrity is protected: any AI-assisted price change passes through the existing pricing-history recording path so the lowest-prior-price display obligation is not broken.
- We describe what the plugin does in factual terms. We do not claim the plugin makes a store legally compliant. The plugin helps merchants implement specific requirements; legal compliance is the merchant's responsibility with their advisor.

---

## 2. Foundation layer (free, already shipping or shipping next)

The foundation is the part that must exist before any agentic feature is useful. It lives in the free plugin so the whole ecosystem benefits and so the platform is broadly validated.

### 2.1 Abilities provider (WordPress 6.9 Abilities API)

Already present: `src/Service/AbilitiesService.php` registers categories and abilities when `wp_register_ability` exists, with graceful fallback when it does not.

Categories today: `polski/withdrawal`, `polski/legal`, `polski/compliance`, `polski/shop`. Pro adds its own surface via `ProAbilitiesService` (refund, pdf, audit, report).

Foundation goals:

- Treat the Abilities API as the canonical action and read interface for agents and MCP clients. Everything an agent can know or do about the store should be reachable as an ability, with JSON Schema input and output.
- Keep read abilities in free (eligibility checks, business info, compliance reads, content surfaces). Reserve governed write abilities for Pro (section 4).
- Always feature-detect. If the Abilities API is not available on the running WordPress, the plugin keeps working and simply does not advertise the abilities endpoint.
- Advertise the abilities endpoint (`/wp-json/wp-abilities/v1/abilities`) in `llms.txt` only when the API is present.

### 2.2 AI Client adapter (WordPress 7.0 AI Client / AI Connectors)

Already present: `src/AI/AiClient.php` is a thin facade over `wp_ai_client_prompt()` that degrades gracefully when the API is unavailable, plus `src/AI/WithdrawalReasonClassifier.php` as a first consumer.

Foundation goals:

- All AI text/structured generation goes through one internal facade (`AiClient`) so we never scatter provider calls across services.
- The facade is provider-agnostic. It relies on the WordPress 7.0 AI Client and whatever connector/provider the merchant has configured. We do not embed a provider SDK and we do not store provider credentials. Credentials are owned by the connector/provider plugin the merchant installs and configures.
- The facade exposes a small surface: availability check (`isAvailableForText()` style), a structured-output call that takes an instruction, a prompt, and a JSON Schema, and returns validated structured data or a typed failure.
- Hard rule: no `wp_remote_post` / `wp_remote_get` to any AI provider from inside Polski. The connector owns the HTTP call and the key. This keeps us out of the credential-handling and PII-egress business and keeps WordPress.org review clean.

### 2.3 Graceful fallback contract

Every AI/agentic code path must answer "what happens on an older WordPress, or with no provider configured, or with no merchant key" before it ships.

- Abilities API missing: abilities not registered, endpoint not advertised, plugin fully functional.
- AI Client missing or no provider: AI-optional features show a clear "configure an AI provider to enable this" state and the rest of the feature (manual path) still works.
- No merchant key: free AI-optional features stay dormant and never error; they never fall back to a Polski-hosted endpoint because none exists.

This contract is the same graceful-fallback discipline already used across the codebase (HPOS detection, Abilities detection, sodium detection, third-party class checks).

---

## 3. Free features

Constraint that shapes everything in this section: WordPress.org guidelines. The free plugin must be fully functional on its own. Pro is a separate plugin. Free features must work without our key and without our servers. AI is optional in free and, when used, runs on the merchant's own provider via their own key. No phoning home, no required external service.

### 3.1 Agent-readable store surfaces (ship and harden)

Already shipping in free:

- `llms.txt` manifest at site root (`AIFeedLlmsTxtHooks`, `LlmsTxtService`): legal, shop, and category sections, plus the abilities endpoint link when available.
- Markdown content negotiation (`AIFeedHooks`, `RequestNegotiator`, `MarkdownConverter`, `PostMarkdownBuilder`, `ProductMarkdownBuilder`): serve clean Markdown with YAML front-matter for posts, pages, and products when an agent requests `text/markdown` or `?output_format=md`. Product front-matter already carries SKU, GTIN, price, categories, pricing-history context, delivery time, weight, dimensions, and responsible-person info.

Roadmap for this surface:

- Extend product and shop Markdown to cover the structured facts an agent needs to answer buyer questions correctly: stock state, variation matrix summary, tax-inclusive vs net price clarity, return-window eligibility summary, and business identification block.
- Keep all of this filterable (the `polski/ai_feed/*` filters already exist) so themes and other plugins can extend the agent view without forking.
- Make sure every surface is read-only and side-effect-free. These are GET responses for agents and crawlers.

### 3.2 Read abilities for agents (free)

Expose the safe, read-only knowledge of the store as abilities so any MCP client or agent can query it:

- Business identification (NIP, REGON, address, contact) via `polski/shop`.
- Return/withdrawal eligibility checks and window math via `polski/withdrawal` (read side).
- Compliance reads (presence and basic audit of required pages, cookie banner state) via `polski/compliance`.
- Document/legal metadata reads via `polski/legal` (what documents exist, not generation).

Each read ability declares a permission callback. Public-safe reads can be open; anything tied to a specific order or customer requires the appropriate capability or a customer-scoped check.

### 3.3 AI-optional free helpers (merchant key)

These are genuinely useful on day one without AI, and better with the merchant's own provider key:

- Withdrawal reason classification (already in code via `WithdrawalReasonClassifier`): free-text reason mapped to a category, stored with confidence. Manual categorization remains available when no provider is configured.
- Content tidy-up for the agent surface: optionally let the merchant generate a short, factual product summary for the Markdown front-matter using their own key. Without a key, the surface uses the existing description.

All of these are gated by a module toggle in the free admin (`ModulesPage::isModuleEnabled`) and by provider availability. None are required for the plugin to function.

### 3.4 What free deliberately does not include

- No bulk AI automation (Pro).
- No admin AI assistant / copilot (Pro).
- No write abilities that change orders, prices, refunds, or documents (Pro, governed).

This split keeps the free plugin a clean, fully functional WordPress.org citizen and reserves the operationally sensitive, supported, governed capabilities for Pro.

---

## 4. PRO features (governed agentic commerce)

Pro is where AI does work for the merchant and where agents are allowed to take actions, always under guardrails. Pro features are license-gated and seal-gated (existing `LicenseManager` + `SecureGate` flow); when the gate is closed, none of these features register.

### 4.1 Bulk AI automation

- Bulk product description and summary generation using the merchant's own provider key, building on the existing `AiDescriptionService` pattern.
- Bulk classification and enrichment jobs (categorize, tag, fill missing structured fields for the agent surface) run as background batches with progress, cancellation, and a per-item before/after diff.
- Every bulk job is preview-first: the merchant sees proposed changes and approves (all, none, or per item) before anything is written. Jobs are logged.

### 4.2 Store Copilot (admin assistant)

An in-admin assistant scoped to the store, powered by the merchant's provider key and grounded in the store's own data through the read abilities.

- Answers operational questions ("which orders are eligible for return this week", "what is my business block", "which products are missing GTIN") by calling read abilities, not by hallucinating.
- Proposes actions but never executes them silently. A proposed action is rendered as a preview with the exact ability call and arguments, and requires a click to run.
- Runs entirely client-to-provider via the configured connector. Polski supplies the grounding data (via abilities) and the guardrails, not the model and not the key.

### 4.3 Agent-facing and MCP-facing write abilities (governed)

This is the core R&D-sensitive surface: letting an external agent or MCP client take real actions in a Polish/EU store safely.

Write abilities (Pro, examples): create a withdrawal/return declaration, issue or regenerate a return PDF, process a governed refund, transition an order status, adjust a price within bounds, regenerate a legal annex. These extend the existing Pro abilities surface (`ProAbilitiesService`).

Every governed write ability must enforce all of the following guardrails. This is a hard contract, not a recommendation:

1. Capability check. The ability's permission callback verifies the acting identity has the required WordPress capability (for example `manage_woocommerce`, `edit_shop_orders`). No capability, no call.
2. Dry-run / preview. The ability supports a `dry_run` mode that returns exactly what would change (before/after, computed values, affected records) without writing. Clients are expected to dry-run first.
3. Human approval. State-changing effects above a configurable threshold (refunds, price changes, document issuance, status transitions that trigger customer-facing effects) require an explicit human approval step recorded in the system. Auto-execute is opt-in per action type and off by default.
4. Audit trail. Every governed call (dry-run and real) is recorded: who/what called, arguments, dry-run vs committed, before/after, timestamp, result. This builds on the existing withdrawal audit repository pattern and extends it to a general agentic audit log.
5. Price and pricing-history guard. Any price-affecting action routes through the existing pricing-history recording path so the lowest-prior-price obligation continues to be honored. An AI or agent cannot set a price in a way that bypasses pricing-history capture. Out-of-bounds price moves (configurable percentage and floor/ceiling) are rejected, not silently clamped.

Design intent: an agent can do useful, real work (file a return, issue the correct document, refund within policy) but it physically cannot do the dangerous things (silent irreversible refund, price change that breaks pricing-history display, bulk destructive change with no record).

### 4.4 Pro safety posture summary

- Default-deny: nothing auto-executes unless the merchant turns on auto mode for a specific narrow action.
- Reversibility bias: prefer actions that are previewable and recordable; flag irreversible ones loudly.
- No legal guarantee language anywhere in the UI or docs. We describe what the action does and which obligation it helps implement, not that it makes the merchant compliant.

---

## 5. Universal Commerce Protocol / agentic-commerce layer (R&D-novel)

This is the innovation layer and the part framed for funded R&D (EIC, Sciezka SMART). It sits on top of the foundation and Pro guardrails.

### 5.1 What it is

A protocol-level capability that lets any conforming AI agent or commerce protocol client:

- Discover what a Polish/EU store offers and which rules apply (via the agent-readable surfaces and read abilities).
- Negotiate and execute a transaction-related action through governed write abilities, with the guardrails of section 4 enforced server-side regardless of which client calls.
- Do so in a way that respects the specific obligations of the Polish and EU market (pricing transparency, return rights, business identification, document obligations), which generic agentic-commerce protocols do not encode.

The novelty is not "an AI writes product copy". The novelty is a governed, market-aware action layer that makes agentic commerce safe in a regulated jurisdiction: the store exposes capabilities to autonomous agents while the platform guarantees that regulated invariants (pricing-history integrity, approval and audit on sensitive actions, capability scoping) hold no matter how the agent behaves.

### 5.2 TRL framing

- The free plugin is the TRL 9 validated, in-market platform. It is shipping, used in real stores, and de-risks the project: distribution, WooCommerce integration, the compliance domain model, the agent-readable surfaces, and the read-ability layer all already work in production. This is the proven base that the funded work stands on.
- The agentic action layer (governed write abilities, the Store Copilot grounded on store data, and the Universal Commerce Protocol conformance) is the TRL 5 to 8 innovation. It moves from validated components and lab integration (TRL 5) through demonstration in an operational store environment (TRL 6 to 7) to a market-ready governed agentic-commerce capability (TRL 8). This is the part that is genuinely new and is the subject of the funded R&D.

The framing for funders: the platform is real and adopted, which removes the usual "will anyone use it" risk; the funded innovation is the hard, novel, defensible part (safe agentic commerce for a regulated market), and it has a built-in distribution channel the moment it is ready.

### 5.3 Standards posture

- Build on open, documented surfaces: the WordPress Abilities API, the WordPress AI Client/Connectors, llms.txt, and Markdown content negotiation.
- Track emerging agentic-commerce and agent-tool protocols and aim for conformance rather than a proprietary lock-in. The differentiator is the regulated-market guardrail layer, not a closed protocol.

---

## 6. Phased milestones

Each feature ships with a version bump, and the landing pages and docs are updated at the moment the feature ships, never deferred and never batched. Every PHP change passes WPCS (phpcs) and WordPress Plugin Check before it is considered done. Stay compatible with the newest WordPress (Abilities API on 6.9, AI Client/Connectors on 7.0, including RC/beta) with graceful fallback on older versions.

### MVP (foundation solid, read-only agentic, AI-optional helpers)

Free:

- Harden the agent-readable surfaces (richer product/shop Markdown facts; all filterable).
- Complete the read-ability set (shop, withdrawal read, compliance read, legal metadata) with permission callbacks.
- Stabilize the `AiClient` facade and the graceful-fallback contract across all AI paths.
- Withdrawal reason classification and optional content tidy-up, both merchant-key, both module-gated.

Version notes: free patch/minor bumps per feature (for example a minor bump for the new read-ability set, patches for surface enrichments). Update free landing page and docs per feature.

Exit criteria: an external MCP client can read everything safely; no AI feature breaks on old WP or with no provider; gates green.

### v1 (governed write abilities + Store Copilot, Pro)

Pro:

- Ship the governed write-ability framework with all five guardrails enforced server-side (capability, dry-run, approval, audit, price/pricing-history guard).
- Ship the first concrete governed write abilities (withdrawal create, return PDF issue/regenerate, governed refund, order status transition).
- Ship the Store Copilot grounded on read abilities, proposing-not-executing by default.
- Ship bulk AI automation with preview-first apply.

Version notes: Pro minor bump for the write-ability framework (new module), Pro patches for each additional concrete ability, Pro minor for Store Copilot. Update Pro landing page and docs per feature, immediately.

Exit criteria: an agent can file a return and issue the correct document end-to-end, with a full audit trail, and cannot bypass pricing-history or execute a sensitive action without approval; gates green.

### v2 (Universal Commerce Protocol conformance, the funded innovation surface)

Pro (and any necessary free foundation extension):

- Generalize the audit log into a full agentic-action ledger.
- Add price-adjust and legal-annex regeneration as governed abilities, with bounds enforcement.
- Implement conformance to the target agentic-commerce/agent-tool protocol(s) on top of the governed abilities, so any conforming client gets the same server-side guardrails.
- Demonstrate the end-to-end regulated agentic-commerce flow in an operational store (the TRL 6 to 8 demonstration).

Version notes: Pro minor/major depending on scope (protocol conformance is a new module, likely a major or significant minor). Free foundation changes get their own bumps. Landing pages and docs updated per feature.

Exit criteria: a third-party conforming agent completes a governed transaction-related action against a Polski Pro store with all regulated invariants provably held; this is the demonstrable funded innovation.

---

## 7. Project constraints (apply to every item above)

These are non-negotiable and override convenience.

- Newest-WP compatibility with graceful fallback. Target the latest WordPress (Abilities API 6.9, AI Client/Connectors 7.0, including RC/beta). Every AI/agentic path feature-detects and degrades cleanly on older WordPress and with no provider/key.
- Bump versions per feature. Patch for small features, minor or major for new modules. Bump the plugin version number whenever a feature is added.
- Update landing pages and docs immediately, per feature. Update the wppoland.com landing content and the docs the moment a feature ships. Do this per feature, never defer, never batch. Documentation lives alongside each new module/service.
- Gates before done. Run phpcs (WPCS) and WordPress Plugin Check after every PHP change and fix issues before marking work complete.
- No competitor names. Never mention benchmark sources or other companies in code, docs, changelogs, or commit messages.
- No legal-compliance guarantees. Never claim the plugin ensures legal compliance. Describe what features do and which obligation they help implement; legal responsibility stays with the merchant.
- Branding. Always WPPoland, never "WP Poland". Contact email hello@wppoland.com with a proper subject when relevant.
- No long-dash character anywhere in the project.
- BYO merchant key, no phoning home. Polski never stores or transmits provider credentials and never makes the AI provider HTTP call itself; the WordPress AI Connector/provider the merchant installs owns the key and the call. No required Polski-hosted AI endpoint exists.
