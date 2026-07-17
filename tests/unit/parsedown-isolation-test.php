<?php
/**
 * Standalone bundled-Parsedown isolation contracts.
 *
 * Regression guard for the production fatal where another plugin loaded a
 * legacy Parsedown (pre-1.7, no setSafeMode()) first and the plugin then
 * called setSafeMode() on that foreign class.
 *
 * Run: php tests/unit/parsedown-isolation-test.php
 */

define("ABSPATH", __DIR__);

// Simulate a foreign plugin that already registered a legacy Parsedown
// without setSafeMode(). Loading the bundled parser must not collide with
// it, and the plugin must never instantiate this class.
class Parsedown
{
    public function text($text)
    {
        return $text;
    }
}

$root = dirname(__DIR__, 2);
$failures = [];
$assertions = 0;
$expect = function ($condition, $label) use (&$failures, &$assertions) {
    $assertions++;
    if (!$condition) {
        $failures[] = $label;
    }
};

require $root . "/includes/Parsedown.php";

$expect(
    class_exists("Moelog_AIQnA_Parsedown"),
    "bundled parser uses the plugin-prefixed class name"
);
$expect(
    method_exists("Moelog_AIQnA_Parsedown", "setSafeMode"),
    "bundled parser supports setSafeMode"
);
$expect(
    method_exists("Moelog_AIQnA_Parsedown", "setBreaksEnabled"),
    "bundled parser supports setBreaksEnabled"
);

$parsedown = new Moelog_AIQnA_Parsedown();
$parsedown->setSafeMode(true);
$expect(
    strpos($parsedown->text("**bold**"), "<strong>bold</strong>") !== false,
    "bundled parser renders markdown"
);
$expect(
    strpos($parsedown->text("<script>alert(1)</script>"), "<script>") === false,
    "safe mode escapes raw HTML"
);

foreach (
    [
        "includes/class-renderer-template.php",
        "moelog-ai-geo.php",
    ] as $consumer
) {
    $source = file_get_contents($root . "/" . $consumer);
    $expect(
        strpos($source, "new Moelog_AIQnA_Parsedown()") !== false,
        $consumer . " instantiates the prefixed parser"
    );
    $expect(
        strpos($source, "new Parsedown()") === false,
        $consumer . " never instantiates a foreign Parsedown"
    );
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(
    STDOUT,
    sprintf("Parsedown isolation tests passed (%d assertions).\n", $assertions)
);
