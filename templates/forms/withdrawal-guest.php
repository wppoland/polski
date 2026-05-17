<?php
/**
 * Authenticated guest withdrawal form (revealed after redeeming the magic-link token).
 *
 * Accessible: live notice region, labelled fields, single descriptive submit, max-width
 * ~65ch to reduce cognitive load.
 *
 * @var \WC_Order $polski_order
 * @var string    $polski_token
 * @var string    $polski_email
 * @var string    $polski_nonce
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<section
    class="polski-withdrawal-guest-form"
    aria-labelledby="polski-withdrawal-guest-title"
    lang="pl"
    style="max-width: 65ch;"
>
    <h2 id="polski-withdrawal-guest-title">
        <?php
        printf(
            /* translators: %s = order number */
            esc_html__('Odstąpienie od umowy — zamówienie #%s', 'polski'),
            esc_html($polski_order->get_order_number()),
        );
        ?>
    </h2>

    <p>
        <?php
        printf(
            /* translators: %s = email address */
            esc_html__('Składasz oświadczenie na rzecz adresu: %s', 'polski'),
            '<strong>' . esc_html($polski_email) . '</strong>',
        );
        ?>
    </p>

    <p style="color:#475569;">
        <?php esc_html_e('Po wysłaniu formularza otrzymasz e-mail potwierdzający z numerem deklaracji oraz podsumowaniem zamówienia.', 'polski'); ?>
    </p>

    <form method="post" action="" novalidate>
        <p>
            <label for="polski_withdrawal_reason">
                <?php esc_html_e('Powód odstąpienia (opcjonalnie)', 'polski'); ?>
            </label>
            <textarea
                id="polski_withdrawal_reason"
                name="polski_withdrawal_reason"
                rows="4"
                style="width: 100%; max-width: 60ch;"
                aria-describedby="polski_withdrawal_reason_help"
            ></textarea>
            <small id="polski_withdrawal_reason_help" style="display:block; color:#475569;">
                <?php esc_html_e('Powód nie jest wymagany — odstąpienie nie wymaga uzasadnienia.', 'polski'); ?>
            </small>
        </p>

        <p>
            <input type="hidden" name="polski_guest_nonce" value="<?php echo esc_attr($polski_nonce); ?>">
            <button
                type="submit"
                name="polski_guest_submit"
                value="1"
                class="button button-primary"
            >
                <?php esc_html_e('Złóż oświadczenie o odstąpieniu', 'polski'); ?>
            </button>
        </p>
    </form>
</section>
