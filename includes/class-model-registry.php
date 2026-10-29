<?php
/**
 * Moelog AI Q&A Model Registry
 *
 * @package Moelog_AIQnA
 */

if (!defined('ABSPATH')) {
    exit;
}

class Moelog_AIQnA_Model_Registry
{
    /**
     * Get the model registry.
     *
     * @return array
     */
    public static function get_registry(): array
    {
        $registry = [
            "openai" => [
                "label" => "OpenAI",
                "default" => self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-6-luna"),
                "hint" => __("預設模型：gpt-6-luna。如需其他 OpenAI 模型請輸入完整 ID。", "moelog-ai-qna"),
                "models" => [
                    ["id" => self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-6-luna"), "label" => __("gpt-6-luna（推薦）", "moelog-ai-qna")],
                    ["id" => "gpt-4o-mini", "label" => __("gpt-4o-mini（2027-01-14 停止服務）", "moelog-ai-qna")],
                ],
            ],
            "gemini" => [
                "label" => "Google Gemini",
                "default" => self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-3.5-flash-lite"),
                "hint" => __("預設模型：gemini-3.5-flash-lite。如需其他 Gemini 模型請輸入完整 ID。", "moelog-ai-qna"),
                "models" => [
                    ["id" => self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-3.5-flash-lite"), "label" => __("gemini-3.5-flash-lite（推薦，快速）", "moelog-ai-qna")],
                    ["id" => "gemini-3.8-flash", "label" => __("gemini-3.8-flash（品質較高，可能較慢）", "moelog-ai-qna")],
                    ["id" => "gemini-2.5-flash", "label" => __("gemini-2.5-flash（僅限既有帳號）", "moelog-ai-qna")],
                ],
            ],
            "anthropic" => [
                "label" => "Anthropic Claude",
                "default" => self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-8"),
                "hint" => __("預設模型：claude-opus-4-8。如需其他 Claude 模型請輸入完整 ID。", "moelog-ai-qna"),
                "models" => [
                    ["id" => self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-8"), "label" => "claude-opus-4-8"],
                    ["id" => "claude-sonnet-5-5", "label" => __("claude-sonnet-5-5（較經濟）", "moelog-ai-qna")],
                    ["id" => "claude-haiku-5-5", "label" => __("claude-haiku-5-5（快速，最經濟）", "moelog-ai-qna")],
                    ["id" => "claude-opus-5-5", "label" => __("claude-opus-5-5（進階）", "moelog-ai-qna")],
                ],
            ],
        ];

        return apply_filters("moelog_aiqna_model_registry", $registry);
    }

    /**
     * Get default model for a provider.
     *
     * @param string $provider
     * @return string
     */
    public static function get_default_model(string $provider): string
    {
        $registry = self::get_registry();
        $default = $registry[$provider]["default"] ?? "";
        if (!empty($default)) {
            return $default;
        }

        switch ($provider) {
            case "gemini":
                return self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-3.5-flash-lite");
            case "anthropic":
                return self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-8");
            case "openai":
            default:
                return self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-6-luna");
        }
    }

    /**
     * Get models list for a provider.
     *
     * @param string $provider
     * @return array
     */
    public static function get_models_for_provider(string $provider): array
    {
        $registry = self::get_registry();
        return $registry[$provider]["models"] ?? [];
    }

    /**
     * Get all models grouped by provider.
     *
     * @return array
     */
    public static function get_all_models(): array
    {
        $registry = self::get_registry();
        $all = [];
        foreach ($registry as $provider => $data) {
            $all[$provider] = $data["models"] ?? [];
        }
        return $all;
    }

    /**
     * Get hint text for a provider.
     *
     * @param string $provider
     * @return string
     */
    public static function get_provider_hint(string $provider): string
    {
        $registry = self::get_registry();
        return $registry[$provider]["hint"] ?? __("請輸入供應商提供的模型 ID。", "moelog-ai-qna");
    }

    /**
     * Helper to read constant defaults with fallback.
     */
    private static function constant_default(string $constant, string $fallback): string
    {
        return defined($constant) && constant($constant)
            ? (string) constant($constant)
            : $fallback;
    }
}
