<?php
/**
 * Standalone tests for feedback stats lifecycle.
 *
 * Guards the rule that interaction stats belong to the *question*, not to the
 * cached HTML file: clearing or expiring static pages must never drop the
 * accumulated views/likes/dislikes. Only questions that genuinely disappeared
 * may be cleaned up.
 *
 * Run: php tests/unit/feedback-stats-lifecycle-test.php
 */

$test_root = sys_get_temp_dir() . "/moelog-stats-" . uniqid("", true);

define("ABSPATH", __DIR__);
define("WP_CONTENT_DIR", $test_root);
define("MOELOG_AIQNA_STATIC_DIR", "ai-answers");
define("MOELOG_AIQNA_CACHE_KEY", "unit-test-static-cache-key");
define("MOELOG_AIQNA_META_KEY", "_moelog_aiqna_questions");

// --- WordPress surface used by the code under test -------------------------

$GLOBALS["moelog_test_posts"] = [];
$GLOBALS["moelog_test_questions"] = [];
$GLOBALS["moelog_test_deleted"] = [];
$GLOBALS["moelog_test_rows"] = [];
$GLOBALS["moelog_test_options"] = [];

class Moelog_AIQnA_Debug
{
    public static function log_error($message) {}
    public static function log_warning($message) {}
    public static function log_info($message) {}
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

function add_action($hook, $callback, $priority = 10, $args = 1)
{
    return true;
}

function delete_transient($key)
{
    return true;
}

function absint($value)
{
    return abs((int) $value);
}

function sanitize_key($key)
{
    return preg_replace('/[^a-z0-9_\-]/', "", strtolower((string) $key));
}

function get_post($post_id)
{
    $post_id = (int) $post_id;

    return isset($GLOBALS["moelog_test_posts"][$post_id])
        ? (object) ["ID" => $post_id]
        : null;
}

function get_post_meta($post_id, $key, $single = false)
{
    if ($key !== MOELOG_AIQNA_META_KEY) {
        return "";
    }

    $post_id = (int) $post_id;

    return isset($GLOBALS["moelog_test_questions"][$post_id])
        ? $GLOBALS["moelog_test_questions"][$post_id]
        : "";
}

/**
 * Meta row presence is tracked separately from its value so the tests can
 * distinguish "questions were cleared" from "the read failed".
 */
function metadata_exists($meta_type, $object_id, $meta_key)
{
    if ($meta_key !== MOELOG_AIQNA_META_KEY) {
        return false;
    }

    return array_key_exists(
        (int) $object_id,
        $GLOBALS["moelog_test_questions"]
    );
}

function get_option($name, $default = false)
{
    return array_key_exists($name, $GLOBALS["moelog_test_options"])
        ? $GLOBALS["moelog_test_options"][$name]
        : $default;
}

function update_option($name, $value, $autoload = null)
{
    $GLOBALS["moelog_test_options"][$name] = $value;

    return true;
}

function delete_post_meta($post_id, $key)
{
    $GLOBALS["moelog_test_deleted"][] = (int) $post_id . "|" . $key;

    return true;
}

/**
 * Mirrors the newline-separated branch of the real helper, including the
 * eight-question ceiling.
 */
function moelog_aiqna_parse_questions($raw)
{
    if (!is_string($raw)) {
        return [];
    }

    $lines = preg_split('/\r\n|\n|\r/', $raw);
    $questions = array_filter(array_map("trim", $lines));

    return array_slice(array_values($questions), 0, 8);
}

class Moelog_Test_WPDB
{
    public $postmeta = "wp_postmeta";
    public $options = "wp_options";

    public function prepare($query, $arg = null)
    {
        return $query;
    }

    public function esc_like($text)
    {
        return addcslashes((string) $text, "_%\\");
    }

    public function get_results($query)
    {
        return $GLOBALS["moelog_test_rows"];
    }

    public function query($query)
    {
        return 0;
    }
}

$GLOBALS["wpdb"] = new Moelog_Test_WPDB();

require_once dirname(__DIR__, 2) . "/includes/class-cache.php";
require_once dirname(__DIR__, 2) . "/includes/class-feedback-controller.php";

// --- Harness --------------------------------------------------------------

$assertions = 0;

function fail_stats_lifecycle($message)
{
    fwrite(STDERR, "FAIL: " . $message . "\n");
    exit(1);
}

function assert_stats_true($condition, $message)
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        fail_stats_lifecycle($message);
    }
}

function stats_meta_key($post_id, $question)
{
    return "_moelog_aiqna_feedback_stats_" .
        Moelog_AIQnA_Cache::generate_hash($post_id, $question);
}

function make_row($post_id, $meta_key)
{
    return (object) ["post_id" => $post_id, "meta_key" => $meta_key];
}

function run_cleanup($rows)
{
    $GLOBALS["moelog_test_rows"] = $rows;
    $GLOBALS["moelog_test_deleted"] = [];

    return Moelog_AIQnA_Feedback_Controller::cleanup_orphaned_stats();
}

function was_deleted($post_id, $meta_key)
{
    return in_array(
        (int) $post_id . "|" . $meta_key,
        $GLOBALS["moelog_test_deleted"],
        true
    );
}

function remove_test_tree($path)
{
    if (!is_dir($path)) {
        return;
    }

    $entries = array_diff(scandir($path), [".", ".."]);
    foreach ($entries as $entry) {
        $full = $path . "/" . $entry;
        is_dir($full) ? remove_test_tree($full) : @unlink($full);
    }

    @rmdir($path);
}

// --- Tests ----------------------------------------------------------------

try {
    // Case 1: questions still present -> stats survive; unknown hash is dropped.
    $GLOBALS["moelog_test_posts"] = [101 => true];
    $GLOBALS["moelog_test_questions"] = [101 => "First question?\nSecond question?"];

    $live_first = stats_meta_key(101, "First question?");
    $live_second = stats_meta_key(101, "Second question?");
    $stale = "_moelog_aiqna_feedback_stats_0123456789abcdef";

    $result = run_cleanup([
        make_row(101, $live_first),
        make_row(101, $live_second),
        make_row(101, $stale),
    ]);

    assert_stats_true(
        !was_deleted(101, $live_first) && !was_deleted(101, $live_second),
        "stats for existing questions were deleted"
    );
    assert_stats_true(
        was_deleted(101, $stale),
        "stats for a removed question were retained"
    );
    assert_stats_true($result["scanned"] === 3, "scanned count mismatch");
    assert_stats_true($result["deleted"] === 1, "deleted count mismatch");
    assert_stats_true(
        count($result["details"]) === 1 &&
            $result["details"][0]["hash"] === "0123456789abcdef",
        "details did not describe the removed question"
    );

    $orphan_candidate = "_moelog_aiqna_feedback_stats_abcdefabcdefabcd";

    // Case 2: every question removed -> stats are orphaned and must be cleaned.
    // The meta row still exists but holds no questions.
    $GLOBALS["moelog_test_posts"] = [202 => true];
    $GLOBALS["moelog_test_questions"] = [202 => ""];

    $result = run_cleanup([make_row(202, $orphan_candidate)]);

    assert_stats_true(
        was_deleted(202, $orphan_candidate),
        "stats survived after every question was removed"
    );
    assert_stats_true($result["deleted"] === 1, "cleared question list mismatch");

    // Case 2b: meta row missing entirely -> also orphaned.
    $GLOBALS["moelog_test_posts"] = [212 => true];
    $GLOBALS["moelog_test_questions"] = [];

    $result = run_cleanup([make_row(212, $orphan_candidate)]);

    assert_stats_true(
        was_deleted(212, $orphan_candidate),
        "stats survived while the question meta row was absent"
    );

    // Case 2c: meta row present but of an unexpected type -> undecidable, skip.
    // This is the only path that must preserve data it cannot vouch for.
    $GLOBALS["moelog_test_posts"] = [222 => true];
    $GLOBALS["moelog_test_questions"] = [222 => 12345];

    $result = run_cleanup([make_row(222, $orphan_candidate)]);

    assert_stats_true(
        !was_deleted(222, $orphan_candidate),
        "stats were deleted despite an undecidable question meta value"
    );
    assert_stats_true(
        $result["deleted"] === 0,
        "undecidable meta must not delete anything"
    );

    // Case 3: post no longer exists -> stats are genuinely orphaned.
    $GLOBALS["moelog_test_posts"] = [];
    $GLOBALS["moelog_test_questions"] = [];

    $result = run_cleanup([make_row(303, $orphan_candidate)]);

    assert_stats_true(
        was_deleted(303, $orphan_candidate),
        "stats of a deleted post were retained"
    );
    assert_stats_true($result["deleted"] === 1, "deleted post cleanup mismatch");

    // Case 4: malformed meta keys are ignored rather than deleted.
    $GLOBALS["moelog_test_posts"] = [404 => true];
    $GLOBALS["moelog_test_questions"] = [404 => "Only question?"];

    $malformed = "_moelog_aiqna_feedback_stats_nothex";
    $result = run_cleanup([make_row(404, $malformed), make_row(0, $stale)]);

    assert_stats_true(
        !was_deleted(404, $malformed),
        "malformed hash suffix was deleted"
    );
    assert_stats_true(
        $result["deleted"] === 0,
        "malformed rows must not be deleted"
    );

    // Case 5: clearing static HTML must leave stats untouched. The regression
    // being guarded is Cache::clear_all()/clear_expired()/delete() deleting
    // feedback meta alongside the files.
    $cache_dir = $test_root . "/ai-answers";
    wp_mkdir_p($cache_dir);

    $hash = Moelog_AIQnA_Cache::generate_hash(505, "Cached question?");
    file_put_contents($cache_dir . "/505-" . $hash . "-abcdefabcdef.html", "page");

    $GLOBALS["moelog_test_deleted"] = [];
    Moelog_AIQnA_Cache::clear_all();

    assert_stats_true(
        $GLOBALS["moelog_test_deleted"] === [],
        "clear_all() deleted feedback stats"
    );

    file_put_contents($cache_dir . "/505-" . $hash . "-abcdefabcdef.html", "page");
    $GLOBALS["moelog_test_deleted"] = [];
    Moelog_AIQnA_Cache::delete(505);

    assert_stats_true(
        $GLOBALS["moelog_test_deleted"] === [],
        "delete() deleted feedback stats"
    );

    file_put_contents($cache_dir . "/505-" . $hash . "-abcdefabcdef.html", "page");
    touch($cache_dir . "/505-" . $hash . "-abcdefabcdef.html", time() - 172800);
    $GLOBALS["moelog_test_deleted"] = [];
    Moelog_AIQnA_Cache::clear_expired();

    assert_stats_true(
        $GLOBALS["moelog_test_deleted"] === [],
        "clear_expired() deleted feedback stats"
    );

    // Case 6: bumping the generation must invalidate every dedupe key without
    // touching storage. Deleting rows from wp_options cannot be relied upon:
    // with a persistent object cache, get_transient() reads the object cache
    // rather than the database.
    $key_method = new ReflectionMethod(
        "Moelog_AIQnA_Feedback_Controller",
        "transient_key"
    );
    // Required below PHP 8.1; deprecated (and a no-op) from 8.5 onwards.
    if (PHP_VERSION_ID < 80100) {
        $key_method->setAccessible(true);
    }

    $payload = "identity|505|" . $hash;
    $GLOBALS["moelog_test_options"] = [];
    $viewed_before = $key_method->invoke(null, "viewed", $payload);
    $vote_before = $key_method->invoke(null, "vote_state", $payload);

    assert_stats_true(
        $viewed_before === $key_method->invoke(null, "viewed", $payload),
        "dedupe key is not stable within a generation"
    );

    $GLOBALS["moelog_test_options"]["moelog_aiqna_feedback_generation"] = 1;

    assert_stats_true(
        $key_method->invoke(null, "viewed", $payload) !== $viewed_before,
        "view dedupe key survived a generation bump"
    );
    assert_stats_true(
        $key_method->invoke(null, "vote_state", $payload) !== $vote_before,
        "vote state key survived a generation bump"
    );

    // Case 7: the front end must not gate view recording on localStorage.
    // localStorage never expires, so a stored flag would permanently stop an
    // existing browser from counting again after an admin reset.
    $answer_js = file_get_contents(
        dirname(__DIR__, 2) . "/includes/assets/js/answer.js"
    );

    assert_stats_true(
        strpos($answer_js, "moe_aiqna_view_") === false ||
            strpos($answer_js, "removeItem('moe_aiqna_view_") !== false,
        "answer.js still persists a permanent view flag"
    );
    assert_stats_true(
        preg_match('/hasViewed[\s\S]{0,40}return/', $answer_js) !== 1,
        "answer.js still short-circuits recordView() on localStorage"
    );
} finally {
    remove_test_tree($test_root);
}

fwrite(
    STDOUT,
    sprintf("Feedback stats lifecycle tests passed (%d assertions).\n", $assertions)
);
