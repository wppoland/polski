/**
 * WordPress-compatible build for the admin/frontend bundles.
 *
 * Vite's default multi-entry build emits ES modules with bare @wordpress/*
 * imports. Enqueued as classic scripts they throw "Cannot use import statement
 * outside a module" and the React admin never mounts. This builds each entry as
 * a self-contained IIFE, maps the externals to the WordPress browser globals
 * (wp.element, wp.components, ...), and emits admin.asset.php with the matching
 * script dependencies for the PHP enqueue.
 *
 * Run: node scripts/build-wp.mjs   (wired as `npm run build`, before the blocks
 * build which keeps using @wordpress/scripts).
 */
import { build } from 'vite';
import { writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..');

const ENTRIES = {
    admin: 'resources/js/admin/index.tsx',
    'frontend-checkout': 'resources/js/frontend/checkout.ts',
    'frontend-omnibus': 'resources/js/frontend/omnibus-badge.ts',
    'frontend-price-toggle': 'resources/js/frontend/price-toggle.ts',
    'frontend-consent': 'resources/js/frontend/consent.ts',
    'frontend-safefonts': 'resources/js/frontend/safefonts.ts',
    'frontend-triggers': 'resources/js/frontend/triggers.ts',
};

// The React admin bundle needs WordPress script dependencies. The consent banner
// is vanilla but still emits an .asset.php (with no deps) so the PHP enqueue can
// read a cache-busting version; the remaining frontend bundles get none, matching
// the prior behaviour.
const ASSET_PHP = new Set([
    'admin',
    'frontend-consent',
    'frontend-safefonts',
    'frontend-triggers',
]);

// Bundles that are framework-free: emit an empty dependency list in their
// .asset.php rather than the WordPress React deps.
const VANILLA = new Set([
    'frontend-consent',
    'frontend-safefonts',
    'frontend-triggers',
]);

const GLOBALS = {
    react: 'React',
    'react-dom': 'ReactDOM',
    wp: 'wp',
    '@wordpress/element': 'wp.element',
    '@wordpress/components': 'wp.components',
    '@wordpress/data': 'wp.data',
    '@wordpress/i18n': 'wp.i18n',
    '@wordpress/api-fetch': 'wp.apiFetch',
};

const DEPENDENCIES = ['wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-api-fetch'];

const version = Date.now().toString(16);

for (const [name, entry] of Object.entries(ENTRIES)) {
    await build({
        configFile: false,
        root: ROOT,
        mode: 'production',
        esbuild: { jsxDev: false },
        define: {
            'process.env.NODE_ENV': JSON.stringify('production'),
            'process.env': '{}',
        },
        resolve: { alias: { '@': resolve(ROOT, 'resources/js') } },
        build: {
            outDir: 'build',
            emptyOutDir: false,
            manifest: false,
            cssCodeSplit: false,
            lib: {
                entry: resolve(ROOT, entry),
                formats: ['iife'],
                name: `polski_${name.replace(/-/g, '_')}`,
                fileName: () => `${name}.js`,
            },
            rollupOptions: {
                external: Object.keys(GLOBALS),
                output: {
                    globals: GLOBALS,
                    assetFileNames: `${name}[extname]`,
                },
            },
        },
    });

    if (ASSET_PHP.has(name)) {
        const deps = VANILLA.has(name) ? [] : DEPENDENCIES;
        writeFileSync(
            resolve(ROOT, `build/${name}.asset.php`),
            `<?php return array(\n    'dependencies' => array(${deps.map((d) => `'${d}'`).join(', ')}),\n    'version' => '${version}',\n);\n`,
            'utf8',
        );
    }

    // eslint-disable-next-line no-console
    console.log(`built build/${name}.js (iife)${ASSET_PHP.has(name) ? ` + build/${name}.asset.php` : ''}`);
}
