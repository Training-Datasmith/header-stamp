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

namespace Tests\Integration\Tools;

/**
 * @see https://github.com/sureshdotariya/folder-compare/
 */
class Folder_Comparator
{
    private $blacklist = [
        '.',
        '..',
        '.git',
        'autoload.php',
        'autoload_classmap.php',
        'autoload_static.php',
        'autoload_real.php',
        'ClassLoader.php',
    ];

    /**
     * @param string $folder_a
     * @param string $folder_b
     * @param string $base_path
     *
     * @return array<int, string> list of items that differ
     */
    public function compare_folders($folder_a, $folder_b, $base_path = '')
    {
        $items_differ = [];
        $handle = opendir($folder_a);

        while (($file = readdir($handle)) !== false) {
            if (in_array($file, $this->blacklist)) {
                continue;
            }

            $file_a = $folder_a . DIRECTORY_SEPARATOR . $file;
            $file_b = $folder_b . DIRECTORY_SEPARATOR . $file;
            $full_path = $base_path . DIRECTORY_SEPARATOR . $file;

            if (is_file($file_a)) {
                if (!file_exists($file_b)) {
                    $items_differ[] = $full_path . ' is missing';
                } else {
                    if (is_file($file_b)) {
                        if (md5_file($file_a) !== md5_file($file_b)) {
                            $items_differ[] = $full_path . ' has different md5';
                        }
                    } elseif (is_dir($file_b)) {
                        $items_differ[] = $full_path . ' is once a dir, once a file';
                    }
                }
            } else {
                $items_differ = array_merge(
                    $items_differ,
                    $this->compare_folders($file_a, $file_b, $full_path)
                );
            }
        }

        closedir($handle);

        return $items_differ;
    }
}
