<?php
/**
 * Moelog AI Q&A Pregenerate Class
 *
 * 負責背景預生成 AI 答案:
 * - 文章發布時自動排程
 * - WP-Cron 任務管理
 * - 批次預生成
 * - 失敗重試機制
 * - 進度追蹤
 *
 * @package Moelog_AIQnA
 * @since   1.8.3
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Pregenerate
{
    /**
     * AI 客戶端實例
     * @var Moelog_AIQnA_AI_Client
     */
    private $ai_client;

    /**
     * 渲染器實例
     * @var Moelog_AIQnA_Renderer
     */
    private $renderer;

    /**
     * 單次任務間隔(秒)
     */
    const TASK_INTERVAL = 90;

    /**
     * 最大重試次數
     */
    const MAX_RETRIES = 2;

    /**
     * 失敗延遲時間(秒)
     */
    const RETRY_DELAY = 300; // 5 分鐘

    /**
     * 建構函數
     *
     * @param Moelog_AIQnA_AI_Client $ai_client AI 客戶端
     * @param Moelog_AIQnA_Renderer  $renderer  渲染器
     */
    public function __construct($ai_client, $renderer)
    {
        $this->ai_client = $ai_client;
        $this->renderer = $renderer;
    }

    // =========================================
    // 自動排程
    // =========================================

    /**
     * 文章發布時排程預生成
     *
     * @param int     $post_id 文章 ID
     * @param WP_Post $post    文章物件
     */
    public function schedule_pregenerate($post_id, $post)
    {
        // 忽略自動儲存和修訂版本
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        // 只處理已發布的文章
        if ($post->post_status !== "publish") {
            return;
        }

        // ✅ 新增:檢查是否為首次發布或從草稿變為發布
        $previous_status = get_post_meta(
            $post_id,
            "_moelog_aiqna_previous_status",
            true
        );

        // 如果之前已經是 publish 狀態,不重新生成(除非手動清除快取)
        if ($previous_status === "publish") {
            return;
        }

        // 更新狀態記錄
        update_post_meta($post_id, "_moelog_aiqna_previous_status", "publish");

        // 讀取問題列表
        $questions = $this->get_questions($post_id);

        if (empty($questions)) {
            return;
        }

        // 為每個問題排程預生成任務
        $delay = 60; // 首次延遲 60 秒(避免發布時的高峰)

        foreach ($questions as $index => $question) {
            // 檢查是否已有快取
            if (Moelog_AIQnA_Cache::exists($post_id, $question)) {
                if (defined("WP_DEBUG") && WP_DEBUG) {
                    moelog_aiqna_log(
                        sprintf(
                            "Pregenerate skipped (cache exists): Post %d, Question: %s",
                            $post_id,
                            substr($question, 0, 50)
                        )
                    );
                }
                continue;
            }

            // 排程單一任務
            $this->schedule_single_task($post_id, $question, $delay);

            // 遞增延遲(避免同時呼叫 API)
            $delay += self::TASK_INTERVAL;
        }

        if (defined("WP_DEBUG") && WP_DEBUG) {
            moelog_aiqna_log(
                sprintf(
                    "Scheduled %d pregeneration tasks for post %d",
                    count($questions),
                    $post_id
                )
            );
        }
    }

    /**
     * 排程單一預生成任務
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     * @param int    $delay    延遲時間(秒)
     */
    private function schedule_single_task($post_id, $question, $delay = 10)
    {
        $hook = "moelog_aiqna_pregenerate";
        $args = [$post_id, $question];

        // 1) 去重:若相同參數的事件已排進 cron,就不要再排
        if (wp_next_scheduled($hook, $args)) {
            return;
        }

        // 2) 節流:用 transient 當 60 秒鎖,避免短時間內重覆進來
        $lock = "aiqna_pregen_lock_" . md5($post_id . "|" . $question);
        if (get_transient($lock)) {
            return;
        }
        set_transient($lock, 1, 60);

        // 3) 真正排程(可依索引錯開數秒,避免同時打 API)
        wp_schedule_single_event(time() + $delay, $hook, $args);
    }

    // =========================================
    // 預生成執行
    // =========================================

    /**
     * 執行預生成任務
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     */
    public function pregenerate_answer($post_id, $question)
    {
        // 開始計時
        $start_time = microtime(true);

        if (defined("WP_DEBUG") && WP_DEBUG) {
            moelog_aiqna_log(
                sprintf(
                    "Pregenerate started: Post %d, Question: %s",
                    $post_id,
                    substr($question, 0, 50)
                )
            );
        }

        // 檢查是否已有快取
        if (Moelog_AIQnA_Cache::exists($post_id, $question)) {
            if (defined("WP_DEBUG") && WP_DEBUG) {
                moelog_aiqna_log("Pregenerate skipped: Cache already exists");
            }
            return;
        }

        // 驗證文章存在
        $post = get_post($post_id);
        if (!$post) {
            $this->log_error($post_id, $question, "Post not found");
            return;
        }

        // 準備參數
        $params = $this->prepare_params($post_id, $question);

        try {
            // 呼叫 AI 生成答案
            $answer = $this->ai_client->generate_answer($params);

            // 檢查是否為錯誤訊息
            if ($this->is_error_response($answer)) {
                throw new Exception($answer);
            }

            // 建立 HTML
            $html = $this->renderer->render_for_pregeneration(
                $post_id,
                $question,
                $params["lang"]
            );

            if (!$html) {
                throw new Exception("Failed to render HTML");
            }

            // 儲存快取
            $saved = Moelog_AIQnA_Cache::save($post_id, $question, $html);

            if (!$saved) {
                throw new Exception("Failed to save cache");
            }

            // 記錄成功
            $duration = round((microtime(true) - $start_time) * 1000);
            $this->log_success($post_id, $question, $duration);
        } catch (Exception $e) {
            // 記錄失敗
            $this->log_error($post_id, $question, $e->getMessage());

            // 嘗試重試
            $this->maybe_retry($post_id, $question);
        }
    }

    /**
     * 準備 AI 參數
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     * @return array
     */
    private function prepare_params($post_id, $question)
    {
        // 偵測語言
        $langs = $this->get_languages($post_id);
        $questions = $this->get_questions($post_id);
        $index = array_search($question, $questions);
        $lang =
            $index !== false && isset($langs[$index]) ? $langs[$index] : "auto";

        if ($lang === "auto") {
            $lang = moelog_aiqna_detect_language($question);
        }

        // 取得設定
        $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);

        // 準備文章內容
        $context = "";
        if (!empty($settings["include_content"])) {
            $post = get_post($post_id);
            if ($post) {
                $max_chars = isset($settings["max_chars"])
                    ? intval($settings["max_chars"])
                    : 6000;
                $raw =
                    $post->post_title .
                    "\n\n" .
                    strip_shortcodes($post->post_content);
                $raw = wp_strip_all_tags($raw);
                $raw = preg_replace("/\s+/u", " ", $raw);

                if (function_exists("mb_strcut")) {
                    $context =
                        mb_strlen($raw, "UTF-8") > $max_chars
                            ? mb_strcut($raw, 0, $max_chars * 4, "UTF-8")
                            : $raw;
                } else {
                    $context =
                        strlen($raw) > $max_chars
                            ? substr($raw, 0, $max_chars)
                            : $raw;
                }
            }
        }

        return [
            "post_id" => $post_id,
            "question" => $question,
            "lang" => $lang,
            "context" => $context,
        ];
    }

    /**
     * 檢查是否為錯誤回應
     *
     * @param string $answer 答案內容
     * @return bool
     */
    private function is_error_response($answer)
    {
        if (empty($answer)) {
            return true;
        }

        $error_keywords = [
            "失敗",
            "錯誤",
            "無法",
            "暫時無法",
            "異常",
            "fail",
            "error",
            "unable",
            "unavailable",
            "invalid",
        ];

        foreach ($error_keywords as $keyword) {
            if (stripos($answer, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    // =========================================
    // 重試機制
    // =========================================

    /**
     * 嘗試重試
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     */
    private function maybe_retry($post_id, $question)
    {
        // 取得重試次數
        $retry_count = $this->get_retry_count($post_id, $question);

        if ($retry_count >= self::MAX_RETRIES) {
            if (defined("WP_DEBUG") && WP_DEBUG) {
                moelog_aiqna_log(
                    sprintf(
                        "Max retries reached for post %d, question: %s",
                        $post_id,
                        substr($question, 0, 50)
                    )
                );
            }
            return;
        }

        // 遞增重試次數
        $this->increment_retry_count($post_id, $question);

        // 排程重試任務
        $delay = self::RETRY_DELAY * ($retry_count + 1); // 5m, 10m, 15m...
        $this->schedule_single_task($post_id, $question, $delay);

        if (defined("WP_DEBUG") && WP_DEBUG) {
            moelog_aiqna_log(
                sprintf(
                    "Scheduled retry %d for post %d, delay: %d seconds",
                    $retry_count + 1,
                    $post_id,
                    $delay
                )
            );
        }
    }

    /**
     * 取得重試次數
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     * @return int
     */
    private function get_retry_count($post_id, $question)
    {
        $key = $this->get_retry_key($post_id, $question);
        return (int) get_transient($key);
    }

    /**
     * 遞增重試次數
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     */
    private function increment_retry_count($post_id, $question)
    {
        $key = $this->get_retry_key($post_id, $question);
        $count = $this->get_retry_count($post_id, $question);
        set_transient($key, $count + 1, DAY_IN_SECONDS);
    }

    /**
     * 清除重試次數
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     */
    private function clear_retry_count($post_id, $question)
    {
        $key = $this->get_retry_key($post_id, $question);
        delete_transient($key);
    }

    /**
     * 取得重試鍵值
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     * @return string
     */
    private function get_retry_key($post_id, $question)
    {
        return "moe_aiqna_retry_" . md5($post_id . "|" . $question);
    }

    // =========================================
    // 批次預生成
    // =========================================

    /**
     * 批次預生成指定文章的所有問題
     *
     * @param int $post_id 文章 ID
     * @return array 結果摘要
     */
    public function batch_pregenerate($post_id)
    {
        $questions = $this->get_questions($post_id);

        if (empty($questions)) {
            return [
                "success" => false,
                "message" => __("文章沒有設定問題", "moelog-ai-qna"),
            ];
        }

        $scheduled = 0;
        $skipped = 0;
        $delay = 0;

        foreach ($questions as $question) {
            // 檢查快取
            if (Moelog_AIQnA_Cache::exists($post_id, $question)) {
                $skipped++;
                continue;
            }

            // 排程任務
            $this->schedule_single_task($post_id, $question, $delay);
            $scheduled++;
            $delay += self::TASK_INTERVAL;
        }

        return [
            "success" => true,
            "total" => count($questions),
            "scheduled" => $scheduled,
            "skipped" => $skipped,
            "message" => sprintf(
                __(
                    "已排程 %d 個預生成任務 (跳過 %d 個已快取)",
                    "moelog-ai-qna"
                ),
                $scheduled,
                $skipped
            ),
        ];
    }

    /**
     * 批次預生成多篇文章
     *
     * @param array $post_ids 文章 ID 陣列
     * @return array 結果摘要
     */
    public function batch_pregenerate_posts($post_ids)
    {
        $total_scheduled = 0;
        $total_skipped = 0;
        $failed = [];

        foreach ($post_ids as $post_id) {
            $result = $this->batch_pregenerate($post_id);

            if ($result["success"]) {
                $total_scheduled += $result["scheduled"];
                $total_skipped += $result["skipped"];
            } else {
                $failed[] = $post_id;
            }
        }

        return [
            "success" => empty($failed),
            "scheduled" => $total_scheduled,
            "skipped" => $total_skipped,
            "failed" => $failed,
            "message" => sprintf(
                __(
                    "已排程 %d 個預生成任務 (跳過 %d 個, 失敗 %d 篇)",
                    "moelog-ai-qna"
                ),
                $total_scheduled,
                $total_skipped,
                count($failed)
            ),
        ];
    }

    // =========================================
    // 任務管理
    // =========================================

    /**
     * 取得待執行的任務數量
     *
     * @return int
     */
    public function get_pending_tasks_count()
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                "_transient_timeout_doing_cron%moelog_aiqna_pregenerate%"
            )
        );

        return (int) $count;
    }

    /**
     * 取消所有待執行的任務
     *
     * @return int 取消的任務數量
     */
    public function cancel_all_tasks()
    {
        global $wpdb;

        // 清除所有相關的 cron 任務
        $crons = _get_cron_array();
        $cancelled = 0;

        if (!is_array($crons)) {
            return 0;
        }

        foreach ($crons as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                if ($hook === "moelog_aiqna_pregenerate") {
                    foreach ($events as $key => $event) {
                        wp_unschedule_event($timestamp, $hook, $event["args"]);
                        $cancelled++;
                    }
                }
            }
        }

        // 清除重試計數
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_moe_aiqna_retry_%'"
        );

        if (defined("WP_DEBUG") && WP_DEBUG) {
            moelog_aiqna_log(
                sprintf("Cancelled %d pregeneration tasks", $cancelled)
            );
        }

        return $cancelled;
    }

    /**
     * 取得任務列表(除錯用)
     *
     * @return array
     */
    public function get_task_list()
    {
        if (!defined("WP_DEBUG") || !WP_DEBUG) {
            return [];
        }

        $crons = _get_cron_array();
        $tasks = [];

        if (!is_array($crons)) {
            return [];
        }

        foreach ($crons as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                if ($hook === "moelog_aiqna_pregenerate") {
                    foreach ($events as $key => $event) {
                        $args = $event["args"];
                        $tasks[] = [
                            "timestamp" => $timestamp,
                            "scheduled" => date("Y-m-d H:i:s", $timestamp),
                            "post_id" => $args[0] ?? 0,
                            "question" => isset($args[1])
                                ? substr($args[1], 0, 50)
                                : "",
                        ];
                    }
                }
            }
        }

        // 按時間排序
        usort($tasks, function ($a, $b) {
            return $a["timestamp"] - $b["timestamp"];
        });

        return $tasks;
    }

    // =========================================
    // 日誌記錄
    // =========================================

    /**
     * 記錄成功
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     * @param int    $duration 耗時(毫秒)
     */
    private function log_success($post_id, $question, $duration)
    {
        // 清除重試次數
        $this->clear_retry_count($post_id, $question);

        if (defined("WP_DEBUG") && WP_DEBUG) {
            moelog_aiqna_log(
                sprintf(
                    "Pregenerate success: Post %d, Question: %s, Duration: %dms",
                    $post_id,
                    substr($question, 0, 50),
                    $duration
                )
            );
        }
    }

    /**
     * 記錄錯誤
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     * @param string $error    錯誤訊息
     */
    private function log_error($post_id, $question, $error)
    {
        if (defined("WP_DEBUG") && WP_DEBUG) {
            moelog_aiqna_log(
                sprintf(
                    "Pregenerate failed: Post %d, Question: %s, Error: %s",
                    $post_id,
                    substr($question, 0, 50),
                    $error
                ),
                "error"
            );
        }
    }

    // =========================================
    // 輔助方法
    // =========================================

    /**
     * 取得問題列表
     *
     * @param int $post_id 文章 ID
     * @return array
     */
    private function get_questions($post_id)
    {
        $raw = get_post_meta($post_id, MOELOG_AIQNA_META_KEY, true);
        return moelog_aiqna_parse_questions($raw);
    }

    /**
     * 取得語言列表
     *
     * @param int $post_id 文章 ID
     * @return array
     */
    private function get_languages($post_id)
    {
        $langs = get_post_meta($post_id, MOELOG_AIQNA_META_LANG_KEY, true);
        return is_array($langs) ? $langs : [];
    }

    // =========================================
    // WP-CLI 支援
    // =========================================

    /**
     * 手動執行預生成(WP-CLI)
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字(可選,預設全部)
     * @return array
     */
    public function cli_pregenerate($post_id, $question = "")
    {
        if (!empty($question)) {
            // 預生成單一問題
            $this->pregenerate_answer($post_id, $question);
            return [
                "success" => true,
                "message" => "Single question pregenerated",
            ];
        } else {
            // 預生成所有問題
            return $this->batch_pregenerate($post_id);
        }
    }
}
