<?php
/**
 * Standalone lifecycle cleanup tests.
 *
 * Run: php tests/unit/lifecycle-test.php
 */

$test_root = sys_get_temp_dir() . "/moelog-lifecycle-" . uniqid("", true);
$cron_events = [
    100 => [
        "moelog_aiqna_pregenerate" => [
            "one" => ["args" => [42, "Question"]],
        ],
        "unrelated_hook" => [
            "two" => ["args" => []],
        ],
    ],
    200 => [
        "moelog_aiqna_clear_cache_async" => [
            "three" => ["args" => [42]],
        ],
    ],
];
$unscheduled = [];

define("ABSPATH", __DIR__);
define("WP_CONTENT_DIR", $test_root);

function _get_cron_array()
{
    global $cron_events;
    return $cron_events;
}

function wp_unschedule_event($timestamp, $hook, $args)
{
    global $unscheduled;
    $unscheduled[] = [$timestamp, $hook, $args];
    return true;
}

require_once dirname(__DIR__, 2) . "/includes/class-lifecycle.php";

$failures = [];
$assertions = 0;
$expect = function ($condition, $label) use (&$failures, &$assertions) {
    $assertions++;
    if (!$condition) {
        $failures[] = $label;
    }
};

mkdir($test_root, 0777, true);
$clean_dir = $test_root . "/old-cache";
$guarded_dir = $test_root . "/guarded-cache";
mkdir($clean_dir);
mkdir($guarded_dir);
file_put_contents($clean_dir . "/42-0123456789abcdef.html", "encrypted");
file_put_contents($clean_dir . "/42-0123456789abcdef-0123456789ab.html", "encrypted");
file_put_contents($clean_dir . "/index.html", "");
file_put_contents($clean_dir . "/.htaccess", "deny");
file_put_contents($clean_dir . "/.htaccess.tmp-deadbeef", "deny");
file_put_contents($guarded_dir . "/42-0123456789abcdef.html", "encrypted");
file_put_contents($guarded_dir . "/keep.txt", "owner data");

try {
    $expect(
        Moelog_AIQnA_Lifecycle::unschedule_all_events() === 2,
        "all plugin cron events removed"
    );
    $expect(count($unscheduled) === 2, "two cron removals recorded");
    $expect(
        $unscheduled[0][1] === "moelog_aiqna_pregenerate" &&
            $unscheduled[1][1] === "moelog_aiqna_clear_cache_async",
        "unrelated cron preserved"
    );

    $removed = Moelog_AIQnA_Lifecycle::delete_cache_directories([
        "old-cache",
        "guarded-cache",
        "../outside",
    ]);
    $expect($removed === 6, "only known cache artifacts counted");
    $expect(!is_dir($clean_dir), "empty cache directory removed");
    $expect(is_file($guarded_dir . "/keep.txt"), "unknown owner file preserved");
    $expect(is_dir($guarded_dir), "non-empty owner directory preserved");
} finally {
    if (is_file($guarded_dir . "/keep.txt")) {
        unlink($guarded_dir . "/keep.txt");
    }
    @rmdir($guarded_dir);
    @rmdir($test_root);
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf("Lifecycle tests passed (%d assertions).\n", $assertions));
