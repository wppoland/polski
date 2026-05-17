<?php

declare(strict_types=1);
namespace Polski\Enum;

defined('ABSPATH') || exit;

/**
 * Pre-defined exemption categories matching Polish Ustawa o prawach konsumenta
 * Art. 38 (transposing Article 16 of Directive 2011/83/EU, as amended by
 * 2023/2673). Storefronts can mark a product or a product category with one of
 * these reasons so the withdrawal flow excludes those items.
 */
enum WithdrawalExemptionReason: string
{
    case Custom = 'custom';
    case CustomMade = 'art38_3';        // Personalised / made to consumer specification.
    case Perishable = 'art38_4';        // Short shelf life / quickly perishable.
    case Sealed = 'art38_5';            // Sealed for health/hygiene reasons.
    case Inseparable = 'art38_6';       // Inseparably mixed with other items.
    case Alcohol = 'art38_7';           // Alcoholic beverages, agreed price + delivered later.
    case SealedMedia = 'art38_9';       // Sealed audio/video recordings, software.
    case DigitalContent = 'art38_13';   // Digital content with prior consent (Art. 16(m)).

    public function label(): string
    {
        return match ($this) {
            self::Custom => __('Inne (własne uzasadnienie)', 'polski'),
            self::CustomMade => __('Produkt na zamówienie indywidualne / personalizowany (Art. 38 pkt 3)', 'polski'),
            self::Perishable => __('Produkt szybko psujący się / krótki termin przydatności (Art. 38 pkt 4)', 'polski'),
            self::Sealed => __('Produkt zapieczętowany ze względu na ochronę zdrowia lub higieniczny (Art. 38 pkt 5)', 'polski'),
            self::Inseparable => __('Produkt nieoddzielnie połączony z innymi (Art. 38 pkt 6)', 'polski'),
            self::Alcohol => __('Napoje alkoholowe (Art. 38 pkt 7)', 'polski'),
            self::SealedMedia => __('Nagrania audio/wideo lub oprogramowanie w zapieczętowanym opakowaniu (Art. 38 pkt 9)', 'polski'),
            self::DigitalContent => __('Treści cyfrowe spełniane przed upływem terminu (Art. 38 pkt 13)', 'polski'),
        };
    }

    /**
     * Short label suitable for badges / product listings.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Custom => __('Inne', 'polski'),
            self::CustomMade => __('Na zamówienie', 'polski'),
            self::Perishable => __('Krótki termin', 'polski'),
            self::Sealed => __('Zapieczętowany', 'polski'),
            self::Inseparable => __('Nieoddzielnie połączony', 'polski'),
            self::Alcohol => __('Alkohol', 'polski'),
            self::SealedMedia => __('Nagranie/Software', 'polski'),
            self::DigitalContent => __('Treści cyfrowe', 'polski'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[] = [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }
        return $choices;
    }
}
