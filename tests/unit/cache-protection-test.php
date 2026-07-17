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
define("MOELOG_AIQNA_CACHE_KEY", "unit-test-static-cache-key");

class Moelog_AIQnA_Debug
{
    public static function log_error($message) {}
    public static function log_warning($message) {}
    public static function logf($message) {}
}

function wp_mkdir_p($path)
{
    return is_dir($path) || mkdir($path, 0777, true);
}

function moelog_aiqna_get_cache_ttl()
{
    return 86400;
}

function wp_cache_get($key, $group)
{
    return false;
}

function wp_cache_set($key, $value, $group, $expiration = 0)
{
    return true;
}

function wp_cache_delete($key, $group)
{
    return true;
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

    $question = "Encrypted cache question";
    $html = "PHP_CACHE_READ_OK";
    if (!Moelog_AIQnA_Cache::save(123, $question, $html)) {
        fail_cache_protection("encrypted cache save failed");
    }
    $cache_file = Moelog_AIQnA_Cache::get_static_path(123, $question);
    $raw_payload = file_get_contents($cache_file);
    if (strpos($raw_payload, $html) !== false) {
        fail_cache_protection("cache file exposed plaintext HTML");
    }
    if (strpos($raw_payload, Moelog_AIQnA_Cache::PROTECTED_PAYLOAD_PREFIX) !== 0) {
        fail_cache_protection("protected payload marker missing");
    }
    if (Moelog_AIQnA_Cache::load(123, $question) !== $html) {
        fail_cache_protection("PHP could not decrypt protected cache");
    }

    file_put_contents($cache_file, $raw_payload . "tampered");
    if (Moelog_AIQnA_Cache::load(123, $question) !== false) {
        fail_cache_protection("tampered cache payload was accepted");
    }
} finally {
    remove_test_tree($test_root);
}

fwrite(STDOUT, "Cache protection tests passed (11 assertions).\n");
