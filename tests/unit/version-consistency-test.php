<?php
/**
 * Standalone release metadata consistency test.
 *
 * Run: php tests/unit/version-consistency-test.php
 */

$root = dirname(__DIR__, 2);
$failures = [];

$main = file_get_contents($root . "/moelog-ai-qna.php");
$readme = file_get_contents($root . "/readme.txt");
$github_readme = file_get_contents($root . "/README.md");
$package = json_decode(file_get_contents($root . "/package.json"), true);
$package_lock = json_decode(file_get_contents($root . "/package-lock.json"), true);

if (!preg_match('/^\s*\* Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/m', $main, $version_match)) {
    fwrite(STDERR, "FAIL: plugin header semantic version\n");
    exit(1);
}
$expected = $version_match[1];

$checks = [
    "plugin header" => true,
    "runtime constant" => strpos($main, 'define("MOELOG_AIQNA_VERSION", "' . $expected . '")') !== false,
    "official update URI" => preg_match(
        '#^\s*\* Update URI:\s*https://github\.com/Horlicks-p/moelog-ai-qna-links/\s*$#m',
        $main
    ),
    "main footer" => strpos($main, "EOF - Moelog AI Q&A v" . $expected) !== false,
    "WordPress stable tag" => preg_match('/^Stable tag:\s*' . preg_quote($expected, '/') . '\s*$/m', $readme),
    "README stable tag" => preg_match('/^Stable tag:\s*' . preg_quote($expected, '/') . '\s*$/m', $github_readme),
    "WordPress changelog" => strpos($readme, "= " . $expected . " (") !== false,
    "README changelog" => strpos($github_readme, "= " . $expected . " (") !== false,
    "package version" => is_array($package) && ($package["version"] ?? "") === $expected,
    "package lock version" => is_array($package_lock) &&
        ($package_lock["version"] ?? "") === $expected,
];

$release_tag = trim((string) getenv("MOELOG_RELEASE_TAG"));
if ($release_tag !== "") {
    $checks["release tag"] = ltrim($release_tag, "v") === $expected;
}

foreach ($checks as $label => $passed) {
    if (!$passed) {
        $failures[] = $label;
    }
}

foreach (["moelog-ai-qna.pot", "moelog-ai-qna-en_US.po", "moelog-ai-qna-ja.po", "moelog-ai-qna-zh_TW.po"] as $file) {
    $content = file_get_contents($root . "/languages/" . $file);
    if (strpos($content, "Project-Id-Version: Moelog AI Q&A Links " . $expected) === false) {
        $failures[] = $file . " project version";
    }
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(
    STDOUT,
    sprintf("Version consistency tests passed (%d assertions).\n", count($checks) + 4)
);
