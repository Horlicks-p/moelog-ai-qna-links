<?php
/** Standalone model default contract tests. */

define("ABSPATH", __DIR__);
define("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-4o-mini");
define("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-2.5-flash");
define("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-8");

function __($text, $domain = null) { return $text; }
function apply_filters($name, $value) { return $value; }

require_once dirname(__DIR__, 2) . "/includes/class-model-registry.php";

$failures = [];
$assertions = 0;
$expect = function ($condition, $label) use (&$failures, &$assertions) {
    $assertions++;
    if (!$condition) { $failures[] = $label; }
};

$registry = Moelog_AIQnA_Model_Registry::get_registry();
$expect(
    Moelog_AIQnA_Model_Registry::get_default_model("anthropic") === "claude-opus-4-8",
    "Anthropic default is Opus 4.8"
);
$expect(
    ($registry["anthropic"]["default"] ?? "") === "claude-opus-4-8",
    "registry default matches constant"
);
$expect(
    ($registry["anthropic"]["models"][0]["id"] ?? "") === "claude-opus-4-8",
    "new-install model option uses Opus 4.8"
);
$expect(
    strpos($registry["anthropic"]["hint"] ?? "", "claude-opus-4-8") !== false,
    "admin hint documents Opus 4.8"
);

$main = file_get_contents(dirname(__DIR__, 2) . "/moelog-ai-qna.php");
$expect(
    strpos(
        $main,
        'define("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-8")'
    ) !== false,
    "plugin bootstrap default matches registry"
);

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf("Model default contract tests passed (%d assertions).\n", $assertions));
