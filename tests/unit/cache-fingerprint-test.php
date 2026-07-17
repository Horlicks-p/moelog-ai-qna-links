<?php
/** Standalone answer and page cache fingerprint tests. */

define("ABSPATH", __DIR__);
define("MOELOG_AIQNA_OPT_KEY", "moelog_aiqna_settings");
define("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-4o-mini");
define("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-2.5-flash");
define("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-7");
define("MOELOG_AIQNA_META_LANG_KEY", "_moelog_aiqna_questions_lang");

$fingerprint_settings = [
    "provider" => "openai",
    "disclaimer" => "Original disclaimer",
];
$fingerprint_post = (object) [
    "post_title" => "Post title",
    "post_content" => "Post body",
];
$answer_cache_salt = "";
$page_cache_salt = "";
$fingerprint_languages = ["en"];

class Moelog_AIQnA_Settings
{
    public static function get($key = null, $default = null)
    {
        global $fingerprint_settings;
        if ($key === null) {
            return $fingerprint_settings;
        }
        return $fingerprint_settings[$key] ?? $default;
    }
}

function get_post($post_id)
{
    global $fingerprint_post;
    return $fingerprint_post;
}

function get_option($key, $default = false)
{
    return $key === "moelog_aiqna_geo_mode" ? 0 : $default;
}

function get_permalink($post_id)
{
    return "https://example.test/post-" . (int) $post_id;
}

function get_post_meta($post_id, $key, $single = false)
{
    global $fingerprint_languages;
    return $fingerprint_languages;
}

function apply_filters($name, $value, ...$args)
{
    global $answer_cache_salt, $page_cache_salt;
    if ($name === "moelog_aiqna_answer_cache_salt") {
        return $answer_cache_salt;
    }
    if ($name === "moelog_aiqna_page_cache_salt") {
        return $page_cache_salt;
    }
    return $value;
}

function wp_json_encode($value)
{
    return json_encode($value);
}

require_once dirname(__DIR__, 2) . "/includes/class-ai-client.php";
require_once dirname(__DIR__, 2) . "/includes/class-cache.php";

$failures = [];
$assertions = 0;
$expect = function ($condition, $label) use (&$failures, &$assertions) {
    $assertions++;
    if (!$condition) {
        $failures[] = $label;
    }
};

$client = new Moelog_AIQnA_AI_Client();
$answer_method = new ReflectionMethod($client, "generate_cache_key");
if (PHP_VERSION_ID < 80100) {
    $answer_method->setAccessible(true);
}
$params = [
    "post_id" => 42,
    "question" => "What changed?",
    "lang" => "en",
    "context" => "Canonical article context",
];
$request = [
    "model" => "gpt-4o-mini",
    "temperature" => 0.3,
    "max_tokens" => 2048,
    "system_prompt" => "Answer from the article.",
    "lang_hint" => "English",
    "user_prompt" => "What changed?",
    "api_key" => "secret-one",
];

$answer_base = $answer_method->invoke($client, $params, $fingerprint_settings, $request);
$answer_repeat = $answer_method->invoke($client, $params, $fingerprint_settings, $request);
$expect($answer_base === $answer_repeat, "answer fingerprint is deterministic");

$request_with_other_key = array_merge($request, ["api_key" => "secret-two"]);
$expect(
    $answer_base === $answer_method->invoke($client, $params, $fingerprint_settings, $request_with_other_key),
    "API key rotation does not regenerate answers"
);

$display_settings = array_merge($fingerprint_settings, ["disclaimer" => "New disclaimer"]);
$expect(
    $answer_base === $answer_method->invoke($client, $params, $display_settings, $request),
    "display-only settings do not regenerate answers"
);

$changed_request = array_merge($request, ["max_tokens" => 4096]);
$expect(
    $answer_base !== $answer_method->invoke($client, $params, $fingerprint_settings, $changed_request),
    "request surface changes regenerate answers"
);

$changed_context = array_merge($params, ["context" => "Updated article context"]);
$expect(
    $answer_base !== $answer_method->invoke($client, $changed_context, $fingerprint_settings, $request),
    "context changes regenerate answers"
);

$answer_cache_salt = "answer-v2";
$expect(
    $answer_base !== $answer_method->invoke($client, $params, $fingerprint_settings, $request),
    "answer cache salt forces regeneration"
);
$answer_cache_salt = "";

$page_base = Moelog_AIQnA_Cache::generate_page_fingerprint(42, "What changed?");
$page_repeat = Moelog_AIQnA_Cache::generate_page_fingerprint(42, "What changed?");
$expect($page_base === $page_repeat, "page fingerprint is deterministic");

$fingerprint_settings["disclaimer"] = "New disclaimer";
$page_display_changed = Moelog_AIQnA_Cache::generate_page_fingerprint(42, "What changed?");
$expect($page_base !== $page_display_changed, "display settings invalidate page cache");

$fingerprint_post->post_content = "Updated post body";
$page_post_changed = Moelog_AIQnA_Cache::generate_page_fingerprint(42, "What changed?");
$expect($page_display_changed !== $page_post_changed, "post content invalidates page cache");

$fingerprint_languages = ["zh_TW"];
$page_language_changed = Moelog_AIQnA_Cache::generate_page_fingerprint(42, "What changed?");
$expect($page_post_changed !== $page_language_changed, "question language invalidates page cache");

$page_cache_salt = "template-v2";
$expect(
    $page_language_changed !== Moelog_AIQnA_Cache::generate_page_fingerprint(42, "What changed?"),
    "page cache salt forces re-rendering"
);

$page_cache_salt = "";
$answer_cache_salt = "answer-v3";
$expect(
    $page_language_changed !== Moelog_AIQnA_Cache::generate_page_fingerprint(42, "What changed?"),
    "answer cache salt also invalidates rendered pages"
);

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf("Cache fingerprint tests passed (%d assertions).\n", $assertions));
