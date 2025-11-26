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
                "default" => self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-4o-mini"),
                "hint" => __("預設模型：gpt-4o-mini。如需其他 OpenAI 模型請輸入完整 ID。", "moelog-ai-qna"),
                "models" => [
                    ["id" => self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-4o-mini"), "label" => "gpt-4o-mini"],
                ],
            ],
            "gemini" => [
                "label" => "Google Gemini",
                "default" => self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-2.5-flash"),
                "hint" => __("預設模型：gemini-2.5-flash。如需其他 Gemini 模型請輸入完整 ID。", "moelog-ai-qna"),
                "models" => [
                    ["id" => self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-2.5-flash"), "label" => "gemini-2.5-flash"],
                ],
            ],
            "anthropic" => [
                "label" => "Anthropic Claude",
                "default" => self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-5-20251101"),
                "hint" => __("預設模型：claude-opus-4-5-20251101。如需其他 Claude 模型請輸入完整 ID。", "moelog-ai-qna"),
                "models" => [
                    ["id" => self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-5-20251101"), "label" => "claude-opus-4-5-20251101"],
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
                return self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-2.5-flash");
            case "anthropic":
                return self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_ANTHROPIC", "claude-opus-4-5-20251101");
            case "openai":
            default:
                return self::constant_default("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-4o-mini");
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
