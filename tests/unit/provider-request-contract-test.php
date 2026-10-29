<?php
/**
 * Standalone provider request contract tests for PR A4.
 *
 * Run: php tests/unit/provider-request-contract-test.php
 */

define("ABSPATH", __DIR__);
define("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-6-luna");
define("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-3.5-flash-lite");
define("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-8");

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
        global $gemini_multipart_response;
        return [
            "response" => ["code" => 200],
            "body" => json_encode([
                "candidates" => [["content" => ["parts" => $gemini_multipart_response ?: [["text" => "ok"]]]]],
            ]),
        ];
    }

    if (strpos($url, "api.openai.com") !== false) {
        return [
            "response" => ["code" => 200],
            "body" => json_encode([
                "choices" => [["message" => ["content" => "ok"]]],
                "usage" => ["total_tokens" => 12],
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

require_once dirname(__DIR__, 2) . "/includes/class-provider-result.php";
require_once dirname(__DIR__, 2) . "/includes/class-ai-client.php";

function fail_provider_contract($message)
{
    fwrite(STDERR, "FAIL: " . $message . "\n");
    exit(1);
}

function call_private_provider($client, $method_name, $params)
{
    $method = new ReflectionMethod($client, $method_name);
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true);
    }
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
        "max_tokens" => 3072,
        "system_prompt" => "system",
        "lang_hint" => "English",
        "user_prompt" => "question",
    ];
}

$client = new Moelog_AIQnA_AI_Client();

function gemini_params($model)
{
    return [
        "api_key" => "gemini-secret",
        "model" => $model,
        "temperature" => 0.3,
        "max_tokens" => 3072,
        "system_prompt" => "system",
        "lang_hint" => "English",
        "user_prompt" => "question",
    ];
}

$gemini_result = call_private_provider($client, "call_gemini", gemini_params("gemini-2.5-flash"));
if (
    !Moelog_AIQnA_Provider_Result::is_valid($gemini_result) ||
    $gemini_result["ok"] !== true
) {
    fail_provider_contract("Gemini did not return a structured success result");
}
$gemini = last_request();
if (
    $gemini["url"] !==
    "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent"
) {
    fail_provider_contract("Gemini endpoint path changed unexpectedly");
}
if (strpos($gemini["url"], "gemini-secret") !== false || strpos($gemini["url"], "?key=") !== false) {
    fail_provider_contract("Gemini API key leaked in URL");
}
if (($gemini["args"]["headers"]["x-goog-api-key"] ?? "") !== "gemini-secret") {
    fail_provider_contract("Gemini API key header missing");
}
$gemini_body = json_decode($gemini["args"]["body"], true);
if (($gemini_body["generationConfig"]["maxOutputTokens"] ?? null) !== 3072) {
    fail_provider_contract("Gemini max token setting was ignored");
}
if (($gemini_body["generationConfig"]["temperature"] ?? null) !== 0.3) {
    fail_provider_contract("Gemini 2.5 lost its temperature");
}
if (isset($gemini_body["generationConfig"]["thinkingConfig"])) {
    fail_provider_contract("Gemini 2.5 received thinkingConfig");
}

// Gemini 3：不送 temperature；非 Lite 送 thinkingLevel low，Lite 維持預設
$gemini3_cases = [
    "gemini-3.5-flash-lite" => null,
    "gemini-3.8-flash" => "low",
    "gemini-4-flash" => "low",
];
foreach ($gemini3_cases as $model => $level) {
    call_private_provider($client, "call_gemini", gemini_params($model));
    $config = json_decode(last_request()["args"]["body"], true)["generationConfig"] ?? [];
    if (array_key_exists("temperature", $config)) {
        fail_provider_contract("Gemini 3 request included temperature for " . $model);
    }
    if (($config["maxOutputTokens"] ?? null) !== 3072) {
        fail_provider_contract("Gemini 3 max token setting was ignored for " . $model);
    }
    if (($config["thinkingConfig"]["thinkingLevel"] ?? null) !== $level) {
        fail_provider_contract("Unexpected Gemini thinking level for " . $model);
    }
}

// Gemini 回應有多個 part 時，略過 thought、串接文字
$gemini_multipart_response = [
    ["thought" => true, "text" => "thinking"],
    ["text" => "Hello "],
    ["text" => "World", "thoughtSignature" => "sig"],
];
$multipart = call_private_provider($client, "call_gemini", gemini_params("gemini-3.8-flash"));
if (($multipart["text"] ?? "") !== "Hello World") {
    fail_provider_contract("Gemini multi-part answer was not joined without thoughts");
}
$gemini_multipart_response = null;

$openai_result = call_private_provider($client, "call_openai", [
    "api_key" => "openai-secret",
    "model" => "gpt-4o-mini",
    "temperature" => 0.3,
    "max_tokens" => 3072,
    "system_prompt" => "system",
    "lang_hint" => "English",
    "user_prompt" => "question",
]);
if (
    !Moelog_AIQnA_Provider_Result::is_valid($openai_result) ||
    $openai_result["ok"] !== true ||
    ($openai_result["usage"]["total_tokens"] ?? null) !== 12
) {
    fail_provider_contract("OpenAI structured result or usage metadata is invalid");
}
$openai_body = json_decode(last_request()["args"]["body"], true);
if (($openai_body["max_tokens"] ?? null) !== 3072) {
    fail_provider_contract("OpenAI max token setting was ignored");
}
if (array_key_exists("reasoning_effort", $openai_body) || array_key_exists("max_completion_tokens", $openai_body)) {
    fail_provider_contract("Legacy OpenAI model received new-generation parameters");
}

// gpt-6-luna / gpt-5.6-luna：max_completion_tokens + reasoning_effort none，保留 temperature
foreach (["gpt-6-luna", "gpt-6-luna-2026-09-30", "gpt-5.6-luna"] as $model) {
    call_private_provider($client, "call_openai", [
        "api_key" => "openai-secret",
        "model" => $model,
        "temperature" => 0.3,
        "max_tokens" => 3072,
        "system_prompt" => "system",
        "lang_hint" => "English",
        "user_prompt" => "question",
    ]);
    $body = json_decode(last_request()["args"]["body"], true);
    if (array_key_exists("max_tokens", $body)) {
        fail_provider_contract("New-generation OpenAI request kept max_tokens for " . $model);
    }
    if (($body["max_completion_tokens"] ?? null) !== 3072 || ($body["reasoning_effort"] ?? null) !== "none") {
        fail_provider_contract("New-generation OpenAI parameters missing for " . $model);
    }
    if (($body["temperature"] ?? null) !== 0.3 || count($body["messages"] ?? []) !== 3) {
        fail_provider_contract("New-generation OpenAI request lost temperature or messages for " . $model);
    }
}

foreach (["claude-opus-4-7", "claude-opus-4-8", "claude-sonnet-5", "future-custom-model"] as $model) {
    $result = call_private_provider($client, "call_anthropic", anthropic_params($model));
    if (!Moelog_AIQnA_Provider_Result::is_valid($result) || $result["ok"] !== true) {
        fail_provider_contract("Anthropic did not return structured success for " . $model);
    }
    $body = json_decode(last_request()["args"]["body"], true);
    if (array_key_exists("temperature", $body)) {
        fail_provider_contract("Conservative Anthropic request included temperature for " . $model);
    }
    if (($body["max_tokens"] ?? null) !== 3072) {
        fail_provider_contract("Anthropic max token setting was ignored for " . $model);
    }
    $is_claude_5 = strpos($model, "claude-sonnet-5") === 0;
    if ($is_claude_5 !== (($body["output_config"]["effort"] ?? null) === "low")) {
        fail_provider_contract("Unexpected Anthropic effort setting for " . $model);
    }
}

// Claude 5：effort low，max_tokens 至少 1024（思考也計入），不送 temperature
foreach (["claude-sonnet-5-5", "claude-haiku-5-5", "claude-opus-5-5", "claude-fable-5-1"] as $model) {
    $params = anthropic_params($model);
    $params["max_tokens"] = 256;
    call_private_provider($client, "call_anthropic", $params);
    $body = json_decode(last_request()["args"]["body"], true);
    if (($body["max_tokens"] ?? null) !== 1024 || ($body["output_config"]["effort"] ?? null) !== "low") {
        fail_provider_contract("Claude 5 request lacks room for thinking for " . $model);
    }
    if (array_key_exists("temperature", $body)) {
        fail_provider_contract("Claude 5 request included temperature for " . $model);
    }
}
$params = anthropic_params("claude-opus-4-8");
$params["max_tokens"] = 256;
call_private_provider($client, "call_anthropic", $params);
$body = json_decode(last_request()["args"]["body"], true);
if (($body["max_tokens"] ?? null) !== 256 || array_key_exists("output_config", $body)) {
    fail_provider_contract("Claude 4 request was changed");
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

fwrite(STDOUT, "Provider request contract tests passed.\n");
