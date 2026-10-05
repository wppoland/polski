<?php
/**
 * Shortcodes that take an id from page content must not show what the visitor
 * could not see anyway. [polski_withdrawal_form order_id=N] printed any order's
 * number, date and eligibility to anyone who could place a shortcode, and every
 * product shortcode with product=N rendered drafts and private products.
 *
 * This loads the real ShortcodeManager and ProductVisibility against stubbed
 * WordPress functions and asks both paths who gets through.
 *
 * Run: php tests/shortcode-access-check.php
 *
 * @package Polski
 */

namespace {
    define('ABSPATH', __DIR__);

    $GLOBALS['t_user'] = 0;
    $GLOBALS['t_caps'] = [];
    $GLOBALS['t_posts'] = [];

    class WC_Order
    {
        public function __construct(private int $id, private int $customer) {}
        public function get_id(): int { return $this->id; }
        public function get_customer_id(): int { return $this->customer; }
    }
    class WC_Product {}
    class WP_Post
    {
        public function __construct(public int $ID, public string $post_type, public string $post_status, public int $post_parent = 0) {}
    }

    function shortcode_atts(array $pairs, array $atts): array { return array_merge($pairs, array_intersect_key($atts, $pairs)); }
    function esc_html__(string $s): string { return $s; }
    function get_current_user_id(): int { return $GLOBALS['t_user']; }
    function current_user_can(string $cap, ...$args): bool
    {
        if ($cap === 'read_post') {
            return in_array('read_private_products', $GLOBALS['t_caps'], true);
        }
        return in_array($cap, $GLOBALS['t_caps'], true);
    }
    function wc_get_order(int $id) { return $id === 7 ? new WC_Order(7, 5) : ($id === 8 ? new WC_Order(8, 0) : false); }
    function wc_get_product(int $id) { return isset($GLOBALS['t_posts'][$id]) ? new WC_Product() : false; }
    function get_post(int $id) { return $GLOBALS['t_posts'][$id] ?? null; }
    function post_password_required($post): bool { return false; }

    require dirname(__DIR__) . '/src/Contract/HasHooks.php';
    require dirname(__DIR__) . '/src/Util/ProductVisibility.php';
    require dirname(__DIR__) . '/src/Shortcode/ShortcodeManager.php';

    $fail = 0;
    $assert = static function (string $label, bool $ok) use (&$fail): void {
        if (! $ok) {
            fwrite(STDERR, "FAIL {$label}\n");
            $fail = 1;
            return;
        }
        echo "ok   {$label}\n";
    };

    $sm = new Polski\Shortcode\ShortcodeManager();
    $hidden = '<p>We could not find that order.</p>';
    $form = static fn (int $id): string => $sm->withdrawalForm(['order_id' => (string) $id]);
    // Whether the order got past the access gate: the next step needs the
    // container, which the stub world does not have, so passing it throws.
    $passes = static function (int $id) use ($form, $hidden): bool {
        try {
            return $form($id) !== $hidden;
        } catch (\Throwable $e) {
            return true;
        }
    };

    $set = static function (int $user, array $caps): void { $GLOBALS['t_user'] = $user; $GLOBALS['t_caps'] = $caps; };

    $set(0, []);
    $assert('a logged-out visitor cannot see a customer order', ! $passes(7));
    $assert('a logged-out visitor cannot see a guest order (0 is not 0)', ! $passes(8));
    $assert('an unknown order gives the same answer', $form(99) === $hidden);
    $set(6, []);
    $assert('another customer cannot see the order', ! $passes(7));
    $set(5, []);
    $assert('the owner gets through', $passes(7));
    $set(2, ['edit_shop_order']);
    $assert('a user who can edit the order gets through', $passes(7));
    $set(3, ['manage_woocommerce']);
    $assert('a shop manager gets through', $passes(8));

    $resolve = (new ReflectionMethod($sm, 'resolveProduct'))->getClosure($sm);
    $GLOBALS['t_posts'] = [
        10 => new WP_Post(10, 'product', 'publish'),
        11 => new WP_Post(11, 'product', 'draft'),
        12 => new WP_Post(12, 'product', 'private'),
        13 => new WP_Post(13, 'product_variation', 'publish', 11),
    ];
    $set(0, []);
    $assert('a published product resolves', $resolve(['product' => '10']) instanceof WC_Product);
    $assert('a draft product does not resolve for a visitor', $resolve(['product' => '11']) === null);
    $assert('a private product does not resolve for a visitor', $resolve(['product' => '12']) === null);
    $assert('a variation of a draft does not resolve for a visitor', $resolve(['product' => '13']) === null);
    $set(1, ['read_private_products']);
    $assert('an editor who can read it still sees a private product', $resolve(['product' => '12']) instanceof WC_Product);

    echo $fail ? "FAILED\n" : "all shortcode access checks passed\n";
    exit($fail);
}
