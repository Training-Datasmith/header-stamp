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
