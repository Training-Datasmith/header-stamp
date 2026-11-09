<?php

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

function check_installed_json_gap(string $name, array $package): ?string
{
    $reference = get_installed_json_reference($package);
    if (!is_string($reference) || strlen($reference) !== 40 || !ctype_xdigit($reference)) {
        return sprintf('check-cluster-pins (installed.json): %s has no valid source.reference or dist.reference.', $name);
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

    $url = '';
    if (isset($package['source']['url']) && is_string($package['source']['url'])) {
        $url = $package['source']['url'];
    } elseif (isset($package['dist']['url']) && is_string($package['dist']['url'])) {
        $url = $package['dist']['url'];
    }
    if ($url !== '' && stripos($url, 'Training-Datasmith') !== false) {
        return sprintf('check-cluster-pins (installed.json): %s must not come from Training-Datasmith (%s).', $name, $url);
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

function run_wrong_sha_demos(): void
{
    $good = '0123456789abcdef0123456789abcdef01234567';
    $bad = 'fedcba9876543210fedcba9876543210fedcba98';
    $demo_name = 'demo/wrong-sha';

    $installed_php_entry = ['reference' => $good];
    $installed_json_pkg = [
        'name' => $demo_name,
        'source' => ['reference' => $good, 'url' => 'https://github.com/example/pkg.git'],
        'version' => '1.0.0',
    ];

    $bad_php = $installed_php_entry;
    $bad_php['reference'] = $bad;
    if (check_installed_php_reference_only($demo_name, $bad_php, $good) === null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.php must fail on wrong SHA.\n");
        exit(1);
    }
    if (check_installed_php_reference_only($demo_name, $installed_php_entry, $good) !== null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.php must pass on correct SHA.\n");
        exit(1);
    }
    if (check_installed_json_reference_only($demo_name, $installed_json_pkg, $good) !== null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.json must pass while installed.php is checked alone.\n");
        exit(1);
    }

    $bad_json = $installed_json_pkg;
    $bad_json['source']['reference'] = $bad;
    if (check_installed_json_reference_only($demo_name, $bad_json, $good) === null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.json must fail on wrong SHA.\n");
        exit(1);
    }
    if (check_installed_json_reference_only($demo_name, $installed_json_pkg, $good) !== null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.json must pass on correct SHA.\n");
        exit(1);
    }
    if (check_installed_php_reference_only($demo_name, $installed_php_entry, $good) !== null) {
        fwrite(STDERR, "check-cluster-pins demo: installed.php must pass while installed.json is checked alone.\n");
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

        $url = '';
        if (isset($json_pkg['source']['url']) && is_string($json_pkg['source']['url'])) {
            $url = $json_pkg['source']['url'];
        } elseif (isset($json_pkg['dist']['url']) && is_string($json_pkg['dist']['url'])) {
            $url = $json_pkg['dist']['url'];
        }
        if ($url !== '' && stripos($url, 'Training-Datasmith') !== false) {
            fwrite(STDERR, sprintf("check-cluster-pins: %s must not come from Training-Datasmith.\n", $name));
            $failed = true;
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

run_wrong_sha_demos();

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
