<?php

declare(strict_types=1);

namespace Polski\PageCompliance\Rules;

use Polski\PageCompliance\Enum\Severity;
use Polski\PageCompliance\Model\CheckRule;

defined('ABSPATH') || exit;

/**
 * RODO / GDPR Article 13 element checker for privacy policy pages.
 *
 * Each rule carries a list of Polish and English pattern variants; the service
 * strips diacritics and lowercases before matching. A rule passes when at
 * least one pattern appears in the page content.
 *
 * Rules are based on RODO Art. 13 (controller identity, purposes, legal basis,
 * recipients, retention, subject rights, supervisory authority) and common
 * Polish privacy-policy conventions.
 */
final class PrivacyPolicyRules
{
    /**
     * @return list<CheckRule>
     */
    public static function all(): array
    {
        return [
            new CheckRule(
                id: 'controller_identity',
                label: __('Controller identity and contact', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'administratorem danych osobowych',
                    'administrator danych',
                    'data controller',
                ],
                hint: __('Add a paragraph identifying the data controller by company name, address and contact details.', 'polski'),
            ),
            new CheckRule(
                id: 'contact_channel',
                label: __('Contact channel (email or form)', 'polski'),
                severity: Severity::Required,
                patterns: [
                    '@',
                    'formularz kontaktowy',
                    'contact form',
                    'e-mail:',
                    'email:',
                ],
                hint: __('Provide an email address or a link to a contact form so users can reach the controller about their data.', 'polski'),
            ),
            new CheckRule(
                id: 'processing_purposes',
                label: __('Purposes of processing', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'cel przetwarzania',
                    'cele przetwarzania',
                    'w celu',
                    'purpose of processing',
                    'processing purposes',
                ],
                hint: __('State why you process personal data (e.g. order fulfilment, marketing, legal obligations).', 'polski'),
            ),
            new CheckRule(
                id: 'legal_basis',
                label: __('Legal basis under RODO Art. 6', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'art. 6 ust. 1',
                    'art. 6 ust.1',
                    'podstawa prawna',
                    'article 6',
                    'legal basis',
                ],
                hint: __('Reference the legal basis (Art. 6(1)(a) consent, (b) contract, (c) legal obligation, (f) legitimate interest).', 'polski'),
            ),
            new CheckRule(
                id: 'retention_period',
                label: __('Retention period', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'okres przechowywania',
                    'przechowywane przez',
                    'retention period',
                    'retained for',
                    'przez okres',
                ],
                hint: __('Indicate how long personal data will be retained (fixed period or criteria).', 'polski'),
            ),
            new CheckRule(
                id: 'recipients',
                label: __('Data recipients (processors)', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'odbiorcy danych',
                    'odbiorca danych',
                    'podmioty przetwarzajace',
                    'podmiotom przetwarzajacym',
                    'recipients of data',
                    'data processors',
                ],
                hint: __('List categories of recipients (shipping carriers, payment providers, accounting, IT vendors).', 'polski'),
            ),
            new CheckRule(
                id: 'subject_right_access',
                label: __('Right of access', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'prawo dostepu',
                    'prawo do dostepu',
                    'right of access',
                ],
                hint: __('State that users can request access to their personal data.', 'polski'),
            ),
            new CheckRule(
                id: 'subject_right_rectify',
                label: __('Right to rectification', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'sprostowania',
                    'prawo sprostowania',
                    'right to rectification',
                ],
                hint: __('State the right to correct inaccurate personal data.', 'polski'),
            ),
            new CheckRule(
                id: 'subject_right_erasure',
                label: __('Right to erasure (to be forgotten)', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'usuniecia',
                    'prawo do usuniecia',
                    'prawo do bycia zapomnianym',
                    'right to erasure',
                    'right to be forgotten',
                ],
                hint: __('State the right to request deletion of personal data.', 'polski'),
            ),
            new CheckRule(
                id: 'subject_right_restrict',
                label: __('Right to restriction of processing', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'ograniczenia przetwarzania',
                    'prawo ograniczenia',
                    'restriction of processing',
                ],
                hint: __('State the right to restrict processing in specific situations.', 'polski'),
            ),
            new CheckRule(
                id: 'subject_right_portability',
                label: __('Right to data portability', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'przenoszenia danych',
                    'prawo przenoszenia',
                    'data portability',
                ],
                hint: __('State the right to receive data in a structured format.', 'polski'),
            ),
            new CheckRule(
                id: 'subject_right_object',
                label: __('Right to object', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'prawo sprzeciwu',
                    'prawo do sprzeciwu',
                    'wniesienia sprzeciwu',
                    'right to object',
                ],
                hint: __('State the right to object to processing (especially for marketing).', 'polski'),
            ),
            new CheckRule(
                id: 'consent_withdraw',
                label: __('Right to withdraw consent', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'cofniecia zgody',
                    'cofnac zgode',
                    'wycofania zgody',
                    'withdraw consent',
                ],
                hint: __('State that consent can be withdrawn at any time where processing is based on consent.', 'polski'),
            ),
            new CheckRule(
                id: 'supervisory_authority',
                label: __('Right to lodge complaint with UODO', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'prezesa urzedu ochrony danych osobowych',
                    'prezes urzedu ochrony danych osobowych',
                    'uodo',
                    'skargi do organu',
                    'supervisory authority',
                ],
                hint: __('Reference the Polish supervisory authority (Prezes UODO) as the complaint recipient.', 'polski'),
            ),
            new CheckRule(
                id: 'automated_decision',
                label: __('Automated decision-making / profiling disclosure', 'polski'),
                severity: Severity::Recommended,
                patterns: [
                    'zautomatyzowane podejmowanie decyzji',
                    'profilowanie',
                    'automated decision',
                    'profiling',
                ],
                hint: __('Disclose whether automated decision-making or profiling occurs (Art. 13(2)(f)).', 'polski'),
            ),
            new CheckRule(
                id: 'international_transfer',
                label: __('International data transfers', 'polski'),
                severity: Severity::Recommended,
                patterns: [
                    'poza europejski obszar gospodarczy',
                    'poza eog',
                    'przekazywanie do panstw trzecich',
                    'international transfer',
                    'outside the eea',
                ],
                hint: __('If you transfer data outside the EEA (e.g. US-based cloud services), disclose the safeguards used.', 'polski'),
            ),
            new CheckRule(
                id: 'dpo_optional',
                label: __('DPO contact (if appointed)', 'polski'),
                severity: Severity::Optional,
                patterns: [
                    'inspektor ochrony danych',
                    'iod',
                    'data protection officer',
                ],
                hint: __('If you have appointed a Data Protection Officer, publish their contact details.', 'polski'),
            ),
        ];
    }
}
