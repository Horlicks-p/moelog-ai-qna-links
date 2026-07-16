<?php
/**
 * Local WordPress HTTP smoke test for PR A1.
 *
 * This script creates temporary posts, requests their answer URLs, and deletes
 * the posts in a finally block. It never requests a valid public answer URL, so
 * it must not call a paid AI provider.
 *
 * Run from the plugin root:
 *   set MOELOG_RUN_WP_SMOKE=1
 *   php tests/integration/a1-wordpress-smoke.php
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

$secret = (string) get_option(MOELOG_AIQNA_SECRET_KEY, "");
$router = new Moelog_AIQnA_Router($secret);
$question = "A1 temporary access policy question?";
$marker = "MOELOG_A1_PRIVATE_MARKER_" . wp_generate_password(12, false, false);
$created_ids = [];
$failures = [];

function moelog_a1_request_404($url, $marker, $label, &$failures)
{
    $response = wp_remote_get($url, [
        "timeout" => 15,
        "redirection" => 0,
        "headers" => ["Cache-Control" => "no-cache"],
    ]);

    if (is_wp_error($response)) {
        $failures[] = $label . ": HTTP error: " . $response->get_error_message();
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code !== 404) {
        $failures[] = sprintf("%s: expected 404, got %d", $label, $code);
    }
    if (strpos($body, $marker) !== false) {
        $failures[] = $label . ": private marker leaked in response";
    }
}

function moelog_a1_request_200($url, $expected_marker, $label, &$failures)
{
    $response = wp_remote_get($url, [
        "timeout" => 15,
        "redirection" => 0,
        "headers" => ["Cache-Control" => "no-cache"],
    ]);

    if (is_wp_error($response)) {
        $failures[] = $label . ": HTTP error: " . $response->get_error_message();
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code !== 200) {
        $failures[] = sprintf("%s: expected 200, got %d", $label, $code);
    }
    if (strpos($body, $expected_marker) === false) {
        $failures[] = $label . ": expected cached marker was not returned";
    }
}

try {
    foreach (["draft", "pending", "private", "trash"] as $status) {
        $post_id = wp_insert_post([
            "post_title" => "A1 temporary " . $status,
            "post_content" => $marker,
            "post_status" => $status === "trash" ? "draft" : $status,
            "post_type" => "post",
        ], true);

        if (is_wp_error($post_id)) {
            $failures[] = $status . ": could not create post";
            continue;
        }

        $created_ids[] = $post_id;
        update_post_meta($post_id, MOELOG_AIQNA_META_KEY, [$question]);
        update_post_meta($post_id, MOELOG_AIQNA_META_LANG_KEY, ["en"]);
        if ($status === "trash") {
            wp_trash_post($post_id);
        }

        moelog_a1_request_404(
            $router->build_url($post_id, $question),
            $marker,
            $status,
            $failures
        );

        if ($status === "private") {
            moelog_a1_request_404(
                add_query_arg("pf", "1", $router->build_url($post_id, $question)),
                $marker,
                "private prefetch",
                $failures
            );
        }
    }

    $password_post_id = wp_insert_post([
        "post_title" => "A1 temporary password protected",
        "post_content" => $marker,
        "post_status" => "publish",
        "post_password" => "temporary-password",
        "post_type" => "post",
    ], true);
    if (!is_wp_error($password_post_id)) {
        $created_ids[] = $password_post_id;
        update_post_meta($password_post_id, MOELOG_AIQNA_META_KEY, [$question]);
        moelog_a1_request_404(
            $router->build_url($password_post_id, $question),
            $marker,
            "password protected",
            $failures
        );
    } else {
        $failures[] = "password protected: could not create post";
    }

    $published_id = wp_insert_post([
        "post_title" => "A1 temporary published invalid token",
        "post_content" => $marker,
        "post_status" => "publish",
        "post_type" => "post",
    ], true);
    if (!is_wp_error($published_id)) {
        $created_ids[] = $published_id;
        update_post_meta($published_id, MOELOG_AIQNA_META_KEY, [$question]);
        $valid_url = $router->build_url($published_id, $question);
        $invalid_url = preg_replace_callback(
            '/-([a-f0-9]{3})-([0-9]+)\/?$/',
            function ($matches) {
                $bad_hash = $matches[1] === "000" ? "fff" : "000";
                return "-" . $bad_hash . "-" . $matches[2] . "/";
            },
            $valid_url
        );
        moelog_a1_request_404(
            $invalid_url,
            $marker,
            "published invalid token",
            $failures
        );

        $cache_marker = "MOELOG_A1_PUBLIC_CACHE_MARKER";
        $cached_html = "<!doctype html><html><body>" . $cache_marker . "</body></html>";
        if (!Moelog_AIQnA_Cache::save($published_id, $question, $cached_html)) {
            $failures[] = "published valid token: could not create test cache";
        } else {
            moelog_a1_request_200(
                $valid_url,
                $cache_marker,
                "published valid cached answer",
                $failures
            );
        }

        // Sitemap must follow the same password-protection policy as the route.
        $geo = new Moelog_AIQnA_GEO();
        $method = new ReflectionMethod($geo, "get_question_post_ids");
        $method->setAccessible(true);
        $sitemap_ids = $method->invoke($geo, ["post"], 100000, 0);
        if (in_array($password_post_id, $sitemap_ids, true)) {
            $failures[] = "sitemap: password-protected post was included";
        }
        if (!in_array($published_id, $sitemap_ids, true)) {
            $failures[] = "sitemap: public post was unexpectedly excluded";
        }
    } else {
        $failures[] = "published invalid token: could not create post";
    }
} finally {
    foreach ($created_ids as $post_id) {
        wp_clear_scheduled_hook("moelog_aiqna_clear_cache_async", [$post_id]);
        Moelog_AIQnA_Cache::delete($post_id);
        wp_delete_post($post_id, true);
        wp_clear_scheduled_hook("moelog_aiqna_clear_cache_async", [$post_id]);
        if (get_post($post_id)) {
            $failures[] = sprintf("cleanup: temporary post %d still exists", $post_id);
        }
    }
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "WordPress A1 smoke test passed (8 HTTP cases, 2 sitemap assertions).\n");
