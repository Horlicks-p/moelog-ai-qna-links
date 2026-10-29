<?php
/** Standalone model default contract tests. */

define("ABSPATH", __DIR__);
define("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-6-luna");
define("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-3.5-flash-lite");
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

$model_ids = function ($provider) use ($registry) {
    return array_column($registry[$provider]["models"] ?? [], "id");
};

// OpenAI：gpt-4o-mini 於 2027-01-14 停止服務，預設改為 gpt-6-luna，舊模型留在清單供已保存的站點辨識
$expect(Moelog_AIQnA_Model_Registry::get_default_model("openai") === "gpt-6-luna", "OpenAI default is gpt-6-luna");
$expect(($model_ids("openai")[0] ?? "") === "gpt-6-luna", "OpenAI first option is gpt-6-luna");
$expect(in_array("gpt-4o-mini", $model_ids("openai"), true), "gpt-4o-mini stays listed");
$expect(strpos($registry["openai"]["hint"] ?? "", "gpt-6-luna") !== false, "OpenAI hint documents gpt-6-luna");

// Gemini：2.5 Flash 不再開放給新 API Key，預設改為 3.5 Flash-Lite
$expect(Moelog_AIQnA_Model_Registry::get_default_model("gemini") === "gemini-3.5-flash-lite", "Gemini default is 3.5 Flash-Lite");
$expect(($model_ids("gemini")[0] ?? "") === "gemini-3.5-flash-lite", "Gemini first option is 3.5 Flash-Lite");
$expect(in_array("gemini-3.8-flash", $model_ids("gemini"), true), "Gemini 3.8 Flash is listed");
$expect(in_array("gemini-2.5-flash", $model_ids("gemini"), true), "Gemini 2.5 Flash stays listed");
$expect(strpos($registry["gemini"]["hint"] ?? "", "gemini-3.5-flash-lite") !== false, "Gemini hint documents 3.5 Flash-Lite");

// Anthropic：預設維持 Opus 4.8，另列 5.5 系列
foreach (["claude-sonnet-5-5", "claude-haiku-5-5", "claude-opus-5-5"] as $model) {
    $expect(in_array($model, $model_ids("anthropic"), true), $model . " is listed");
}

$main = file_get_contents(dirname(__DIR__, 2) . "/moelog-ai-qna.php");
foreach ([
    'define("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-6-luna")',
    'define("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-3.5-flash-lite")',
    'define("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-8")',
] as $line) {
    $expect(strpos($main, $line) !== false, "plugin bootstrap default matches registry: " . $line);
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: " . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf("Model default contract tests passed (%d assertions).\n", $assertions));
