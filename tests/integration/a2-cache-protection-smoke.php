<?php
/**
 * Local WordPress/Apache smoke test for PR A2.
 *
 * Run from the plugin root:
 *   set MOELOG_RUN_WP_SMOKE=1
 *   php tests/integration/a2-cache-protection-smoke.php
 */

if (getenv("MOELOG_RUN_WP_SMOKE") !== "1") {
    fwrite(STDERR, "Set MOELOG_RUN_WP_SMOKE=1 to run this filesystem/HTTP smoke test.\n");
    exit(2);
}

$wp_load = dirname(__DIR__, 5) . "/wp-load.php";
if (!file_exists($wp_load)) {
    fwrite(STDERR, "WordPress wp-load.php was not found.\n");
    exit(2);
}

require_once $wp_load;

$question = "What's PHP's <=> cache protection behavior?";
$marker = "MOELOG_A2_PHP_CACHE_READ_OK";
$failures = [];
$post_id = 0;

try {
    // Force the upgrade path to prove an existing installation is repaired.
    delete_option("moelog_aiqna_cache_protection_version");
    moelog_aiqna_maybe_upgrade_cache_protection();

    if (
        get_option("moelog_aiqna_cache_protection_version") !==
        MOELOG_AIQNA_CACHE_PROTECTION_VERSION
    ) {
        $failures[] = "upgrade routine did not record protection version";
    }

    $post_id = wp_insert_post([
        "post_title" => "A2 temporary public cache route",
        "post_content" => "A2 temporary content",
        "post_status" => "publish",
        "post_type" => "post",
    ], true);

    if (is_wp_error($post_id)) {
        $failures[] = "could not create temporary published post";
        $post_id = 0;
    } else {
        update_post_meta($post_id, MOELOG_AIQNA_META_KEY, [$question]);
        update_post_meta($post_id, MOELOG_AIQNA_META_LANG_KEY, ["en"]);
    }

    if (!$post_id || !Moelog_AIQnA_Cache::save($post_id, $question, $marker)) {
        $failures[] = "could not create temporary cache file";
    } else {
        $loaded = Moelog_AIQnA_Cache::load($post_id, $question);
        if ($loaded !== $marker) {
            $failures[] = "Cache::load() could not read protected cache file";
        }

        $path = Moelog_AIQnA_Cache::get_static_path($post_id, $question);
        $url = content_url(MOELOG_AIQNA_STATIC_DIR . "/" . basename($path));
        $response = wp_remote_get($url, [
            "timeout" => 15,
            "redirection" => 0,
            "headers" => ["Cache-Control" => "no-cache"],
        ]);

        if (is_wp_error($response)) {
            $failures[] = "direct HTTP request failed: " . $response->get_error_message();
        } else {
            $status = wp_remote_retrieve_response_code($response);
            if (!in_array($status, [403, 404], true)) {
                $failures[] = sprintf(
                    "direct cache URL should be denied, got HTTP %d",
                    $status
                );
            }
            if (strpos(wp_remote_retrieve_body($response), $marker) !== false) {
                $failures[] = "direct HTTP response leaked cached HTML";
            }
        }

        $secret = (string) get_option(MOELOG_AIQNA_SECRET_KEY, "");
        $router = new Moelog_AIQnA_Router($secret);
        $answer_response = wp_remote_get(
            $router->build_url($post_id, $question),
            ["timeout" => 15, "redirection" => 0]
        );
        if (is_wp_error($answer_response)) {
            $failures[] = "answer route request failed: " .
                $answer_response->get_error_message();
        } else {
            $answer_status = wp_remote_retrieve_response_code($answer_response);
            $answer_body = wp_remote_retrieve_body($answer_response);
            if ($answer_status !== 200) {
                $failures[] = sprintf(
                    "normal answer route should return 200, got HTTP %d",
                    $answer_status
                );
            }
            if (strpos($answer_body, $marker) === false) {
                $failures[] = "normal answer route did not return cached content";
            }
        }

        $original_post = $_POST;
        try {
            // Dropdown path: a canonical server-generated hash selects the
            // target; a stale/tampered question string must be ignored.
            $_POST = [
                "moelog_aiqna_clear_single" => "1",
                "moelog_aiqna_clear_single_nonce" => wp_create_nonce(
                    "moelog_aiqna_clear_single"
                ),
                "post_id" => (string) $post_id,
                "question_hash" => Moelog_AIQnA_Cache::generate_hash(
                    $post_id,
                    $question
                ),
                "question" => "stale client text",
            ];
            $admin_cache = new Moelog_AIQnA_Admin_Cache();
            $admin_cache->handle_cache_actions();
            if (file_exists($path)) {
                $failures[] = "hash-based admin deletion did not remove cache";
            }

            // Manual-input path: simulate the slashed value seen by WordPress
            // request handlers and prove the canonical special-character
            // question is still resolved before deleting.
            if (!Moelog_AIQnA_Cache::save($post_id, $question, $marker)) {
                $failures[] = "could not recreate cache for manual deletion";
            } else {
                $_POST = [
                    "moelog_aiqna_clear_single" => "1",
                    "moelog_aiqna_clear_single_nonce" => wp_create_nonce(
                        "moelog_aiqna_clear_single"
                    ),
                    "post_id" => (string) $post_id,
                    "question_hash" => "",
                    "question" => addslashes($question),
                ];
                $admin_cache->handle_cache_actions();
                if (file_exists($path)) {
                    $failures[] = "manual admin deletion did not unslash question";
                }
            }
        } finally {
            $_POST = $original_post;
        }
    }
} finally {
    if ($post_id) {
        wp_clear_scheduled_hook("moelog_aiqna_clear_cache_async", [$post_id]);
        Moelog_AIQnA_Cache::delete($post_id, $question);
        wp_delete_post($post_id, true);
        wp_clear_scheduled_hook("moelog_aiqna_clear_cache_async", [$post_id]);
    }
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "A2 cache protection smoke test passed (8 assertions).\n");
