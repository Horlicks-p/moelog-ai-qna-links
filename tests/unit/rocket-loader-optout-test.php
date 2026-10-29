<?php
/** Standalone contract: answer-page scripts opt out of Cloudflare Rocket Loader. */

// Rocket Loader re-injects inline scripts without the CSP nonce, so the
// feedback config is blocked and answer.js never boots (stats stay "—",
// buttons do nothing). Every answer-page <script> must carry data-cfasync.

$root = dirname(__DIR__, 2);
$templates = [
    "templates/answer-page.php",
    "includes/class-renderer-template.php",
];

$checks = [];
foreach ($templates as $template) {
    $source = file_get_contents($root . "/" . $template);
    preg_match_all('/<script\b[^>]*>/', $source, $matches);
    $tags = $matches[0];
    $checks[$template . " has scripts"] = count($tags) > 0;
    foreach ($tags as $i => $tag) {
        $checks[$template . " script #" . ($i + 1) . " data-cfasync"] =
            strpos($tag, 'data-cfasync="false"') !== false;
    }
}

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

fwrite(STDOUT, sprintf("Rocket Loader opt-out tests passed (%d assertions).\n", count($checks)));
