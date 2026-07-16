<?php
/**
 * Local WordPress HTTP smoke test for PR A3.
 *
 * Run from the plugin root:
 *   set MOELOG_RUN_WP_SMOKE=1
 *   php tests/integration/a3-feedback-smoke.php
 */

if (getenv("MOELOG_RUN_WP_SMOKE") !== "1") {
    fwrite(STDERR, "Set MOELOG_RUN_WP_SMOKE=1 to run this database-backed smoke test.\n");
    exit(2);
}

$wp_load = dirname(__DIR__, 5) . "/wp-load.php";
if (!file_exists($wp_load)) {
    fwrite(STDERR, "WordPress wp-load.php was not found.\n");
    exit(2);
}
require_once $wp_load;

$ajax_url = admin_url("admin-ajax.php");
$question = "A3 temporary feedback question?";
$created_ids = [];
$transient_keys = [];
$failures = [];

function a3_post_ajax($url, $body)
{
    $response = wp_remote_post($url, ["timeout" => 15, "body" => $body]);
    if (is_wp_error($response)) {
        return ["http_error" => $response->get_error_message()];
    }
    $decoded = json_decode(wp_remote_retrieve_body($response), true);
    return is_array($decoded) ? $decoded : ["invalid_json" => true];
}

function a3_expect_success($response, $label, &$failures)
{
    if (empty($response["success"])) {
        $failures[] = $label . ": expected success";
        return false;
    }
    return true;
}

function a3_expect_failure($response, $label, &$failures)
{
    if (!array_key_exists("success", $response) || $response["success"] !== false) {
        $failures[] = $label . ": expected failure";
        return false;
    }
    return true;
}

try {
    $post_id = wp_insert_post([
        "post_title" => "A3 temporary public feedback",
        "post_content" => "temporary",
        "post_status" => "publish",
        "post_type" => "post",
    ], true);
    $private_id = wp_insert_post([
        "post_title" => "A3 temporary private feedback",
        "post_content" => "temporary",
        "post_status" => "private",
        "post_type" => "post",
    ], true);
    if (is_wp_error($post_id) || is_wp_error($private_id)) {
        throw new RuntimeException("Could not create temporary posts");
    }
    $created_ids = [$post_id, $private_id];
    foreach ($created_ids as $id) {
        update_post_meta($id, MOELOG_AIQNA_META_KEY, [$question]);
        update_post_meta($id, MOELOG_AIQNA_META_LANG_KEY, ["en"]);
    }

    $hash = Moelog_AIQnA_Cache::generate_hash($post_id, $question);
    $private_hash = Moelog_AIQnA_Cache::generate_hash($private_id, $question);
    $invalid_hash = str_repeat("f", 16);
    if ($invalid_hash === $hash) {
        $invalid_hash = str_repeat("0", 16);
    }

    $bootstrap = a3_post_ajax($ajax_url, [
        "action" => "moelog_aiqna_feedback_bootstrap",
        "post_id" => $post_id,
        "question" => $question,
        "question_hash" => $hash,
    ]);
    if (a3_expect_success($bootstrap, "valid bootstrap", $failures)) {
        $nonce = $bootstrap["data"]["nonce"] ?? "";
    } else {
        $nonce = "";
    }

    a3_expect_failure(a3_post_ajax($ajax_url, [
        "action" => "moelog_aiqna_feedback_bootstrap",
        "post_id" => $private_id,
        "question" => $question,
        "question_hash" => $private_hash,
    ]), "private bootstrap", $failures);

    a3_expect_failure(a3_post_ajax($ajax_url, [
        "action" => "moelog_aiqna_feedback_bootstrap",
        "post_id" => $post_id,
        "question" => $question,
        "question_hash" => $invalid_hash,
    ]), "arbitrary hash bootstrap", $failures);

    a3_expect_failure(a3_post_ajax($ajax_url, [
        "action" => "moelog_aiqna_feedback_bootstrap",
        "post_id" => $post_id,
        "question" => $question,
        "question_hash" => $hash . "-suffix",
    ]), "malformed hash bootstrap", $failures);

    $base = [
        "nonce" => $nonce,
        "post_id" => $post_id,
        "question_hash" => $hash,
    ];

    $view_one = a3_post_ajax($ajax_url, array_merge($base, [
        "action" => "moelog_aiqna_record_view",
        "increment" => "1",
    ]));
    $view_two = a3_post_ajax($ajax_url, array_merge($base, [
        "action" => "moelog_aiqna_record_view",
        "increment" => "1",
    ]));
    if (
        !a3_expect_success($view_one, "first view", $failures) ||
        !a3_expect_success($view_two, "duplicate view", $failures) ||
        (int) ($view_two["data"]["stats"]["views"] ?? -1) !== 1
    ) {
        $failures[] = "duplicate view was not idempotent";
    }

    $like_one = a3_post_ajax($ajax_url, array_merge($base, [
        "action" => "moelog_aiqna_vote",
        "vote" => "like",
        "previous_vote" => "dislike",
    ]));
    $like_two = a3_post_ajax($ajax_url, array_merge($base, [
        "action" => "moelog_aiqna_vote",
        "vote" => "like",
        "previous_vote" => "",
    ]));
    $switch_vote = a3_post_ajax($ajax_url, array_merge($base, [
        "action" => "moelog_aiqna_vote",
        "vote" => "dislike",
        "previous_vote" => "",
    ]));
    if (
        !a3_expect_success($like_one, "first vote", $failures) ||
        !a3_expect_success($like_two, "duplicate vote", $failures) ||
        !a3_expect_success($switch_vote, "switch vote", $failures) ||
        (int) ($like_two["data"]["stats"]["likes"] ?? -1) !== 1 ||
        (int) ($switch_vote["data"]["stats"]["likes"] ?? -1) !== 0 ||
        (int) ($switch_vote["data"]["stats"]["dislikes"] ?? -1) !== 1
    ) {
        $failures[] = "server-side vote state was not idempotent";
    }

    a3_expect_failure(a3_post_ajax($ajax_url, [
        "action" => "moelog_aiqna_vote",
        "nonce" => $nonce,
        "post_id" => $post_id,
        "question_hash" => $invalid_hash,
        "vote" => "like",
    ]), "arbitrary hash vote", $failures);

    a3_expect_failure(a3_post_ajax($ajax_url, [
        "action" => "moelog_aiqna_record_view",
        "nonce" => $nonce,
        "post_id" => $post_id,
        "question_hash" => $invalid_hash,
        "increment" => "1",
    ]), "arbitrary hash view", $failures);

    a3_expect_failure(a3_post_ajax($ajax_url, [
        "action" => "moelog_aiqna_report_issue",
        "nonce" => $nonce,
        "post_id" => $post_id,
        "question" => $question,
        "question_hash" => $invalid_hash,
        "message" => "This must not send mail",
    ]), "arbitrary hash report", $failures);

    if (metadata_exists("post", $post_id, "_moelog_aiqna_feedback_stats_" . $invalid_hash)) {
        $failures[] = "arbitrary hash created post meta";
    }

    $method = new ReflectionMethod("Moelog_AIQnA_Feedback_Controller", "transient_key");
    $method->setAccessible(true);
    // Clean keys for common local Apache peer representations.
    foreach (["127.0.0.1", "::1", "0.0.0.0"] as $peer_ip) {
        $identity = Moelog_AIQnA_Client_IP::anonymized_id($peer_ip);
        foreach (["bootstrap", "view", "vote"] as $action) {
            $transient_keys[] = $method->invoke(null, $action, $identity);
        }
        $transient_keys[] = $method->invoke(
            null,
            "viewed",
            $identity . "|" . $post_id . "|" . $hash
        );
        $transient_keys[] = $method->invoke(
            null,
            "vote_state",
            $identity . "|" . $post_id . "|" . $hash
        );
    }

    $limit_identity = "a3-rate-test-" . wp_generate_password(8, false, false);
    $limit_key = $method->invoke(null, "test_limit", $limit_identity);
    $transient_keys[] = $limit_key;
    $limit_method = new ReflectionMethod(
        "Moelog_AIQnA_Feedback_Controller",
        "consume_rate_limit"
    );
    $limit_method->setAccessible(true);
    if (
        $limit_method->invoke(null, "test_limit", $limit_identity, 2) !== true ||
        $limit_method->invoke(null, "test_limit", $limit_identity, 2) !== true ||
        $limit_method->invoke(null, "test_limit", $limit_identity, 2) !== false
    ) {
        $failures[] = "transient rate limiter did not enforce its limit";
    }
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
} finally {
    foreach (array_unique($transient_keys) as $key) {
        delete_transient($key);
    }
    foreach ($created_ids as $id) {
        wp_clear_scheduled_hook("moelog_aiqna_clear_cache_async", [$id]);
        wp_delete_post($id, true);
        wp_clear_scheduled_hook("moelog_aiqna_clear_cache_async", [$id]);
    }
}

if ($failures) {
    foreach (array_unique($failures) as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "A3 feedback smoke test passed (14 endpoint cases, 1 limiter assertion).\n");
