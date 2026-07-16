<?php
/**
 * Tests the WordPress 5.0-5.6 access-policy fallback.
 *
 * Deliberately does not define is_post_publicly_viewable().
 * Run: php tests/unit/access-policy-fallback-test.php
 */

define("ABSPATH", __DIR__);

class WP_Post
{
    public $ID;
    public $post_type;
    public $post_status;
    public $post_password;

    public function __construct(
        $id,
        $post_type,
        $status = "publish",
        $password = ""
    ) {
        $this->ID = $id;
        $this->post_type = $post_type;
        $this->post_status = $status;
        $this->post_password = $password;
    }
}

function absint($value)
{
    return abs((int) $value);
}

function get_post($post)
{
    return $post instanceof WP_Post ? $post : null;
}

function get_post_type_object($post_type)
{
    $types = [
        "post" => (object) [
            "publicly_queryable" => true,
            "_builtin" => true,
            "public" => true,
        ],
        // Core page is public but intentionally not publicly_queryable.
        "page" => (object) [
            "publicly_queryable" => false,
            "_builtin" => true,
            "public" => true,
        ],
        "public_custom" => (object) [
            "publicly_queryable" => true,
            "_builtin" => false,
            "public" => true,
        ],
        "private_custom" => (object) [
            "publicly_queryable" => false,
            "_builtin" => false,
            "public" => false,
        ],
    ];

    return $types[$post_type] ?? null;
}

function get_post_status_object($status)
{
    return (object) ["public" => $status === "publish"];
}

require_once dirname(__DIR__, 2) . "/includes/class-access-policy.php";

function assert_fallback_access($expected, $post, $label)
{
    $actual = Moelog_AIQnA_Access_Policy::is_publicly_accessible($post);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: " . $label . "\n");
        exit(1);
    }
}

assert_fallback_access(true, new WP_Post(1, "post"), "built-in post");
assert_fallback_access(true, new WP_Post(2, "page"), "built-in page");
assert_fallback_access(true, new WP_Post(3, "public_custom"), "public custom type");
assert_fallback_access(false, new WP_Post(4, "private_custom"), "private custom type");
assert_fallback_access(false, new WP_Post(5, "page", "draft"), "draft page");
assert_fallback_access(false, new WP_Post(6, "page", "publish", "secret"), "password page");
assert_fallback_access(false, new WP_Post(7, "missing_type"), "missing post type");

fwrite(STDOUT, "Access policy fallback tests passed (7 assertions).\n");
