<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap file.
 */

// Define ABSPATH for plugin files that guard against direct access.
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (! defined('Polski\\PLUGIN_DIR')) {
    define('Polski\\PLUGIN_DIR', dirname(__DIR__));
}

// Composer autoloader.
require_once dirname(__DIR__) . '/vendor/autoload.php';

// WordPress time constants (used as class-constant values in some services).
if (! defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 60 * 60);
}
if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 60 * 60 * 24);
}
if (! defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 60 * 60 * 24 * 7);
}

// ── Minimal WordPress function stubs for unit tests ─────────────────────────
// These stubs allow services to be instantiated without a full WordPress load.
// They return sensible defaults and are intentionally simple.

if (! function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        if (isset($GLOBALS['polski_test_options']) && is_array($GLOBALS['polski_test_options'])
            && array_key_exists($option, $GLOBALS['polski_test_options'])) {
            return $GLOBALS['polski_test_options'][$option];
        }

        return $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $option, mixed $value, string|bool $autoload = 'yes'): bool
    {
        return true;
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('_e')) {
    function _e(string $text, string $domain = 'default'): void
    {
        echo $text;
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('wp_kses')) {
    function wp_kses(string $string, array $allowed = []): string
    {
        unset($allowed);
        return strip_tags($string);
    }
}

if (! function_exists('wp_kses_post')) {
    function wp_kses_post(string $data): string
    {
        return $data;
    }
}

if (! function_exists('admin_url')) {
    function admin_url(string $path = '', string $scheme = 'admin'): string
    {
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }
}

if (! function_exists('wp_salt')) {
    function wp_salt(string $scheme = 'auth'): string
    {
        return 'polski-test-salt-' . $scheme;
    }
}

if (! function_exists('get_the_terms')) {
    function get_the_terms(int $postId, string $taxonomy): array|false|\WP_Error
    {
        $store = $GLOBALS['polski_test_terms'][$taxonomy][$postId] ?? null;
        if ($store === null) {
            return false;
        }
        if (is_callable($store)) {
            return $store();
        }
        return $store;
    }
}

if (! class_exists('WP_Term')) {
    class WP_Term
    {
        public int $term_id = 0;
        public string $name = '';
        public string $slug = '';
        public string $taxonomy = '';

        public function __construct(int $term_id = 0, string $name = '', string $taxonomy = '', string $slug = '')
        {
            $this->term_id = $term_id;
            $this->name = $name;
            $this->taxonomy = $taxonomy;
            $this->slug = $slug;
        }
    }
}

if (! class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;
        public string $post_type = 'post';

        public function __construct(int $id = 0, string $post_type = 'post')
        {
            $this->ID = $id;
            $this->post_type = $post_type;
        }
    }
}

if (! class_exists('WP_Query')) {
    class WP_Query
    {
        /** @var list<WP_Post> */
        public array $posts = [];

        /**
         * @param list<WP_Post> $posts
         */
        public function __construct(array $posts = [])
        {
            $this->posts = $posts;
        }
    }
}

if (! function_exists('wp_cache_get')) {
    function wp_cache_get(string $key, string $group = '', bool $force = false, ?bool &$found = null): mixed
    {
        unset($force);
        $store = $GLOBALS['polski_test_object_cache'][$group] ?? [];
        if (array_key_exists($key, $store)) {
            $found = true;
            return $store[$key];
        }
        $found = false;
        return false;
    }
}

if (! function_exists('wp_cache_set')) {
    function wp_cache_set(string $key, mixed $value, string $group = '', int $ttl = 0): bool
    {
        unset($ttl);
        $GLOBALS['polski_test_object_cache'][$group][$key] = $value;
        return true;
    }
}

if (! function_exists('wp_cache_delete')) {
    function wp_cache_delete(string $key, string $group = ''): bool
    {
        unset($GLOBALS['polski_test_object_cache'][$group][$key]);
        return true;
    }
}

if (! function_exists('set_transient')) {
    function set_transient(string $transient, mixed $value, int $expiration = 0): bool
    {
        $GLOBALS['polski_test_transients'][$transient] = [
            'value' => $value,
            'expires' => $expiration > 0 ? time() + $expiration : 0,
        ];
        return true;
    }
}

if (! function_exists('get_transient')) {
    function get_transient(string $transient): mixed
    {
        if (! isset($GLOBALS['polski_test_transients'][$transient])) {
            return false;
        }
        $entry = $GLOBALS['polski_test_transients'][$transient];
        if ($entry['expires'] > 0 && $entry['expires'] < time()) {
            unset($GLOBALS['polski_test_transients'][$transient]);
            return false;
        }
        return $entry['value'];
    }
}

if (! function_exists('delete_transient')) {
    function delete_transient(string $transient): bool
    {
        unset($GLOBALS['polski_test_transients'][$transient]);
        return true;
    }
}

if (! function_exists('wp_mail')) {
    function wp_mail(string|array $to, string $subject, string $message, string|array $headers = '', string|array $attachments = []): bool
    {
        $GLOBALS['polski_test_mail'][] = compact('to', 'subject', 'message', 'headers', 'attachments');
        return true;
    }
}

if (! function_exists('wc_get_orders')) {
    function wc_get_orders(array $args = []): array
    {
        return $GLOBALS['polski_test_orders'] ?? [];
    }
}

if (! function_exists('wc_get_order')) {
    function wc_get_order(int|object $order = 0): mixed
    {
        if (is_object($order)) {
            return $order;
        }
        return $GLOBALS['polski_test_order_by_id'][$order] ?? false;
    }
}

if (! function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $specialChars = true, bool $extraSpecialChars = false): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }
}

if (! function_exists('is_email')) {
    function is_email(string $email): string|false
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) === $email ? $email : false;
    }
}

if (! function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return $email !== false ? $email : '';
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        if (is_string($value)) {
            return stripslashes($value);
        }
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }
        return $value;
    }
}

if (! function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action = ''): int|false
    {
        // In unit tests we accept any non-empty nonce that matches the action prefix.
        return ($nonce !== '' && str_starts_with($nonce, 'nonce_' . $action)) ? 1 : false;
    }
}

if (! function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = ''): string
    {
        return 'nonce_' . $action;
    }
}

if (! function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://example.test/' . ltrim($path, '/');
    }
}

if (! function_exists('add_query_arg')) {
    function add_query_arg(string|array $key, mixed $value = null, string $url = ''): string
    {
        $args = is_array($key) ? $key : [$key => $value];
        if ($url === '') {
            $url = '/';
        }
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($args);
    }
}

if (! function_exists('wp_safe_redirect')) {
    function wp_safe_redirect(string $location, int $status = 302): bool
    {
        $GLOBALS['polski_test_last_redirect'] = $location;
        return true;
    }
}

if (! function_exists('absint')) {
    function absint(mixed $value): int
    {
        return abs((int) $value);
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options, $depth);
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text, bool $removeBreaks = false): string
    {
        $text = strip_tags($text);
        if ($removeBreaks) {
            $text = preg_replace('/[\r\n\t ]+/', ' ', $text) ?? $text;
        }
        return trim($text);
    }
}

if (! function_exists('wp_parse_args')) {
    function wp_parse_args(array|string $args, array $defaults = []): array
    {
        if (is_string($args)) {
            parse_str($args, $parsed);
            $args = $parsed;
        }
        return array_merge($defaults, $args);
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hookName, mixed $value, mixed ...$args): mixed
    {
        return $value;
    }
}

if (! function_exists('add_action')) {
    function add_action(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return true;
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return true;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $hookName, mixed ...$args): void
    {
        // No-op in unit tests.
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof \WP_Error;
    }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(public readonly string $code = '', public readonly string $message = '')
        {
        }
    }
}

if (! function_exists('get_permalink')) {
    function get_permalink(int|object $post = 0): string|false
    {
        return 'https://example.com/page/' . (is_int($post) ? $post : 0);
    }
}

if (! function_exists('get_privacy_policy_url')) {
    function get_privacy_policy_url(): string
    {
        return 'https://example.com/privacy-policy';
    }
}

if (! function_exists('wc_price')) {
    function wc_price(float|string $price, array $args = []): string
    {
        $currency = $args['currency'] ?? 'PLN';
        return '<span class="woocommerce-Price-amount">' . number_format((float) $price, 2, ',', ' ') . '&nbsp;' . $currency . '</span>';
    }
}

if (! function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency(): string
    {
        return 'PLN';
    }
}

if (! function_exists('wc_get_product')) {
    function wc_get_product(int $productId): ?object
    {
        return null;
    }
}

if (! function_exists('wc_attribute_label')) {
    function wc_attribute_label(string $name, string $product = ''): string
    {
        return match ($name) {
            'pa_color' => 'Kolor',
            'pa_size' => 'Rozmiar',
            default => $name,
        };
    }
}

if (! function_exists('get_term_by')) {
    function get_term_by(string $field, string $value, string $taxonomy = ''): object|false
    {
        return false;
    }
}

if (! function_exists('wp_get_post_terms')) {
    /**
     * @return list<int>|object
     */
    function wp_get_post_terms(int $postId, string $taxonomy = 'post_tag', array $args = []): array|object
    {
        if (! isset($GLOBALS['polski_test_post_terms'][$postId][$taxonomy])) {
            return [];
        }
        return $GLOBALS['polski_test_post_terms'][$postId][$taxonomy];
    }
}

if (! function_exists('get_term_meta')) {
    function get_term_meta(int $termId, string $key = '', bool $single = false): mixed
    {
        $store = $GLOBALS['polski_test_term_meta'][$termId] ?? [];
        if ($key === '') {
            return $store;
        }
        return $store[$key] ?? ($single ? '' : []);
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $key) ?? '');
    }
}

// Intentionally not stubbing current_time() here — WaitlistRepositoryTest defines
// its own namespaced shim that returns a fixed fixture string. Withdrawal tests
// don't exercise code paths that hit current_time().

// Intentionally not stubbing get_bloginfo() here — existing tests
// (LlmsTxtServiceTest) define their own namespaced shim guarded by function_exists().
// Tests that exercise code paths reaching get_bloginfo() either seed
// polski_general.company_name (so the ?? fallback short-circuits) or define
// their own get_bloginfo() shim.

if (! function_exists('nl2br')) {
    // PHP built-in; reference for stub-only contexts.
}

if (! class_exists('wpdb')) {
    /**
     * Test stub for the WordPress wpdb class. Combines:
     *  - a generic "no-op" surface used by the withdrawal tests, which only need
     *    a type-compatible instance to satisfy reflection-set typed properties,
     *  - an in-memory row store used by WaitlistRepositoryTest (and any future
     *    test that wants to record state across prepare/insert/update calls).
     *
     * Methods accept either the WordPress contract (string $query) or the
     * specialised test contract (array $prepared = [$sql, $args]). They detect
     * which form was passed and dispatch accordingly so both patterns coexist.
     */
    class wpdb
    {
        public string $prefix = 'wp_';
        public int $insert_id = 0;

        /** @var array<int, array<string, mixed>> */
        public array $rows = [];

        /**
         * @return string|array{0: string, 1: array<int, mixed>}
         */
        public function prepare(string|array $query, mixed ...$args): string|array
        {
            // Some tests pass an array tuple; mirror it back as-is.
            if (is_array($query)) {
                return $query;
            }
            // WP-style: return a packed tuple so consumers using the test
            // wpdb can introspect (sql, args). The first arg may itself be
            // an array; flatten one level.
            if (count($args) === 1 && is_array($args[0])) {
                return [$query, $args[0]];
            }
            return [$query, $args];
        }

        public function get_var(string|array $prepared, int $col = 0, int $row = 0): mixed
        {
            return null;
        }

        /**
         * @return list<\stdClass>
         */
        public function get_col(string|array $prepared, int $col = 0): array
        {
            return [];
        }

        /**
         * @return ?\stdClass
         */
        public function get_row(string|array $prepared, mixed $output = 0): ?\stdClass
        {
            if (! is_array($prepared)) {
                return null;
            }
            [, $args] = $prepared;
            if (! is_array($args) || count($args) < 3) {
                return null;
            }
            $productId = (int) ($args[1] ?? 0);
            $email = (string) ($args[2] ?? '');

            foreach ($this->rows as $row) {
                if ((int) ($row['product_id'] ?? 0) === $productId && (string) ($row['email'] ?? '') === $email) {
                    return (object) $row;
                }
            }
            return null;
        }

        /**
         * @return list<\stdClass>
         */
        public function get_results(string|array $prepared, mixed $output = 0): array
        {
            if (! is_array($prepared)) {
                return [];
            }
            [, $args] = $prepared;
            if (! is_array($args)) {
                return [];
            }
            $productId = (int) ($args[1] ?? 0);
            $results = [];
            foreach ($this->rows as $row) {
                if ((int) ($row['product_id'] ?? 0) === $productId && (int) ($row['notified'] ?? 0) === 0) {
                    $results[] = (object) $row;
                }
            }
            return $results;
        }

        /**
         * @param array<string, mixed> $data
         * @param array<int, string>|null $format
         */
        public function insert(string $table, array $data, ?array $format = null): bool
        {
            $this->insert_id++;
            $data['id'] = $this->insert_id;
            $this->rows[] = $data;
            return true;
        }

        /**
         * @param array<string, mixed> $data
         * @param array<string, mixed> $where
         * @param array<int, string>|null $format
         * @param array<int, string>|null $whereFormat
         */
        public function update(string $table, array $data, array $where, ?array $format = null, ?array $whereFormat = null): bool
        {
            foreach ($this->rows as &$row) {
                if ((int) ($row['id'] ?? 0) === (int) ($where['id'] ?? 0)) {
                    $row = array_merge($row, $data);
                    return true;
                }
            }
            unset($row);
            return false;
        }
    }
}

// Minimal WooCommerce class stubs so type-hinted code paths (instanceof checks,
// constructor signatures) succeed in pure-PHP unit tests. They expose no behaviour;
// real WC behaviour is exercised through subclasses defined inline in tests.

if (! class_exists('WC_Product')) {
    class WC_Product
    {
        public function get_id(): int
        {
            return 0;
        }
        public function get_parent_id(): int
        {
            return 0;
        }
        public function get_meta(string $key, bool $single = true): mixed
        {
            return '';
        }
        public function is_type(string|array $type): bool
        {
            return false;
        }
        public function get_name(): string
        {
            return '';
        }
        public function get_sku(): string
        {
            return '';
        }
        public function is_downloadable(): bool
        {
            return false;
        }
        public function is_virtual(): bool
        {
            return false;
        }
        public function get_files(): array
        {
            return [];
        }
        public function get_price(): string
        {
            return '0';
        }
    }
}

if (! class_exists('WC_Order_Item_Product')) {
    class WC_Order_Item_Product
    {
        public function get_product(): ?WC_Product
        {
            return null;
        }
        public function get_quantity(): int
        {
            return 0;
        }
        public function get_total(): string
        {
            return '0';
        }
        public function get_total_tax(): string
        {
            return '0';
        }
        public function get_name(): string
        {
            return '';
        }
        public function get_variation_id(): int
        {
            return 0;
        }
        public function get_product_id(): int
        {
            return 0;
        }
        public function get_meta(string $key, bool $single = true): mixed
        {
            return '';
        }
    }
}

if (! class_exists('WC_Order')) {
    class WC_Order
    {
        public function get_id(): int
        {
            return 0;
        }
        public function get_items(string $type = 'line_item'): array
        {
            return [];
        }
        public function get_currency(): string
        {
            return 'PLN';
        }
        public function get_status(): string
        {
            return 'pending';
        }
        public function get_total(): string
        {
            return '0';
        }
        public function get_customer_id(): int
        {
            return 0;
        }
        public function get_billing_email(): string
        {
            return '';
        }
        public function get_billing_first_name(): string
        {
            return '';
        }
        public function get_billing_last_name(): string
        {
            return '';
        }
        public function get_order_number(): string
        {
            return '0';
        }
        public function get_date_created(): ?object
        {
            return null;
        }
        public function get_date_completed(): ?object
        {
            return null;
        }
        public function get_meta(string $key, bool $single = true): mixed
        {
            return '';
        }
        public function update_meta_data(string $key, mixed $value): void
        {
        }
        public function save(): int
        {
            return 0;
        }
        public function update_status(string $status, string $note = '', bool $manual = false): bool
        {
            return true;
        }
        public function add_order_note(string $note, int $isCustomerNote = 0, bool $addedByUser = false): int
        {
            return 0;
        }
    }
}
