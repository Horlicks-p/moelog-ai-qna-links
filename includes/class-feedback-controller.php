<?php
/**
 * 回饋互動控制器
 *
 * @package Moelog_AIQnA
 * @since   1.10.0
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Feedback_Controller
{
    const META_KEY = "_moelog_aiqna_feedback_stats";
    const META_KEY_PREFIX = "_moelog_aiqna_feedback_stats_";
    const BOOTSTRAP_RATE_LIMIT = 60;
    const VIEW_RATE_LIMIT = 120;
    const VOTE_RATE_LIMIT = 30;
    const RATE_WINDOW = 3600;
    const VIEW_DEDUPE_WINDOW = 86400;
    const VOTE_STATE_WINDOW = 2592000;
    private static $last_rate_limit_status = null;

    /**
     * 註冊 AJAX 掛鉤
     */
    public static function register_hooks()
    {
        add_action(
            "wp_ajax_moelog_aiqna_record_view",
            [__CLASS__, "ajax_record_view"]
        );
        add_action(
            "wp_ajax_nopriv_moelog_aiqna_record_view",
            [__CLASS__, "ajax_record_view"]
        );

        add_action(
            "wp_ajax_moelog_aiqna_vote",
            [__CLASS__, "ajax_vote"]
        );
        add_action(
            "wp_ajax_nopriv_moelog_aiqna_vote",
            [__CLASS__, "ajax_vote"]
        );

        add_action(
            "wp_ajax_moelog_aiqna_report_issue",
            [__CLASS__, "ajax_report_issue"]
        );
        add_action(
            "wp_ajax_nopriv_moelog_aiqna_report_issue",
            [__CLASS__, "ajax_report_issue"]
        );

        add_action(
            "wp_ajax_moelog_aiqna_feedback_bootstrap",
            [__CLASS__, "ajax_bootstrap"]
        );
        add_action(
            "wp_ajax_nopriv_moelog_aiqna_feedback_bootstrap",
            [__CLASS__, "ajax_bootstrap"]
        );

        // 後台：清除所有回饋統計
        add_action(
            "wp_ajax_moelog_aiqna_clear_feedback_stats",
            [__CLASS__, "ajax_clear_all_stats"]
        );
    }

    /**
     * 取得統計資料
     */
    public static function get_stats($post_id, $question_hash = null)
    {
        $defaults = [
            "views" => 0,
            "likes" => 0,
            "dislikes" => 0,
        ];

        $post_id = absint($post_id);
        if (!$post_id) {
            return $defaults;
        }

        $question_hash = self::normalize_hash($question_hash);

        if ($question_hash) {
            $saved = get_post_meta(
                $post_id,
                self::get_meta_key($question_hash),
                true
            );
        } else {
            $saved = get_post_meta($post_id, self::META_KEY, true);
        }
        if (!is_array($saved)) {
            $saved = [];
        }

        foreach ($defaults as $key => $value) {
            $saved[$key] = isset($saved[$key]) ? absint($saved[$key]) : $value;
        }

        return $saved;
    }

    /**
     * AJAX: 紀錄觀看次數
     */
    public static function ajax_record_view()
    {
        self::verify_nonce();
        $target = self::validate_feedback_target();
        $identity = Moelog_AIQnA_Client_IP::anonymized_id();

        if (!self::consume_rate_limit("view", $identity, self::VIEW_RATE_LIMIT)) {
            self::send_rate_limit_error();
        }

        $should_increment = isset($_POST["increment"])
            ? filter_var($_POST["increment"], FILTER_VALIDATE_BOOLEAN)
            : true;

        if ($should_increment) {
            $dedupe_key = self::transient_key(
                "viewed",
                $identity . "|" . $target["post_id"] . "|" . $target["question_hash"]
            );
            if (get_transient($dedupe_key)) {
                $stats = self::get_stats($target["post_id"], $target["question_hash"]);
            } else {
                $stats = self::increment(
                    $target["post_id"],
                    "views",
                    $target["question_hash"]
                );
                set_transient($dedupe_key, 1, self::VIEW_DEDUPE_WINDOW);
            }
        } else {
            $stats = self::get_stats($target["post_id"], $target["question_hash"]);
        }

        wp_send_json_success(["stats" => $stats]);
    }

    /**
     * AJAX: 按讚/倒讚
     */
    public static function ajax_vote()
    {
        self::verify_nonce();

        $target = self::validate_feedback_target();
        $vote = sanitize_key($_POST["vote"] ?? "");

        if (!in_array($vote, ["like", "dislike"], true)) {
            wp_send_json_error([
                "message" => __("無效的操作", "moelog-ai-qna"),
            ]);
        }

        $identity = Moelog_AIQnA_Client_IP::anonymized_id();
        if (!self::consume_rate_limit("vote", $identity, self::VOTE_RATE_LIMIT)) {
            self::send_rate_limit_error();
        }

        $stats = self::toggle_vote(
            $target["post_id"],
            $vote,
            $target["question_hash"],
            $identity
        );

        wp_send_json_success([
            "stats" => $stats,
            "vote" => $vote,
        ]);
    }

    /**
     * 問題回報頻率限制常數
     */
    const REPORT_RATE_LIMIT = 3;        // 每小時最大回報次數
    const REPORT_MAX_LENGTH = 300;      // 訊息最大字數
    const REPORT_SITE_RATE_LIMIT = 30;  // 全站每小時最大回報次數

    /**
     * AJAX: 回報問題
     */
    public static function ajax_report_issue()
    {
        self::verify_nonce();
        $target = self::validate_feedback_target();

        // =========================================
        // 🔒 防濫用檢查
        // =========================================
        
        // 1. 蜜罐欄位檢查 (機器人陷阱)
        // 前端會有一個隱藏的 website 欄位，正常用戶不會填寫
        $honeypot = trim($_POST["website"] ?? "");
        if (!empty($honeypot)) {
            // 機器人填寫了蜜罐欄位，靜默拒絕
            Moelog_AIQnA_Debug::log_warning("Report blocked: honeypot triggered");
            wp_send_json_success([
                "message" => __("已送出,感謝您的回饋!", "moelog-ai-qna"),
            ]);
            return;
        }

        $post_id = $target["post_id"];
        $message = sanitize_textarea_field($_POST["message"] ?? "");
        $question = $target["question"];

        // 3. 訊息長度限制
        if (mb_strlen($message, 'UTF-8') > self::REPORT_MAX_LENGTH) {
            wp_send_json_error([
                "message" => sprintf(
                    __("訊息過長,請限制在 %d 字以內", "moelog-ai-qna"),
                    self::REPORT_MAX_LENGTH
                ),
            ]);
            return;
        }

        if (!$post_id || empty($message)) {
            wp_send_json_error([
                "message" => __("請輸入回饋內容", "moelog-ai-qna"),
            ]);
        }

        // 4. 最小長度檢查 (防止空白或單字元垃圾)
        if (mb_strlen(trim($message), 'UTF-8') < 5) {
            wp_send_json_error([
                "message" => __("請輸入更詳細的回饋內容", "moelog-ai-qna"),
            ]);
            return;
        }

        $admin_email = get_option("admin_email");
        if (empty($admin_email) || !is_email($admin_email)) {
            wp_send_json_error([
                "message" => __("目前無法送出回饋", "moelog-ai-qna"),
            ]);
        }

        // 只有格式有效、準備寄出的回報才消耗訪客與站點配額。
        $identity = Moelog_AIQnA_Client_IP::anonymized_id();
        if (
            !self::consume_rate_limit("report", $identity, self::REPORT_RATE_LIMIT) ||
            !self::consume_rate_limit("report_site", "site", self::REPORT_SITE_RATE_LIMIT)
        ) {
            Moelog_AIQnA_Debug::log_warning("Report rate limited");
            self::send_rate_limit_error();
        }

        $subject = sprintf(
            __("AI 回答頁回饋 - 文章 #%d", "moelog-ai-qna"),
            $post_id
        );

        $body = [];
        $body[] =
            __("收到新的回報內容：", "moelog-ai-qna") .
            "\n----------------------------------------";
        $body[] =
            __("文章標題：", "moelog-ai-qna") . " " . get_the_title($post_id);
        $body[] =
            __("文章連結：", "moelog-ai-qna") . " " . get_permalink($post_id);
        if (!empty($question)) {
            $body[] = __("題目：", "moelog-ai-qna") . " " . $question;
        }
        $body[] = __("使用者回饋：", "moelog-ai-qna");
        $body[] = $message;
        $body[] = "\n" . __("--- 系統通知 ---", "moelog-ai-qna");
        $body[] = sprintf(
            __("時間：%s", "moelog-ai-qna"),
            wp_date("Y-m-d H:i:s")
        );
        $body[] =
            __("匿名來源識別：", "moelog-ai-qna") .
            " " .
            substr($identity, 0, 16);

        $sent = wp_mail(
            $admin_email,
            $subject,
            implode("\n", $body),
            ["Content-Type: text/plain; charset=UTF-8"]
        );

        if (!$sent) {
            wp_send_json_error([
                "message" => __("郵件傳送失敗,請稍後再試", "moelog-ai-qna"),
            ]);
        }

        wp_send_json_success([
            "message" => __("已送出,感謝您的回饋!", "moelog-ai-qna"),
        ]);
    }

    /**
     * AJAX: 提供即時 nonce 與統計 (給靜態頁面初始化使用)
     */
    public static function ajax_bootstrap()
    {
        $target = self::validate_feedback_target();
        $identity = Moelog_AIQnA_Client_IP::anonymized_id();
        if (!self::consume_rate_limit("bootstrap", $identity, self::BOOTSTRAP_RATE_LIMIT)) {
            self::send_rate_limit_error();
        }

        $nonce = wp_create_nonce("moelog_aiqna_feedback");
        $stats = self::get_stats($target["post_id"], $target["question_hash"]);

        wp_send_json_success([
            "nonce" => $nonce,
            "stats" => $stats,
            "question" => $target["question"],
            "question_hash" => $target["question_hash"],
        ]);
    }

    /**
     * 驗證公開文章與實際問題，並回傳伺服器端 canonical target。
     *
     * 不信任前端 question/hash；以文章目前最多 8 個問題逐一重算 hash。
     * 前端問題原文不參與驗證，避免 WordPress request slashing 或
     * sanitize_text_field() 改寫引號、HTML/XML 字樣等合法問題。
     *
     * @return array
     */
    private static function validate_feedback_target()
    {
        $post_id = absint($_POST["post_id"] ?? 0);
        $submitted_hash = strtolower(
            sanitize_text_field($_POST["question_hash"] ?? "")
        );
        if (!$post_id || preg_match('/^[a-f0-9]{16}$/', $submitted_hash) !== 1) {
            self::send_invalid_target_error();
        }

        $post = Moelog_AIQnA_Post_Cache::get($post_id);
        if (!Moelog_AIQnA_Access_Policy::is_publicly_accessible($post)) {
            self::send_invalid_target_error();
        }

        $questions = moelog_aiqna_parse_questions(
            get_post_meta($post_id, MOELOG_AIQNA_META_KEY, true)
        );
        foreach (array_slice($questions, 0, 8) as $question) {
            $expected_hash = Moelog_AIQnA_Cache::generate_hash($post_id, $question);
            if (!hash_equals($expected_hash, $submitted_hash)) {
                continue;
            }

            return [
                "post_id" => $post_id,
                "post" => $post,
                "question" => $question,
                "question_hash" => $expected_hash,
            ];
        }

        self::send_invalid_target_error();
    }

    private static function send_invalid_target_error()
    {
        wp_send_json_error([
            "message" => __("找不到對應的內容", "moelog-ai-qna"),
        ]);
        exit();
    }

    private static function send_rate_limit_error()
    {
        $retry_after = is_array(self::$last_rate_limit_status)
            ? max(1, (int) self::$last_rate_limit_status["retry_after"])
            : self::RATE_WINDOW;
        if (!headers_sent()) {
            status_header(429);
            header("Retry-After: " . $retry_after);
        }
        wp_send_json_error([
            "message" => __("操作次數過多,請稍後再試", "moelog-ai-qna"),
            "retry_after" => $retry_after,
        ], 429);
        exit();
    }

    /**
     * Atomic fixed-window limiter backed by non-autoloaded options.
     */
    private static function consume_rate_limit($action, $identity, $limit)
    {
        $limit = (int) apply_filters(
            "moelog_aiqna_feedback_rate_limit",
            $limit,
            $action
        );
        $window = (int) apply_filters(
            "moelog_aiqna_feedback_rate_window",
            self::RATE_WINDOW,
            $action
        );
        self::$last_rate_limit_status = Moelog_AIQnA_Feedback_Rate_Limiter::consume(
            $action,
            $identity,
            max(1, $limit),
            max(1, $window)
        );

        return self::$last_rate_limit_status["allowed"] === true;
    }

    private static function transient_key($action, $value)
    {
        return "moe_aiqna_fb_" . sanitize_key($action) . "_" .
            substr(hash("sha256", (string) $value), 0, 32);
    }

    /**
     * 驗證 nonce
     */
    private static function verify_nonce()
    {
        $nonce = sanitize_text_field($_POST["nonce"] ?? "");
        if (!wp_verify_nonce($nonce, "moelog_aiqna_feedback")) {
            wp_send_json_error([
                "message" => __("驗證失敗", "moelog-ai-qna"),
            ]);
        }
    }

    /**
     * AJAX: 清除所有回饋統計（僅限管理員）
     */
    public static function ajax_clear_all_stats()
    {
        // 驗證管理員權限
        if (!current_user_can("manage_options")) {
            wp_send_json_error([
                "message" => __("權限不足", "moelog-ai-qna"),
            ]);
            return;
        }

        // 驗證 nonce
        $nonce = sanitize_text_field($_POST["nonce"] ?? "");
        if (!wp_verify_nonce($nonce, "moelog_aiqna_clear_feedback")) {
            wp_send_json_error([
                "message" => __("驗證失敗", "moelog-ai-qna"),
            ]);
            return;
        }

        global $wpdb;

        // 刪除所有回饋統計相關的 post meta
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
                $wpdb->esc_like(self::META_KEY_PREFIX) . "%"
            )
        );

        // 也刪除舊版的通用 key
        $deleted += $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                self::META_KEY
            )
        );

        Moelog_AIQnA_Debug::log_info("Cleared all feedback stats, deleted {$deleted} records");

        wp_send_json_success([
            "message" => sprintf(
                __("已清除所有回饋統計（共 %d 筆記錄）", "moelog-ai-qna"),
                $deleted
            ),
            "deleted" => $deleted,
        ]);
    }

    /**
     * 切換投票（處理切換邏輯）
     *
     * @param int    $post_id       文章 ID
     * @param string $new_vote      新的投票（like 或 dislike）
     * @param string $question_hash 問題雜湊
     * @param string $identity      匿名訪客識別
     * @return array 更新後的統計數據
     */
    private static function toggle_vote($post_id, $new_vote, $question_hash, $identity)
    {
        $post_id = absint($post_id);
        if (!$post_id) {
            wp_send_json_error([
                "message" => __("缺少文章資訊", "moelog-ai-qna"),
            ]);
        }

        $normalized_hash = self::normalize_hash($question_hash);
        $meta_key = self::get_meta_key($normalized_hash);

        $vote_state_key = self::transient_key(
            "vote_state",
            $identity . "|" . $post_id . "|" . $normalized_hash
        );
        $previous_vote = sanitize_key((string) get_transient($vote_state_key));
        if (!in_array($previous_vote, ["like", "dislike"], true)) {
            $previous_vote = "";
        }

        $stats = self::get_stats($post_id, $normalized_hash);
        
        // 確保所有欄位存在
        if (!isset($stats["likes"])) {
            $stats["likes"] = 0;
        }
        if (!isset($stats["dislikes"])) {
            $stats["dislikes"] = 0;
        }

        $new_field = $new_vote === "like" ? "likes" : "dislikes";
        $old_field = $previous_vote === "like" ? "likes" : ($previous_vote === "dislike" ? "dislikes" : null);

        // 如果用戶切換投票（從正確改為錯誤，或從錯誤改為正確）
        if ($old_field && $old_field !== $new_field) {
            // 減少之前的投票數（但不能小於 0）
            $stats[$old_field] = max(0, absint($stats[$old_field]) - 1);
            // 增加新的投票數
            $stats[$new_field] = absint($stats[$new_field]) + 1;
        } elseif ($old_field === $new_field) {
            return $stats;
        } else {
            // 第一次投票（沒有 previous_vote）
            $stats[$new_field] = absint($stats[$new_field]) + 1;
        }

        update_post_meta($post_id, $meta_key, $stats);
        set_transient($vote_state_key, $new_vote, self::VOTE_STATE_WINDOW);

        return $stats;
    }

    /**
     * 增加統計值（保留給其他用途，如觀看次數）
     */
    private static function increment($post_id, $field, $question_hash = null)
    {
        $post_id = absint($post_id);
        if (!$post_id) {
            wp_send_json_error([
                "message" => __("缺少文章資訊", "moelog-ai-qna"),
            ]);
        }

        // ✅ 相容性：如果沒有 hash，退回共用的 post meta
        $normalized_hash = self::normalize_hash($question_hash);
        $meta_key = $normalized_hash
            ? self::get_meta_key($normalized_hash)
            : self::META_KEY;

        $stats = self::get_stats($post_id, $normalized_hash);
        if (!isset($stats[$field])) {
            $stats[$field] = 0;
        }
        $stats[$field] = absint($stats[$field]) + 1;

        update_post_meta($post_id, $meta_key, $stats);

        return $stats;
    }

    /**
     * 根據 hash 取得 meta key
     *
     * @param string|null $question_hash
     * @return string
     */
    private static function get_meta_key($question_hash = null)
    {
        if (empty($question_hash)) {
            return self::META_KEY;
        }

        return self::META_KEY_PREFIX . $question_hash;
    }

    /**
     * 標準化問題 hash
     *
     * @param string|null $hash
     * @return string|null
     */
    private static function normalize_hash($hash)
    {
        if (empty($hash)) {
            return null;
        }

        // PHP 8.1+: 確保 preg_replace 不返回 null
        $hash = strtolower(preg_replace('/[^a-f0-9]/', "", (string) $hash) ?? "");

        if ($hash === "") {
            return null;
        }

        return substr($hash, 0, 16);
    }

    /**
     * 清理孤兒統計數據（對應的靜態檔案已不存在）
     *
     * @return array {
     *     @type int $scanned 掃描的 meta 數量
     *     @type int $deleted 刪除的孤兒數據數量
     *     @type array $details 詳細資訊
     * }
     */
    public static function cleanup_orphaned_stats()
    {
        global $wpdb;

        $prefix = self::META_KEY_PREFIX;
        $scanned = 0;
        $deleted = 0;
        $details = [];

        // 取得所有符合格式的 meta key
        $meta_keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} 
                 WHERE meta_key LIKE %s",
                $wpdb->esc_like($prefix) . "%"
            )
        );

        if (empty($meta_keys)) {
            return [
                "scanned" => 0,
                "deleted" => 0,
                "details" => [],
            ];
        }

        $scanned = count($meta_keys);
        $static_dir = Moelog_AIQnA_Cache::get_static_dir_path();

        foreach ($meta_keys as $meta_key) {
            // 從 meta_key 中提取 hash
            $hash = str_replace($prefix, "", $meta_key);
            if (empty($hash) || strlen($hash) !== 16) {
                continue;
            }

            // 取得所有使用此 meta_key 的文章 ID
            $post_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} 
                     WHERE meta_key = %s",
                    $meta_key
                )
            );

            foreach ($post_ids as $post_id) {
                // 檢查對應的靜態檔案是否存在
                $pattern = $static_dir . "/" . $post_id . "-" . $hash . "*.html";
                $files = glob($pattern);

                if (empty($files) || !file_exists($files[0])) {
                    // 檔案不存在，刪除統計數據
                    delete_post_meta($post_id, $meta_key);
                    $deleted++;

                    $details[] = [
                        "post_id" => $post_id,
                        "meta_key" => $meta_key,
                        "hash" => $hash,
                    ];
                }
            }
        }

        return [
            "scanned" => $scanned,
            "deleted" => $deleted,
            "details" => $details,
        ];
    }
}

Moelog_AIQnA_Feedback_Controller::register_hooks();
