<?php
/**
 * Standalone tests for the atomic fixed-window feedback limiter.
 *
 * Run: php tests/unit/feedback-rate-limiter-test.php
 */

define("ABSPATH", __DIR__);

$options = [];
$observed = [];

function sanitize_key($key)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
}

function add_option($name, $value, $deprecated = "", $autoload = null)
{
    global $options;
    if (array_key_exists($name, $options)) {
        return false;
    }
    $options[$name] = $value;
    return true;
}

function get_option($name, $default = false)
{
    global $options;
    return array_key_exists($name, $options) ? $options[$name] : $default;
}

function delete_option($name)
{
    global $options;
    $existed = array_key_exists($name, $options);
    unset($options[$name]);
    return $existed;
}

function wp_cache_delete($key, $group = "")
{
    return true;
}

function do_action($name, ...$args)
{
    global $observed;
    if ($name === "moelog_aiqna_feedback_rate_limit_status") {
        $observed[] = $args;
    }
}

class Feedback_Rate_Test_WPDB
{
    public $options = "wp_options";

    public function prepare($query, ...$args)
    {
        return [$query, $args];
    }

    public function esc_like($value)
    {
        return $value;
    }

    public function query($prepared)
    {
        global $options;
        [$query, $args] = $prepared;

        if (strpos($query, "UPDATE ") !== false) {
            [$option, $limit] = $args;
            if (!isset($options[$option]) || (int) $options[$option] >= (int) $limit) {
                return 0;
            }
            $options[$option] = (int) $options[$option] + 1;
            return 1;
        }

        $removed = 0;
        if (strpos($query, "SUBSTRING_INDEX") !== false) {
            $now = (int) $args[2];
            foreach (array_keys($options) as $option) {
                if (
                    strpos($option, Moelog_AIQnA_Feedback_Rate_Limiter::OPTION_PREFIX) === 0 &&
                    strpos($option, Moelog_AIQnA_Feedback_Rate_Limiter::CLEANUP_PREFIX) !== 0 &&
                    preg_match('/_(\d+)$/', $option, $matches) === 1 &&
                    (int) $matches[1] <= $now
                ) {
                    unset($options[$option]);
                    $removed++;
                }
            }
            return $removed;
        }

        if (strpos($query, "option_name <> %s") !== false) {
            $marker = $args[1];
            foreach (array_keys($options) as $option) {
                if (
                    strpos($option, Moelog_AIQnA_Feedback_Rate_Limiter::CLEANUP_PREFIX) === 0 &&
                    $option !== $marker
                ) {
                    unset($options[$option]);
                    $removed++;
                }
            }
            return $removed;
        }

        return false;
    }
}

$wpdb = new Feedback_Rate_Test_WPDB();
require_once dirname(__DIR__, 2) . "/includes/class-feedback-rate-limiter.php";

$failures = [];
$assertions = 0;
$expect = function ($condition, $label) use (&$failures, &$assertions) {
    $assertions++;
    if (!$condition) {
        $failures[] = $label;
    }
};

$first = Moelog_AIQnA_Feedback_Rate_Limiter::consume("vote", "visitor-ip", 2, 60, 100);
$second = Moelog_AIQnA_Feedback_Rate_Limiter::consume("vote", "visitor-ip", 2, 60, 100);
$blocked = Moelog_AIQnA_Feedback_Rate_Limiter::consume("vote", "visitor-ip", 2, 60, 100);

$expect($first["allowed"] && $first["remaining"] === 1, "first request consumed");
$expect($second["allowed"] && $second["remaining"] === 0, "second request consumed atomically");
$expect(!$blocked["allowed"] && $blocked["count"] === 2, "limit rejects without over-increment");
$expect($blocked["reset_at"] === 120 && $blocked["retry_after"] === 20, "fixed window metadata");

$status = Moelog_AIQnA_Feedback_Rate_Limiter::get_status("vote", "visitor-ip", 2, 60, 100);
$expect(
    !$status["allowed"] && $status["count"] === 2 && $status["remaining"] === 0,
    "observable status read"
);

$new_window = Moelog_AIQnA_Feedback_Rate_Limiter::consume("vote", "visitor-ip", 2, 60, 121);
$expect($new_window["allowed"] && $new_window["count"] === 1, "new fixed window resets quota");
$expect(count($observed) === 4 && $observed[2][0] === "vote", "status action emitted");

$stored_names = implode("\n", array_keys($options));
$expect(strpos($stored_names, "visitor-ip") === false, "raw identity not persisted");

Moelog_AIQnA_Feedback_Rate_Limiter::consume("view", "cleanup-test", 1, 60, 3700);
$expect(strpos(implode("\n", array_keys($options)), "_120") === false, "expired counters cleaned");

$controller_source = file_get_contents(
    dirname(__DIR__, 2) . "/includes/class-feedback-controller.php"
);
$expect(strpos($controller_source, "status_header(429)") !== false, "controller emits HTTP 429");
$expect(strpos($controller_source, 'header("Retry-After: "') !== false, "controller emits Retry-After");
$expect(
    strpos($controller_source, '"retry_after" => $retry_after') !== false,
    "controller exposes retry delay in JSON"
);

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf("Feedback rate limiter tests passed (%d assertions).\n", $assertions));
