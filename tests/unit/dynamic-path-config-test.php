<?php
/**
 * Standalone dynamic route/cache path configuration tests.
 *
 * Run: php tests/unit/dynamic-path-config-test.php
 */

$test_root = sys_get_temp_dir() . "/moelog-paths-" . uniqid("", true);
$pretty_base = "knowledge";
$static_dir = "custom-ai-cache";
$rewrite_rules = [];

define("ABSPATH", __DIR__);
define("WP_CONTENT_DIR", $test_root);

function moelog_aiqna_get_pretty_base()
{
    global $pretty_base;
    return $pretty_base;
}

function moelog_aiqna_get_static_dir()
{
    global $static_dir;
    return $static_dir;
}

function add_rewrite_tag($tag, $regex) {}

function add_rewrite_rule($pattern, $destination, $position)
{
    global $rewrite_rules;
    $rewrite_rules[] = [$pattern, $destination, $position];
}

function home_url($path)
{
    return "https://example.test/" . ltrim($path, "/");
}

function user_trailingslashit($url)
{
    return rtrim($url, "/") . "/";
}

function wp_mkdir_p($path)
{
    return is_dir($path) || mkdir($path, 0777, true);
}

class Moelog_AIQnA_Debug
{
    public static function log_error($message) {}
    public static function log_warning($message) {}
    public static function logf($message) {}
}

require_once dirname(__DIR__, 2) . "/includes/class-router.php";
require_once dirname(__DIR__, 2) . "/includes/class-cache.php";

$failures = [];
$assertions = 0;
$expect = function ($condition, $label) use (&$failures, &$assertions) {
    $assertions++;
    if (!$condition) {
        $failures[] = $label;
    }
};

function moelog_remove_path_test_tree($path)
{
    if (!is_dir($path)) {
        return;
    }
    foreach (array_diff(scandir($path), [".", ".."]) as $entry) {
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            moelog_remove_path_test_tree($child);
        } else {
            unlink($child);
        }
    }
    rmdir($path);
}

try {
    $router = new Moelog_AIQnA_Router("dynamic-path-secret");
    $router->register_routes();
    $expect(
        strpos($rewrite_rules[0][0] ?? "", "^knowledge/") === 0,
        "rewrite rule uses configured pretty base"
    );
    $expect(
        strpos($router->build_url(42, "How does this work?"), "/knowledge/") !== false,
        "public URL uses configured pretty base"
    );

    $expect(
        Moelog_AIQnA_Cache::prepare_static_root(),
        "configured static directory prepared"
    );
    $expect(
        Moelog_AIQnA_Cache::get_static_dir_name() === "custom-ai-cache",
        "configured static directory name used"
    );
    $expect(
        is_file($test_root . "/custom-ai-cache/.htaccess"),
        "configured static directory protected"
    );

    $static_dir = "changed-cache";
    Moelog_AIQnA_Cache::reset_runtime_config();
    $expect(
        Moelog_AIQnA_Cache::prepare_static_root(),
        "runtime cache directory reset"
    );
    $expect(
        Moelog_AIQnA_Cache::get_static_dir_name() === "changed-cache",
        "changed static directory takes effect"
    );
} finally {
    moelog_remove_path_test_tree($test_root);
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(
    STDOUT,
    sprintf("Dynamic path configuration tests passed (%d assertions).\n", $assertions)
);
