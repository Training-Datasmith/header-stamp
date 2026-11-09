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

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Presta_Shop\Header_Stamp\Command\Update_Licenses_Command;
use Presta_Shop\Header_Stamp\License_Header;
use Presta_Shop\Header_Stamp\Reporter;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;

class License_Header_Test extends TestCase
{
    public function test_get_content(): void
    {
        $license_header = new License_Header(__DIR__ . '/../../assets/afl.txt');
        $this->assert_command_finds_license_when_declare_precedes_namespace_docblock($license_header);
        $this->assertEquals('/**
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
', $license_header->get_content());
    }

    /**
     * @dataProvider get_contents_by_type
     */
    public function test_get_content_by_type(string $type, string $expected_content): void
    {
        $license_header = new License_Header(__DIR__ . '/../../assets/afl.txt');
        $this->assertEquals($expected_content, $license_header->get_content_by_type($type));
    }

    public static function get_contents_by_type(): iterable
    {
        yield 'php' => [
            'php',
            '/**
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
 */',
        ];

        yield 'tpl' => [
            'tpl',
            '{**
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
 *}',
        ];

        yield 'twig' => [
            'twig',
            '{#
 # Copyright since 2007 PrestaShop SA and Contributors
 # PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 #
 # NOTICE OF LICENSE
 #
 # This source file is subject to the Academic Free License version 3.0
 # that is bundled with this package in the file LICENSE.md.
 # It is also available through the world-wide-web at this URL:
 # https://opensource.org/licenses/AFL-3.0
 # If you did not receive a copy of the license and are unable to
 # obtain it through the world-wide-web, please send an email
 # to license@prestashop.com so we can send you a copy immediately.
 #
 # @author    PrestaShop SA and Contributors <contact@prestashop.com>
 # @copyright Since 2007 PrestaShop SA and Contributors
 # @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 #}',
        ];

        yield 'vue' => [
            'vue',
            '<!--*
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
 *-->',
        ];

        yield 'html' => [
            'html',
            '<!--*
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
 *-->',
        ];

        yield 'js' => [
            'js',
            '/**
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
 */',
        ];

        yield 'ts' => [
            'ts',
            '/**
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
 */',
        ];

        yield 'css' => [
            'css',
            '/**
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
 */',
        ];

        yield 'scss' => [
            'scss',
            '/**
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
 */',
        ];
    }

    /**
     * @dataProvider get_types_to_check
     */
    public function test_regex_matches_their_own_content(string $type_to_check): void
    {
        $licenses = [
            __DIR__ . '/../../assets/afl.txt',
            __DIR__ . '/../../assets/osl3.txt',
        ];

        foreach ($licenses as $license) {
            $license_header = new License_Header($license);
            // We get the license content for the specified type
            $type_content = $license_header->get_content_by_type($type_to_check);
            // Then we get the associated regx for the same type
            $type_regex = $license_header->get_regex_by_type($type_to_check);
            // We check that the generated regex is able to match the generated content, or else we won't
            // be able to detect nor replace existing headers, meaning it will create duplicates
            $this->assertTrue(preg_match($type_regex, $type_content) === 1);
        }
    }

    public static function get_types_to_check(): iterable
    {
        yield ['vue'];
        yield ['html'];
        yield ['tpl'];
        yield ['twig'];
        yield ['php'];
        yield ['js'];
        yield ['ts'];
        yield ['css'];
        yield ['scss'];
    }

    private function assert_command_finds_license_when_declare_precedes_namespace_docblock(License_Header $license): void
    {
        $header = $license->get_content_by_type('php');
        $workdir = sys_get_temp_dir() . '/header-stamp-declare-order-' . uniqid('', true);
        if (!mkdir($workdir) && !is_dir($workdir)) {
            throw new \RuntimeException('Could not create temp dir for declare-order check.');
        }
        $relative = 'Declare_Order_Sample.php';
        $payload = '<?php' . "\n"
            . "declare(strict_types=1);\n"
            . $header . "\n"
            . "namespace Header_Stamp_Sample;\n";
        file_put_contents($workdir . '/' . $relative, $payload);

        $command = new Update_Licenses_Command();
        $rc = new ReflectionClass($command);
        foreach (['run_as_dry', 'target_directory', 'license', 'license_header', 'reporter', 'discrimination_string'] as $prop) {
            $rp = $rc->getProperty($prop);
            $rp->setAccessible(true);
            if ($prop === 'run_as_dry') {
                $rp->setValue($command, true);
            } elseif ($prop === 'target_directory') {
                $rp->setValue($command, $workdir);
            } elseif ($prop === 'license') {
                $rp->setValue($command, __DIR__ . '/../../assets/afl.txt');
            } elseif ($prop === 'license_header') {
                $rp->setValue($command, $license);
            } elseif ($prop === 'reporter') {
                $rp->setValue($command, new Reporter());
            } elseif ($prop === 'discrimination_string') {
                $rp->setValue($command, ['PrestaShop SA and Contributors', 'NOTICE OF LICENSE']);
            }
        }

        $parser = (new \PhpParser\ParserFactory())->create(\PhpParser\ParserFactory::PREFER_PHP7);
        $nodes = $parser->parse(file_get_contents($workdir . '/' . $relative));
        if (!is_array($nodes)) {
            throw new \RuntimeException('Parser did not return an array of nodes.');
        }
        $file = new SplFileInfo($workdir . '/' . $relative, '', $relative);
        $rm = $rc->getMethod('add_license_to_nodes');
        $rm->setAccessible(true);
        $rm->invoke($command, $nodes, $file);

        $reporter = $rc->getProperty('reporter');
        $reporter->setAccessible(true);
        /** @var Reporter $rep */
        $rep = $reporter->getValue($command);
        $report = $rep->get_report();
        if ($report['fixed'] !== []) {
            throw new \RuntimeException('Declare-before-namespace license must not be reported as missing.');
        }
    }
}
