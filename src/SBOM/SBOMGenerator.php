<?php

declare(strict_types=1);

namespace Polski\SBOM;

defined('ABSPATH') || exit;

/**
 * Generate a Software Bill of Materials (SBOM) in CycloneDX 1.4 JSON
 * format for a given plugin directory. Reads `composer.lock` for PHP
 * dependencies and `package-lock.json` for JS dependencies, plus the
 * plugin's own header metadata.
 *
 * CycloneDX was chosen over SPDX because its JSON shape is simpler and
 * widely consumed by vulnerability scanners (Trivy, Dependency-Track).
 *
 * @see https://cyclonedx.org/docs/1.4/json/
 */
final class SBOMGenerator
{
    private const BOM_FORMAT = 'CycloneDX';
    private const SPEC_VERSION = '1.4';

    /**
     * Build a CycloneDX document describing the plugin rooted at $pluginDir.
     *
     * @return array<string, mixed>
     */
    public function generate(string $pluginDir, string $pluginSlug, string $pluginVersion): array
    {
        $metadataComponent = [
            'type' => 'application',
            'bom-ref' => 'wppoland/' . $pluginSlug,
            'name' => $pluginSlug,
            'version' => $pluginVersion,
            'publisher' => 'WPPoland',
        ];

        $components = array_merge(
            $this->readComposerLock($pluginDir),
            $this->readPackageLock($pluginDir),
        );

        return [
            'bomFormat' => self::BOM_FORMAT,
            'specVersion' => self::SPEC_VERSION,
            'serialNumber' => 'urn:uuid:' . wp_generate_uuid4(),
            'version' => 1,
            'metadata' => [
                'timestamp' => gmdate('c'),
                'tools' => [[
                    'vendor' => 'WPPoland',
                    'name' => 'Polski SBOM generator',
                    'version' => '1.0',
                ]],
                'component' => $metadataComponent,
            ],
            'components' => array_values($components),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readComposerLock(string $pluginDir): array
    {
        $path = rtrim($pluginDir, '/') . '/composer.lock';

        if (! is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return [];
        }

        $components = [];

        foreach (['packages', 'packages-dev'] as $section) {
            foreach ((array) ($decoded[$section] ?? []) as $package) {
                if (! is_array($package) || ! isset($package['name'])) {
                    continue;
                }

                $ref = 'composer:' . (string) $package['name'];
                $components[$ref] = [
                    'type' => 'library',
                    'bom-ref' => $ref,
                    'name' => (string) $package['name'],
                    'version' => (string) ($package['version'] ?? ''),
                    'scope' => $section === 'packages-dev' ? 'optional' : 'required',
                    'purl' => sprintf(
                        'pkg:composer/%s@%s',
                        (string) $package['name'],
                        ltrim((string) ($package['version'] ?? ''), 'v'),
                    ),
                    'licenses' => $this->normalizeLicenses($package['license'] ?? []),
                ];
            }
        }

        return $components;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readPackageLock(string $pluginDir): array
    {
        $path = rtrim($pluginDir, '/') . '/package-lock.json';

        if (! is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return [];
        }

        $components = [];

        // npm v7+ uses "packages" map keyed by path; "" is the root.
        foreach ((array) ($decoded['packages'] ?? []) as $path => $meta) {
            if ($path === '' || ! is_array($meta) || ! isset($meta['version'])) {
                continue;
            }

            $name = $this->extractNpmName((string) $path);

            if ($name === '') {
                continue;
            }

            $ref = 'npm:' . $name . '@' . (string) $meta['version'];
            $components[$ref] = [
                'type' => 'library',
                'bom-ref' => $ref,
                'name' => $name,
                'version' => (string) $meta['version'],
                'scope' => ! empty($meta['dev']) ? 'optional' : 'required',
                'purl' => sprintf('pkg:npm/%s@%s', $name, (string) $meta['version']),
                'licenses' => isset($meta['license']) ? $this->normalizeLicenses($meta['license']) : [],
            ];
        }

        return $components;
    }

    /**
     * "node_modules/@wordpress/components" -> "@wordpress/components"
     * "node_modules/lodash" -> "lodash"
     */
    private function extractNpmName(string $path): string
    {
        if (! str_contains($path, 'node_modules/')) {
            return '';
        }

        $parts = explode('node_modules/', $path);
        $candidate = end($parts);

        if (! is_string($candidate)) {
            return '';
        }

        return trim($candidate, '/');
    }

    /**
     * @param mixed $value
     * @return list<array<string, array<string, string>>>
     */
    private function normalizeLicenses(mixed $value): array
    {
        $ids = [];

        if (is_string($value)) {
            $ids[] = $value;
        } elseif (is_array($value)) {
            foreach ($value as $entry) {
                if (is_string($entry)) {
                    $ids[] = $entry;
                }
            }
        }

        return array_map(
            static fn (string $id): array => ['license' => ['id' => $id]],
            $ids,
        );
    }
}
