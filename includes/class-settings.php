<?php
/**
 * Moelog AI Q&A Settings Manager Class
 *
 * 統一的設定管理，減少重複的 get_option 調用
 *
 * @package Moelog_AIQnA
 * @since   1.8.4
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Settings
{
    /**
     * 設定快取
     *
     * @var array|null
     */
    private static $cache = null;

    /**
     * 是否已載入設定
     *
     * @var bool
     */
    private static $loaded = false;

    /**
     * 取得設定值
     *
     * @param string|null $key     設定鍵值（null = 取得所有設定）
     * @param mixed       $default 預設值
     * @return mixed
     */
    public static function get($key = null, $default = null)
    {
        self::ensure_loaded();

        if ($key === null) {
            return self::$cache;
        }

        return moelog_aiqna_array_get(self::$cache, $key, $default);
    }

    /**
     * 設定值是否存在
     *
     * @param string $key 設定鍵值
     * @return bool
     */
    public static function has($key)
    {
        self::ensure_loaded();
        return isset(self::$cache[$key]);
    }

    /**
     * 更新設定值
     *
     * @param string|array $key   設定鍵值或設定陣列
     * @param mixed        $value 設定值（當 $key 為字串時）
     * @return bool
     */
    public static function set($key, $value = null)
    {
        if (is_array($key)) {
            // 批量更新
            $settings = $key;
        } else {
            // 單一更新
            self::ensure_loaded();
            self::$cache[$key] = $value;
            $settings = self::$cache;
        }

        $result = update_option(MOELOG_AIQNA_OPT_KEY, $settings);
        
        if ($result) {
            self::clear_cache();
        }

        return $result;
    }

    /**
     * 清除設定快取
     *
     * @return void
     */
    public static function clear_cache()
    {
        self::$cache = null;
        self::$loaded = false;
    }

    /**
     * 確保設定已載入
     *
     * @return void
     */
    private static function ensure_loaded()
    {
        if (self::$loaded && self::$cache !== null) {
            return;
        }

        self::$cache = get_option(MOELOG_AIQNA_OPT_KEY, []);
        self::$loaded = true;
    }

    /**
     * 取得 Provider（AI 供應商）
     *
     * @return string
     */
    public static function get_provider()
    {
        return self::get("provider", "openai");
    }

    /**
     * 取得 API Key（已處理加密）
     *
     * @return string
     */
    public static function get_api_key()
    {
        // 優先使用常數定義
        if (defined("MOELOG_AIQNA_API_KEY") && MOELOG_AIQNA_API_KEY) {
            return trim((string) MOELOG_AIQNA_API_KEY);
        }

        // 從資料庫取得
        $stored_key = self::get("api_key", "");

        if (empty($stored_key)) {
            return "";
        }

        // 如果是加密格式，嘗試解密
        if (
            function_exists("moelog_aiqna_is_encrypted") &&
            function_exists("moelog_aiqna_decrypt_api_key") &&
            moelog_aiqna_is_encrypted($stored_key)
        ) {
            $decrypted = moelog_aiqna_decrypt_api_key($stored_key);
            return $decrypted !== false ? trim($decrypted) : "";
        }

        // 明文格式（舊版相容）
        return trim($stored_key);
    }

    /**
     * 取得模型名稱
     *
     * @return string
     */
    public static function get_model()
    {
        $model = self::get("model", "");
        
        if (!empty($model)) {
            return $model;
        }

        // 根據 Provider 返回預設模型
        $provider = self::get_provider();
        
        switch ($provider) {
            case "gemini":
                return Moelog_AIQnA_AI_Client::DEFAULT_MODEL_GEMINI;
            case "anthropic":
                return Moelog_AIQnA_AI_Client::DEFAULT_MODEL_ANTHROPIC;
            case "openai":
            default:
                return Moelog_AIQnA_AI_Client::DEFAULT_MODEL_OPENAI;
        }
    }

    /**
     * 取得 Temperature
     *
     * @return float
     */
    public static function get_temperature()
    {
        return floatval(self::get("temperature", 0.3));
    }

    /**
     * 取得快取 TTL（天數）
     *
     * @return int
     */
    public static function get_cache_ttl_days()
    {
        return absint(self::get("cache_ttl_days", 30));
    }

    /**
     * 取得快取 TTL（秒數）
     *
     * @return int
     */
    public static function get_cache_ttl_seconds()
    {
        return self::get_cache_ttl_days() * 86400; // 86400 = 1 day in seconds
    }

    /**
     * 取得 System Prompt
     *
     * @return string
     */
    public static function get_system_prompt()
    {
        $default = __("你是嚴謹的專業編輯，提供簡潔準確的答案。", "moelog-ai-qna");
        return self::get("system_prompt", $default);
    }

    /**
     * 是否包含文章內容
     *
     * @return bool
     */
    public static function include_content()
    {
        return !empty(self::get("include_content"));
    }

    /**
     * 取得最大字元數
     *
     * @return int
     */
    public static function get_max_chars()
    {
        return absint(self::get("max_chars", 6000));
    }

    /**
     * 取得問題清單標題
     *
     * @return string
     */
    public static function get_list_heading()
    {
        $default = __("Have more questions? Ask the AI below.", "moelog-ai-qna");
        return self::get("list_heading", $default);
    }

    /**
     * 取得免責聲明
     *
     * @return string
     */
    public static function get_disclaimer_text()
    {
        $default = "本頁面由 AI 生成，可能會發生錯誤，請查核重要資訊。\n" .
                   "使用本 AI 生成內容服務即表示您同意此內容僅供個人參考，且您了解輸出內容可能不準確。\n" .
                   "所有爭議內容 {site} 保有最終解釋權。";
        return self::get("disclaimer_text", $default);
    }

    /**
     * 取得 URL 路徑前綴
     *
     * @return string
     */
    public static function get_pretty_base()
    {
        return self::get("pretty_base", "qna");
    }

    /**
     * 取得靜態目錄名稱
     *
     * @return string
     */
    public static function get_static_dir()
    {
        return self::get("static_dir", "ai-answers");
    }

    /**
     * 驗證 Provider 是否有效
     *
     * @param string $provider Provider 名稱
     * @return bool
     */
    public static function is_valid_provider($provider)
    {
        $valid_providers = ["openai", "gemini", "anthropic"];
        return in_array($provider, $valid_providers, true);
    }
}

