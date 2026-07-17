<?php
/** Standalone CI, integration environment, and release workflow contracts. */

$root = dirname(__DIR__, 2);
$quality = file_get_contents($root . "/.github/workflows/quality.yml");
$release = file_get_contents($root . "/.github/workflows/release.yml");
$package = json_decode(file_get_contents($root . "/package.json"), true);
$wp_env = json_decode(file_get_contents($root . "/.wp-env.json"), true);
$phpunit = file_get_contents($root . "/phpunit.integration.xml.dist");
$bootstrap = file_get_contents($root . "/tests/wp-integration/bootstrap.php");

$checks = [
    "PHP 7.4 CI boundary" => strpos($quality, '"7.4"') !== false,
    "PHP 8.5 CI boundary" => strpos($quality, '"8.5"') !== false,
    "standalone contracts in CI" => strpos($quality, 'tests/unit/*.php') !== false,
    "WordPress integration job" => strpos($quality, "npm run test:integration") !== false,
    "wp-env failure logs" => strpos($quality, "npm run wp-env -- logs --no-watch") !== false,
    "integration cleanup" => strpos($quality, "if: always()") !== false,
    "pinned official wp-env" => ($package["devDependencies"]["@wordpress/env"] ?? "") === "11.10.0",
    "wp-env mounts plugin" => ($wp_env["plugins"] ?? null) === ["."],
    "wp-env PHP version" => ($wp_env["phpVersion"] ?? "") === "8.3",
    "WordPress PHPUnit suite" => strpos($phpunit, "tests/wp-integration") !== false,
    "WordPress test bootstrap" => strpos($bootstrap, 'getenv("WP_TESTS_DIR")') !== false,
    "release tag gate" => strpos($release, "MOELOG_RELEASE_TAG") !== false,
    "exact updater asset name" => strpos($release, 'moelog-ai-qna-links-${VERSION}.zip') !== false,
    "draft release safety" => strpos($release, "--draft --verify-tag") !== false,
    "development files excluded" => strpos($release, "--exclude 'tests/'") !== false &&
        strpos($release, "--exclude 'plan/'") !== false,
];

$failures = [];
foreach ($checks as $label => $passed) {
    if (!$passed) { $failures[] = $label; }
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf("CI contract tests passed (%d assertions).\n", count($checks)));
