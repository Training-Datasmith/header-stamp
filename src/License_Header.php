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
declare (strict_types=1);
namespace Presta_Shop\Header_Stamp;

use Exception;
/**
 * Class responsible of loading license file in memory and returning its content
 */
class License_Header
{
    /**
     * Header content
     *
     * @var ?string
     */
    private $content;
    private $content_by_types;
    private $regex_by_types;
    /**
     * Path to the file
     *
     * @var string
     */
    private $file_path;
    public function __construct(string $file_path)
    {
        $this->file_path = $file_path;
        $this->content_by_types = [];
        $this->regex_by_types = [];
    }
    /**
     * @return string Getter for Header content
     */
    public function get_content(): string
    {
        if (null === $this->content) {
            $this->content = $this->load_file();
        }
        return $this->content;
    }
    /**
     * Returns the license header text reformatted for the given file extension type.
     *
     * Rewrites the raw `/**` PHP comment block into the correct comment syntax
     * for each supported type (HTML, Twig, Smarty `.tpl`, PHP, JS, TS, CSS, SCSS).
     *
     * @param string $type File extension without the leading dot (e.g. 'php', 'twig', 'vue').
     *
     * @return string The license text wrapped in comment delimiters appropriate for the type.
     *
     * @throws \RuntimeException When an unsupported file type is requested.
     */
    public function get_content_by_type(string $type): string
    {
        if (!isset($this->content_by_types[$type])) {
            switch ($type) {
                case 'vue':
                case 'html':
                    $this->content_by_types[$type] = $this->rewrite_content($this->get_content(), '<!--*', '*-->');
                    break;
                case 'tpl':
                    $this->content_by_types[$type] = $this->rewrite_content($this->get_content(), '{**', '*}');
                    break;
                case 'twig':
                    $this->content_by_types[$type] = $this->rewrite_content($this->get_content(), '{#', '#}', ' #');
                    break;
                case 'php':
                case 'js':
                case 'ts':
                case 'css':
                case 'scss':
                    $this->content_by_types[$type] = $this->rewrite_content($this->get_content(), '/**', '*/');
                    break;
                default:
                    throw new \RuntimeException('Unknown type ' . $type);
            }
        }
        return $this->content_by_types[$type];
    }
    /**
     * Returns a regular expression that matches an existing license comment block for the given type.
     *
     * Used to detect and replace pre-existing header blocks rather than prepending a duplicate.
     *
     * @param string $type File extension without the leading dot (e.g. 'php', 'twig', 'vue').
     *
     * @return string A PCRE pattern (with `%` as delimiter) that matches the expected comment block.
     *
     * @throws \RuntimeException When an unsupported file type is requested.
     */
    public function get_regex_by_type(string $type): string
    {
        if (!isset($this->regex_by_types[$type])) {
            switch ($type) {
                case 'vue':
                case 'html':
                    $this->regex_by_types[$type] = $this->get_license_regex('<!--', '-->');
                    break;
                case 'tpl':
                    $this->regex_by_types[$type] = $this->get_license_regex('{', '}');
                    break;
                case 'twig':
                    $this->regex_by_types[$type] = $this->get_twig_license_regex('{#', '#}', '#');
                    break;
                case 'php':
                case 'js':
                case 'ts':
                case 'css':
                case 'scss':
                    $this->regex_by_types[$type] = $this->get_license_regex('\/', '\/');
                    break;
                default:
                    throw new \RuntimeException('Unknown type ' . $type);
            }
        }
        return $this->regex_by_types[$type];
    }
    /**
     * Builds a regex pattern that matches a Twig-style block comment (e.g. `{# ... #}`).
     *
     * Twig comments use `{#` / `#}` delimiters and `#` as the inner comment prefix,
     * which differs from the standard `/** ... *\/` PHP block handled by `get_license_regex()`.
     *
     * @param string $start_delimiter   Opening comment token (e.g. `{#`).
     * @param string $end_delimiter     Closing comment token (e.g. `#}`).
     * @param string $comment_delimiter Per-line prefix character inside the block (e.g. `#`).
     *
     * @return string A PCRE pattern anchored to the start of the string (`^`).
     */
    public function get_twig_license_regex(string $start_delimiter, string $end_delimiter, string $comment_delimiter = '*'): string
    {
        $start_delimiter = addcslashes($start_delimiter, '*#{}');
        $end_delimiter = addcslashes($end_delimiter, '*#{}');
        $comment_delimiter = addcslashes($comment_delimiter, '*#{}');
        return '%^' . $start_delimiter . '([^' . $comment_delimiter . ']|[\r\n]|(' . $comment_delimiter . '+((?!\})|[\r\n])))*+' . $end_delimiter . '%';
    }
    private function get_license_regex(string $start_delimiter, string $end_delimiter): string
    {
        // Regular expression found thanks to Stephen Ostermiller's Blog. http://blog.ostermiller.org/find-comment
        // $regex = '%^' . $startDelimiter . '\*([^*]|[\r\n]|(\*+([^*' . $endDelimiter . ']|[\r\n])))*\*+' . $endDelimiter . '%';
        // Initial regex was improved for special cases in Twig
        return '%^' . $start_delimiter . '\*([^*]|[\r\n]|(\*+((?!' . $end_delimiter . ')|[\r\n])))*\*+' . $end_delimiter . '%';
    }
    private function rewrite_content(string $text, string $start_delimiter, string $end_delimiter, ?string $comment_delimiter = null): string
    {
        // Adapt the license header with expected delimiters
        $text = rtrim($text, PHP_EOL);
        $text = $start_delimiter . ltrim($text, '/**');
        $text = rtrim($text, '*/') . $end_delimiter;
        if (null !== $comment_delimiter) {
            $text = (string) preg_replace('% \*%', $comment_delimiter, $text);
        }
        return trim($text, PHP_EOL);
    }
    /**
     * Checks the file and loads its content in memory
     */
    private function load_file(): string
    {
        if (!\file_exists($this->file_path)) {
            // If the file is not found, we might have a relative path
            // We check this before throwing any exception
            $from_relative_file_path = getcwd() . '/' . $this->file_path;
            $from_src_folder_file_path = __DIR__ . '/../' . $this->file_path;
            if (\file_exists($from_relative_file_path)) {
                $this->file_path = $from_relative_file_path;
            } elseif (\file_exists($from_src_folder_file_path)) {
                $this->file_path = $from_src_folder_file_path;
            } else {
                throw new \Exception('File ' . $this->file_path . ' does not exist.');
            }
        }
        if (!\is_readable($this->file_path)) {
            throw new \Exception('File ' . $this->file_path . ' cannot be read.');
        }
        $content = \file_get_contents($this->file_path);
        if ($content === false) {
            throw new Exception('Cannot load license file ' . $this->file_path . '.');
        }
        return $content;
    }
}