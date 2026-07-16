<?php
/**
 * Standalone provider request contract tests for PR A4.
 *
 * Run: php tests/unit/provider-request-contract-test.php
 */

define("ABSPATH", __DIR__);
define("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-4o-mini");
define("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-2.5-flash");
define("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-7");

$captured_requests = [];
$temperature_models_filter = null;

class WP_Error
{
}

class Moelog_AIQnA_Debug
{
    public static function log_error($message, $error = null) {}
    public static function logf($format, ...$args) {}
}

function __($text, $domain = null)
{
    return $text;
}

function wp_json_encode($value)
{
    return json_encode($value);
}

function is_wp_error($value)
{
    return $value instanceof WP_Error;
}

function wp_remote_request($url, $args)
{
    global $captured_requests;
    $captured_requests[] = ["url" => $url, "args" => $args];

    if (strpos($url, "generativelanguage.googleapis.com") !== false) {
        return [
            "response" => ["code" => 200],
            "body" => json_encode([
                "candidates" => [["content" => ["parts" => [["text" => "ok"]]]]],
            ]),
        ];
    }

    return [
        "response" => ["code" => 200],
        "body" => json_encode([
            "content" => [["type" => "text", "text" => "ok"]],
        ]),
    ];
}

function wp_remote_retrieve_response_code($response)
{
    return $response["response"]["code"] ?? 0;
}

function wp_remote_retrieve_body($response)
{
    return $response["body"] ?? "";
}

function apply_filters($name, $value)
{
    global $temperature_models_filter;
    if (
        $name === "moelog_aiqna_anthropic_temperature_models" &&
        is_array($temperature_models_filter)
    ) {
        return $temperature_models_filter;
    }
    return $value;
}

require_once dirname(__DIR__, 2) . "/includes/class-ai-client.php";

function fail_provider_contract($message)
{
    fwrite(STDERR, "FAIL: " . $message . "\n");
    exit(1);
}

function call_private_provider($client, $method_name, $params)
{
    $method = new ReflectionMethod($client, $method_name);
    $method->setAccessible(true);
    return $method->invoke($client, $params);
}

function last_request()
{
    global $captured_requests;
    return $captured_requests[count($captured_requests) - 1];
}

function anthropic_params($model)
{
    return [
        "api_key" => "anthropic-secret",
        "model" => $model,
        "temperature" => 0.7,
        "system_prompt" => "system",
        "lang_hint" => "English",
        "user_prompt" => "question",
    ];
}

$client = new Moelog_AIQnA_AI_Client();

call_private_provider($client, "call_gemini", [
    "api_key" => "gemini-secret",
    "model" => "gemini-2.5-flash",
    "temperature" => 0.3,
    "system_prompt" => "system",
    "lang_hint" => "English",
    "user_prompt" => "question",
]);
$gemini = last_request();
if (
    $gemini["url"] !==
    "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent"
) {
    fail_provider_contract("Gemini endpoint path changed unexpectedly");
}
if (strpos($gemini["url"], "gemini-secret") !== false || strpos($gemini["url"], "?key=") !== false) {
    fail_provider_contract("Gemini API key leaked in URL");
}
if (($gemini["args"]["headers"]["x-goog-api-key"] ?? "") !== "gemini-secret") {
    fail_provider_contract("Gemini API key header missing");
}

foreach (["claude-opus-4-7", "claude-opus-4-8", "claude-sonnet-5", "future-custom-model"] as $model) {
    call_private_provider($client, "call_anthropic", anthropic_params($model));
    $body = json_decode(last_request()["args"]["body"], true);
    if (array_key_exists("temperature", $body)) {
        fail_provider_contract("Conservative Anthropic request included temperature for " . $model);
    }
}

call_private_provider(
    $client,
    "call_anthropic",
    anthropic_params("claude-3-5-sonnet-20241022")
);
$legacy_body = json_decode(last_request()["args"]["body"], true);
if (($legacy_body["temperature"] ?? null) !== 0.7) {
    fail_provider_contract("Explicit legacy model did not receive temperature");
}

$temperature_models_filter = ["site-tested-custom-model"];
call_private_provider(
    $client,
    "call_anthropic",
    anthropic_params("site-tested-custom-model")
);
$filtered_body = json_decode(last_request()["args"]["body"], true);
if (($filtered_body["temperature"] ?? null) !== 0.7) {
    fail_provider_contract("Site-tested exact model filter was ignored");
}

fwrite(STDOUT, "Provider request contract tests passed (10 assertions).\n");
