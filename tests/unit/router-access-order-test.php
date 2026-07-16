<?php
/**
 * Verifies that Router does not read question meta for inaccessible posts.
 *
 * Run: php tests/unit/router-access-order-test.php
 */

define("ABSPATH", __DIR__);
define("MOELOG_AIQNA_PRETTY_BASE", "qna");
define("MOELOG_AIQNA_META_KEY", "_moelog_aiqna_questions");
define("MOELOG_AIQNA_META_LANG_KEY", "_moelog_aiqna_questions_lang");

class WP_Post
{
    public $ID;
    public $post_type = "post";
    public $post_status;
    public $post_password = "";

    public function __construct($id, $status)
    {
        $this->ID = $id;
        $this->post_status = $status;
    }
}

$test_posts = [
    1 => new WP_Post(1, "publish"),
    2 => new WP_Post(2, "private"),
];
$test_query_vars = [];
$test_meta_reads = 0;
$wp_query = new stdClass();

function absint($value)
{
    return abs((int) $value);
}

function sanitize_key($value)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}

function sanitize_text_field($value)
{
    return trim((string) $value);
}

function get_query_var($key)
{
    global $test_query_vars;
    return $test_query_vars[$key] ?? "";
}

function get_post($post_id)
{
    global $test_posts;
    return $test_posts[(int) $post_id] ?? null;
}

function get_post_meta($post_id, $key, $single)
{
    global $test_meta_reads;
    $test_meta_reads++;

    if ($key === MOELOG_AIQNA_META_KEY) {
        return ["What is this?"];
    }

    if ($key === MOELOG_AIQNA_META_LANG_KEY) {
        return ["en"];
    }

    return "";
}

function is_post_publicly_viewable($post)
{
    return $post instanceof WP_Post && $post->post_status === "publish";
}

class Moelog_AIQnA_Post_Cache
{
    public static function get($post_id)
    {
        return get_post($post_id);
    }
}

require_once dirname(__DIR__, 2) . "/includes/class-access-policy.php";
require_once dirname(__DIR__, 2) . "/includes/class-router.php";

function fail_test($message)
{
    fwrite(STDERR, "FAIL: " . $message . "\n");
    exit(1);
}

function set_route($post_id, $slug, $hash)
{
    global $test_query_vars;
    $test_query_vars = [
        "moe_ai" => "1",
        "post_id" => $post_id,
        "v150_slug" => $slug,
        "v150_hash" => $hash,
    ];
}

$router = new Moelog_AIQnA_Router("test-secret");

set_route(2, "wit", "abc");
$test_meta_reads = 0;
if ($router->parse_request() !== false || $test_meta_reads !== 0) {
    fail_test("private post must be rejected before get_post_meta()");
}

set_route(999, "wit", "abc");
$test_meta_reads = 0;
if ($router->parse_request() !== false || $test_meta_reads !== 0) {
    fail_test("missing post must be rejected before get_post_meta()");
}

$valid_slug = $router->slugify_question("What is this?", 1);
$parts = explode("-", $valid_slug);
$hash = $parts[count($parts) - 2];
$slug = implode("-", array_slice($parts, 0, -2));
set_route(1, $slug, $hash);
$test_meta_reads = 0;
$parsed = $router->parse_request();
if (!is_array($parsed) || $parsed["post_id"] !== 1 || $test_meta_reads !== 2) {
    fail_test("published post should resolve after policy and read expected meta");
}

fwrite(STDOUT, "Router access-order tests passed (3 assertions).\n");
