<?php
/**
 * Minimal standalone tests for Moelog_AIQnA_Access_Policy.
 *
 * Run: php tests/unit/access-policy-test.php
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
        $status = "publish",
        $password = "",
        $post_type = "post"
    ) {
        $this->ID = $id;
        $this->post_type = $post_type;
        $this->post_status = $status;
        $this->post_password = $password;
    }
}

$test_posts = [];

function absint($value)
{
    return abs((int) $value);
}

function get_post($post)
{
    global $test_posts;
    return $post instanceof WP_Post ? $post : ($test_posts[(int) $post] ?? null);
}

function is_post_publicly_viewable($post)
{
    return $post instanceof WP_Post &&
        $post->post_status === "publish" &&
        in_array($post->post_type, ["post", "page"], true);
}

require_once dirname(__DIR__, 2) . "/includes/class-access-policy.php";

function assert_access($expected, $post, $label)
{
    $actual = Moelog_AIQnA_Access_Policy::is_publicly_accessible($post);
    if ($actual !== $expected) {
        fwrite(
            STDERR,
            sprintf(
                "FAIL: %s (expected %s, got %s)\n",
                $label,
                $expected ? "true" : "false",
                $actual ? "true" : "false"
            )
        );
        exit(1);
    }
}

$test_posts[1] = new WP_Post(1, "publish");

assert_access(true, $test_posts[1], "published post object");
assert_access(true, 1, "published post ID");
assert_access(false, new WP_Post(2, "draft"), "draft post");
assert_access(false, new WP_Post(3, "pending"), "pending post");
assert_access(false, new WP_Post(4, "private"), "private post");
assert_access(false, new WP_Post(5, "trash"), "trashed post");
assert_access(false, new WP_Post(6, "publish", "secret"), "password protected post");
assert_access(false, new WP_Post(7, "publish", "", "internal_type"), "non-public post type");
assert_access(false, 999, "missing post");
assert_access(false, null, "null post");

fwrite(STDOUT, "Access policy tests passed (10 assertions).\n");
