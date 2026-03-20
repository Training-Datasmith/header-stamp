<?php

declare(strict_types=1);

/**
 * Example: stamp license headers on a directory of PHP files.
 *
 * Run from the header-stamp project root:
 *   php examples/stamp_php_files.php
 *
 * Or use the CLI binary directly:
 *   vendor/bin/header-stamp --license=assets/osl3.txt --target=src/
 */

require __DIR__ . '/../vendor/autoload.php';

use PrestaShop\HeaderStamp\LicenseHeader;

// Load the default OSL 3.0 license text shipped with this package.
$licenseHeader = new LicenseHeader(__DIR__ . '/../assets/osl3.txt');

// Retrieve the license block formatted as a PHP comment (/** ... */).
$phpBlock = $licenseHeader->get_content_by_type('php');
echo "PHP comment block:\n";
echo $phpBlock . "\n\n";

// Retrieve the same license formatted for a Twig template ({# ... #}).
$twigBlock = $licenseHeader->get_content_by_type('twig');
echo "Twig comment block:\n";
echo $twigBlock . "\n\n";

// Get the regex that can detect an existing PHP license header so it can be replaced.
$phpRegex = $licenseHeader->get_regex_by_type('php');
echo "PHP detection regex:\n";
echo $phpRegex . "\n";
