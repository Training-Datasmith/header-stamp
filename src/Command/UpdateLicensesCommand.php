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
namespace Presta_Shop\Header_Stamp\Command;

use Exception;
use Php_Parser\Node\Stmt;
use Php_Parser\Parser_Factory;
use Presta_Shop\Header_Stamp\License_Header;
use Presta_Shop\Header_Stamp\Reporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Progress_Bar;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Spl_File_Info;
use Symfony\Component\Yaml\Yaml;
class Update_Licenses_Command extends Command
{
    public const CONFIG_PARAMETERS_MAPPING = ['extensions' => 'extensions', 'excludedFiles' => 'exclude', 'notNamePatterns' => 'not-name', 'license' => 'license', 'targetDirectory' => 'target', 'runAsDry' => 'dry-run', 'displayReport' => 'display-report', 'discriminationString' => 'header-discrimination-string'];
    public const DEFAULT_CONFIG = ['extensions' => ['php', 'js', 'ts', 'css', 'scss', 'tpl', 'html.twig', 'json', 'vue'], 'excludedFiles' => ['vendor', 'node_modules'], 'notNamePatterns' => [], 'license' => __DIR__ . '/../../assets/osl3.txt', 'targetDirectory' => '', 'runAsDry' => false, 'displayReport' => false, 'discriminationString' => ['PrestaShop SA and Contributors', 'NOTICE OF LICENSE']];
    public const DEFAULT_CONFIG_FILE = '.header-stamp-config.yml';
    /**
     * @var LicenseHeader
     */
    private $license_header;
    /**
     * License file path (not content)
     *
     * @var string
     */
    private $license;
    /**
     * @var string Can be false because of realpath function
     */
    private $target_directory;
    /**
     * List of extensions to update
     *
     * @var array<int, string>
     */
    private $extensions;
    /**
     * List of folders/files to exclude from the search
     *
     * @var array<int, string>
     */
    private $excluded_files;
    /**
     * List of file names to exclude from the search
     *
     * @var array<int, string>
     */
    private $not_name_patterns;
    /**
     * dry-run feature flag
     *
     * @var bool
     */
    private $run_as_dry;
    /**
     * display-report feature flag
     *
     * @var bool
     */
    private $display_report;
    /**
     * Reporter in charge of monitoring what is done and provide a complete report
     * at the end of execution
     *
     * @var Reporter
     */
    private $reporter;
    /**
     * @var string[]
     */
    private $discrimination_string;
    protected function configure(): void
    {
        // Note: we don't use short parameter because they are badly handled by hasParameterOption
        // We also define NO default values because they are already handled via the default config const,
        // and it's important to keep this separated for the override system to work properly (default < config
        // file < cli parameter)
        $this->set_name('prestashop:licenses:update')->set_description('Rewrite your file headers to add the license or to make them up-to-date')->add_option('license', null, Input_Option::VALUE_REQUIRED, 'License file to apply')->add_option('target', null, Input_Option::VALUE_REQUIRED, 'Folder to work in (default: current dir)')->add_option('exclude', null, Input_Option::VALUE_REQUIRED, 'Comma-separated list of folders/files to exclude from the update')->add_option('not-name', null, Input_Option::VALUE_REQUIRED, 'Comma-separated list of file patterns to exclude from the update (ex: *.min.js)')->add_option('extensions', null, Input_Option::VALUE_REQUIRED, 'Comma-separated list of file extensions to update')->add_option('display-report', null, Input_Option::VALUE_NONE, 'Whether or not to display a report')->add_option('dry-run', null, Input_Option::VALUE_NONE, 'Dry-run mode does not modify files')->add_option('header-discrimination-string', null, Input_Option::VALUE_OPTIONAL, 'Fix existing licenses only if they contain that string (multiple values separated by comma are possible)')->add_option('config', null, Input_Option::VALUE_OPTIONAL, 'Use YAML config file instead of individual options');
    }
    protected function initialize(Input_Interface $input, Output_Interface $output): void
    {
        $file_config = $this->get_config_from_file($input);
        $token_config = $this->get_token_config($input);
        $merged_config = array_merge(
            static::DEFAULT_CONFIG,
            // Config file has more priority that the default values (especially the automatic fallbacks)
            $file_config,
            // But explicit individual parameter still has more value than the config file
            $token_config
        );
        // Adapt config to have absolute real path
        if (!empty($merged_config['license'])) {
            $resolved_license = realpath($merged_config['license']);
            if ($resolved_license === false) {
                throw new \RuntimeException(sprintf('License file not found or inaccessible: "%s"', $merged_config['license']));
            }
            $merged_config['license'] = $resolved_license;
        }
        if (!empty($merged_config['targetDirectory'])) {
            $resolved_target = realpath($merged_config['targetDirectory']);
            if ($resolved_target === false) {
                throw new \RuntimeException(sprintf('Target directory not found or inaccessible: "%s"', $merged_config['targetDirectory']));
            }
            $merged_config['targetDirectory'] = $resolved_target;
        } else {
            $merged_config['targetDirectory'] = (string) getcwd();
        }
        // Adapt string CLI parameter into arrays
        if (is_string($merged_config['extensions'])) {
            $merged_config['extensions'] = explode(',', $merged_config['extensions']);
        }
        if (is_string($merged_config['excludedFiles'])) {
            $merged_config['excludedFiles'] = explode(',', $merged_config['excludedFiles']);
        }
        if (is_string($merged_config['notNamePatterns'])) {
            $merged_config['notNamePatterns'] = explode(',', $merged_config['notNamePatterns']);
        }
        if (is_string($merged_config['discriminationString'])) {
            $merged_config['discriminationString'] = explode(',', $merged_config['discriminationString']);
        }
        // Adapt boolean parameters
        $merged_config['runAsDry'] = filter_var($merged_config['runAsDry'], FILTER_VALIDATE_BOOLEAN);
        $merged_config['displayReport'] = filter_var($merged_config['displayReport'], FILTER_VALIDATE_BOOLEAN);
        // Now apply the config to the command fields
        $this->extensions = $merged_config['extensions'];
        $this->excluded_files = $merged_config['excludedFiles'];
        $this->not_name_patterns = $merged_config['notNamePatterns'];
        $this->license = $merged_config['license'];
        $this->target_directory = $merged_config['targetDirectory'];
        $this->run_as_dry = $merged_config['runAsDry'];
        $this->display_report = $merged_config['displayReport'];
        $this->discrimination_string = $merged_config['discriminationString'];
        // Output configuration
        $output->writeln('Header stamp configuration:');
        foreach (array_keys(self::CONFIG_PARAMETERS_MAPPING) as $command_field) {
            $config_value = $merged_config[$command_field];
            if (is_array($config_value)) {
                $config_value = implode(', ', $config_value);
            } elseif (is_bool($config_value)) {
                $config_value = $config_value ? 'true' : 'false';
            }
            $output->writeln(sprintf(' - %s: %s', $command_field, $config_value));
        }
    }
    /**
     * Return the config only based on explicitly specified parameters in the CLI command.
     *
     * @return array{extensions?: string[], excludedFiles?: string[], notNamePatterns?: string[], license?: string, targetDirectory?: string, runAsDry?: bool, displayReport?: bool, discriminationString?: string[]}
     */
    protected function get_token_config(Input_Interface $input): array
    {
        $token_config = [];
        foreach (self::CONFIG_PARAMETERS_MAPPING as $config_parameter => $cli_parameter) {
            // Only keep parameters that are explicitly specified as CLI parameters
            if ($input->has_parameter_option('--' . $cli_parameter)) {
                $token_config[$config_parameter] = $input->get_option($cli_parameter);
            }
        }
        return $token_config;
    }
    /**
     * Return the config defined in the config file (when present), the yaml file
     * can use the same keys as the CLI command parameters OR the config ones (they
     * even have a higher priority).
     *
     * Ex: notNamePatterns will be preferred over not-name
     *
     * @return array{extensions?: string, excludedFiles?: string, notNamePatterns?: string, license?: string, targetDirectory?: string, runAsDry?: bool, displayReport?: bool, discriminationString?: string}
     */
    protected function get_config_from_file(Input_Interface $input): array
    {
        $config_from_file = [];
        $config_file = null;
        // When config is explicitly passed the file MUST exist
        if ($input->has_parameter_option('--config')) {
            $config_file = $input->get_option('config');
            if (!file_exists($config_file)) {
                throw new \RuntimeException(sprintf('The config file "%s" was not found', $config_file));
            }
        } elseif (file_exists(static::DEFAULT_CONFIG_FILE)) {
            // For the default config file, it can be used by convention but if it's not present we gracefully ignore it
            $config_file = static::DEFAULT_CONFIG_FILE;
        }
        if (null !== $config_file) {
            $parsed_config = Yaml::parse(file_get_contents($config_file) ?: '');
            // Transform parameters with the appropriate naming when they are present in the yaml file
            foreach (self::CONFIG_PARAMETERS_MAPPING as $parameter => $value) {
                $parameter_name = self::CONFIG_PARAMETERS_MAPPING[$parameter];
                if (isset($parsed_config[$parameter_name])) {
                    $config_from_file[$parameter] = $parsed_config[$parameter_name];
                }
                // If the YAML contains a value using the config property name directly it is prioritized
                if (isset($parsed_config[$parameter])) {
                    $config_from_file[$parameter] = $parsed_config[$parameter];
                }
            }
        }
        return $config_from_file;
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $this->license_header = new License_Header($this->license);
        $this->reporter = new Reporter();
        foreach ($this->extensions as $extension) {
            $this->find_and_check_extension($output, $extension);
        }
        if ($this->run_as_dry) {
            $this->print_dry_run_pretty_report($input, $output);
            if (empty($this->reporter->get_report()['fixed'])) {
                return 0;
            }
            return 1;
        }
        if ($this->display_report) {
            $this->print_pretty_report($input, $output);
        }
        return 0;
    }
    private function find_and_check_extension(Output_Interface $output, string $ext): void
    {
        if (!is_dir($this->target_directory)) {
            throw new \Exception('Could not get target directory. Check your permissions.');
        }
        $finder = new Finder();
        $finder->files()->name('*.' . $ext)->in($this->target_directory)->exclude($this->excluded_files)->not_path($this->excluded_files)->not_name($this->not_name_patterns);
        $parser = (new Parser_Factory())->create(Parser_Factory::PREFER_PHP7);
        $output->writeln('Updating license in ' . strtoupper($ext) . ' files ...');
        $progress = new Progress_Bar($output, count($finder));
        $progress->start();
        $progress->set_redraw_frequency(20);
        foreach ($finder as $file) {
            switch ($file->get_extension()) {
                case 'php':
                    try {
                        $nodes = $parser->parse($file->get_contents());
                        if ($nodes !== null && count($nodes)) {
                            $this->add_license_to_node($nodes[0], $file);
                        }
                    } catch (\Php_Parser\Error $exception) {
                        $output->writeln('Syntax error on file ' . $file->get_relative_pathname() . '. Continue ...');
                        $this->reporter->report_license_could_not_be_fixed($file->get_filename());
                    }
                    break;
                case 'js':
                case 'ts':
                case 'css':
                case 'scss':
                case 'vue':
                case 'html':
                case 'tpl':
                    $this->add_license_to_file($file, $this->license_header->get_regex_by_type($file->get_extension()));
                    break;
                case 'twig':
                    $this->add_license_to_twig_template($file);
                    break;
                case 'json':
                    $this->add_license_to_json_file($file);
                    break;
            }
            $progress->advance();
        }
        $progress->finish();
        $output->writeln('');
    }
    private function add_license_to_file(Spl_File_Info $file, string $regex): void
    {
        $content = $file->get_contents();
        $old_content = $content;
        $matches = [];
        $text = $this->license_header->get_content_by_type($file->get_extension());
        // Try to find an existing license
        preg_match($regex, $content, $matches);
        $found_license_comment = false;
        if (count($matches)) {
            // Found - Replace it if prestashop one
            foreach ($matches as $match) {
                if ($this->is_license_comment($match)) {
                    $found_license_comment = true;
                    $content = str_replace($match, $text, $content);
                }
            }
        }
        if (!$found_license_comment) {
            // Not found - Add it at the beginning of the file
            $content = $text . "\n" . $content;
        }
        if (!$this->run_as_dry) {
            file_put_contents($this->target_directory . '/' . $file->get_relative_pathname(), $content);
        }
        $this->report_operation_result($content, $old_content, $file->get_relative_pathname());
    }
    private function add_license_to_node(Stmt $node, Spl_File_Info $file): void
    {
        if (!$node->has_attribute('comments')) {
            $this->prepend_in_php_file($file);
            return;
        }
        $comments = $node->get_attribute('comments');
        foreach ($comments as $comment) {
            if ($comment instanceof \Php_Parser\Comment && $this->is_license_comment($comment->get_text())) {
                $new_content = str_replace($comment->get_text(), $this->license_header->get_content_by_type('php'), $file->get_contents());
                if (!$this->run_as_dry) {
                    file_put_contents($this->target_directory . '/' . $file->get_relative_pathname(), $new_content);
                }
                $this->report_operation_result($new_content, $file->get_contents(), $file->get_relative_pathname());
                return;
            }
        }
        // No comment was replaced so we prepend the license
        $this->prepend_in_php_file($file);
    }
    private function is_license_comment(string $comment_content): bool
    {
        foreach ($this->discrimination_string as $discrimination_string) {
            if (strpos($comment_content, $discrimination_string) !== false) {
                return true;
            }
        }
        return false;
    }
    private function prepend_in_php_file(Spl_File_Info $file): void
    {
        $needle = '<?php';
        $replace = "<?php\n" . $this->license_header->get_content_by_type('php');
        $haystack = $file->get_contents();
        $pos = strpos($haystack, $needle);
        // Important, if the <?php is in the middle of the file, continue
        if ($pos === 0) {
            // Check if an empty newline is present right after the <?php tag
            // Append newline to replacement if missing
            $check_newline = substr($haystack, strlen($needle), 2) === "\n\n";
            if (!$check_newline) {
                $replace .= "\n";
            }
            $newstring = substr_replace($haystack, $replace, $pos, strlen($needle));
            if (!$this->run_as_dry) {
                file_put_contents($this->target_directory . '/' . $file->get_relative_pathname(), $newstring);
            }
            $this->report_operation_result($newstring, $haystack, $file->get_relative_pathname());
        }
    }
    private function add_license_to_twig_template(Spl_File_Info $file): void
    {
        $file_content = $file->get_contents();
        $regexp_candidates = [
            // For a short moment v9 had some twig headers a bit wrongly written with extra spaces because of automatic
            // changes made by the new linter This special cas aims at fixing those
            $this->license_header->get_twig_license_regex('{# ', ' #}', '*'),
            $this->license_header->get_twig_license_regex('{# ', ' #}', '#'),
            // These are properly formatted headers but using the * delimiter
            $this->license_header->get_twig_license_regex('{#', '#}', '*'),
        ];
        foreach ($regexp_candidates as $regexp_candidate) {
            if (preg_match($regexp_candidate, $file_content)) {
                $this->add_license_to_file($file, $regexp_candidate);
                return;
            }
        }
        // These are the most recent and adopted headers with only # which are more twig style
        $default_twig_regex = $this->license_header->get_regex_by_type('twig');
        $this->add_license_to_file($file, $default_twig_regex);
    }
    /**
     * @throws Exception
     */
    private function add_license_to_json_file(Spl_File_Info $file): void
    {
        if (!in_array($file->get_filename(), ['composer.json', 'package.json'])) {
            return;
        }
        $content = json_decode($file->get_contents(), true);
        $author_details = ['name' => 'PrestaShop SA', 'email' => 'contact@prestashop.com'];
        // update author information depending of file
        if ('composer.json' === $file->get_filename()) {
            $content['authors'] = [$author_details];
        } else {
            // package.json
            $content['author'] = $author_details;
        }
        $content['license'] = false !== strpos($this->license, 'afl') ? 'AFL-3.0' : 'OSL-3.0';
        $encoded_content = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!$encoded_content) {
            throw new Exception('File can not be encoded to JSON format');
        }
        // add blank line in end of file if not exist
        if (substr($encoded_content, -1) !== "\n") {
            $encoded_content .= "\n";
        }
        if (!$this->run_as_dry) {
            file_put_contents($this->target_directory . '/' . $file->get_relative_pathname(), $encoded_content);
        }
        $this->report_operation_result($encoded_content, $file->get_contents(), $file->get_relative_pathname());
    }
    private function report_operation_result(string $new_file_content, string $old_file_content, string $filename): void
    {
        if ($new_file_content !== $old_file_content) {
            $this->reporter->report_license_has_been_fixed($filename);
        } else {
            $this->reporter->report_license_was_fine($filename);
        }
    }
    private function print_pretty_report(Input_Interface $input, Output_Interface $output): void
    {
        $style = new Symfony_Style($input, $output);
        $style->section('Header Stamp Report');
        $report = $this->reporter->get_report();
        $sections = ['fixed', 'nothing to fix', 'failed'];
        foreach ($sections as $section) {
            if (empty($report[$section])) {
                continue;
            }
            $style->text(ucfirst($section) . ':');
            $style->listing($report[$section]);
        }
    }
    private function print_dry_run_pretty_report(Input_Interface $input, Output_Interface $output): void
    {
        $style = new Symfony_Style($input, $output);
        $style->section('Header Stamp Dry Run Report');
        $report = $this->reporter->get_report();
        if (!empty($report['fixed'])) {
            $style->text('Files with bad license headers:');
            $style->listing($report['fixed']);
        }
        if (!empty($report['failed'])) {
            $style->text('Failed fixing license headers:');
            $style->listing($report['failed']);
        }
    }
}