<?php
/** Standalone AI generation guard tests. */
define("ABSPATH", __DIR__);
$options = [];
$limits = ["ai_daily_limit" => 2, "ai_monthly_limit" => 10];
$cleanup_queries = 0;

function add_option($key, $value, $deprecated = "", $autoload = null) {
    global $options;
    if (array_key_exists($key, $options)) { return false; }
    $options[$key] = $value;
    return true;
}
function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
function delete_option($key) { global $options; $exists = isset($options[$key]); unset($options[$key]); return $exists; }
function apply_filters($name, $value) { return $value; }
function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_-]/i', '', $key)); }
function wp_cache_delete($key, $group) { return true; }
function current_time($format) { return gmdate($format); }

class Moelog_AIQnA_Settings {
    public static function get($key, $default = null) { global $limits; return $limits[$key] ?? $default; }
}
class FakeWpdb {
    public $options = "wp_options";
    public function prepare($query, ...$args) { return [$query, $args]; }
    public function esc_like($text) { return $text; }
    public function query($prepared) {
        global $options, $cleanup_queries;
        [$query, $args] = $prepared;
        if (strpos($query, "DELETE FROM") !== false) {
            $cleanup_queries++;
            [$daily_like, $daily_current, $monthly_like, $monthly_current, $cleanup_like, $cleanup_current] = $args;
            foreach (array_keys($options) as $key) {
                if (
                    (strpos($key, "moelog_aiqna_usage_day-") === 0 && $key !== $daily_current) ||
                    (strpos($key, "moelog_aiqna_usage_month-") === 0 && $key !== $monthly_current) ||
                    (strpos($key, "moelog_aiqna_usage_cleanup_") === 0 && $key !== $cleanup_current)
                ) {
                    unset($options[$key]);
                }
            }
            return 3;
        }
        if (strpos($query, "GREATEST") !== false) {
            $key = $args[0];
            $options[$key] = max(0, (int) ($options[$key] ?? 0) - 1);
            return 1;
        }
        [$key, $limit] = $args;
        $current = (int) ($options[$key] ?? 0);
        if ($current >= $limit) { return 0; }
        $options[$key] = $current + 1;
        return 1;
    }
}
$wpdb = new FakeWpdb();

require_once dirname(__DIR__, 2) . "/includes/class-ai-guard.php";
$failures = [];
function expect_guard($condition, $label) { global $failures; if (!$condition) { $failures[] = $label; } }

expect_guard(Moelog_AIQnA_AI_Guard::acquire_generation_lock("answer-a"), "first lock");
expect_guard(!Moelog_AIQnA_AI_Guard::acquire_generation_lock("answer-a"), "duplicate lock");
expect_guard(Moelog_AIQnA_AI_Guard::release_generation_lock("answer-a"), "release lock");
expect_guard(Moelog_AIQnA_AI_Guard::acquire_generation_lock("answer-a"), "reacquire lock");
Moelog_AIQnA_AI_Guard::release_generation_lock("answer-a");
$stale_lock = "moelog_aiqna_lock_" . substr(hash("sha256", "answer-a"), 0, 32);
$options[$stale_lock] = time() - 1;
expect_guard(Moelog_AIQnA_AI_Guard::acquire_generation_lock("answer-a"), "replace stale lock");
Moelog_AIQnA_AI_Guard::release_generation_lock("answer-a");

$options["moelog_aiqna_usage_day-2000-01-01"] = 5;
$options["moelog_aiqna_usage_month-2000-01"] = 50;
$options["moelog_aiqna_usage_cleanup_2000-01-01"] = 1;
expect_guard(Moelog_AIQnA_AI_Guard::consume_generation_budget(), "budget one");
expect_guard(Moelog_AIQnA_AI_Guard::consume_generation_budget(), "budget two");
expect_guard(!Moelog_AIQnA_AI_Guard::consume_generation_budget(), "daily budget enforced");
expect_guard(!isset($options["moelog_aiqna_usage_day-2000-01-01"]), "old daily counter removed");
expect_guard(!isset($options["moelog_aiqna_usage_month-2000-01"]), "old monthly counter removed");
expect_guard(!isset($options["moelog_aiqna_usage_cleanup_2000-01-01"]), "old cleanup marker removed");
expect_guard($cleanup_queries === 1, "usage cleanup runs once per local day");

$options = [];
$limits = ["ai_daily_limit" => 0, "ai_monthly_limit" => 2];
expect_guard(Moelog_AIQnA_AI_Guard::consume_generation_budget(), "monthly budget one");
expect_guard(Moelog_AIQnA_AI_Guard::consume_generation_budget(), "monthly budget two");
expect_guard(!Moelog_AIQnA_AI_Guard::consume_generation_budget(), "monthly budget enforced");

$limits = ["ai_daily_limit" => 10, "ai_monthly_limit" => 2];
expect_guard(!Moelog_AIQnA_AI_Guard::consume_generation_budget(), "combined budget rejected");
$daily_option = "moelog_aiqna_usage_day-" . gmdate("Y-m-d");
expect_guard(($options[$daily_option] ?? null) === 0, "daily reservation rolled back");

if ($failures) {
    foreach ($failures as $failure) { fwrite(STDERR, "FAIL: {$failure}\n"); }
    exit(1);
}
fwrite(STDOUT, "AI guard tests passed (17 assertions).\n");
