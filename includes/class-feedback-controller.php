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
        $post_id = absint($_POST["post_id"] ?? 0);
        $question_hash = sanitize_text_field($_POST["question_hash"] ?? "");

        $should_increment = isset($_POST["increment"])
            ? filter_var($_POST["increment"], FILTER_VALIDATE_BOOLEAN)
            : true;

        if ($should_increment) {
            $stats = self::increment($post_id, "views", $question_hash);
        } else {
            $stats = self::get_stats($post_id, $question_hash);
        }

        wp_send_json_success(["stats" => $stats]);
    }

    /**
     * AJAX: 按讚/倒讚
     */
    public static function ajax_vote()
    {
        self::verify_nonce();

        $post_id = absint($_POST["post_id"] ?? 0);
        $vote = sanitize_key($_POST["vote"] ?? "");

        if (!in_array($vote, ["like", "dislike"], true)) {
            wp_send_json_error([
                "message" => __("無效的操作", "moelog-ai-qna"),
            ]);
        }

        $question_hash = sanitize_text_field($_POST["question_hash"] ?? "");
        // 取得用戶之前的投票（從前端傳入）
        $previous_vote = sanitize_key($_POST["previous_vote"] ?? "");
        
        $stats = self::toggle_vote($post_id, $vote, $previous_vote, $question_hash);

        wp_send_json_success([
            "stats" => $stats,
            "vote" => $vote,
        ]);
    }

    /**
     * AJAX: 回報問題
     */
    public static function ajax_report_issue()
    {
        self::verify_nonce();

        $post_id = absint($_POST["post_id"] ?? 0);
        $message = sanitize_textarea_field($_POST["message"] ?? "");
        $question = sanitize_text_field($_POST["question"] ?? "");
        $question_hash = sanitize_text_field($_POST["question_hash"] ?? "");

        if (!$post_id || empty($message)) {
            wp_send_json_error([
                "message" => __("請輸入回饋內容", "moelog-ai-qna"),
            ]);
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error([
                "message" => __("找不到對應的內容", "moelog-ai-qna"),
            ]);
        }

        $admin_email = get_option("admin_email");
        if (empty($admin_email) || !is_email($admin_email)) {
            wp_send_json_error([
                "message" => __("目前無法送出回饋", "moelog-ai-qna"),
            ]);
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
            __("來源 IP：", "moelog-ai-qna") .
            " " .
            sanitize_text_field($_SERVER["REMOTE_ADDR"] ?? "unknown");

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
        $post_id = absint($_POST["post_id"] ?? 0);
        $question_hash = sanitize_text_field($_POST["question_hash"] ?? "");
        $question = sanitize_text_field($_POST["question"] ?? "");

        if (!$post_id) {
            wp_send_json_error([
                "message" => __("缺少文章資訊", "moelog-ai-qna"),
            ]);
        }

        $nonce = wp_create_nonce("moelog_aiqna_feedback");
        $stats = self::get_stats($post_id, $question_hash);

        wp_send_json_success([
            "nonce" => $nonce,
            "stats" => $stats,
            "question" => $question,
            "question_hash" => $question_hash,
        ]);
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
     * 切換投票（處理切換邏輯）
     *
     * @param int    $post_id       文章 ID
     * @param string $new_vote      新的投票（like 或 dislike）
     * @param string $previous_vote 之前的投票（like、dislike 或空字串）
     * @param string $question_hash 問題雜湊
     * @return array 更新後的統計數據
     */
    private static function toggle_vote($post_id, $new_vote, $previous_vote, $question_hash = null)
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
            // 如果用戶點擊相同的按鈕，不處理（或可以減少，視需求而定）
            // 這裡選擇不處理，保持原樣
            return $stats;
        } else {
            // 第一次投票（沒有 previous_vote）
            $stats[$new_field] = absint($stats[$new_field]) + 1;
        }

        update_post_meta($post_id, $meta_key, $stats);

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
        $static_dir = WP_CONTENT_DIR . "/" . Moelog_AIQnA_Cache::STATIC_DIR;

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
                $pattern = $static_dir . "/" . $post_id . "-" . $hash . ".html";
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

