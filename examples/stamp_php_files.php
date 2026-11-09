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

use Presta_Shop\Header_Stamp\License_Header;

// Load the default OSL 3.0 license text shipped with this package.
$license_header = new License_Header(__DIR__ . '/../assets/osl3.txt');

// Retrieve the license block formatted as a PHP comment (/** ... */).
$php_block = $license_header->get_content_by_type('php');
echo "PHP comment block:\n";
echo $php_block . "\n\n";

// Retrieve the same license formatted for a Twig template ({# ... #}).
$twig_block = $license_header->get_content_by_type('twig');
echo "Twig comment block:\n";
echo $twig_block . "\n\n";

// Get the regex that can detect an existing PHP license header so it can be replaced.
$php_regex = $license_header->get_regex_by_type('php');
echo "PHP detection regex:\n";
echo $php_regex . "\n";
