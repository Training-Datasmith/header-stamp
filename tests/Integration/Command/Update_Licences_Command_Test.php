<?php

declare(strict_types=1);
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

namespace Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use Presta_Shop\Header_Stamp\Command\Update_Licenses_Command;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Tests\Integration\Tools\Folder_Comparator;

class Update_Licences_Command_Test extends TestCase
{
    private static $workspace_folder;
    private static $fs;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$workspace_folder = __DIR__ . '/../../Resources/workspace/';
        self::clean_workspace();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::clean_workspace();
    }

    protected static function clean_workspace(): void
    {
        // Remove and re-create folder to make it empty and existent
        self::$fs = new Filesystem();
        $finder = new Finder();
        foreach ($finder->directories()->depth(1)->in(self::$workspace_folder) as $directory) {
            if (is_dir($directory->getRealPath())) {
                self::$fs->remove($directory->getRealPath());
            }
        }
    }

    /**
     * @dataProvider get_folders_to_test
     */
    public function test_command_modifications(string $folder_to_test, bool $is_folder_valid, array $invalid_files = [], string $discrimination_param = ''): void
    {
        // Prepare module workspace
        $module_source = __DIR__ . '/../../Resources/module-samples/' . $folder_to_test;
        $module_expected = __DIR__ . '/../../Resources/expected/' . $folder_to_test;
        $module_workspace = self::$workspace_folder . $folder_to_test;
        if (is_dir($module_workspace)) {
            self::$fs->remove($module_workspace);
        }
        self::$fs->mirror($module_source, $module_workspace);

        // Execute command
        $application = new Application();
        $application->add(new Update_Licenses_Command());
        $command = $application->find('prestashop:licenses:update');
        $command_tester = new CommandTester($command);
        $command_parameters = [
            '--license' => __DIR__ . '/../../../assets/afl.txt',
            '--target' => $module_workspace,
            '--not-name' => '*.min.js',
            '--exclude' => 'ignoredFolder',
        ];
        if (!empty($discrimination_param)) {
            $command_parameters['--header-discrimination-string'] = $discrimination_param;
        }

        $command_tester->execute($command_parameters);

        // Compare folders
        $folder_comparator = new Folder_Comparator();
        $check = $folder_comparator->compare_folders($module_expected, $module_workspace);
        $check2 = $folder_comparator->compare_folders($module_workspace, $module_expected);
        $this->assertEquals([], $check);
        $this->assertEquals([], $check2);

        // Check the corrections based on the expected status of the module (to make sure fixtures are not broken)
        $corrections = $folder_comparator->compare_folders($module_expected, $module_source);
        sort($corrections);

        // Format expected messages based on file name list
        $invalid_files = array_map(static function (string $file_name): string {
            return '/' . $file_name . ' has different md5';
        }, $invalid_files);
        sort($invalid_files);
        $this->assertEquals($invalid_files, $corrections);

        // If all is good we can already clean the workspace folder
        self::$fs->remove($module_workspace);
    }

    /**
     * @dataProvider get_folders_to_test
     */
    public function test_command_dry_run(string $folder_to_test, bool $is_folder_valid, array $invalid_files = []): void
    {
        // Prepare module workspace
        $module_source = __DIR__ . '/../../Resources/module-samples/' . $folder_to_test;
        $module_workspace = self::$workspace_folder . $folder_to_test;
        if (is_dir($module_workspace)) {
            self::$fs->remove($module_workspace);
        }
        self::$fs->mirror($module_source, $module_workspace);

        // Execute command
        $application = new Application();
        $application->add(new Update_Licenses_Command());
        $command = $application->find('prestashop:licenses:update');
        $command_tester = new CommandTester($command);
        $command_parameters = [
            '--license' => __DIR__ . '/../../../assets/afl.txt',
            '--target' => $module_workspace,
            '--not-name' => '*.min.js',
            '--exclude' => 'ignoredFolder',
            '--dry-run' => true,
        ];
        if (!empty($discrimination_param)) {
            $command_parameters['--header-discrimination-string'] = $discrimination_param;
        }

        $command_result = $command_tester->execute($command_parameters);
        $this->assertEquals($is_folder_valid ? 0 : 1, $command_result);

        $command_output = $command_tester->getDisplay();
        if (!empty($invalid_files)) {
            // First find the fixed files block
            $matches = [];
            $this->assertNotFalse(preg_match('/ Files with bad license headers:\n( \* [^\n]+\n)+/', $command_output, $matches));
            // Then extract the list from it
            $matches2 = [];
            preg_match_all('/( \* [^\n]+\n)/', $matches[0], $matches2);
            $this->assertEquals(count($matches2[0]), count($invalid_files));
            $output_files = [];
            foreach ($matches2[0] as $file) {
                $output_files[] = str_replace(["\n", ' * '], '', $file);
            }
            sort($output_files);
            sort($invalid_files);
            $this->assertEquals($output_files, $invalid_files);
        } else {
            $this->assertFalse(str_contains($command_output, 'Files with bad license headers:'));
        }

        // If all is good we can already clean the workspace folder
        self::$fs->remove($module_workspace);
    }

    public static function get_folders_to_test(): iterable
    {
        // Contains most of the files to be fixed:
        // - files without headers
        // - files with headers but not the proper license
        // - files that don't need to be modified
        // - files with old format of headers (mostly twig)
        // - an ignoredFolder that shouldn't be modified (passed as the argument)
        // - and ignoredFile (fake.min.js) that shouldn't be modified (passed as the argument)
        yield 'fakemodule, reference with invalid files of many types to validate them all' => [
            'fakemodule',
            false,
            [
                'Fake_Class_Without_Header.php',
                'Fake_Class_Without_Header_But_Comment.php',
                'views/css/app.css',
                'views/js/wrong_header.js',
                'views/js/app.js',
                'views/templates/app.tpl',
                'views/templates/old_v8_license_header.html.twig',
                'views/templates/no_license_header_but_comment.html.twig',
                'views/templates/invalid_v90_linter_format.html.twig',
                'views/templates/invalid_osl_header.html.twig',
                'views/templates/no_comment.html.twig',
                'views/templates/app.html.twig',
                'views/templates/missing-header.vue',
                'views/templates/wrong-osl-header.vue',
                'Fake_Class_With_Wrong_Header.php',
                'composer.json',
            ],
            // Use two discriminators (to check the implode works as expected)
            'PrestaShop SA and Contributors,NOTICE OF LICENSE',
        ];

        yield 'valid module gsitemap' => [
            'gsitemap',
            true,
            [],
            // Use only one discriminator
            'NOTICE OF LICENSE',
        ];

        yield 'valid module dashproducts' => [
            'dashproducts',
            true,
            [],
            // Use only one discriminator, but the other one (both are supposed to work with our test resources anyway)
            'PrestaShop SA and Contributors',
        ];

        yield 'existing-headers-discrimination' => [
            'existing-headers-discrimination',
            false,
            [
                'existing-headers-discrimination.php',
            ],
            'friendsofpresta',
        ];

        yield 'smart-headers' => [
            'smart-headers',
            false,
            [
                'emptynewlineheader.php',
            ],
        ];
    }
}
