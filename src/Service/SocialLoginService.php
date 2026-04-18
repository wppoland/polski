<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Social login via OAuth2 (Facebook, Google).
 *
 * Allows customers to register and login via their social accounts.
 * Integrates with WooCommerce My Account and checkout login forms.
 * Creates WordPress user on first social login with linked provider ID.
 *
 * Web Vitals: buttons are pure CSS, no external SDK loaded until click.
 */
final class SocialLoginService implements HasHooks
{
    private const OPTION = 'polski_social_login';
    private const META_PROVIDER = '_polski_social_provider';
    private const META_PROVIDER_ID = '_polski_social_id';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('social_login')) {
            return;
        }

        // OAuth callback handler.
        add_action('init', [$this, 'handleOAuthCallback']);

        // Display login buttons.
        add_action('woocommerce_login_form_end', [$this, 'renderButtons']);
        add_action('woocommerce_after_checkout_billing_form', [$this, 'renderButtonsCheckout']);
        add_action('login_form', [$this, 'renderButtons']);

        // Register REST endpoint for OAuth redirect.
        add_action('rest_api_init', [$this, 'registerRoutes']);

        // Enqueue minimal CSS.
        add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return wp_parse_args(
            get_option(self::OPTION, []),
            [
                'google_enabled' => false,
                'google_client_id' => '',
                'google_client_secret' => '',
                'facebook_enabled' => false,
                'facebook_app_id' => '',
                'facebook_app_secret' => '',
                'auto_register' => true,
                'button_style' => 'branded',
            ],
        );
    }

    /**
     * Render social login buttons on login form.
     */
    public function renderButtons(): void
    {
        $settings = $this->getSettings();
        $buttons = [];

        if (! empty($settings['google_enabled']) && ! empty($settings['google_client_id'])) {
            $buttons[] = [
                'provider' => 'google',
                'label' => __('Continue with Google', 'polski'),
                'color' => '#4285f4',
                'icon' => '<svg width="18" height="18" viewBox="0 0 18 18"><path fill="#fff" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615Z"/><path fill="#fff" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18Z"/></svg>',
                'url' => $this->getOAuthUrl('google'),
            ];
        }

        if (! empty($settings['facebook_enabled']) && ! empty($settings['facebook_app_id'])) {
            $buttons[] = [
                'provider' => 'facebook',
                'label' => __('Continue with Facebook', 'polski'),
                'color' => '#1877f2',
                'icon' => '<svg width="18" height="18" viewBox="0 0 18 18"><path fill="#fff" d="M18 9a9 9 0 1 0-10.406 8.89v-6.29H5.309V9h2.285V7.017c0-2.255 1.343-3.501 3.4-3.501.984 0 2.014.176 2.014.176v2.215h-1.135c-1.118 0-1.467.694-1.467 1.406V9h2.496l-.399 2.6h-2.097v6.29A9.002 9.002 0 0 0 18 9Z"/></svg>',
                'url' => $this->getOAuthUrl('facebook'),
            ];
        }

        if (empty($buttons)) {
            return;
        }

        echo '<div class="polski-social-login" style="margin:16px 0;text-align:center">';
        echo '<p style="color:#64748b;font-size:13px;margin-bottom:12px">' . esc_html__('Or sign in with', 'polski') . '</p>';

        foreach ($buttons as $btn) {
            printf(
                '<a href="%s" class="polski-social-btn" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;margin:4px;border-radius:6px;background:%s;color:#fff;text-decoration:none;font-size:14px;font-weight:500;transition:opacity .2s" onmouseover="this.style.opacity=\'0.9\'" onmouseout="this.style.opacity=\'1\'">%s %s</a>',
                esc_url($btn['url']),
                esc_attr($btn['color']),
                wp_kses_post($btn['icon']),
                esc_html($btn['label']),
            );
        }

        echo '</div>';
    }

    /**
     * Render buttons on checkout (only if not logged in).
     */
    public function renderButtonsCheckout(): void
    {
        if (is_user_logged_in()) {
            return;
        }

        $this->renderButtons();
    }

    public function enqueueStyles(): void
    {
        if (! is_account_page() && ! is_checkout()) {
            return;
        }

        wp_add_inline_style('polski-frontend', '
            .polski-social-login { border-top: 1px solid #e2e8f0; padding-top: 16px; }
            .polski-social-btn:hover { box-shadow: 0 2px 8px rgba(0,0,0,.15); }
        ');
    }

    /**
     * Register REST routes for OAuth flow.
     */
    public function registerRoutes(): void
    {
        register_rest_route('polski/v1', '/social/(?P<provider>[a-z]+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'initiateOAuth'],
            'permission_callback' => '__return_true',
            'args' => [
                'provider' => ['type' => 'string', 'enum' => ['google', 'facebook']],
            ],
        ]);
    }

    /**
     * Initiate OAuth redirect to provider.
     */
    public function initiateOAuth(\WP_REST_Request $request): void
    {
        $provider = $request->get_param('provider');
        $redirectUrl = $this->buildProviderAuthUrl($provider);

        // phpcs:disable WordPressVIPMinimum.Security.SafeRedirect.wp_redirect_wp_redirect, WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External OAuth providers cannot use wp_safe_redirect().
        if ($redirectUrl) {
            wp_redirect($redirectUrl);
            exit;
        }
        // phpcs:enable WordPressVIPMinimum.Security.SafeRedirect.wp_redirect_wp_redirect, WordPress.Security.SafeRedirect.wp_redirect_wp_redirect

        wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
        exit;
    }

    /**
     * Handle OAuth callback from provider.
     */
    public function handleOAuthCallback(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth callback verification uses the provider state token.
        if (! isset($_GET['polski_social_callback'], $_GET['provider'], $_GET['code'])) {
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            return;
        }

        $provider = sanitize_key((string) wp_unslash($_GET['provider']));
        $code = sanitize_text_field((string) wp_unslash($_GET['code']));

        if (! in_array($provider, ['google', 'facebook'], true) || empty($code)) {
            return;
        }

        // Verify state nonce.
        $state = sanitize_text_field((string) wp_unslash($_GET['state'] ?? ''));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if (! wp_verify_nonce($state, 'polski_social_' . $provider)) {
            wc_add_notice(__('Social login verification failed. Please try again.', 'polski'), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
            exit;
        }

        // Exchange code for token.
        $tokenData = $this->exchangeCodeForToken($provider, $code);

        if (! $tokenData) {
            wc_add_notice(__('Could not authenticate with the social provider. Please try again.', 'polski'), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
            exit;
        }

        // Get user profile from provider.
        $profile = $this->getUserProfile($provider, $tokenData['access_token']);

        if (! $profile || empty($profile['email'])) {
            wc_add_notice(__('Could not retrieve your profile. Please ensure email access is granted.', 'polski'), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
            exit;
        }

        // Find or create WordPress user.
        $user = $this->findOrCreateUser($provider, $profile);

        if (! $user) {
            wc_add_notice(__('Could not create your account. Please try again.', 'polski'), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
            exit;
        }

        // Log the user in.
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        /**
         * Fires after successful social login.
         *
         * @param \WP_User             $user
         * @param string               $provider
         * @param array<string, mixed> $profile
         */
        do_action('polski/social_login/authenticated', $user, $provider, $profile);

        wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
        exit;
    }

    // ── OAuth URL builders ─���────────────────────────────

    private function getOAuthUrl(string $provider): string
    {
        return rest_url('polski/v1/social/' . $provider);
    }

    private function buildProviderAuthUrl(string $provider): ?string
    {
        $settings = $this->getSettings();
        $state = wp_create_nonce('polski_social_' . $provider);
        $callbackUrl = add_query_arg([
            'polski_social_callback' => '1',
            'provider' => $provider,
        ], home_url('/'));

        if ($provider === 'google') {
            $clientId = $settings['google_client_id'] ?? '';

            if (empty($clientId)) {
                return null;
            }

            return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $callbackUrl,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'access_type' => 'online',
                'prompt' => 'select_account',
            ]);
        }

        if ($provider === 'facebook') {
            $appId = $settings['facebook_app_id'] ?? '';

            if (empty($appId)) {
                return null;
            }

            return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
                'client_id' => $appId,
                'redirect_uri' => $callbackUrl,
                'response_type' => 'code',
                'scope' => 'email,public_profile',
                'state' => $state,
            ]);
        }

        return null;
    }

    // ── Token exchange ──────────────────────────────────

    /**
     * @return array<string, mixed>|null
     */
    private function exchangeCodeForToken(string $provider, string $code): ?array
    {
        $settings = $this->getSettings();
        $callbackUrl = add_query_arg([
            'polski_social_callback' => '1',
            'provider' => $provider,
        ], home_url('/'));

        if ($provider === 'google') {
            $response = wp_remote_post('https://oauth2.googleapis.com/token', [
                'body' => [
                    'code' => $code,
                    'client_id' => $settings['google_client_id'],
                    'client_secret' => $settings['google_client_secret'],
                    'redirect_uri' => $callbackUrl,
                    'grant_type' => 'authorization_code',
                ],
            ]);
        } elseif ($provider === 'facebook') {
            $response = wp_remote_get('https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
                'client_id' => $settings['facebook_app_id'],
                'client_secret' => $settings['facebook_app_secret'],
                'redirect_uri' => $callbackUrl,
                'code' => $code,
            ]));
        } else {
            return null;
        }

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return isset($body['access_token']) ? $body : null;
    }

    // ── Profile fetching ────────────────────────────────

    /**
     * @return array{email: string, name: string, first_name: string, last_name: string, provider_id: string}|null
     */
    private function getUserProfile(string $provider, string $accessToken): ?array
    {
        if ($provider === 'google') {
            $response = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            ]);
        } elseif ($provider === 'facebook') {
            $response = wp_remote_get('https://graph.facebook.com/v18.0/me?' . http_build_query([
                'fields' => 'id,name,email,first_name,last_name',
                'access_token' => $accessToken,
            ]));
        } else {
            return null;
        }

        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($data) || empty($data['email'])) {
            return null;
        }

        return [
            'email' => sanitize_email($data['email']),
            'name' => sanitize_text_field($data['name'] ?? ''),
            'first_name' => sanitize_text_field($data['first_name'] ?? $data['given_name'] ?? ''),
            'last_name' => sanitize_text_field($data['last_name'] ?? $data['family_name'] ?? ''),
            'provider_id' => sanitize_text_field((string) ($data['id'] ?? '')),
        ];
    }

    // ── User management ──��──────────────────────────────

    /**
     * @param array<string, string> $profile
     */
    private function findOrCreateUser(string $provider, array $profile): ?\WP_User
    {
        // Check if user exists by provider ID.
        $users = get_users([
            'meta_key' => self::META_PROVIDER_ID,
            'meta_value' => $provider . ':' . $profile['provider_id'],
            'number' => 1,
        ]);

        if (! empty($users)) {
            return $users[0];
        }

        // Check if user exists by email.
        $existing = get_user_by('email', $profile['email']);

        if ($existing) {
            // Link social account to existing user.
            update_user_meta($existing->ID, self::META_PROVIDER, $provider);
            update_user_meta($existing->ID, self::META_PROVIDER_ID, $provider . ':' . $profile['provider_id']);

            return $existing;
        }

        // Auto-register if enabled.
        $settings = $this->getSettings();

        if (empty($settings['auto_register'])) {
            return null;
        }

        // Create new user.
        $username = sanitize_user(strtolower($profile['first_name'] . '.' . $profile['last_name']));
        $username = $this->ensureUniqueUsername($username);
        $password = wp_generate_password(24, true, true);

        $userId = wp_create_user($username, $password, $profile['email']);

        if (is_wp_error($userId)) {
            return null;
        }

        // Set user meta.
        wp_update_user([
            'ID' => $userId,
            'first_name' => $profile['first_name'],
            'last_name' => $profile['last_name'],
            'display_name' => $profile['name'],
            'role' => 'customer',
        ]);

        update_user_meta($userId, self::META_PROVIDER, $provider);
        update_user_meta($userId, self::META_PROVIDER_ID, $provider . ':' . $profile['provider_id']);

        // WooCommerce billing fields.
        update_user_meta($userId, 'billing_first_name', $profile['first_name']);
        update_user_meta($userId, 'billing_last_name', $profile['last_name']);
        update_user_meta($userId, 'billing_email', $profile['email']);

        $user = get_user_by('ID', $userId);

        return $user instanceof \WP_User ? $user : null;
    }

    private function ensureUniqueUsername(string $username): string
    {
        if (! username_exists($username)) {
            return $username;
        }

        $i = 1;

        while (username_exists($username . $i)) {
            $i++;
        }

        return $username . $i;
    }
}
