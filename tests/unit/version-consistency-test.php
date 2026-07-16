<?php
/**
 * Standalone release metadata consistency test.
 *
 * Run: php tests/unit/version-consistency-test.php
 */

$root = dirname(__DIR__, 2);
$expected = "2.0.4";
$failures = [];

$main = file_get_contents($root . "/moelog-ai-qna.php");
$readme = file_get_contents($root . "/readme.txt");
$github_readme = file_get_contents($root . "/README.md");

$checks = [
    "plugin header" => preg_match('/^\s*\* Version:\s*' . preg_quote($expected, '/') . '\s*$/m', $main),
    "runtime constant" => strpos($main, 'define("MOELOG_AIQNA_VERSION", "' . $expected . '")') !== false,
    "main footer" => strpos($main, "EOF - Moelog AI Q&A v" . $expected) !== false,
    "WordPress stable tag" => preg_match('/^Stable tag:\s*' . preg_quote($expected, '/') . '\s*$/m', $readme),
    "README stable tag" => preg_match('/^Stable tag:\s*' . preg_quote($expected, '/') . '\s*$/m', $github_readme),
    "WordPress changelog" => strpos($readme, "= " . $expected . " (") !== false,
    "README changelog" => strpos($github_readme, "= " . $expected . " (") !== false,
];

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

fwrite(STDOUT, "Version consistency tests passed (11 assertions).\n");
