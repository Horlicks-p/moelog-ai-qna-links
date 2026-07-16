<?php
/**
 * Standalone tests for static cache directory protection.
 *
 * Run: php tests/unit/cache-protection-test.php
 */

$test_root = sys_get_temp_dir() . "/moelog-a2-" . uniqid("", true);
define("ABSPATH", __DIR__);
define("WP_CONTENT_DIR", $test_root);
define("MOELOG_AIQNA_STATIC_DIR", "ai-answers");

class Moelog_AIQnA_Debug
{
    public static function log_error($message) {}
    public static function logf($message) {}
}

function wp_mkdir_p($path)
{
    return is_dir($path) || mkdir($path, 0777, true);
}

require_once dirname(__DIR__, 2) . "/includes/class-cache.php";

function fail_cache_protection($message)
{
    fwrite(STDERR, "FAIL: " . $message . "\n");
    exit(1);
}

function remove_test_tree($path)
{
    if (!is_dir($path)) {
        return;
    }

    foreach (array_diff(scandir($path), [".", ".."]) as $entry) {
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            remove_test_tree($child);
        } else {
            unlink($child);
        }
    }
    rmdir($path);
}

$cache_dir = $test_root . "/ai-answers";
mkdir($cache_dir, 0777, true);
file_put_contents(
    $cache_dir . "/.htaccess",
    "Options -Indexes\n<FilesMatch \"\\.html$\">\nRequire all granted\n</FilesMatch>\n"
);

try {
    if (!Moelog_AIQnA_Cache::prepare_static_root()) {
        fail_cache_protection("prepare_static_root() failed");
    }

    $rules = file_get_contents($cache_dir . "/.htaccess");
    if (strpos($rules, "Cache Protection v1") === false) {
        fail_cache_protection("versioned protection marker missing");
    }
    if (strpos($rules, "Require all denied") === false) {
        fail_cache_protection("Apache 2.4 deny rule missing");
    }
    if (strpos($rules, "Deny from all") === false) {
        fail_cache_protection("legacy Apache deny rule missing");
    }
    if (strpos($rules, "Require all granted") !== false) {
        fail_cache_protection("legacy public access rule was not removed");
    }

    $first_rules = $rules;
    if (!Moelog_AIQnA_Cache::prepare_static_root()) {
        fail_cache_protection("idempotent prepare failed");
    }
    if (file_get_contents($cache_dir . "/.htaccess") !== $first_rules) {
        fail_cache_protection("idempotent prepare changed protection rules");
    }

    file_put_contents($cache_dir . "/sample.html", "PHP_CACHE_READ_OK");
    if (file_get_contents($cache_dir . "/sample.html") !== "PHP_CACHE_READ_OK") {
        fail_cache_protection("filesystem reads were unexpectedly blocked");
    }
} finally {
    remove_test_tree($test_root);
}

fwrite(STDOUT, "Cache protection tests passed (7 assertions).\n");
