# Architecture: header-stamp

## Purpose
A CLI tool that stamps (adds or updates) license header comments at the top of source files across a project. Supports PHP, JavaScript, TypeScript, CSS/SCSS, Vue, Twig, and Smarty template files.

## Directory Structure
```
src/
  Command/
    Update_Licenses_Command.php  # Symfony Console command — the main entry point
  License_Header.php             # Loads a license file and reformats it per file type
  Reporter.php                   # Collects and displays the stamping report
assets/
  osl3.txt                       # Default license text (OSL 3.0)
tests/
  Unit/                          # Unit tests for License_Header
  Integration/                   # End-to-end tests that run the command on fixture dirs
  Resources/
    module-samples/              # Input fixture directories
    expected/                    # Expected output after stamping
```

## Key Design Decisions
- **Symfony Console** — the tool is exposed as a single console command with rich `--dry-run`, `--display-report`, and `--license` options.
- **Per-type comment wrapping** — `License_Header::get_content_by_type()` rewrites the raw license text into the correct comment syntax for each file extension (`/* */`, `<!-- -->`, `{# #}`, `{** *}`).
- **Discrimination strings** — rather than blindly overwriting any comment block, the tool checks for configurable "discrimination strings" to avoid replacing unrelated file headers.
- **YAML config** — a `.header-stamp-config.yml` file in the project root can override all CLI defaults.

## Extension Points
- Add a new file type by extending the `switch` in `License_Header::get_content_by_type()` and `get_regex_by_type()`.
- Override the license file path via `--license` CLI option or the YAML config `license` key.
- Integrate into CI by running with `--dry-run` and checking the exit code.

## Dependency Flow
```
CLI entrypoint (bin/header-stamp)
  └─ Update_Licenses_Command (Symfony Console)
       ├─ License_Header — loads & reformats the license text
       ├─ Symfony\Finder — discovers files matching extensions/excludes
       ├─ PHP-Parser — parses PHP files to detect existing headers accurately
       └─ Reporter — accumulates per-file results and prints the summary
```
