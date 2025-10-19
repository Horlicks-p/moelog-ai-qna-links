<?php
/**
 * Moelog AI Q&A Router Class
 *
 * 負責 URL 路由、Rewrite Rules 註冊、URL 生成與解析
 *
 * @package Moelog_AIQnA
 * @since   1.8.1
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
     * HMAC 密鑰
     * @var string
     */
    private $secret;

    /**
     * 建構函數
     *
     * @param string $secret HMAC 密鑰
     */
    public function __construct($secret)
    {
        $this->secret = (string) $secret;
    }

    // =========================================
    // Rewrite Rules 註冊
    // =========================================

    /**
     * 註冊所有 URL 路由規則
     */
    public function register_routes()
    {
        // 註冊 query vars
        $this->register_query_vars();

        // 註冊 rewrite rules
        $this->register_rewrite_rules();
    }

    /**
     * 註冊 Query Variables
     */
    private function register_query_vars()
    {
        // 主要標識
        add_rewrite_tag("%moe_ai%", "([^&]+)");
        add_rewrite_tag("%post_id%", "([0-9]+)");

        // v1.5.x 簡短 slug 參數
        add_rewrite_tag("%v150_slug%", "([a-z0-9]+)");
        add_rewrite_tag("%v150_hash%", "([a-f0-9]{3})");

        // 舊版相容參數
        add_rewrite_tag("%k%", "([A-Za-z0-9_\-=]+)"); // token
        add_rewrite_tag("%slug%", "([^/]+)"); // 舊 slug
        add_rewrite_tag("%q%", "(.+)"); // 問題文字
        add_rewrite_tag("%lang%", "([a-z]+)"); // 語言
        add_rewrite_tag("%_nonce%", "(.+)"); // nonce
        add_rewrite_tag("%ts%", "([0-9]+)"); // 時間戳
        add_rewrite_tag("%sig%", "([A-Fa-f0-9]{64})"); // 簽章
    }

    /**
     * 註冊 Rewrite Rules
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

        // 舊版相容格式: /ai-answer/slug-123
        add_rewrite_rule(
            '^ai-answer/([^/]+)-([0-9]+)/?$',
            'index.php?moe_ai=1&slug=$matches[1]&post_id=$matches[2]',
            "top"
        );
    }

    // =========================================
    // URL 生成
    // =========================================

    /**
     * 建立回答頁 URL (公開 API)
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
     * 策略:
     * 1. 提取大寫縮寫 (API, URL)
     * 2. 提取英文單字首字母
     * 3. 後備: q + md5 hash
     *
     * @param string $text    原始文字
     * @param int    $max_len 最大長度
     * @return string
     */
    private function extract_meaningful_chars($text, $max_len = 3)
    {
        $text = trim((string) $text);
        if (empty($text)) {
            return "q";
        }

        // 策略 1: 提取連續大寫字母 (API, URL, HTTP)
        if (preg_match_all("/\b[A-Z]{2,}\b/", $text, $matches)) {
            $abbr = strtolower(implode("", $matches[0]));
            if (strlen($abbr) >= 2) {
                return substr($abbr, 0, $max_len);
            }
        }

        // 策略 2: 提取英文單字首字母
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

        // 策略 3: 後備方案
        return "q" . substr(md5($text), 0, $max_len - 1);
    }

    // =========================================
    // URL 解析與驗證
    // =========================================

    /**
     * 解析當前請求並返回參數
     *
     * @return array|false {
     *     @type int    $post_id  文章 ID
     *     @type string $question 問題文字
     *     @type string $lang     語言代碼
     * }
     */
    public function parse_request()
    {
        // 檢查是否為回答頁請求
        if (!$this->is_answer_request()) {
            return false;
        }

        $post_id = 0;
        $question = "";
        $lang = "auto";

        // === 解析策略 1: v1.5.x 簡短 slug ===
        $v150_slug = get_query_var("v150_slug");
        $v150_hash = get_query_var("v150_hash");
        $query_post_id = get_query_var("post_id");

        if ($query_post_id && $v150_slug && $v150_hash) {
            $result = $this->parse_v150_slug(
                intval($query_post_id),
                $v150_slug,
                $v150_hash
            );
            if ($result) {
                return $result;
            }
        }

        // === 解析策略 2: 舊版 token 格式 ===
        $token = isset($_GET["k"]) ? sanitize_text_field($_GET["k"]) : "";
        $query_post_id = isset($_GET["post_id"])
            ? intval($_GET["post_id"])
            : intval(get_query_var("post_id"));

        if ($query_post_id && $token) {
            $result = $this->parse_legacy_token($query_post_id, $token);
            if ($result) {
                return $result;
            }
        }

        // === 解析策略 3: 直接參數(最舊格式) ===
        $result = $this->parse_direct_params();
        if ($result) {
            return $result;
        }

        return false;
    }

    /**
     * 解析 v1.5.x 簡短 slug
     *
     * @param int    $post_id     文章 ID
     * @param string $v150_slug   slug 前綴
     * @param string $v150_hash   hash 部分
     * @return array|false
     */
    private function parse_v150_slug($post_id, $v150_slug, $v150_hash)
    {
        // 讀取文章的所有問題
        $raw_questions = get_post_meta($post_id, MOELOG_AIQNA_META_KEY, true);
        $questions = $this->parse_questions_list($raw_questions);

        if (empty($questions)) {
            return false;
        }

        // 讀取語言設定
        $langs = get_post_meta($post_id, MOELOG_AIQNA_META_LANG_KEY, true);
        $langs = is_array($langs) ? $langs : [];

        // 驗證每個問題
        foreach ($questions as $idx => $candidate_question) {
            $expected_slug = $this->slugify_question(
                $candidate_question,
                $post_id
            );
            $full_slug = $v150_slug . "-" . $v150_hash . "-" . $post_id;

            if ($expected_slug === $full_slug) {
                return [
                    "post_id" => $post_id,
                    "question" => $candidate_question,
                    "lang" => $langs[$idx] ?? "auto",
                ];
            }
        }

        return false;
    }

    /**
     * 解析舊版 token 格式
     *
     * @param int    $post_id 文章 ID
     * @param string $token   驗證 token
     * @return array|false
     */
    private function parse_legacy_token($post_id, $token)
    {
        $raw_questions = get_post_meta($post_id, MOELOG_AIQNA_META_KEY, true);
        $questions = $this->parse_questions_list($raw_questions);

        if (empty($questions)) {
            return false;
        }

        $langs = get_post_meta($post_id, MOELOG_AIQNA_META_LANG_KEY, true);
        $langs = is_array($langs) ? $langs : [];

        // 驗證每個問題的 token
        foreach ($questions as $idx => $candidate_question) {
            $expected_token = $this->build_token($post_id, $candidate_question);

            if (hash_equals($expected_token, $token)) {
                return [
                    "post_id" => $post_id,
                    "question" => $candidate_question,
                    "lang" => $langs[$idx] ?? "auto",
                ];
            }
        }

        return false;
    }

    /**
     * 解析直接參數(最舊格式,需簽章驗證)
     *
     * @return array|false
     */
    private function parse_direct_params()
    {
        $post_id = isset($_GET["post_id"]) ? intval($_GET["post_id"]) : 0;
        $question = isset($_GET["q"]) ? wp_unslash($_GET["q"]) : "";
        $nonce = isset($_GET["_nonce"])
            ? sanitize_text_field($_GET["_nonce"])
            : "";
        $ts = isset($_GET["ts"]) ? intval($_GET["ts"]) : 0;
        $sig = isset($_GET["sig"]) ? sanitize_text_field($_GET["sig"]) : "";
        $lang = isset($_GET["lang"])
            ? sanitize_text_field($_GET["lang"])
            : "auto";

        if (!$post_id || !$question || !$nonce || !$ts || !$sig) {
            return false;
        }

        // 時間戳驗證(15 分鐘內有效)
        if (abs(time() - $ts) > MINUTE_IN_SECONDS * 15) {
            wp_die(
                __("連結已過期,請回原文重新點擊問題。", "moelog-ai-qna"),
                __("錯誤", "moelog-ai-qna"),
                ["response" => 403]
            );
        }

        // Nonce 驗證
        $nonce_action =
            "moe_aiqna_open|" .
            $post_id .
            "|" .
            substr(
                hash_hmac("sha256", (string) $post_id, $this->secret),
                0,
                12
            );

        if (!wp_verify_nonce($nonce, $nonce_action)) {
            wp_die(
                __("連結驗證失敗或已過期。", "moelog-ai-qna"),
                __("錯誤", "moelog-ai-qna"),
                ["response" => 403]
            );
        }

        // 簽章驗證
        $expected_sig = hash_hmac(
            "sha256",
            $post_id . "|" . $question . "|" . $ts,
            $this->secret
        );
        if (!hash_equals($expected_sig, $sig)) {
            wp_die(
                __("簽章檢核失敗。", "moelog-ai-qna"),
                __("錯誤", "moelog-ai-qna"),
                ["response" => 403]
            );
        }

        return [
            "post_id" => $post_id,
            "question" => $question,
            "lang" => $lang,
        ];
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
        // 檢查 query var
        global $wp_query;
        if (isset($wp_query)) {
            $moe_ai = get_query_var("moe_ai");
            if ($moe_ai) {
                return true;
            }
        }

        // 檢查 URL 路徑
        $request_uri = $_SERVER["REQUEST_URI"] ?? "";
        if (strpos($request_uri, "/" . self::PRETTY_BASE . "/") !== false) {
            return true;
        }
        if (strpos($request_uri, "/ai-answer/") !== false) {
            return true;
        }

        // 檢查直接參數
        if (isset($_GET["moe_ai"])) {
            return true;
        }

        return false;
    }

    /**
     * 解析問題列表(從 meta 值)
     *
     * @param mixed $raw 原始問題資料
     * @return array
     */
    private function parse_questions_list($raw)
    {
        // ... 後續程式碼保持不變
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

        // 字串(每行一題)
        if (is_string($raw)) {
            $questions = array_filter(
                array_map("trim", preg_split('/\r\n|\n|\r/', $raw))
            );
            return array_slice(array_values($questions), 0, 8);
        }

        return [];
    }

    /**
     * 建立舊版 token(向後相容)
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     * @return string
     */
    private function build_token($post_id, $question)
    {
        $raw = hash_hmac(
            "sha256",
            $post_id . "|" . $question,
            $this->secret,
            true
        );
        return rtrim(strtr(base64_encode($raw), "+/", "-_"), "=");
    }

    /**
     * 取得 HMAC 密鑰
     *
     * @return string
     */
    public function get_secret()
    {
        return $this->secret;
    }

    // =========================================
    // Debug 輔助(僅在 WP_DEBUG 時使用)
    // =========================================

    /**
     * 輸出當前路由資訊(除錯用)
     *
     * @return array
     */
    public function debug_current_route()
    {
        if (!defined("WP_DEBUG") || !WP_DEBUG) {
            return [];
        }

        return [
            "is_answer_request" => $this->is_answer_request(),
            "query_vars" => [
                "moe_ai" => get_query_var("moe_ai"),
                "post_id" => get_query_var("post_id"),
                "v150_slug" => get_query_var("v150_slug"),
                "v150_hash" => get_query_var("v150_hash"),
                "slug" => get_query_var("slug"),
            ],
            "request_uri" => $_SERVER["REQUEST_URI"] ?? "",
            "parsed_params" => $this->parse_request(),
        ];
    }

    /**
     * 列出所有已註冊的 rewrite rules(除錯用)
     *
     * @return array
     */
    public function debug_rewrite_rules()
    {
        if (!defined("WP_DEBUG") || !WP_DEBUG) {
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
