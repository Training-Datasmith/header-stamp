<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
declare(strict_types=1);

const INDEX_REFERENCES = [
    'nikic/php-parser' => [
        'f7e11c33f9fbd4aab93d4b718d21e78289913587',
        'ccb6c31631175a7cdaa9dac93d7dd029eb2ef004',
    ],
    'phpstan/phpstan' => [
        '63f848a4b1dbe2f2cb87acb3ff32596661e45417',
        'ff62edb2f684beab2738a854e9becf425d3e662e',
    ],
    'phpunit/phpunit' => [
        'a37d66fabf01af0eedabffe271b70dbe71723001',
        '698cbbc1e75a1ef1968f9cbfec89be056b288d0f',
    ],
];

/**
 * @param array{reference?: string} $entry
 */
function check_installed_php_reference_only(string $name, array $entry, string $want_reference): ?string
{
    $reference = $entry['reference'] ?? null;
    if (!is_string($reference) || strlen($reference) !== 40 || !ctype_xdigit($reference)) {
        return sprintf('check-cluster-pins (installed.php): %s has no valid 40-char reference.', $name);
    }
    if (!hash_equals(strtolower($want_reference), strtolower($reference))) {
        return sprintf(
            'check-cluster-pins (installed.php): %s reference mismatch (expected %s, got %s).',
            $name,
            $want_reference,
            $reference
        );
    }

    return null;
}

/**
 * @param array{source?: array{reference?: string}} $package
 */
function check_installed_json_reference_only(string $name, array $package, string $want_reference): ?string
{
    $source = $package['source'] ?? null;
    if (!is_array($source)) {
        return sprintf('check-cluster-pins (installed.json): %s has no source block.', $name);
    }
    $reference = $source['reference'] ?? null;
    if (!is_string($reference) || strlen($reference) !== 40 || !ctype_xdigit($reference)) {
        return sprintf('check-cluster-pins (installed.json): %s has no valid source.reference.', $name);
    }
    if (!hash_equals(strtolower($want_reference), strtolower($reference))) {
        return sprintf(
            'check-cluster-pins (installed.json): %s source.reference mismatch (expected %s, got %s).',
            $name,
            $want_reference,
            $reference
        );
    }

    return null;
}

/** @var array<string, array{min: string, max: string}> */
const GAP_VERSION_RANGES = [
    'nikic/php-parser' => ['min' => '4.10.0', 'max' => '5.0.0'],
    'phpstan/phpstan' => ['min' => '1.12.0', 'max' => '2.0.0'],
    'phpunit/phpunit' => ['min' => '8.5.52', 'max' => '9.0.0'],
];

/**
 * @param array{reference?: string, aliases?: list<string>, version?: string} $entry
 */
function check_installed_php_gap(string $name, array $entry, string $json_ref): ?string
{
    $reference = $entry['reference'] ?? null;
    if (!is_string($reference) || strlen($reference) !== 40 || !ctype_xdigit($reference)) {
        return sprintf('check-cluster-pins (installed.php): %s has no valid 40-char reference.', $name);
    }

    $ref_lower = strtolower($reference);
    foreach (INDEX_REFERENCES[$name] ?? [] as $index_ref) {
        if (hash_equals(strtolower($index_ref), $ref_lower)) {
            return sprintf(
                'check-cluster-pins (installed.php): %s must not match Training-Datasmith index SHA %s.',
                $name,
                $index_ref
            );
        }
    }

    if (!hash_equals($ref_lower, strtolower($json_ref))) {
        return sprintf(
            'check-cluster-pins (installed.php): %s reference %s != installed.json source.reference %s.',
            $name,
            $reference,
            $json_ref
        );
    }

    $version = $entry['version'] ?? null;
    if (!is_string($version) || $version === '') {
        return sprintf('check-cluster-pins (installed.php): %s has no version.', $name);
    }

    $normalized = ltrim($version, 'vV');
    $range = GAP_VERSION_RANGES[$name];
    if (version_compare($normalized, $range['min'], '<') || version_compare($normalized, $range['max'], '>=')) {
        return sprintf(
            'check-cluster-pins (installed.php): %s version %s outside [%s, %s).',
            $name,
            $version,
            $range['min'],
            $range['max']
        );
    }

    return null;
}

/**
 * @param array<string, mixed> $package
 */
/**
 * @param array<string, mixed> $package
 */
function get_installed_json_reference(array $package): ?string
{
    if (isset($package['source']['reference']) && is_string($package['source']['reference'])) {
        return $package['source']['reference'];
    }
    if (isset($package['dist']['reference']) && is_string($package['dist']['reference'])) {
        return $package['dist']['reference'];
    }

    return null;
}

function collect_json_refs_and_urls(array $package): array
{
    $refs = [];
    $urls = [];
    foreach (['source', 'dist'] as $block) {
        if (!isset($package[$block]) || !is_array($package[$block])) {
            continue;
        }
        if (isset($package[$block]['reference']) && is_string($package[$block]['reference'])) {
            $refs[] = $package[$block]['reference'];
        }
        if (isset($package[$block]['url']) && is_string($package[$block]['url'])) {
            $urls[] = $package[$block]['url'];
        }
    }

    return [$refs, $urls];
}

function check_installed_json_gap(string $name, array $package): ?string
{
    [$refs, $urls] = collect_json_refs_and_urls($package);
    if ($refs === []) {
        return sprintf('check-cluster-pins (installed.json): %s has no valid source.reference or dist.reference.', $name);
    }

    foreach ($refs as $reference) {
        if (strlen($reference) !== 40 || !ctype_xdigit($reference)) {
            return sprintf('check-cluster-pins (installed.json): %s has invalid reference %s.', $name, $reference);
        }
        foreach (INDEX_REFERENCES[$name] ?? [] as $index_ref) {
            if (hash_equals(strtolower($index_ref), strtolower($reference))) {
                return sprintf(
                    'check-cluster-pins (installed.json): %s must not match Training-Datasmith index SHA %s.',
                    $name,
                    $index_ref
                );
            }
        }
    }

    foreach ($urls as $url) {
        if (stripos($url, 'Training-Datasmith') !== false) {
            return sprintf('check-cluster-pins (installed.json): %s must not come from Training-Datasmith (%s).', $name, $url);
        }
    }

    $version = $package['version'] ?? null;
    if (!is_string($version) || $version === '') {
        return sprintf('check-cluster-pins (installed.json): %s has no version.', $name);
    }

    $normalized = ltrim($version, 'vV');
    $range = GAP_VERSION_RANGES[$name];
    if (version_compare($normalized, $range['min'], '<') || version_compare($normalized, $range['max'], '>=')) {
        return sprintf(
            'check-cluster-pins (installed.json): %s version %s outside [%s, %s).',
            $name,
            $version,
            $range['min'],
            $range['max']
        );
    }

    return null;
}

function demo_gap_packagist_snapshot(string $name): array
{
    $good_ref = '0123456789abcdef0123456789abcdef01234567';
    $php_entry = ['reference' => $good_ref, 'version' => '4.19.5.0'];
    $json_pkg = [
        'name' => $name,
        'version' => 'v4.19.5',
        'source' => [
            'type' => 'git',
            'url' => 'https://github.com/nikic/PHP-Parser.git',
            'reference' => $good_ref,
        ],
        'dist' => [
            'type' => 'zip',
            'url' => 'https://api.github.com/repos/nikic/PHP-Parser/zipball/' . $good_ref,
            'reference' => $good_ref,
        ],
    ];
    if ($name === 'phpstan/phpstan') {
        $php_entry['version'] = '1.12.34.0';
        $json_pkg['version'] = '1.12.34';
        $json_pkg['source'] = null;
        $json_pkg['dist'] = [
            'type' => 'zip',
            'url' => 'https://api.github.com/repos/phpstan/phpstan/zipball/' . $good_ref,
            'reference' => $good_ref,
        ];
        unset($json_pkg['source']);
    }
    if ($name === 'phpunit/phpunit') {
        $php_entry['version'] = '8.5.56.0';
        $json_pkg['version'] = '8.5.56';
        $json_pkg['source']['url'] = 'https://github.com/sebastianbergmann/phpunit.git';
    }

    return [$php_entry, $json_pkg, $good_ref];
}

function run_gap_demos(): void
{
    $name = 'nikic/php-parser';
    [$php_entry, $json_pkg, $json_ref] = demo_gap_packagist_snapshot($name);

    if (check_installed_php_gap($name, $php_entry, $json_ref) !== null) {
        fwrite(STDERR, "check-cluster-pins demo: baseline installed.php must pass.\n");
        exit(1);
    }
    if (check_installed_json_gap($name, $json_pkg) !== null) {
        fwrite(STDERR, "check-cluster-pins demo: baseline installed.json must pass.\n");
        exit(1);
    }

    $index_ref = INDEX_REFERENCES[$name][0];
    $bad_php = $php_entry;
    $bad_php['reference'] = $index_ref;
    if (check_installed_php_gap($name, $bad_php, $json_ref) === null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.php must fail on index SHA.\n");
        exit(1);
    }
    if (check_installed_json_gap($name, $json_pkg) !== null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.json must pass when only installed.php is bad.\n");
        exit(1);
    }

    $bad_json = $json_pkg;
    $bad_json['dist']['url'] = 'https://github.com/Training-Datasmith/PHP-Parser.git';
    if (check_installed_json_gap($name, $bad_json) === null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.json must fail on Training-Datasmith URL.\n");
        exit(1);
    }
    if (check_installed_php_gap($name, $php_entry, $json_ref) !== null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.php must pass when only installed.json is bad.\n");
        exit(1);
    }

    $bad_json2 = $json_pkg;
    $bad_json2['dist']['reference'] = INDEX_REFERENCES[$name][1];
    if (check_installed_json_gap($name, $bad_json2) === null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.json must fail on alternate index SHA.\n");
        exit(1);
    }
    if (check_installed_php_gap($name, $php_entry, $json_ref) !== null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.php must pass when installed.json dist reference is bad.\n");
        exit(1);
    }

    $bad_php_version = $php_entry;
    $bad_php_version['version'] = '3.0.0';
    if (check_installed_php_gap($name, $bad_php_version, $json_ref) === null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.php must fail on out-of-range version.\n");
        exit(1);
    }
    if (check_installed_json_gap($name, $json_pkg) !== null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.json must pass when only installed.php version is bad.\n");
        exit(1);
    }

    $bad_json_version = $json_pkg;
    $bad_json_version['version'] = 'v9.9.9';
    if (check_installed_json_gap($name, $bad_json_version) === null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.json must fail on out-of-range version.\n");
        exit(1);
    }
    if (check_installed_php_gap($name, $php_entry, $json_ref) !== null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.php must pass when only installed.json version is bad.\n");
        exit(1);
    }
}

/**
 * @param array<string, array<string, mixed>> $versions
 * @param array<string, array<string, mixed>> $json_by_name
 */
function check_gap_packages(array $versions, array $json_by_name): bool
{
    $failed = false;

    foreach (array_keys(GAP_VERSION_RANGES) as $name) {
        if (!isset($versions[$name])) {
            fwrite(STDERR, sprintf("check-cluster-pins: %s is not installed.\n", $name));
            $failed = true;
            continue;
        }

        if (!isset($json_by_name[$name])) {
            fwrite(STDERR, sprintf("check-cluster-pins: %s missing from installed.json.\n", $name));
            $failed = true;
            continue;
        }

        $json_pkg = $json_by_name[$name];
        $json_ref = get_installed_json_reference($json_pkg);
        if (!is_string($json_ref)) {
            fwrite(STDERR, sprintf("check-cluster-pins: %s has no json source.reference or dist.reference.\n", $name));
            $failed = true;
            continue;
        }

        $php_err = check_installed_php_gap($name, $versions[$name], $json_ref);
        if ($php_err !== null) {
            fwrite(STDERR, $php_err . "\n");
            $failed = true;
        }

        $json_err = check_installed_json_gap($name, $json_pkg);
        if ($json_err !== null) {
            fwrite(STDERR, $json_err . "\n");
            $failed = true;
        }
    }

    return !$failed;
}

run_gap_demos();

$installed_php = dirname(__DIR__) . '/vendor/composer/installed.php';
if (!is_readable($installed_php)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
    exit(1);
}

/** @var array{versions?: array<string, array<string, mixed>>} $installed */
$installed = require $installed_php;
$versions = $installed['versions'] ?? [];

$installed_json_path = dirname(__DIR__) . '/vendor/composer/installed.json';
if (!is_readable($installed_json_path)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.json not found.\n");
    exit(1);
}

$raw = file_get_contents($installed_json_path);
if ($raw === false) {
    fwrite(STDERR, "check-cluster-pins: could not read installed.json.\n");
    exit(1);
}

/** @var array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>} $json_data */
$json_data = json_decode($raw, true);
if (!is_array($json_data)) {
    fwrite(STDERR, "check-cluster-pins: invalid installed.json.\n");
    exit(1);
}

$json_packages = array_merge($json_data['packages'] ?? [], $json_data['packages-dev'] ?? []);
/** @var array<string, array<string, mixed>> $json_by_name */
$json_by_name = [];
foreach ($json_packages as $package) {
    if (is_array($package) && isset($package['name']) && is_string($package['name'])) {
        $json_by_name[$package['name']] = $package;
    }
}

if (!check_gap_packages($versions, $json_by_name)) {
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);
