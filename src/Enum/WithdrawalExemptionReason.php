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
            self::Custom => __('Other (custom reason)', 'polski'),
            self::CustomMade => __('Made to the consumer\'s specification or clearly personalised (Article 38(3))', 'polski'),
            self::Perishable => __('Liable to deteriorate or expire rapidly (Article 38(4))', 'polski'),
            self::Sealed => __('Sealed for health protection or hygiene reasons (Article 38(5))', 'polski'),
            self::Inseparable => __('Inseparably mixed with other items after delivery (Article 38(6))', 'polski'),
            self::Alcohol => __('Alcoholic beverages (Article 38(7))', 'polski'),
            self::SealedMedia => __('Sealed audio or video recordings or computer software (Article 38(9))', 'polski'),
            self::DigitalContent => __('Digital content supplied before the withdrawal period ends (Article 38(13))', 'polski'),
        };
    }

    /**
     * Short label suitable for badges / product listings.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Custom => __('Other', 'polski'),
            self::CustomMade => __('Made to order', 'polski'),
            self::Perishable => __('Perishable', 'polski'),
            self::Sealed => __('Sealed', 'polski'),
            self::Inseparable => __('Inseparably mixed', 'polski'),
            self::Alcohol => __('Alcohol', 'polski'),
            self::SealedMedia => __('Recording or software', 'polski'),
            self::DigitalContent => __('Digital content', 'polski'),
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
