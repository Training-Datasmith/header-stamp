<?php

declare(strict_types=1);

$root = dirname(__DIR__);

/** @var list<string> */
$scan_roots = [
    'src',
    'bin',
    'examples',
    'tests',
];

/** @var array<string, list<string>> site => list of Class::method */
$allowlist = [
    'global' => [
        'Symfony\\Component\\Console\\Command\\Command::setName',
        'Symfony\\Component\\Console\\Command\\Command::setDescription',
        'Symfony\\Component\\Console\\Command\\Command::addOption',
        'Symfony\\Component\\Console\\Command\\Command::getName',
        'Symfony\\Component\\Console\\Input\\InputInterface::hasParameterOption',
        'Symfony\\Component\\Console\\Input\\InputInterface::getOption',
        'Symfony\\Component\\Finder\\Finder::notPath',
        'Symfony\\Component\\Finder\\Finder::notName',
        'Symfony\\Component\\Finder\\SplFileInfo::getExtension',
        'Symfony\\Component\\Finder\\SplFileInfo::getContents',
        'Symfony\\Component\\Finder\\SplFileInfo::getRelativePathname',
        'Symfony\\Component\\Finder\\SplFileInfo::getFilename',
        'Symfony\\Component\\Finder\\SplFileInfo::getRealPath',
        'Symfony\\Component\\Console\\Helper\\ProgressBar::setRedrawFrequency',
        'Symfony\\Component\\Console\\Style\\SymfonyStyle::section',
        'Symfony\\Component\\Console\\Style\\SymfonyStyle::text',
        'Symfony\\Component\\Console\\Style\\SymfonyStyle::listing',
        'Symfony\\Component\\Console\\Application::add',
        'Symfony\\Component\\Console\\Application::find',
        'Symfony\\Component\\Console\\Application::setDefaultCommand',
        'Symfony\\Component\\Console\\Application::run',
        'Symfony\\Component\\Console\\Tester\\CommandTester::execute',
        'Symfony\\Component\\Console\\Tester\\CommandTester::getDisplay',
        'Symfony\\Component\\Filesystem\\Filesystem::remove',
        'Symfony\\Component\\Filesystem\\Filesystem::mirror',
        'PhpParser\\ParserFactory::create',
        'PhpParser\\Node::hasAttribute',
        'PhpParser\\Node::getAttribute',
        'PhpParser\\Comment::getText',
        'PHPUnit\\Framework\\TestCase::assertEquals',
        'PHPUnit\\Framework\\TestCase::assertTrue',
        'PHPUnit\\Framework\\TestCase::assertFalse',
        'PHPUnit\\Framework\\TestCase::assertNotFalse',
        'PHPUnit\\Framework\\TestCase::setUpBeforeClass',
        'PHPUnit\\Framework\\TestCase::tearDownAfterClass',
        'Tests\\Integration\\Command\\Update_Licences_Command_Test::setUpBeforeClass',
        'Tests\\Integration\\Command\\Update_Licences_Command_Test::tearDownAfterClass',
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

function is_allowlisted_call(string $class, string $method, array $allowlist): bool
{
    $key = $class . '::' . $method;
    foreach ($allowlist as $site => $entries) {
        foreach ($entries as $entry) {
            if ($entry === $key) {
                return true;
            }
        }
    }

    return false;
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

function resolve_object_call_receiver(array $tokens, int $op_index, string $namespace, string $class, array $uses): string
{
    $paren_depth = 0;
    for ($j = $op_index - 1; $j >= 0; --$j) {
        $tok = $tokens[$j];
        if (is_array($tok) && $tok[0] === T_WHITESPACE) {
            continue;
        }
        if ($tok === ')') {
            $paren_depth++;
            continue;
        }
        if ($tok === '(') {
            if ($paren_depth > 0) {
                $paren_depth--;
                continue;
            }
            continue;
        }
        if ($paren_depth > 0) {
            continue;
        }
        if (is_array($tok) && $tok[0] === T_VARIABLE) {
            $var = $tok[1];
            if ($var === '$this') {
                return trim($namespace, '\\') . '\\' . $class;
            }
            $key = ltrim($var, '$');
            if (isset($uses[$key])) {
                return $uses[$key];
            }

            return '';
        }
        if (is_array($tok) && $tok[0] === T_STRING) {
            continue;
        }
        if ($tok === ';' || $tok === '{' || $tok === '}') {
            break;
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
function scan_php_tokens(string $file, string $contents, array $allowlist, array $magic_methods, array &$failures): void
{
    $tokens = token_get_all($contents);
    $count = count($tokens);
    $namespace = '';
    $class = '';
    $uses = [];

    for ($i = 0; $i < $count; ++$i) {
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
                            $failures[] = sprintf('%s: repo-owned camelCase class %s', $file, $class);
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
                    $failures[] = sprintf('%s: repo-owned camelCase function %s', $file, $fn);
                }
            }
        }

        if ($id === T_VARIABLE && preg_match('/^\$[a-z]+[A-Z]/', $text)) {
            if (strpos($file, '/tests/Resources/') === false) {
                $failures[] = sprintf('%s: repo-owned camelCase variable %s', $file, $text);
            }
        }

        if ($id === T_STRING && $text === 'parent' && isset($tokens[$i + 1]) && $tokens[$i + 1] === '::') {
            $method = '';
            if (isset($tokens[$i + 2]) && is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_STRING) {
                $method = $tokens[$i + 2][1];
            }
            if ($method !== '' && has_camel_hump($method)) {
                if (!is_allowlisted_call('PHPUnit\\Framework\\TestCase', $method, $allowlist)
                    && !is_allowlisted_call($namespace . '\\' . $class, $method, $allowlist)
                    && !in_array($method, $magic_methods, true)) {
                    if (strpos($namespace, 'Tests\\') === 0) {
                        $fq = $namespace . '\\' . $class . '::' . $method;
                        $failures[] = sprintf('%s: unallowlisted parent call %s', $file, $fq);
                    }
                }
            }
        }

        if ($id === T_OBJECT_OPERATOR && isset($tokens[$i + 1]) && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] === T_STRING) {
            $method = $tokens[$i + 1][1];
            if (!has_camel_hump($method)) {
                continue;
            }
            $resolved_receiver = resolve_object_call_receiver($tokens, $i, $namespace, $class, $uses);
            if ($resolved_receiver === '') {
                continue;
            }

            $allowed = false;
            foreach (inheritance_allowlist_bases($resolved_receiver) as $base) {
                if (is_allowlisted_call($base, $method, $allowlist)) {
                    $allowed = true;
                    break;
                }
            }
            if ($allowed || is_allowlisted_call($resolved_receiver, $method, $allowlist)) {
                continue;
            }

            if (strpos($resolved_receiver, 'Symfony\\') === 0
                || strpos($resolved_receiver, 'PhpParser\\') === 0
                || strpos($resolved_receiver, 'PHPUnit\\') === 0
            ) {
                $failures[] = sprintf('%s: unallowlisted third-party call %s::%s', $file, $resolved_receiver, $method);
            } elseif (strpos($resolved_receiver, 'Presta_Shop\\') === 0 || strpos($resolved_receiver, 'Tests\\') === 0) {
                $failures[] = sprintf('%s: repo-owned camelCase method call %s::%s', $file, $resolved_receiver, $method);
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
        scan_php_tokens($virtual_file, $contents, $allowlist, $magic_methods, $failures);
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
}

require $root . '/vendor/autoload.php';

run_allowlist_demo($allowlist, $forbidden_identifiers, $magic_methods);

$failures = [];
foreach (collect_php_files($root, $scan_roots) as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        continue;
    }
    foreach (scan_content($file, $contents, $allowlist, $forbidden_identifiers, $magic_methods) as $failure) {
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
