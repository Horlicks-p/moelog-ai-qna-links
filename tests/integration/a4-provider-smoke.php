<?php
/**
 * Local WordPress provider contract smoke test for PR A4.
 *
 * All HTTP requests are intercepted by pre_http_request. No provider traffic is
 * sent and no API key is persisted.
 *
 * Run from the plugin root:
 *   set MOELOG_RUN_WP_SMOKE=1
 *   php tests/integration/a4-provider-smoke.php
 */

if (getenv("MOELOG_RUN_WP_SMOKE") !== "1") {
    fwrite(STDERR, "Set MOELOG_RUN_WP_SMOKE=1 to run this WordPress smoke test.\n");
    exit(2);
}

$wp_load = dirname(__DIR__, 5) . "/wp-load.php";
if (!file_exists($wp_load)) {
    fwrite(STDERR, "WordPress wp-load.php was not found.\n");
    exit(2);
}
require_once $wp_load;

$captured = [];
$failures = [];
$saved_settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
$test_model = "claude-user-saved-a4-smoke";
$test_settings = is_array($saved_settings) ? $saved_settings : [];
$test_settings["model"] = $test_model;
update_option(MOELOG_AIQNA_OPT_KEY, $test_settings);
Moelog_AIQnA_Settings::clear_cache();

$interceptor = function ($preempt, $args, $url) use (&$captured) {
    if (
        strpos($url, "generativelanguage.googleapis.com") === false &&
        strpos($url, "api.anthropic.com") === false
    ) {
        return $preempt;
    }

    $captured[] = ["url" => $url, "args" => $args];
    if (strpos($url, "generativelanguage.googleapis.com") !== false) {
        $body = [
            "candidates" => [["content" => ["parts" => [["text" => "Hello World"]]]]],
        ];
    } else {
        $body = ["content" => [["type" => "text", "text" => "Hello World"]]];
    }

    return [
        "headers" => [],
        "body" => wp_json_encode($body),
        "response" => ["code" => 200, "message" => "OK"],
        "cookies" => [],
        "filename" => null,
    ];
};

add_filter("pre_http_request", $interceptor, 10, 3);

try {
    $client = new Moelog_AIQnA_AI_Client();

    $gemini_result = $client->test_connection(
        "gemini",
        "gemini-smoke-secret",
        "gemini-2.5-flash"
    );
    if (empty($gemini_result["success"])) {
        $failures[] = "Gemini intercepted connection test failed";
    }
    $gemini_request = $captured[count($captured) - 1] ?? [];
    if (
        strpos($gemini_request["url"] ?? "", "gemini-smoke-secret") !== false ||
        strpos($gemini_request["url"] ?? "", "?key=") !== false
    ) {
        $failures[] = "Gemini key appeared in request URL";
    }
    if (
        ($gemini_request["args"]["headers"]["x-goog-api-key"] ?? "") !==
        "gemini-smoke-secret"
    ) {
        $failures[] = "Gemini key header missing";
    }

    foreach (["claude-opus-4-7", "claude-opus-4-8", "claude-sonnet-5", "unknown-custom"] as $model) {
        $result = $client->test_connection(
            "anthropic",
            "anthropic-smoke-secret",
            $model
        );
        if (empty($result["success"])) {
            $failures[] = "Anthropic intercepted test failed for " . $model;
            continue;
        }
        $request = $captured[count($captured) - 1];
        $body = json_decode($request["args"]["body"], true);
        if (array_key_exists("temperature", $body)) {
            $failures[] = "Conservative Anthropic request included temperature for " . $model;
        }
    }

    $after_settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $after_model = is_array($after_settings) ? ($after_settings["model"] ?? null) : null;
    if ($after_model !== $test_model) {
        $failures[] = "Saved model ID changed during A4 provider checks";
    }
} finally {
    remove_filter("pre_http_request", $interceptor, 10);
    update_option(MOELOG_AIQNA_OPT_KEY, $saved_settings);
    Moelog_AIQnA_Settings::clear_cache();
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "A4 provider smoke test passed (8 assertions).\n");
