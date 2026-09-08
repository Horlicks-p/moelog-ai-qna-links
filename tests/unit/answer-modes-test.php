<?php
/** Standalone tests for guided and manual per-question answer modes. */

define("ABSPATH", __DIR__);
define("MOELOG_AIQNA_META_KEY", "_moelog_aiqna_questions");
define("MOELOG_AIQNA_META_MODE_KEY", "_moelog_aiqna_answer_modes");
define("MOELOG_AIQNA_META_HINT_KEY", "_moelog_aiqna_answer_hints");
define("MOELOG_AIQNA_META_MANUAL_KEY", "_moelog_aiqna_manual_answers");
define("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-4o-mini");
define("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-2.5-flash");
define("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-test");

$answer_mode_meta = [
    MOELOG_AIQNA_META_KEY => ["Guided question", "Manual question"],
    MOELOG_AIQNA_META_MODE_KEY => ["guided", "manual"],
    MOELOG_AIQNA_META_HINT_KEY => ["Editor verified fact", ""],
    MOELOG_AIQNA_META_MANUAL_KEY => ["", "The editor's exact answer."],
];

function get_post_meta($post_id, $key, $single = false)
{
    global $answer_mode_meta;
    return $answer_mode_meta[$key] ?? "";
}

function get_permalink($post_id)
{
    return "https://example.test/post-" . (int) $post_id;
}

class Moelog_AIQnA_Settings
{
    public static function include_content()
    {
        return false;
    }
}

class Answer_Modes_Fake_AI
{
    public $calls = [];

    public function generate_answer($params)
    {
        $this->calls[] = $params;
        return "Generated answer";
    }
}

class Answer_Modes_Fake_Router {}

require_once dirname(__DIR__, 2) . "/includes/helpers-utils.php";
require_once dirname(__DIR__, 2) . "/includes/class-ai-client.php";
require_once dirname(__DIR__, 2) . "/includes/class-renderer-security.php";
require_once dirname(__DIR__, 2) . "/includes/class-renderer-template.php";
require_once dirname(__DIR__, 2) . "/includes/class-renderer.php";

$failures = [];
$assertions = 0;
$expect = function ($condition, $label) use (&$failures, &$assertions) {
    $assertions++;
    if (!$condition) {
        $failures[] = $label;
    }
};

$guided = moelog_aiqna_get_answer_config(42, "Guided question");
$manual = moelog_aiqna_get_answer_config(42, "Manual question");
$missing = moelog_aiqna_get_answer_config(42, "Missing question");
$expect($guided["mode"] === "guided", "guided mode is loaded by question");
$expect($guided["hint"] === "Editor verified fact", "guided hint is loaded");
$expect($manual["manual_answer"] === "The editor's exact answer.", "manual answer is loaded");
$expect($missing["mode"] === "ai", "unknown questions use normal AI mode");

$prompt_client = new Moelog_AIQnA_AI_Client();
$build_prompt = new ReflectionMethod($prompt_client, "build_user_prompt");
if (PHP_VERSION_ID < 80100) {
    $build_prompt->setAccessible(true);
}
$prompt = $build_prompt->invoke($prompt_client, [
    "post_id" => 42,
    "question" => "Guided question",
    "context" => "Article context",
    "answer_hint" => "Editor verified fact",
], []);
$expect(strpos($prompt, "Editor verified fact") !== false, "guided fact is included in the provider prompt");
$expect(strpos($prompt, "答案不得與其衝突") !== false, "provider prompt gives the editor fact priority");

$ai = new Answer_Modes_Fake_AI();
$renderer = new Moelog_AIQnA_Renderer(new Answer_Modes_Fake_Router(), $ai, "secret");
$resolve = new ReflectionMethod($renderer, "resolve_answer");
if (PHP_VERSION_ID < 80100) {
    $resolve->setAccessible(true);
}
$post = (object) ["post_title" => "Title", "post_content" => "Body"];

$guided_answer = $resolve->invoke($renderer, [
    "post_id" => 42,
    "question" => "Guided question",
    "lang" => "zh",
], $post);
$expect($guided_answer === "Generated answer", "guided mode calls the AI provider");
$expect(count($ai->calls) === 1, "guided mode makes one provider call");
$expect($ai->calls[0]["answer_hint"] === "Editor verified fact", "guided mode passes the editor hint");

$manual_answer = $resolve->invoke($renderer, [
    "post_id" => 42,
    "question" => "Manual question",
    "lang" => "zh",
], $post);
$expect($manual_answer === "The editor's exact answer.", "manual mode returns the saved answer");
$expect(count($ai->calls) === 1, "manual mode bypasses the AI provider");

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf("Answer mode tests passed (%d assertions).\n", $assertions));
