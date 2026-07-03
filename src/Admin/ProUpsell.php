<?php

declare(strict_types=1);

namespace Polski\Admin;

defined('ABSPATH') || exit;

/**
 * PRO upgrade promotion, shown ONLY on the main Polski Dashboard: a dismissible
 * top banner, a sidebar promo panel, and a "what PRO adds" locked-card list.
 *
 * It is pure advertising: no disabled form fields, nothing blocks a free
 * workflow, it is scoped to this one screen and the banner is dismissible per
 * user. That keeps it inside the WordPress.org guidelines (no admin hijacking,
 * no trialware). Content comes from config/pro-upsell.php, generated from the
 * plogins.com registry, so the feature copy always matches the real PRO edition.
 */
final class ProUpsell
{
    private const META   = 'polski_pro_banner_dismissed';
    private const ACTION = 'polski_dismiss_pro';

    /** How many feature bullets the sidebar promo shows before the "+N more" line. */
    private const ASIDE_LIMIT = 6;

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function registerHooks(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handleDismiss']);
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        if ($this->data === null) {
            $file = \Polski\PLUGIN_DIR . '/config/pro-upsell.php';
            $this->data = is_readable($file) ? (array) require $file : [];
        }
        return $this->data;
    }

    /** Whether to render the promo at all (filterable for white-label builds). */
    public function enabled(): bool
    {
        /**
         * Filters whether the Polski PRO promo is shown on the dashboard screen.
         *
         * @param bool $show Default true.
         */
        return (bool) apply_filters('polski/show_pro_cta', true) && $this->features() !== [];
    }

    private function url(): string
    {
        $default = (string) ($this->data()['url'] ?? 'https://plogins.com/polski-pro/pricing/');
        /**
         * Filters the URL the "Upgrade to PRO" buttons point at.
         *
         * @param string $url Default the Polski PRO pricing page.
         */
        return (string) apply_filters('polski/pro_url', $default);
    }

    private function isPolish(): bool
    {
        return str_starts_with((string) get_locale(), 'pl');
    }

    private function priceLabel(): string
    {
        $d = $this->data();
        if ($this->isPolish() && ! empty($d['price_pln'])) {
            /* translators: %d: yearly price in PLN */
            return sprintf(__('od %d zł/rok', 'polski'), (int) $d['price_pln']);
        }
        if (! empty($d['price_from'])) {
            $cur = ($d['currency'] ?? 'EUR') === 'EUR' ? '€' : (string) $d['currency'] . ' ';
            /* translators: 1: currency symbol, 2: yearly price */
            return sprintf(__('from %1$s%2$d/yr', 'polski'), $cur, (int) $d['price_from']);
        }
        return '';
    }

    /** @return array<int, array{title: string, desc: string}> */
    private function features(): array
    {
        $lang = $this->isPolish() ? 'pl' : 'en';
        $out  = [];
        foreach ((array) ($this->data()['features'] ?? []) as $f) {
            $x = is_array($f) ? ($f[$lang] ?? $f['en'] ?? null) : null;
            if (is_array($x) && ! empty($x['title'])) {
                $out[] = ['title' => (string) $x['title'], 'desc' => (string) ($x['desc'] ?? '')];
            }
        }
        return $out;
    }

    public function bannerDismissed(): bool
    {
        return (bool) get_user_meta(get_current_user_id(), self::META, true);
    }

    private function dismissUrl(): string
    {
        return wp_nonce_url(admin_url('admin-post.php?action=' . self::ACTION), self::ACTION);
    }

    public function handleDismiss(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied.', 'polski'));
        }
        check_admin_referer(self::ACTION);
        update_user_meta(get_current_user_id(), self::META, 1);
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=polski'));
        exit;
    }

    /* ------------------------------------------------------------------ */
    /* Render pieces                                                       */
    /* ------------------------------------------------------------------ */

    /** Dismissible strip at the top of the dashboard screen. */
    public function banner(): void
    {
        if (! $this->enabled() || $this->bannerDismissed()) {
            return;
        }
        $name     = (string) ($this->data()['name'] ?? 'Polski PRO');
        $price    = $this->priceLabel();
        $subtitle = implode(', ', array_slice(array_map(
            static fn (array $f): string => $f['title'],
            $this->features(),
        ), 0, 3));
        ?>
        <div class="polski-pro-banner" role="note">
            <span class="polski-pro-banner__tag">PRO</span>
            <p class="polski-pro-banner__text">
                <strong><?php
                /* translators: %s: PRO edition name */
                printf(esc_html__('Do more with %s', 'polski'), esc_html($name)); ?></strong>
                <?php if ($subtitle !== '') : ?><span class="polski-pro-banner__sub"><?php echo esc_html($subtitle); ?></span><?php endif; ?>
                <?php if ($price !== '') : ?><span class="polski-pro-banner__price"><?php echo esc_html($price); ?></span><?php endif; ?>
            </p>
            <a class="button button-primary polski-pro-banner__cta" href="<?php echo esc_url($this->url()); ?>" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('Upgrade to PRO', 'polski'); ?>
            </a>
            <a class="polski-pro-banner__dismiss" href="<?php echo esc_url($this->dismissUrl()); ?>" aria-label="<?php esc_attr_e('Dismiss this notice', 'polski'); ?>">&times;</a>
        </div>
        <?php
    }

    /** Sidebar promo panel (sits in the dashboard two-column layout). */
    public function aside(): void
    {
        if (! $this->enabled()) {
            return;
        }
        $name     = (string) ($this->data()['name'] ?? 'Polski PRO');
        $price    = $this->priceLabel();
        $features = $this->features();
        // Polski PRO carries a lot of modules; keep the sticky sidebar sane by
        // listing only the first few and summarising the rest, while the cards
        // grid below the dashboard still shows every feature.
        $shown     = array_slice($features, 0, self::ASIDE_LIMIT);
        $remaining = count($features) - count($shown);
        ?>
        <aside class="polski-pro-aside" aria-labelledby="polski-pro-aside-h">
            <p class="polski-pro-aside__eyebrow"><?php echo esc_html($name); ?></p>
            <h2 id="polski-pro-aside-h" class="polski-pro-aside__heading"><?php esc_html_e('Unlock every PRO feature', 'polski'); ?></h2>
            <ul class="polski-pro-aside__list">
                <?php foreach ($shown as $f) : ?>
                    <li>
                        <span class="polski-pro-aside__lock" aria-hidden="true"></span>
                        <span><?php echo esc_html($f['title']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($remaining > 0) : ?>
                <p class="polski-pro-aside__more"><?php
                    /* translators: %d: number of additional PRO features not listed above */
                    printf(esc_html__('+%d more in PRO', 'polski'), (int) $remaining); ?></p>
            <?php endif; ?>
            <a class="button button-primary button-hero polski-pro-aside__cta" href="<?php echo esc_url($this->url()); ?>" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('Upgrade to PRO', 'polski'); ?>
            </a>
            <?php if ($price !== '') : ?>
                <p class="polski-pro-aside__price"><?php echo esc_html($price); ?> · <?php esc_html_e('one licence, every PRO feature', 'polski'); ?></p>
            <?php endif; ?>
        </aside>
        <?php
    }

    /** "What PRO adds" locked-card grid, appended after the dashboard body. */
    public function cards(): void
    {
        if (! $this->enabled()) {
            return;
        }
        $features = $this->features();
        $name     = (string) ($this->data()['name'] ?? 'Polski PRO');
        ?>
        <section class="polski-pro-cards" aria-labelledby="polski-pro-cards-h">
            <h2 id="polski-pro-cards-h" class="polski-pro-cards__title">
                <?php
                /* translators: %s: PRO edition name */
                printf(esc_html__('What %s adds', 'polski'), esc_html($name)); ?>
            </h2>
            <div class="polski-pro-cards__grid">
                <?php foreach ($features as $f) : ?>
                    <article class="polski-pro-card">
                        <span class="polski-pro-card__badge">PRO</span>
                        <span class="polski-pro-card__lock" aria-hidden="true"></span>
                        <h3 class="polski-pro-card__title"><?php echo esc_html($f['title']); ?></h3>
                        <?php if ($f['desc'] !== '') : ?>
                            <p class="polski-pro-card__desc"><?php echo esc_html($f['desc']); ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
}
