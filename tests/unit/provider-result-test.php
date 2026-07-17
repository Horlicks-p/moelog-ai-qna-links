<?php
/** Standalone structured provider result and cache admission tests. */

define("ABSPATH", __DIR__);
define("HOUR_IN_SECONDS", 3600);
define("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-4o-mini");
define("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-2.5-flash");
define("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-8");

$provider_mode = "success_short_error_word";
$cached_answer = null;

class WP_Error {}

class Moelog_AIQnA_Debug
{
    public static function log_error($message, $error = null) {}
    public static function log_warning($message) {}
    public static function log_info($message) {}
    public static function logf($format, ...$args) {}
}

class Moelog_AIQnA_Settings
{
    public static function get_provider() { return "openai"; }
    public static function get($key = null, $default = null)
    {
        $settings = [
            "provider" => "openai",
            "api_key" => "unit-secret",
            "model" => "gpt-4o-mini",
            "temperature" => 0.3,
            "max_tokens" => 2048,
            "system_prompt" => "Answer accurately.",
            "include_content" => 1,
            "max_chars" => 12000,
        ];
        return $key === null ? $settings : ($settings[$key] ?? $default);
    }
}

class Moelog_AIQnA_Cache
{
    public static function get_transient($key) { return false; }
    public static function set_transient($key, $value, $ttl)
    {
        global $cached_answer;
        $cached_answer = $value;
        return true;
    }
}

class Moelog_AIQnA_AI_Guard
{
    const LOCK_TTL = 180;
    public static function acquire_generation_lock($key) { return true; }
    public static function release_generation_lock($key) { return true; }
    public static function consume_generation_budget() { return true; }
}

function __($text, $domain = null) { return $text; }
function wp_json_encode($value) { return json_encode($value); }
function apply_filters($name, $value, ...$args) { return $value; }
function get_permalink($post_id) { return "https://example.test/article"; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_-]/i', '', $value)); }
function wp_remote_retrieve_response_code($response) { return $response["response"]["code"] ?? 0; }
function wp_remote_retrieve_body($response) { return $response["body"] ?? ""; }
function wp_remote_retrieve_header($response, $name)
{
    return $response["headers"][$name] ?? "";
}
function wp_remote_request($url, $args)
{
    global $provider_mode;
    if ($provider_mode === "success_short_error_word") {
        return [
            "response" => ["code" => 200],
            "headers" => ["x-request-id" => "req-success"],
            "body" => json_encode([
                "choices" => [["message" => ["content" => "error"]]],
                "usage" => ["total_tokens" => 7],
            ]),
        ];
    }
    return [
        "response" => ["code" => 418],
        "headers" => ["x-request-id" => "req-failure"],
        "body" => json_encode([
            "error" => ["message" => str_repeat("provider failure ", 20)],
        ]),
    ];
}

require_once dirname(__DIR__, 2) . "/includes/class-provider-result.php";
require_once dirname(__DIR__, 2) . "/includes/class-ai-client.php";

$failures = [];
$assertions = 0;
$expect = function ($condition, $label) use (&$failures, &$assertions) {
    $assertions++;
    if (!$condition) { $failures[] = $label; }
};

$params = [
    "post_id" => 42,
    "question" => "What happened?",
    "lang" => "en",
    "context" => "Article context",
];

$client = new Moelog_AIQnA_AI_Client();
$answer = $client->generate_answer($params);
$success = $client->get_last_provider_result();
$expect($answer === "error", "short answer preserved verbatim");
$expect($cached_answer === "error", "structured success admitted to answer cache");
$expect($success["ok"] === true, "success result marked ok");
$expect($success["usage"]["total_tokens"] === 7, "usage metadata preserved");
$expect($success["provider_request_id"] === "req-success", "request ID preserved");
$expect($client->is_error_message("error") === true, "legacy heuristic would reject short answer");
$expect($client->is_last_result_error("error") === false, "structured status overrides heuristic");

$provider_mode = "long_http_error";
$cached_answer = null;
$client = new Moelog_AIQnA_AI_Client();
$answer = $client->generate_answer($params);
$failure = $client->get_last_provider_result();
$expect($failure["ok"] === false, "HTTP error marked failed");
$expect($failure["error_code"] === "openai_http_418", "stable error code returned");
$expect($failure["http_status"] === 418, "provider HTTP status preserved");
$expect($failure["provider_request_id"] === "req-failure", "error request ID preserved");
$expect($cached_answer === null, "provider error excluded from answer cache");
$expect($answer === $failure["text"], "public string API remains compatible");

if ($failures) {
    foreach ($failures as $failure_label) {
        fwrite(STDERR, "FAIL: " . $failure_label . "\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf("Provider result tests passed (%d assertions).\n", $assertions));
