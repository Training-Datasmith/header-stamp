#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1

ROOT_DIR="/app"
UNIT_OK_LINE="$(php -r 'echo json_decode(file_get_contents("tests/baseline.json"), true)["unit_phpunit_ok_line"];')"
INTEGRATION_OK_LINE="$(php -r 'echo json_decode(file_get_contents("tests/baseline.json"), true)["integration_phpunit_ok_line"];')"

strip_ansi_file() {
    local in_file="$1"
    local out_file="$2"
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${in_file}" >"${out_file}"
}

fail_deprecation_output() {
    local label="$1"
    local file="$2"
    local stripped
    stripped="$(mktemp)"
    strip_ansi_file "${file}" "${stripped}"
    if grep -E '^(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Warning, Deprecated, or Notice lines" >&2
        grep -E '^(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >&2 || true
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

require_exact_line_on_stripped() {
    local label="$1"
    local file="$2"
    local line="$3"
    local stripped
    stripped="$(mktemp)"
    strip_ansi_file "${file}" "${stripped}"
    if ! grep -Fxq "${line}" "${stripped}"; then
        echo "${label}: missing exact success line on ANSI-stripped output: ${line}" >&2
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

require_regex_on_stripped() {
    local label="$1"
    local file="$2"
    local pattern="$3"
    local stripped
    stripped="$(mktemp)"
    strip_ansi_file "${file}" "${stripped}"
    if ! grep -Eq "${pattern}" "${stripped}"; then
        echo "${label}: missing required pattern on ANSI-stripped output: ${pattern}" >&2
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

php -v | tee /tmp/php-version.txt
php -r '
if (PHP_VERSION_ID !== 70234) {
    fwrite(STDERR, "Expected PHP 7.2.34, got ".PHP_VERSION."\n");
    exit(1);
}
echo "php: PHP ".PHP_VERSION."\n";
'

for ext in mbstring xml dom json tokenizer iconv; do
    if ! php -m | grep -qi "^${ext}$"; then
        echo "Missing required PHP extension: ${ext}" >&2
        exit 1
    fi
done

mkdir -p "${ROOT_DIR}/.system-test"

git config --global --add safe.directory "${ROOT_DIR}" 2>/dev/null || true

COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o "${ROOT_DIR}/.system-test/composer.phar" "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  ${ROOT_DIR}/.system-test/composer.phar" | sha256sum -c -

composer_out="$(mktemp)"
set +e
php -d error_reporting=32767 "${ROOT_DIR}/.system-test/composer.phar" update --no-interaction --prefer-dist --prefer-stable --no-progress >"${composer_out}" 2>&1
composer_status=$?
set -e
cat "${composer_out}"
fail_deprecation_output "composer update" "${composer_out}"
if [[ ${composer_status} -ne 0 ]]; then
    rm -f "${composer_out}"
    exit "${composer_status}"
fi
rm -f "${composer_out}"

pins_out="$(mktemp)"
set +e
php -d error_reporting=32767 scripts/check-cluster-pins.php >"${pins_out}" 2>&1
pins_status=$?
set -e
cat "${pins_out}"
fail_deprecation_output "pin check" "${pins_out}"
if [[ ${pins_status} -ne 0 ]]; then
    rm -f "${pins_out}"
    exit "${pins_status}"
fi
require_exact_line_on_stripped 'pin check' "${pins_out}" 'check-cluster-pins: all cluster packages match expected SHAs and aliases.'
rm -f "${pins_out}"

audit_out="$(mktemp)"
set +e
php "${ROOT_DIR}/.system-test/composer.phar" audit --no-interaction --abandoned=report >"${audit_out}" 2>&1
audit_status=$?
set -e
cat "${audit_out}"
fail_deprecation_output "composer audit" "${audit_out}"
if [[ ${audit_status} -ne 0 ]]; then
    rm -f "${audit_out}"
    exit "${audit_status}"
fi
require_exact_line_on_stripped 'composer audit' "${audit_out}" 'No security vulnerability advisories found.'
rm -f "${audit_out}"

lint_out="$(mktemp)"
bash scripts/lint-src.sh >"${lint_out}" 2>&1
lint_status=$?
cat "${lint_out}"
fail_deprecation_output "php -l" "${lint_out}"
if [[ ${lint_status} -ne 0 ]]; then
    rm -f "${lint_out}"
    exit "${lint_status}"
fi
require_regex_on_stripped 'php -l' "${lint_out}" '^php -l: No syntax errors detected in [0-9]+ files$'
rm -f "${lint_out}"

unit_out="$(mktemp)"
set +e
php -d date.timezone=UTC -d error_reporting=32767 ./vendor/phpunit/phpunit/phpunit -c tests/Unit/phpunit.xml --colors=never >"${unit_out}" 2>&1
unit_status=$?
set -e
cat "${unit_out}"
fail_deprecation_output "phpunit unit" "${unit_out}"
if [[ ${unit_status} -ne 0 ]]; then
    rm -f "${unit_out}"
    exit "${unit_status}"
fi
require_exact_line_on_stripped 'phpunit unit' "${unit_out}" "${UNIT_OK_LINE}"
rm -f "${unit_out}"

integration_out="$(mktemp)"
set +e
php -d date.timezone=UTC -d error_reporting=32767 ./vendor/phpunit/phpunit/phpunit -c tests/Integration/phpunit.xml --colors=never >"${integration_out}" 2>&1
integration_status=$?
set -e
cat "${integration_out}"
fail_deprecation_output "phpunit integration" "${integration_out}"
if [[ ${integration_status} -ne 0 ]]; then
    rm -f "${integration_out}"
    exit "${integration_status}"
fi
require_exact_line_on_stripped 'phpunit integration' "${integration_out}" "${INTEGRATION_OK_LINE}"
rm -f "${integration_out}"

phpstan_out="$(mktemp)"
set +e
vendor/bin/phpstan analyse --configuration=tests/phpstan/phpstan.neon --no-progress --no-ansi --memory-limit=1G >"${phpstan_out}" 2>&1
phpstan_status=$?
set -e
cat "${phpstan_out}"
fail_deprecation_output "phpstan" "${phpstan_out}"
if [[ ${phpstan_status} -ne 0 ]]; then
    rm -f "${phpstan_out}"
    exit "${phpstan_status}"
fi
require_regex_on_stripped 'phpstan' "${phpstan_out}" '^[[:space:]]+\[OK\] No errors[[:space:]]*$'
rm -f "${phpstan_out}"

rename_out="$(mktemp)"
set +e
php -d error_reporting=32767 scripts/check-rename.php >"${rename_out}" 2>&1
rename_status=$?
set -e
cat "${rename_out}"
fail_deprecation_output "rename-check" "${rename_out}"
if [[ ${rename_status} -ne 0 ]]; then
    rm -f "${rename_out}"
    exit "${rename_status}"
fi
require_exact_line_on_stripped 'rename-check' "${rename_out}" 'rename-check: no stale repo-owned names.'
rm -f "${rename_out}"

stamp_out="$(mktemp)"
set +e
php -d date.timezone=UTC -d error_reporting=32767 bin/header-stamp --dry-run >"${stamp_out}" 2>&1
stamp_status=$?
set -e
cat "${stamp_out}"
fail_deprecation_output "header-stamp" "${stamp_out}"
if [[ ${stamp_status} -ne 0 ]]; then
    rm -f "${stamp_out}"
    exit "${stamp_status}"
fi
require_exact_line_on_stripped 'header-stamp' "${stamp_out}" 'Header stamp configuration:'
rm -f "${stamp_out}"

echo 'system-test: all steps passed.'
