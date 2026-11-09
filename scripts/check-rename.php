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

$root = dirname(__DIR__);

/** @var list<string> */
$scan_roots = [
    'src',
    'bin',
    'examples',
    'tests',
];

/** @var array<string, list<string>> path relative to repo root => list of Class::method */
$allowlist = [
    'demo/allowed.php' => [
        'Symfony\\Component\\Finder\\SplFileInfo::getContents',
    ],
    'bin/header-stamp' => [
        'Symfony\\Component\\Console\\Application::add',
        'Symfony\\Component\\Console\\Application::setDefaultCommand',
        'Symfony\\Component\\Console\\Application::run',
        'Symfony\\Component\\Console\\Command\\Command::getName',
    ],
    'src/Command/Update_Licenses_Command.php' => [
        'Symfony\\Component\\Console\\Command\\Command::setName',
        'Symfony\\Component\\Console\\Command\\Command::setDescription',
        'Symfony\\Component\\Console\\Command\\Command::addOption',
        'Symfony\\Component\\Console\\Input\\InputInterface::hasParameterOption',
        'Symfony\\Component\\Console\\Input\\InputInterface::getOption',
        'Symfony\\Component\\Console\\Output\\OutputInterface::writeln',
        'Symfony\\Component\\Finder\\Finder::files',
        'Symfony\\Component\\Finder\\Finder::name',
        'Symfony\\Component\\Finder\\Finder::in',
        'Symfony\\Component\\Finder\\Finder::exclude',
        'Symfony\\Component\\Finder\\Finder::notPath',
        'Symfony\\Component\\Finder\\Finder::notName',
        'Symfony\\Component\\Finder\\SplFileInfo::getExtension',
        'Symfony\\Component\\Finder\\SplFileInfo::getContents',
        'Symfony\\Component\\Finder\\SplFileInfo::getRelativePathname',
        'Symfony\\Component\\Finder\\SplFileInfo::getFilename',
        'Symfony\\Component\\Console\\Helper\\ProgressBar::setRedrawFrequency',
        'Symfony\\Component\\Console\\Helper\\ProgressBar::start',
        'Symfony\\Component\\Console\\Helper\\ProgressBar::advance',
        'Symfony\\Component\\Console\\Helper\\ProgressBar::finish',
        'Symfony\\Component\\Console\\Style\\SymfonyStyle::section',
        'Symfony\\Component\\Console\\Style\\SymfonyStyle::text',
        'Symfony\\Component\\Console\\Style\\SymfonyStyle::listing',
        'PhpParser\\ParserFactory::create',
        'PhpParser\\Node::hasAttribute',
        'PhpParser\\Node::getAttribute',
        'PhpParser\\Comment::getText',
    ],
    'tests/Integration/Command/Update_Licences_Command_Test.php' => [
        'PHPUnit\\Framework\\TestCase::assertEquals',
        'PHPUnit\\Framework\\TestCase::assertNotFalse',
        'PHPUnit\\Framework\\TestCase::assertFalse',
        'PHPUnit\\Framework\\TestCase::setUpBeforeClass',
        'PHPUnit\\Framework\\TestCase::tearDownAfterClass',
        'Symfony\\Component\\Console\\Application::add',
        'Symfony\\Component\\Console\\Application::find',
        'Symfony\\Component\\Console\\Tester\\CommandTester::execute',
        'Symfony\\Component\\Console\\Tester\\CommandTester::getDisplay',
        'Symfony\\Component\\Filesystem\\Filesystem::remove',
        'Symfony\\Component\\Filesystem\\Filesystem::mirror',
        'Symfony\\Component\\Finder\\Finder::directories',
        'Symfony\\Component\\Finder\\Finder::depth',
        'Symfony\\Component\\Finder\\Finder::in',
        'Symfony\\Component\\Finder\\SplFileInfo::getRealPath',
    ],
    'tests/Unit/License_Header_Test.php' => [
        'PHPUnit\\Framework\\TestCase::assertEquals',
        'PHPUnit\\Framework\\TestCase::assertTrue',
        'ReflectionClass::getMethod',
        'ReflectionClass::getProperty',
        'ReflectionProperty::setAccessible',
        'ReflectionProperty::setValue',
        'ReflectionProperty::getValue',
        'ReflectionMethod::setAccessible',
        'ReflectionMethod::invoke',
    ],
];

/** @var list<string> */
$forbidden_identifiers = [
    'PrestaShop\\HeaderStamp',
    'LicenseHeader',
    'LicenseHeaderTest',
    'UpdateLicensesCommand',
    'UpdateLicencesCommandTest',
    'FolderComparator',
    'getContentByType',
    'getRegexByType',
    'getTwigLicenseRegex',
    'compareFolders',
    'testGetContent',
    'testGetContentByType',
    'testCommandModifications',
    'testCommandDryRun',
];

/** @var list<string> */
$magic_methods = [
    '__construct',
    '__destruct',
    '__call',
    '__callStatic',
    '__get',
    '__set',
    '__isset',
    '__unset',
    '__sleep',
    '__wakeup',
    '__toString',
    '__invoke',
    '__set_state',
    '__clone',
    '__debugInfo',
    'setUpBeforeClass',
    'tearDownAfterClass',
    'setUp',
    'tearDown',
];

/**
 * @return list<string>
 */
function collect_php_files(string $root, array $scan_roots): array
{
    $files = [];
    foreach ($scan_roots as $rel) {
        $path = $root . '/' . $rel;
        if (is_file($path) && substr($path, -4) === '.php') {
            $files[] = $path;
            continue;
        }
        if (!is_dir($path)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $pathname = $file->getPathname();
            if (strpos($pathname, '/tests/Resources/') !== false) {
                continue;
            }
            if (substr($pathname, -4) === '.php' || basename($pathname) === 'header-stamp') {
                $files[] = $pathname;
            }
        }
    }
    $docs = ['README.md', 'ARCHITECTURE.md', 'tests/Integration/README.md'];
    foreach ($docs as $doc) {
        $full = $root . '/' . $doc;
        if (is_readable($full)) {
            $files[] = $full;
        }
    }

    return $files;
}

function has_camel_hump(string $name): bool
{
    if ($name === '' || $name[0] === '_') {
        return false;
    }
    if (preg_match('/^[A-Z0-9_]+$/', $name)) {
        return false;
    }
    if (preg_match('/[a-z][A-Z]/', $name)) {
        return true;
    }

    return false;
}

function normalize_site(string $site): string
{
    return str_replace('\\', '/', $site);
}

function site_from_path(string $path, string $root): string
{
    $path = normalize_site($path);
    $root = rtrim(normalize_site($root), '/');
    if (strpos($path, $root . '/') === 0) {
        return substr($path, strlen($root) + 1);
    }

    return $path;
}

function is_allowlisted_call(string $site, string $class, string $method, array $allowlist): bool
{
    $site = normalize_site($site);
    $key = $class . '::' . $method;
    if (!isset($allowlist[$site])) {
        return false;
    }

    return in_array($key, $allowlist[$site], true);
}

/**
 * @return list<string>
 */
function inheritance_allowlist_bases(string $fqcn): array
{
  $bases = [];
    if (is_subclass_of($fqcn, 'Symfony\\Component\\Console\\Command\\Command')) {
        $bases[] = 'Symfony\\Component\\Console\\Command\\Command';
    }
    if (is_subclass_of($fqcn, 'Symfony\\Component\\Console\\Command\\Command') === false
        && class_exists($fqcn)
        && is_subclass_of($fqcn, 'Symfony\\Component\\Console\\Tester\\CommandTester')) {
        $bases[] = 'Symfony\\Component\\Console\\Tester\\CommandTester';
    }
    if (is_subclass_of($fqcn, 'PHPUnit\\Framework\\TestCase')) {
        $bases[] = 'PHPUnit\\Framework\\TestCase';
    }
    if (is_subclass_of($fqcn, 'Symfony\\Component\\Console\\Application')) {
        $bases[] = 'Symfony\\Component\\Console\\Application';
    }
    if (is_subclass_of($fqcn, 'Symfony\\Component\\Finder\\Finder')) {
        $bases[] = 'Symfony\\Component\\Finder\\Finder';
    }
    if (is_subclass_of($fqcn, 'Symfony\\Component\\Filesystem\\Filesystem')) {
        $bases[] = 'Symfony\\Component\\Filesystem\\Filesystem';
    }

    return $bases;
}

function fqcn_from_short_class(string $short, array $uses, string $namespace): string
{
    if (strpos($short, '\\') !== false) {
        return $short;
    }
    if (isset($uses[$short])) {
        return $uses[$short];
    }

    return '';
}

/**
 * @param array<int, array{0: int, 1: string, 2?: int}|string> $tokens
 */
function known_reflection_method_return_type(string $receiver, string $method): string
{
    if ($receiver === 'ReflectionClass') {
        if ($method === 'getProperty') {
            return 'ReflectionProperty';
        }
        if ($method === 'getMethod') {
            return 'ReflectionMethod';
        }
    }

    return '';
}

/**
 * @param array<int, array{0: int, 1: string, 2?: int}|string> $tokens
 * @param array<string, string> $var_types
 */
function infer_type_from_assignment_rhs(
    array $tokens,
    int $start,
    string $namespace,
    string $class,
    array $uses,
    array $var_types
): string {
    $type = infer_type_from_new_expression($tokens, $start, $uses, $namespace);
    if ($type !== '') {
        return $type;
    }

    $count = count($tokens);
    $end = $start;
    while ($end < $count && $tokens[$end] !== ';') {
        ++$end;
    }
    for ($j = $end - 1; $j >= $start; --$j) {
        if (!is_object_operator_token($tokens[$j])) {
            continue;
        }
        if (!isset($tokens[$j + 1])
            || !is_array($tokens[$j + 1])
            || $tokens[$j + 1][0] !== T_STRING
        ) {
            continue;
        }
        $paren = $j + 2;
        while ($paren < $end && is_array($tokens[$paren]) && $tokens[$paren][0] === T_WHITESPACE) {
            ++$paren;
        }
        if ($paren >= $end || $tokens[$paren] !== '(') {
            continue;
        }
        $method = $tokens[$j + 1][1];
        $receiver = resolve_object_call_receiver($tokens, $j, $namespace, $class, $uses, $var_types);
        $return_type = known_reflection_method_return_type($receiver, $method);
        if ($return_type !== '') {
            return $return_type;
        }

        return '';
    }

    return '';
}

function infer_type_from_new_expression(array $tokens, int $start, array $uses, string $namespace): string
{
    $count = count($tokens);
    for ($j = $start; $j < $count; ++$j) {
        $tok = $tokens[$j];
        if (is_array($tok) && $tok[0] === T_WHITESPACE) {
            continue;
        }
        if ($tok === '(') {
            continue;
        }
        if (is_array($tok) && $tok[0] === T_NEW) {
            for ($k = $j + 1; $k < $count; ++$k) {
                $inner = $tokens[$k];
                if (is_array($inner) && $inner[0] === T_WHITESPACE) {
                    continue;
                }
                if (is_array($inner) && in_array($inner[0], [T_STRING, T_NS_SEPARATOR], true)) {
                    $class = '';
                    for ($m = $k; $m < $count; ++$m) {
                        if (is_array($tokens[$m]) && in_array($tokens[$m][0], [T_STRING, T_NS_SEPARATOR], true)) {
                            $class .= $tokens[$m][1];
                            continue;
                        }
                        if (is_array($tokens[$m]) && $tokens[$m][0] === T_WHITESPACE) {
                            continue;
                        }
                        break;
                    }

                    return fqcn_from_short_class($class, $uses, $namespace);
                }
                break;
            }
        }
        if (is_array($tok) && $tok[0] === T_STRING) {
            return fqcn_from_short_class($tok[1], $uses, $namespace);
        }
        break;
    }

    return '';
}

/**
 * @param array{0: int, 1: string, 2?: int}|string $token
 */
function is_object_operator_token($token): bool
{
    return $token === '->' || (is_array($token) && $token[0] === T_OBJECT_OPERATOR);
}

/**
 * @param array<int, array{0: int, 1: string, 2?: int}|string> $tokens
 */
function skip_optional_call_arguments_backwards(array $tokens, int &$index): void
{
    if ($index < 0 || $tokens[$index] !== ')') {
        return;
    }
    $depth = 1;
    --$index;
    while ($index >= 0 && $depth > 0) {
        $tok = $tokens[$index];
        if ($tok === ')') {
            $depth++;
        } elseif ($tok === '(') {
            $depth--;
        }
        --$index;
    }
}

function guess_receiver_from_variable(string $var, array $uses, array $var_types = []): string
{
    $key = ltrim($var, '$');
    if (isset($uses[$key])) {
        return $uses[$key];
    }
    if (isset($var_types[$key])) {
        return $var_types[$key];
    }
    $hints = [
        'output' => 'Symfony\\Component\\Console\\Output\\OutputInterface',
        'input' => 'Symfony\\Component\\Console\\Input\\InputInterface',
        'progress' => 'Symfony\\Component\\Console\\Helper\\ProgressBar',
        'style' => 'Symfony\\Component\\Console\\Style\\SymfonyStyle',
        'command' => 'Symfony\\Component\\Console\\Command\\Command',
        'command_tester' => 'Symfony\\Component\\Console\\Tester\\CommandTester',
        'finder' => 'Symfony\\Component\\Finder\\Finder',
        'file' => 'Symfony\\Component\\Finder\\SplFileInfo',
        'directory' => 'Symfony\\Component\\Finder\\SplFileInfo',
        'fs' => 'Symfony\\Component\\Filesystem\\Filesystem',
        'rc' => 'ReflectionClass',
        'rm' => 'ReflectionMethod',
        'rp' => 'ReflectionProperty',
        'rep' => 'Presta_Shop\\Header_Stamp\\Reporter',
        'node' => 'PhpParser\\Node',
        'comment' => 'PhpParser\\Comment',
        'parser' => 'PhpParser\\Parser',
    ];

    return $hints[$key] ?? '';
}

/**
 * @param array<int, array{0: int, 1: string, 2?: int}|string> $tokens
 * @param array<string, string> $var_types
 */
function resolve_object_call_receiver(array $tokens, int $op_index, string $namespace, string $class, array $uses, array $var_types): string
{
    $j = $op_index - 1;
    skip_optional_call_arguments_backwards($tokens, $j);

    while ($j >= 0) {
        while ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            --$j;
        }
        if ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
            $prev = $j - 1;
            while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
                --$prev;
            }
            if ($prev >= 0 && is_object_operator_token($tokens[$prev])) {
                $j = $prev - 1;
                skip_optional_call_arguments_backwards($tokens, $j);
                continue;
            }
        }
        break;
    }

    while ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
        --$j;
    }
    if ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_VARIABLE) {
        $var = $tokens[$j][1];
        if ($var === '$this') {
            return trim($namespace, '\\') . '\\' . $class;
        }

        return guess_receiver_from_variable($var, $uses, $var_types);
    }
    if ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_NEW) {
        return infer_type_from_new_expression($tokens, $j, $uses, $namespace);
    }
    if ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && $j >= 1 && $tokens[$j - 1] === '(') {
        $prev = $j - 2;
        while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
            --$prev;
        }
        if ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_NEW) {
            return infer_type_from_new_expression($tokens, $prev, $uses, $namespace);
        }
    }

    return '';
}

/**
 * @param list<string> $failures
 */
function scan_forbidden_tokens(string $file, string $contents, array $forbidden_identifiers, array &$failures): void
{
    foreach ($forbidden_identifiers as $token) {
        $pattern = '/(?<![a-zA-Z0-9_\\\\])' . preg_quote($token, '/') . '(?![a-zA-Z0-9_])/';
        if (preg_match($pattern, $contents)) {
            $failures[] = sprintf('%s: forbidden identifier %s', $file, $token);
        }
    }
}

/**
 * @param list<string> $failures
 */
function scan_php_tokens(string $site, string $contents, array $allowlist, array $magic_methods, array &$failures): void
{
    $tokens = token_get_all($contents);
    $count = count($tokens);
    $namespace = '';
    $class = '';
    $uses = [];
    /** @var array<string, string> $var_types */
    $var_types = [];

    for ($i = 0; $i < $count; ++$i) {
        if (is_array($tokens[$i]) && $tokens[$i][0] === T_VARIABLE) {
            $eq = $i + 1;
            while ($eq < $count && is_array($tokens[$eq]) && $tokens[$eq][0] === T_WHITESPACE) {
                ++$eq;
            }
            if (isset($tokens[$eq]) && $tokens[$eq] === '=') {
                $vn = ltrim($tokens[$i][1], '$');
                $rhs = $eq + 1;
                while ($rhs < $count && is_array($tokens[$rhs]) && $tokens[$rhs][0] === T_WHITESPACE) {
                    ++$rhs;
                }
                $type = infer_type_from_assignment_rhs($tokens, $rhs, $namespace, $class, $uses, $var_types);
                if ($type !== '') {
                    $var_types[$vn] = $type;
                }
            }
        }

        $token = $tokens[$i];
        if (!is_array($token)) {
            continue;
        }
        [$id, $text] = $token;

        if ($id === T_NAMESPACE) {
            $namespace = '';
            for ($j = $i + 1; $j < $count; ++$j) {
                if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NS_SEPARATOR], true)) {
                    $namespace .= $tokens[$j][1];
                    continue;
                }
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
                break;
            }
        }

        if ($id === T_CLASS) {
            for ($j = $i + 1; $j < $count; ++$j) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $class = $tokens[$j][1];
                    if (strpos($namespace, 'Presta_Shop\\') === 0 || strpos($namespace, 'Tests\\') === 0) {
                        if (has_camel_hump($class)) {
                            $failures[] = sprintf('%s: repo-owned camelCase class %s', $site, $class);
                        }
                    }
                    break;
                }
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
            }
        }

        if ($id === T_USE) {
            $import = '';
            for ($j = $i + 1; $j < $count; ++$j) {
                if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NS_SEPARATOR], true)) {
                    $import .= $tokens[$j][1];
                    continue;
                }
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_AS) {
                    $alias = '';
                    for ($k = $j + 1; $k < $count; ++$k) {
                        if (is_array($tokens[$k]) && $tokens[$k][0] === T_STRING) {
                            $alias = $tokens[$k][1];
                            break;
                        }
                    }
                    if ($alias !== '') {
                        $uses[$alias] = $import;
                    }
                    break;
                }
                if ($import !== '') {
                    $parts = explode('\\', $import);
                    $uses[end($parts)] = $import;
                }
                break;
            }
        }

        if ($id === T_FUNCTION) {
            $fn = '';
            for ($j = $i + 1; $j < $count; ++$j) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $fn = $tokens[$j][1];
                    break;
                }
            }
            if ($fn !== '' && !in_array($fn, $magic_methods, true)) {
                if ((strpos($namespace, 'Presta_Shop\\') === 0 || strpos($namespace, 'Tests\\') === 0) && has_camel_hump($fn)) {
                    $failures[] = sprintf('%s: repo-owned camelCase function %s', $site, $fn);
                }
            }
        }

        if ($id === T_VARIABLE && preg_match('/^\$[a-z]+[A-Z]/', $text)) {
            if (strpos($site, 'tests/Resources/') === false) {
                $failures[] = sprintf('%s: repo-owned camelCase variable %s', $site, $text);
            }
        }

        if ($id === T_STRING && $text === 'parent' && isset($tokens[$i + 1]) && $tokens[$i + 1] === '::') {
            $method = '';
            if (isset($tokens[$i + 2]) && is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_STRING) {
                $method = $tokens[$i + 2][1];
            }
            if ($method !== '' && has_camel_hump($method)) {
                if (!is_allowlisted_call($site, 'PHPUnit\\Framework\\TestCase', $method, $allowlist)
                    && !is_allowlisted_call($site, $namespace . '\\' . $class, $method, $allowlist)
                    && !in_array($method, $magic_methods, true)) {
                    if (strpos($namespace, 'Tests\\') === 0) {
                        $fq = $namespace . '\\' . $class . '::' . $method;
                        $failures[] = sprintf('%s: unallowlisted parent call %s', $site, $fq);
                    }
                }
            }
        }

        if ($id === T_OBJECT_OPERATOR && isset($tokens[$i + 1]) && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] === T_STRING) {
            $method = $tokens[$i + 1][1];
            if (!has_camel_hump($method)) {
                continue;
            }
            $resolved_receiver = resolve_object_call_receiver($tokens, $i, $namespace, $class, $uses, $var_types);
            if ($resolved_receiver === '') {
                $failures[] = sprintf('%s: unresolved camelCase method call %s', $site, $method);
                continue;
            }

            $allowed = false;
            foreach (inheritance_allowlist_bases($resolved_receiver) as $base) {
                if (is_allowlisted_call($site, $base, $method, $allowlist)) {
                    $allowed = true;
                    break;
                }
            }
            if ($allowed || is_allowlisted_call($site, $resolved_receiver, $method, $allowlist)) {
                continue;
            }

            if (strpos($resolved_receiver, 'Symfony\\') === 0
                || strpos($resolved_receiver, 'PhpParser\\') === 0
                || strpos($resolved_receiver, 'PHPUnit\\') === 0
            ) {
                $failures[] = sprintf('%s: unallowlisted third-party call %s::%s', $site, $resolved_receiver, $method);
            } elseif (strpos($resolved_receiver, 'Presta_Shop\\') === 0 || strpos($resolved_receiver, 'Tests\\') === 0) {
                $failures[] = sprintf('%s: repo-owned camelCase method call %s::%s', $site, $resolved_receiver, $method);
            }
        }

        if ($id === T_STRING && isset($tokens[$i + 1], $tokens[$i + 2])
            && $tokens[$i + 1] === '::'
            && is_array($tokens[$i + 2])
            && $tokens[$i + 2][0] === T_STRING) {
            $static_method = $tokens[$i + 2][1];
            if (!has_camel_hump($static_method) || in_array($static_method, $magic_methods, true)) {
                continue;
            }
            $static_class = $text;
            if (isset($uses[$static_class])) {
                $static_class = $uses[$static_class];
            } elseif (strpos($static_class, '\\') === false && $namespace !== '') {
                $static_class = $namespace . '\\' . $static_class;
            }
            if (is_allowlisted_call($site, $static_class, $static_method, $allowlist)) {
                continue;
            }
            foreach (inheritance_allowlist_bases($static_class) as $base) {
                if (is_allowlisted_call($site, $base, $static_method, $allowlist)) {
                    continue 2;
                }
            }
            if (strpos($static_class, 'Presta_Shop\\') === 0 || strpos($static_class, 'Tests\\') === 0) {
                $failures[] = sprintf('%s: repo-owned camelCase static call %s::%s', $site, $static_class, $static_method);
            } elseif (strpos($static_class, 'Symfony\\') === 0
                || strpos($static_class, 'PhpParser\\') === 0
                || strpos($static_class, 'PHPUnit\\') === 0
            ) {
                $failures[] = sprintf('%s: unallowlisted third-party static call %s::%s', $site, $static_class, $static_method);
            }
        }
    }
}

/**
 * @param list<string> $failures
 */
function verify_baseline_test_methods(string $root, array &$failures): void
{
    $baseline_path = $root . '/tests/baseline.json';
    if (!is_readable($baseline_path)) {
        $failures[] = 'tests/baseline.json is missing';
        return;
    }
    /** @var array{test_methods?: array<string, string>} $baseline */
    $baseline = json_decode((string) file_get_contents($baseline_path), true);
    if (!is_array($baseline) || !isset($baseline['test_methods']) || !is_array($baseline['test_methods'])) {
        $failures[] = 'tests/baseline.json missing test_methods';
        return;
    }
    foreach ($baseline['test_methods'] as $new_fq => $old) {
        [$class, $method] = explode('::', $new_fq, 2);
        if (!class_exists($class)) {
            $failures[] = sprintf('baseline class not loadable: %s (was %s)', $class, $old);
        } elseif (!method_exists($class, $method)) {
            $failures[] = sprintf('baseline method missing: %s (was %s)', $new_fq, $old);
        }
    }
}

/**
 * @return list<string>
 */
function scan_content(string $virtual_file, string $contents, array $allowlist, array $forbidden_identifiers, array $magic_methods): array
{
    $failures = [];
    scan_forbidden_tokens($virtual_file, $contents, $forbidden_identifiers, $failures);
    if (substr($virtual_file, -4) === '.php' || substr($virtual_file, -11) === 'header-stamp') {
        scan_php_tokens($virtual_file, $contents, $allowlist, $magic_methods, $failures); // virtual path is site key
    }

    return $failures;
}

function run_allowlist_demo(array $allowlist, array $forbidden_identifiers, array $magic_methods): void
{
    $allowed = <<<'PHP'
<?php
namespace Demo;
use Symfony\Component\Finder\SplFileInfo;
function demo(SplFileInfo $file): string {
    return $file->getContents();
}
PHP;
    $rejected = <<<'PHP'
<?php
namespace Presta_Shop\Header_Stamp;
class License_Header {
    public function demo(): string { return $this->getContents(); }
}
PHP;
    $allow_failures = scan_content('demo/allowed.php', $allowed, $allowlist, $forbidden_identifiers, $magic_methods);
    if ($allow_failures !== []) {
        fwrite(STDERR, "rename-check demo: allowed SplFileInfo::getContents must pass.\n");
        foreach ($allow_failures as $f) {
            fwrite(STDERR, $f . "\n");
        }
        exit(1);
    }
    $reject_failures = scan_content('demo/rejected.php', $rejected, $allowlist, $forbidden_identifiers, $magic_methods);
    $found_repo_camel = false;
    foreach ($reject_failures as $failure) {
        if (strpos($failure, 'getContents') !== false) {
            $found_repo_camel = true;
        }
    }
    if (!$found_repo_camel) {
        fwrite(STDERR, "rename-check demo: repo-owned getContents must be rejected outside allowlist.\n");
        exit(1);
    }

    $command_site = 'src/Command/Update_Licenses_Command.php';
    $mystery_reject = <<<'PHP'
<?php
namespace Presta_Shop\Header_Stamp\Command;
use Symfony\Component\Finder\Finder;
class Demo_Command {
    public function demo($mystery): void {
        $mystery->getContents();
    }
}
PHP;
    $mystery_failures = scan_content($command_site, $mystery_reject, $allowlist, $forbidden_identifiers, $magic_methods);
    $found_unresolved = false;
    foreach ($mystery_failures as $failure) {
        if (strpos($failure, 'unresolved camelCase method call getContents') !== false
            || strpos($failure, 'getContents') !== false) {
            $found_unresolved = true;
        }
    }
    if (!$found_unresolved) {
        fwrite(STDERR, "rename-check demo: \$mystery->getContents() must be rejected in command site.\n");
        foreach ($mystery_failures as $f) {
            fwrite(STDERR, $f . "\n");
        }
        exit(1);
    }

    $finder_allow = <<<'PHP'
<?php
namespace Presta_Shop\Header_Stamp\Command;
use Symfony\Component\Finder\Finder;
class Demo_Command {
    public function demo(): void {
        $finder = new Finder();
        $finder->files()->name('*.php')->in('/tmp')->exclude([])->notPath([]);
    }
}
PHP;
    $finder_failures = scan_content($command_site, $finder_allow, $allowlist, $forbidden_identifiers, $magic_methods);
    if ($finder_failures !== []) {
        fwrite(STDERR, "rename-check demo: chained Finder::notPath must pass on command site.\n");
        foreach ($finder_failures as $f) {
            fwrite(STDERR, $f . "\n");
        }
        exit(1);
    }
}

require $root . '/vendor/autoload.php';

run_allowlist_demo($allowlist, $forbidden_identifiers, $magic_methods);

$failures = [];
foreach (collect_php_files($root, $scan_roots) as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        continue;
    }
    $site = site_from_path($file, $root);
    if (basename($file) === 'header-stamp') {
        $site = 'bin/header-stamp';
    }
    foreach (scan_content($site, $contents, $allowlist, $forbidden_identifiers, $magic_methods) as $failure) {
        $failures[] = $failure;
    }
}

verify_baseline_test_methods($root, $failures);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "rename-check: no stale repo-owned names.\n");
exit(0);
