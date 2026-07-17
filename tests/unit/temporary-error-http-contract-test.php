<?php
/** Standalone temporary AI error HTTP contract tests. */

define("ABSPATH", __DIR__);
define("HOUR_IN_SECONDS", 3600);
define("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-4o-mini");
define("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-2.5-flash");
define("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-8");

$retry_after_override = null;

function __($text, $domain = null)
{
    return $text;
}

function apply_filters($name, $value, ...$args)
{
    global $retry_after_override;
    if (
        $name === "moelog_aiqna_temporary_error_retry_after" &&
        $retry_after_override !== null
    ) {
        return $retry_after_override;
    }
    return $value;
}

require_once dirname(__DIR__, 2) . "/includes/class-ai-guard.php";
require_once dirname(__DIR__, 2) . "/includes/class-provider-result.php";
require_once dirname(__DIR__, 2) . "/includes/class-ai-client.php";

$failures = [];
$assertions = 0;
$expect = function ($condition, $label) use (&$failures, &$assertions) {
    $assertions++;
    if (!$condition) {
        $failures[] = $label;
    }
};

$client = new Moelog_AIQnA_AI_Client();
$expect(
    $client->get_temporary_error_retry_after("此答案正在產生中,請稍後再試。") === 180,
    "generation lock uses 180-second retry window"
);
$expect(
    $client->get_temporary_error_retry_after("AI 產生額度已達上限,請稍後再試。") === 3600,
    "generation budget supplies Retry-After"
);
$expect(
    $client->get_temporary_error_retry_after("呼叫 provider 失敗") === 0,
    "ordinary provider errors remain HTTP 500 candidates"
);

$retry_after_override = 45;
$expect(
    $client->get_temporary_error_retry_after("此答案正在產生中,請稍後再試。") === 45,
    "site filter can override Retry-After"
);

$renderer_source = file_get_contents(
    dirname(__DIR__, 2) . "/includes/class-renderer.php"
);
$expect(
    strpos($renderer_source, 'header("Retry-After: "') !== false,
    "renderer emits Retry-After header"
);
$expect(
    strpos($renderer_source, 'render_error(503, $answer)') !== false,
    "renderer maps temporary capacity errors to HTTP 503"
);

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(
    STDOUT,
    sprintf("Temporary error HTTP contract tests passed (%d assertions).\n", $assertions)
);
