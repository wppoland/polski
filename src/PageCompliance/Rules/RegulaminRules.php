<?php

declare(strict_types=1);

namespace Polski\PageCompliance\Rules;

use Polski\PageCompliance\Enum\Severity;
use Polski\PageCompliance\Model\CheckRule;

defined('ABSPATH') || exit;

/**
 * Required elements checker for the online shop regulations (regulamin)
 * under Ustawa o swiadczeniu uslug droga elektroniczna (U.s.u.d.e.) and
 * Ustawa o prawach konsumenta.
 *
 * The rules cover:
 * - service-provider identity
 * - scope of services
 * - technical requirements
 * - contract formation and termination
 * - payment and delivery methods
 * - withdrawal right (14 days)
 * - complaints procedure
 * - reference to the privacy policy / RODO
 * - amendment procedure
 * - out-of-court dispute resolution
 */
final class RegulaminRules
{
    /**
     * @return list<CheckRule>
     */
    public static function all(): array
    {
        return [
            new CheckRule(
                id: 'provider_identity',
                label: __('Service provider identity', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'nip',
                    'regon',
                    'sprzedawca',
                    'uslugodawca',
                    'service provider',
                ],
                hint: __('Identify the service provider by company name, NIP and REGON.', 'polski'),
            ),
            new CheckRule(
                id: 'provider_contact',
                label: __('Provider contact (address, email)', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'adres siedziby',
                    'adres korespondencyjny',
                    '@',
                    'e-mail',
                    'ul.',
                ],
                hint: __('Provide a registered office address and an email address for customer contact.', 'polski'),
            ),
            new CheckRule(
                id: 'service_scope',
                label: __('Scope of services offered', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'rodzaj uslug',
                    'zakres uslug',
                    'przedmiot regulaminu',
                    'rodzaje i zakres uslug',
                    'scope of services',
                ],
                hint: __('Describe what products or services the store sells and to whom.', 'polski'),
            ),
            new CheckRule(
                id: 'technical_requirements',
                label: __('Technical requirements for using the service', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'wymagania techniczne',
                    'przegladarka internetowa',
                    'przegladarki internetowe',
                    'technical requirements',
                ],
                hint: __('List the technical requirements (browser, internet, optional: account).', 'polski'),
            ),
            new CheckRule(
                id: 'order_placement',
                label: __('How orders are placed', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'zlozenie zamowienia',
                    'zlozenia zamowienia',
                    'proces zamowienia',
                    'skladanie zamowienia',
                    'placing an order',
                ],
                hint: __('Describe the ordering flow (cart, checkout, order confirmation).', 'polski'),
            ),
            new CheckRule(
                id: 'payment_methods',
                label: __('Payment methods', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'sposob platnosci',
                    'sposoby platnosci',
                    'formy platnosci',
                    'metody platnosci',
                    'payment methods',
                ],
                hint: __('List accepted payment methods (cards, BLIK, bank transfer, pay-on-delivery).', 'polski'),
            ),
            new CheckRule(
                id: 'delivery_methods',
                label: __('Delivery methods and times', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'sposob dostawy',
                    'sposoby dostawy',
                    'formy dostawy',
                    'czas dostawy',
                    'delivery methods',
                ],
                hint: __('List delivery options with indicative times (couriers, paczkomat, personal pickup).', 'polski'),
            ),
            new CheckRule(
                id: 'withdrawal_right',
                label: __('14-day withdrawal right (consumer)', 'polski'),
                severity: Severity::Required,
                patterns: [
                    '14 dni',
                    'czternastu dni',
                    'odstapic od umowy',
                    'odstapienie od umowy',
                    'prawo odstapienia',
                    '14-day',
                ],
                hint: __('Explicitly state the consumer 14-day right of withdrawal and the return procedure.', 'polski'),
            ),
            new CheckRule(
                id: 'withdrawal_form',
                label: __('Withdrawal form availability', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'formularz odstapienia',
                    'oswiadczenie o odstapieniu',
                    'withdrawal form',
                ],
                hint: __('Reference the statutory withdrawal form (załącznik do ustawy o prawach konsumenta).', 'polski'),
            ),
            new CheckRule(
                id: 'complaints_procedure',
                label: __('Complaints procedure (reklamacje)', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'reklamacji',
                    'reklamacje',
                    'postepowanie reklamacyjne',
                    'complaints procedure',
                ],
                hint: __('Describe how a customer files a complaint and the timeline for response.', 'polski'),
            ),
            new CheckRule(
                id: 'privacy_reference',
                label: __('Reference to privacy policy / RODO', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'polityka prywatnosci',
                    'politce prywatnosci',
                    'polityce prywatnosci',
                    'rodo',
                    'gdpr',
                    'privacy policy',
                ],
                hint: __('Link to the privacy policy and note that RODO/GDPR obligations apply.', 'polski'),
            ),
            new CheckRule(
                id: 'amendments',
                label: __('Amendment procedure for the regulations', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'zmiany regulaminu',
                    'zmiana regulaminu',
                    'zmian regulaminu',
                    'amendments to these terms',
                ],
                hint: __('Explain how and with what notice the regulations can be amended.', 'polski'),
            ),
            new CheckRule(
                id: 'governing_law',
                label: __('Governing law', 'polski'),
                severity: Severity::Required,
                patterns: [
                    'prawo polskie',
                    'prawo wlasciwe',
                    'prawem wlasciwym',
                    'governed by',
                ],
                hint: __('State the governing law (typically Polish law for PL-based stores).', 'polski'),
            ),
            new CheckRule(
                id: 'odr_platform',
                label: __('ODR platform (out-of-court dispute)', 'polski'),
                severity: Severity::Recommended,
                patterns: [
                    'ec.europa.eu/consumers/odr',
                    'platforma odr',
                    'pozasadowego rozwiazywania',
                    'odr platform',
                ],
                hint: __('Link to the EU ODR platform and mention other out-of-court dispute options.', 'polski'),
            ),
            new CheckRule(
                id: 'effective_date',
                label: __('Effective date of the regulations', 'polski'),
                severity: Severity::Recommended,
                patterns: [
                    'obowiazuje od',
                    'wchodzi w zycie',
                    'effective date',
                ],
                hint: __('Publish the effective date so customers know which version they agreed to.', 'polski'),
            ),
        ];
    }
}
