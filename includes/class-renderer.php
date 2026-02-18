<?php
/**
 * Moelog AI Q&A Renderer Class
 *
 * 負責渲染 AI 回答頁面:
 * - 解析請求參數
 * - 檢查快取
 * - 呼叫 AI 生成答案
 * - 渲染 HTML 模板
 * - 輸出 HTTP Headers
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
  exit();
}

class Moelog_AIQnA_Renderer
{
  /**
   * 路由器實例
   * @var Moelog_AIQnA_Router
   */
  private $router;

  /**
   * AI 客戶端實例
   * @var Moelog_AIQnA_AI_Client
   */
  private $ai_client;

  /**
   * HMAC 密鑰
   * @var string
   */
  private $secret;

  /**
   * 安全處理實例
   * @var Moelog_AIQnA_Renderer_Security
   */
  private $security;

  /**
   * 模板處理實例
   * @var Moelog_AIQnA_Renderer_Template
   */
  private $template;

  /**
   * 頻率限制 TTL (秒)
   */
  const RATE_TTL = 60;

  /**
   * 每小時每 IP 最大請求數
   */
  const MAX_REQUESTS_PER_HOUR = 10;

  /**
   * Nonce placeholder 常數
   */
  const NONCE_PLACEHOLDER = "{{MOELOG_CSP_NONCE}}";

  /**
   * 建構函數
   *
   * @param Moelog_AIQnA_Router    $router    路由器
   * @param Moelog_AIQnA_AI_Client $ai_client AI 客戶端
   * @param string                 $secret    HMAC 密鑰
   */
  public function __construct($router, $ai_client, $secret)
  {
    $this->router = $router;
    $this->ai_client = $ai_client;
    $this->secret = $secret;

    // 初始化安全處理和模板處理類別
    $this->security = new Moelog_AIQnA_Renderer_Security();
    $this->template = new Moelog_AIQnA_Renderer_Template($router, $ai_client);
  }

  // =========================================
  // 主要渲染流程
  // =========================================

  /**
   * 渲染回答頁面(主入口)
   */
  public function render_answer_page()
  {
    // 檢查是否為回答頁請求
    if (!$this->router->is_answer_request()) {
      return;
    }

    // 修正: 在這裡生成 Nonce, 確保 router 狀態正確
    $nonce = $this->security->generate_csp_nonce();
    $this->template->set_csp_nonce($nonce);

    // 設定安全 Headers
    $this->security->set_security_headers();

    // 執行爬蟲封鎖
    $this->security->block_unwanted_bots();

    // 處理預抓取請求
    if ($this->is_prefetch_request()) {
      $this->handle_prefetch();
      return;
    }

    // 解析請求參數
    $params = $this->router->parse_request();
    if (!$params) {
      $this->render_error(400, __("參數錯誤或連結已失效。", "moelog-ai-qna"));
      return;
    }

    // 驗證文章存在（使用快取）
    $post = Moelog_AIQnA_Post_Cache::get($params["post_id"]);
    if (!$post) {
      $this->render_error(404, __("找不到文章。", "moelog-ai-qna"));
      return;
    }

    // 檢查靜態快取 (load 內部已包含 exists 檢查,避免重複檔案系統操作)
    $html = Moelog_AIQnA_Cache::load($params["post_id"], $params["question"]);
    if ($html) {
      // ⭐ 關鍵:讀取快取時,替換 placeholder 為新的 nonce
      $html = $this->template->replace_nonce_in_html($html, $nonce);

      $this->security->set_cache_headers(true);
      $this->output_html($html);
      exit();
    }

    // 執行頻率限制
    if (!$this->check_rate_limit($params["post_id"], $params["question"])) {
      $this->render_error(
        429,
        __("請求過於頻繁,請稍後再試。", "moelog-ai-qna"),
      );
      return;
    }

    // 生成新答案
    $this->generate_and_render($params, $post);
  }

  /**
   * 生成新答案並渲染
   *
   * ✅ 優化: 取得設定一次後傳入子方法,避免重複 get_option
   *
   * @param array   $params 請求參數
   * @param WP_Post $post   文章物件
   */
  private function generate_and_render($params, $post)
  {
    $question_hash = Moelog_AIQnA_Cache::generate_hash(
      $params["post_id"],
      $params["question"],
    );
    $params["question_hash"] = $question_hash;

    // 準備 AI 呼叫參數
    $ai_params = [
      "post_id" => $params["post_id"],
      "question" => $params["question"],
      "lang" =>
        $params["lang"] !== "auto"
          ? $params["lang"]
          : $this->detect_language($params["question"]),
      "context" => $this->get_post_context($post),
    ];

    // 呼叫 AI 生成答案
    $answer = $this->ai_client->generate_answer($ai_params);

    // ✅ 優化: 檢查答案是否為錯誤訊息,避免寫入錯誤快取(優化項目 5)
    if ($this->template->is_error_response($answer)) {
      // 不寫入快取,直接渲染錯誤頁面
      $this->render_error(500, $answer);
      return;
    }

    if (!empty($question_hash)) {
      delete_post_meta(
        $params["post_id"],
        "_moelog_aiqna_feedback_stats_" . $question_hash,
      );
    }

    // 建立 HTML
    $html = $this->template->build_html($params, $post, $answer);

    // ⭐ 儲存前:將 nonce 替換成 placeholder
    $html_for_cache = $this->template->replace_nonce_in_html(
      $html,
      self::NONCE_PLACEHOLDER,
    );
    Moelog_AIQnA_Cache::save(
      $params["post_id"],
      $params["question"],
      $html_for_cache,
    );

    // 設定 Headers
    $this->security->set_cache_headers(false);

    // 輸出(使用含真實 nonce 的版本)
    $this->output_html($html);
    exit();
  }

  // =========================================
  // HTTP Headers 處理
  // =========================================

  /**
   * 輸出 HTML 並結束執行
   *
   * @param string $html HTML 內容
   */
  private function output_html($html)
  {
    if (!headers_sent()) {
      status_header(200);
      header("Content-Type: text/html; charset=UTF-8");
    }
    echo $html;
  }

  // =========================================
  // 輔助方法
  // =========================================

  /**
   * 取得文章內容作為上下文
   *
   * @param WP_Post $post 文章物件
   * @return string
   */
  private function get_post_context($post)
  {
    // 檢查是否啟用內容附加
    if (!Moelog_AIQnA_Settings::include_content()) {
      return "";
    }
    $max_chars = Moelog_AIQnA_Settings::get_max_chars();
    $raw = $post->post_title . "\n\n" . strip_shortcodes($post->post_content);
    $raw = wp_strip_all_tags($raw);
    // PHP 8.1+: 確保 preg_replace 不返回 null
    $raw = preg_replace("/\s+/u", " ", $raw) ?? "";

    // 截斷
    if (function_exists("mb_strlen") && function_exists("mb_strcut")) {
      return mb_strlen($raw, "UTF-8") > $max_chars
        ? mb_strcut($raw, 0, $max_chars * 4, "UTF-8")
        : $raw;
    } else {
      return strlen($raw) > $max_chars ? substr($raw, 0, $max_chars) : $raw;
    }
  }

  /**
   * 檢測語言
   *
   * @param string $text 文字
   * @return string 語言代碼 (ja|zh|en)
   */
  private function detect_language($text)
  {
    return moelog_aiqna_detect_language($text);
  }

  /**
   * 檢查是否為預抓取請求
   *
   * @return bool
   */
  private function is_prefetch_request()
  {
    return isset($_GET["pf"]) && $_GET["pf"] === "1";
  }

  /**
   * 處理預抓取請求
   */
  private function handle_prefetch()
  {
    status_header(204);
    header("Cache-Control: private, max-age=300");
    exit();
  }

  /**
   * 檢查頻率限制
   *
   * ✅ 優化: 使用雙層快取機制（wp_cache + transient）確保頻率限制有效
   * - wp_cache: 高效能，但需要物件快取外掛
   * - transient: 作為後備，確保在沒有物件快取時仍然有效
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   * @return bool 是否允許繼續
   */
  private function check_rate_limit($post_id, $question)
  {
    $ip = $this->security->get_client_ip();

    // ✅ 安全增強: 使用 SHA-256 替代 MD5（更安全，避免碰撞）
    // 單一問題頻率限制(60秒內不能重複請求)
    $freq_hash = hash("sha256", $ip . "|" . $post_id . "|" . $question);
    $freq_key = "moe_aiqna_freq_" . substr($freq_hash, 0, 32); // 縮短 key 長度
    
    // ✅ 雙層快取檢查: 優先使用 wp_cache，後備使用 transient
    $cached = wp_cache_get($freq_key, "moelog_aiqna_rate");
    if ($cached !== false) {
      return false;
    }
    
    // 後備檢查: Transient（確保沒有物件快取時仍有效）
    if (get_transient($freq_key)) {
      // 同步到 wp_cache 以提高後續檢查效率
      wp_cache_set($freq_key, 1, "moelog_aiqna_rate", self::RATE_TTL);
      return false;
    }

    // ✅ IP 總請求數限制(每小時最多 10 次)
    $ip_hash = hash("sha256", $ip);
    $ip_key = "moe_aiqna_ip_" . substr($ip_hash, 0, 32);
    
    // 雙層檢查 IP 計數
    $ip_count = (int) wp_cache_get($ip_key, "moelog_aiqna_rate");
    if ($ip_count === 0) {
      // 從 transient 讀取後備值
      $ip_count = (int) get_transient($ip_key);
    }
    
    if ($ip_count >= self::MAX_REQUESTS_PER_HOUR) {
      if (Moelog_AIQnA_Debug::is_enabled()) {
        error_log(
          sprintf(
            "[Moelog AIQnA] Rate limit hit: IP %s, Post %d, Question: %s",
            $ip,
            $post_id,
            substr($question, 0, 50),
          ),
        );
      }
      return false;
    }

    // ✅ 雙層快取設定: 同時設定 wp_cache 和 transient
    wp_cache_set($freq_key, 1, "moelog_aiqna_rate", self::RATE_TTL);
    set_transient($freq_key, 1, self::RATE_TTL);
    
    wp_cache_set($ip_key, $ip_count + 1, "moelog_aiqna_rate", HOUR_IN_SECONDS);
    set_transient($ip_key, $ip_count + 1, HOUR_IN_SECONDS);

    return true;
  }

  /**
   * 渲染錯誤頁面
   *
   * @param int    $code    HTTP 狀態碼
   * @param string $message 錯誤訊息
   */
  private function render_error($code, $message)
  {
    status_header($code);
    if (!headers_sent()) {
      header("Content-Type: text/html; charset=UTF-8");
      header("Cache-Control: no-cache, no-store, must-revalidate");
    }
    $title = $this->get_error_title($code);
    $site_name = get_bloginfo("name", "display");
    ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo("charset"); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html(
  $title,
); ?> - <?php echo esc_html($site_name); ?></title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;margin:0;padding:40px 20px;background:#f5f5f5;color:#333;}
.error-container{max-width:600px;margin:0 auto;background:#fff;padding:40px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
h1{margin:0 0 20px;font-size:24px;color:#d63638;}
p{margin:0 0 20px;line-height:1.6;}
.back-link{display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;}
.back-link:hover{background:#135e96;}
</style>
</head>
<body>
<div class="error-container">
  <h1><?php echo esc_html($title); ?></h1>
  <p><?php echo esc_html($message); ?></p>
  <a href="<?php echo esc_url(
    home_url("/"),
  ); ?>" class="back-link"><?php esc_html_e("返回首頁", "moelog-ai-qna"); ?></a>
</div>
</body>
</html>
        <?php exit();
  }

  /**
   * 取得錯誤標題
   *
   * @param int $code HTTP 狀態碼
   * @return string
   */
  private function get_error_title($code)
  {
    $titles = [
      400 => __("請求錯誤", "moelog-ai-qna"),
      403 => __("禁止存取", "moelog-ai-qna"),
      404 => __("頁面不存在", "moelog-ai-qna"),
      410 => __("連結已失效", "moelog-ai-qna"),
      429 => __("請求過於頻繁", "moelog-ai-qna"),
      500 => __("伺服器錯誤", "moelog-ai-qna"),
    ];
    return $titles[$code] ?? __("發生錯誤", "moelog-ai-qna");
  }

  // =========================================
  // 公開 API
  // =========================================

  /**
   * 取得 CSP Nonce
   *
   * @return string
   */
  public function get_csp_nonce()
  {
    return $this->security->get_csp_nonce();
  }

  /**
   * 手動渲染特定問題的答案(用於預生成)
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   * @param string $lang     語言代碼
   * @return string|false HTML 內容,失敗返回 false
   */
  public function render_for_pregeneration($post_id, $question, $lang = "auto")
  {
    $post = Moelog_AIQnA_Post_Cache::get($post_id);
    if (!$post) {
      return false;
    }

    $params = [
      "post_id" => $post_id,
      "question" => $question,
      "lang" => $lang !== "auto" ? $lang : $this->detect_language($question),
    ];

    // 準備 AI 呼叫參數
    $ai_params = [
      "post_id" => $params["post_id"],
      "question" => $params["question"],
      "lang" => $params["lang"],
      "context" => $this->get_post_context($post),
    ];

    // 呼叫 AI
    $answer = $this->ai_client->generate_answer($ai_params);
    if (!$answer) {
      return false;
    }

    // 建立 HTML
    $html = $this->template->build_html($params, $post, $answer);

    // ⭐ 預生成時直接使用 placeholder
    return $this->template->replace_nonce_in_html($html, self::NONCE_PLACEHOLDER);
  }

  // =========================================
  // Debug 輔助(僅在 WP_DEBUG 時使用)
  // =========================================

  /**
   * 取得當前渲染狀態(除錯用)
   *
   * @return array
   */
  public function debug_render_state()
  {
    if (!Moelog_AIQnA_Debug::is_enabled()) {
      return [];
    }
    return [
      "is_answer_request" => $this->router->is_answer_request(),
      "is_prefetch" => $this->is_prefetch_request(),
      "csp_nonce" => $this->security->get_csp_nonce(),
      "client_ip" => $this->security->get_client_ip(),
      "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? "",
      "request_method" => $_SERVER["REQUEST_METHOD"] ?? "",
      "geo_mode_enabled" => (bool) get_option("moelog_aiqna_geo_mode"),
    ];
  }

  /**
   * 測試模板渲染(除錯用)
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   * @return void
   */
  public function debug_test_render($post_id, $question)
  {
    if (!Moelog_AIQnA_Debug::is_enabled()) {
      return;
    }
    $post = Moelog_AIQnA_Post_Cache::get($post_id);
    if (!$post) {
      echo "Error: Post {$post_id} not found\n";
      return;
    }
    $params = [
      "post_id" => $post_id,
      "question" => $question,
      "lang" => "auto",
    ];
    $test_answer =
      "這是測試答案。\n\n這是第二段。\n\n參考資料\n- [Example](https://example.com)";
    $vars = $this->template->prepare_template_vars($params, $post, $test_answer);
    echo "<pre>";
    print_r($vars);
    echo "</pre>";
    echo "\n\n<hr>\n\n";
    $this->template->render_template($vars);
  }
}
