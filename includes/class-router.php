<?php
/**
 * Moelog AI Q&A Router Class
 *
 * 負責 URL 路由、Rewrite Rules 登錄、URL 生成與解析
 * (僅支援 v1.5.x+ Pretty URL 格式)
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Router
{
    /**
     * URL 路徑基礎
     */
    const PRETTY_BASE = MOELOG_AIQNA_PRETTY_BASE; // 'qna'

    /**
     * HMAC 秘鑰
     * @var string
     */
    private $secret;

    /**
     * 建構函數
     *
     * @param string $secret HMAC 秘鑰
     */
    public function __construct($secret)
    {
        $this->secret = (string) $secret;
    }

    // =========================================
    // Rewrite Rules 登錄
    // =========================================

    /**
     * 登錄所有 URL 路由規則
     */
    public function register_routes()
    {
        // 登錄 query vars
        $this->register_query_vars();

        // 登錄 rewrite rules
        $this->register_rewrite_rules();
    }

    /**
     * 登錄 Query Variables
     */
    private function register_query_vars()
    {
        // 主要標誌
        add_rewrite_tag("%moe_ai%", "([^&]+)");
        add_rewrite_tag("%post_id%", "([0-9]+)");

        // v1.5.x 簡短 slug 參數
        add_rewrite_tag("%v150_slug%", "([a-z0-9]+)");
        add_rewrite_tag("%v150_hash%", "([a-f0-9]{3})");
    }


    /**
     * 登錄 Rewrite Rules
     */
    private function register_rewrite_rules()
    {
        // v1.5.x 標準格式: /qna/abc-123-456
        // abc = 簡短標識符, 123 = hash, 456 = post_id
        add_rewrite_rule(
            "^" . self::PRETTY_BASE . '/([a-z0-9]+)-([a-f0-9]{3})-([0-9]+)/?$',
            'index.php?moe_ai=1&v150_slug=$matches[1]&v150_hash=$matches[2]&post_id=$matches[3]',
            "top"
        );
    }


    // =========================================
    // URL 生成
    // =========================================

    /**
     * 建立回答頁 URL（公開 API）
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     * @return string
     */
    public function build_url($post_id, $question)
    {
        $slug = $this->slugify_question($question, $post_id);
        return user_trailingslashit(home_url(self::PRETTY_BASE . "/" . $slug));
    }

    /**
     * 從問題生成簡短 slug
     *
     * 格式: {abbr}-{hash}-{post_id}
     * 範例: wa-abc-123
     *
     * @param string $question 問題文字
     * @param int    $post_id  文章 ID
     * @return string
     */
    public function slugify_question($question, $post_id)
    {
        $abbr = $this->extract_meaningful_chars($question, 3);
        $salt = substr(hash_hmac("sha256", $question, $this->secret), 0, 3);
        return $abbr . "-" . $salt . "-" . $post_id;
    }

    /**
     * 從文字中提取有意義的字元作為縮寫
     *
     * 策略：
     * 1. 提取大寫縮寫（如 API、URL）
     * 2. 提取英文單字首字母
     * 3. 後備：q + md5 hash
     *
     * @param string $text    原始文字
     * @param int    $max_len 最長長度
     * @return string
     */
    private function extract_meaningful_chars($text, $max_len = 3)
    {
        $text = trim((string) $text);
        if (empty($text)) {
            return "q";
        }

        // 策略 1：提取連續大寫字母（API, URL, HTTP）
        if (preg_match_all("/\b[A-Z]{2,}\b/", $text, $matches)) {
            $abbr = strtolower(implode("", $matches[0]));
            if (strlen($abbr) >= 2) {
                return substr($abbr, 0, $max_len);
            }
        }

        // 策略 2：提取英文單字首字母
        if (preg_match_all("/\b[A-Za-z]+\b/", $text, $matches)) {
            $initials = "";
            foreach ($matches[0] as $word) {
                if (strlen($word) >= 2) {
                    $initials .= strtolower($word[0]);
                }
            }
            if (strlen($initials) >= 2) {
                return substr($initials, 0, $max_len);
            }
        }

        // 策略 3：後備方案
        return "q" . substr(md5($text), 0, $max_len - 1);
    }

    // =========================================
    // URL 解析與驗證
    // =========================================

    /**
     * 解析當前請求並返回參數
     * (已按建議 #3 簡化)
     *
     * @return array|false {
     * @type int    $post_id  文章 ID
     * @type string $question 問題文字
     * @type string $lang     語言代碼
     * }
     */
    public function parse_request()
    {
        // 檢查是否為回答頁請求
        if (!$this->is_answer_request()) {
            return false;
        }

        $v150_slug = get_query_var("v150_slug");
        $v150_hash = get_query_var("v150_hash");
        $post_id = get_query_var("post_id");

        // 提早返回: 檢查必要參數
        if (!$post_id || !$v150_slug || !$v150_hash) {
            return false;
        }

        // 查詢 meta 資料
        $questions = $this->parse_questions_list(
            get_post_meta($post_id, MOELOG_AIQNA_META_KEY, true)
        );

        // 提早返回: 沒有問題
        if (empty($questions)) {
            return false;
        }

        $langs = get_post_meta($post_id, MOELOG_AIQNA_META_LANG_KEY, true);
        $langs = is_array($langs) ? $langs : [];

        // 執行解析 (已按建議 #1 簡化參數)
        return $this->parse_v150_slug(
            intval($post_id),
            $v150_slug,
            $v150_hash,
            $questions,
            $langs
        );
    }

    /**
     * 解析 v1.5.x 簡短 slug
     * 遍歷候選問題，重新計算 slug 並比對
     * (已按建議 #1 簡化參數)
     *
     * @param int    $post_id        文章 ID
     * @param string $v150_slug      URL 上的 slug
     * @param string $v150_hash      URL 上的 hash
     * @param array  $questions      候選問題列表
     * @param array  $langs          語言設定
     * @return array|false
     */
    private function parse_v150_slug(
        $post_id,
        $v150_slug,
        $v150_hash,
        $questions, // <-- 參數已簡化
        $langs      // <-- 參數已簡化
    ) {
        $slug_to_match = $v150_slug . "-" . $v150_hash . "-" . $post_id;

        foreach ($questions as $index => $candidate_q) {
            // 重新計算 slug
            $expected_slug = $this->slugify_question(
                $candidate_q,
                $post_id
            );

            // 安全比對
            if (hash_equals($expected_slug, $slug_to_match)) {
                // 找到對應的語言
                $lang = isset($langs[$index])
                    ? sanitize_text_field($langs[$index])
                    : "auto";

                return [
                    "post_id" => $post_id,
                    "question" => $candidate_q,
                    "lang" => $lang,
                ];
            }
        }

        return false;
    }


    // =========================================
    // 輔助方法
    // =========================================

    /**
     * 檢查當前請求是否為回答頁
     *
     * @return bool
     */
    public function is_answer_request()
    {
        // 只檢查 v1.5.x 的 query var
        global $wp_query;
        if (isset($wp_query)) {
            $moe_ai = get_query_var("moe_ai");
            if ($moe_ai) {
                return true;
            }
        }

        return false;
    }

    /**
     * 解析問題列表（從 meta 值）
     * (已按建議 #2 移除註解)
     *
     * @param mixed $raw 原始問題資料
     * @return array
     */
    private function parse_questions_list($raw)
    {
        if (!$raw) {
            return [];
        }

        // 已是陣列
        if (is_array($raw)) {
            $questions = [];
            foreach ($raw as $item) {
                if (is_string($item)) {
                    $q = trim($item);
                    if ($q !== "") {
                        $questions[] = $q;
                    }
                }
            }
            return array_slice($questions, 0, 8);
        }

        // 字串（每行一題）
        if (is_string($raw)) {
            $questions = array_filter(
                array_map("trim", preg_split('/\r\n|\n|\r/', $raw))
            );
            return array_slice(array_values($questions), 0, 8);
        }

        return [];
    }

    /**
     * 取得 HMAC 秘鑰
     *
     * @return string
     */
    public function get_secret()
    {
        return $this->secret;
    }

    // =========================================
    // Debug 輔助（僅在 WP_DEBUG 時使用）
    // =========================================

    /**
     * 輸出當前路由資訊（除錯用）
     *
     * @return array
     */
    public function debug_current_route()
    {
        if (!Moelog_AIQnA_Debug::is_enabled()) {
            return [];
        }

        return [
            "is_answer_request" => $this->is_answer_request(),
            "query_vars" => [
                "moe_ai" => get_query_var("moe_ai"),
                "post_id" => get_query_var("post_id"),
                "v150_slug" => get_query_var("v150_slug"),
                "v150_hash" => get_query_var("v150_hash"),
            ],
            "request_uri" => $_SERVER["REQUEST_URI"] ?? "",
            "parsed_params" => $this->parse_request(),
        ];
    }

    /**
     * 列出所有已登錄的 rewrite rules（除錯用）
     *
     * @return array
     */
    public function debug_rewrite_rules()
    {
        if (!Moelog_AIQnA_Debug::is_enabled()) {
            return [];
        }

        $rules = get_option("rewrite_rules");
        if (!is_array($rules)) {
            return [];
        }

        $filtered = [];
        foreach ($rules as $pattern => $destination) {
            if (
                strpos($pattern, self::PRETTY_BASE) !== false ||
                strpos($pattern, "moe_ai") !== false ||
                strpos($destination, "moe_ai") !== false
            ) {
                $filtered[$pattern] = $destination;
            }
        }

        return $filtered;
    }
}