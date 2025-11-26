<?php
/**
 * Moelog AI Q&A AI Client Class
 *
 * 負責與 AI API 通訊:
 * - OpenAI API 整合
 * - Google Gemini API 整合
 * - 錯誤處理與重試機制
 * - Prompt 管理與優化
 * - Transient 快取管理
 *
 * @package Moelog_AIQnA
 * @since   1.8.3
 */

if (!defined("ABSPATH")) {
  exit();
}

class Moelog_AIQnA_AI_Client
{
  /**
   * 預設模型
   */
  const DEFAULT_MODEL_OPENAI = MOELOG_AIQNA_DEFAULT_MODEL_OPENAI; // 'gpt-4o-mini'
  const DEFAULT_MODEL_GEMINI = MOELOG_AIQNA_DEFAULT_MODEL_GEMINI; // 'gemini-2.5flash'
  const DEFAULT_MODEL_ANTHROPIC = "claude-opus-4-5-20251101";

  /**
   * 單次請求生命週期的 API Key 快取（避免重複解密與重複記錄 log）
   * @var string|null
   */
  private $api_key_cache = null;

  /**
   * API 請求超時時間(秒)
   */
  // 增加預設逾時，避免大型模型回應被提早終止
  const TIMEOUT = 45;

  /**
   * 最大重試次數
   */
  const MAX_RETRIES = 2;

  /**
   * 快取有效期(秒)
   */
  const CACHE_TTL = 86400; // 24小時

  /**
   * 建構函數
   */
  public function __construct()
  {
    // 預留初始化邏輯
  }

  // =========================================
  // 主要 API - 生成答案
  // =========================================

  /**
   * 生成 AI 答案(主入口)
   *
   * @param array $params {
   *     @type int    $post_id  文章 ID
   *     @type string $question 問題文字
   *     @type string $lang     語言代碼
   *     @type string $context  文章內容(可選)
   * }
   * @return string AI 回答
   */
  public function generate_answer($params)
  {
    // 取得設定
    $provider = Moelog_AIQnA_Settings::get_provider();

    // 檢查 API Key
    $api_key = $this->get_api_key(); // ← 移除參數
    if (empty($api_key)) {
      return __("尚未設定 API Key。", "moelog-ai-qna");
    }

    // 生成快取鍵值
    $cache_key = $this->generate_cache_key($params, Moelog_AIQnA_Settings::get());

    // 檢查快取
    $cached = Moelog_AIQnA_Cache::get_transient($cache_key);
    if ($cached !== false) {
      Moelog_AIQnA_Debug::logf(
        "Cache hit for question: %s",
        substr($params["question"], 0, 50)
      );
      return $cached;
    }

    // 準備 API 請求參數
    $api_params = $this->prepare_api_params($params, Moelog_AIQnA_Settings::get());

    // 呼叫對應的 API
    $answer = $this->call_api($provider, $api_params);

    // 清理引用格式
    $answer = $this->sanitize_citations($answer);

    // 儲存快取
    if ($answer && !$this->is_error_message($answer)) {
      Moelog_AIQnA_Cache::set_transient($cache_key, $answer, self::CACHE_TTL);
    }

    return $answer;
  }

  // =========================================
  // API 呼叫 - OpenAI
  // =========================================

  /**
   * 呼叫 OpenAI API
   *
   * @param array $params API 參數
   * @return string
   */
  private function call_openai($params)
  {
    $endpoint = "https://api.openai.com/v1/chat/completions";

    $body = [
      "model" => $params["model"],
      "temperature" => $params["temperature"],
      "messages" => [
        [
          "role" => "system",
          "content" =>
            $params["system_prompt"] ?:
            "You are a professional editor providing concise and accurate answers.",
        ],
        [
          "role" => "system",
          "content" => $params["lang_hint"],
        ],
        [
          "role" => "user",
          "content" => $params["user_prompt"],
        ],
      ],
    ];

    $args = [
      "headers" => [
        "Authorization" => "Bearer " . $params["api_key"],
        "Content-Type" => "application/json",
      ],
      "body" => wp_json_encode($body),
      "timeout" => self::TIMEOUT,
      "method" => "POST",
    ];

    // 執行請求(含重試)
    $response = $this->request_with_retry($endpoint, $args);

    if (is_wp_error($response)) {
      Moelog_AIQnA_Debug::log_error(
        "OpenAI HTTP Error",
        $response
      );
      return __("呼叫 OpenAI 失敗,請稍後再試。", "moelog-ai-qna");
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    // PHP 8.1+: 確保 $json 是陣列
    if (!is_array($json)) {
      $json = [];
    }

    // 處理成功回應
    if ($code >= 200 && $code < 300) {
      $content = $json["choices"][0]["message"]["content"] ?? "";
      if ($content !== "") {
        return trim((string) $content);
      }
    }

    // 處理錯誤
    return $this->handle_openai_error($code, $json);
  }

  /**
   * 處理 OpenAI 錯誤
   *
   * @param int   $code HTTP 狀態碼
   * @param array $json 回應 JSON
   * @return string
   */
  private function handle_openai_error($code, $json)
  {
    Moelog_AIQnA_Debug::logf(
      "OpenAI Error: HTTP %d, Response: %s",
      $code,
      wp_json_encode($json)
    );

    switch ($code) {
      case 401:
        return __(
          "服務暫時無法使用,請檢查 API Key 或模型名稱。",
          "moelog-ai-qna",
        );

      case 429:
        $message = $json["error"]["message"] ?? "";
        if (strpos($message, "insufficient_quota") !== false) {
          return __("服務暫時無法使用,請檢查 API 額度。", "moelog-ai-qna");
        }
        return __("請求過於頻繁,請稍候再試。", "moelog-ai-qna");

      case 400:
        $message = $json["error"]["message"] ?? "";
        if (strpos($message, "context_length_exceeded") !== false) {
          return __("內容過長,請減少文章內容截斷長度。", "moelog-ai-qna");
        }
        return __("請求參數錯誤。", "moelog-ai-qna");

      case 500:
      case 502:
      case 503:
        return __("AI 服務暫時不可用,請稍後再試。", "moelog-ai-qna");

      default:
        return __("AI 服務回傳異常,請稍後再試。", "moelog-ai-qna");
    }
  }

  // =========================================
  // API 呼叫 - Google Gemini
  // =========================================

  /**
   * 呼叫 Google Gemini API
   *
   * @param array $params API 參數
   * @return string
   */
  private function call_gemini($params)
  {
    $endpoint = sprintf(
      "https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s",
      $params["model"],
      $params["api_key"],
    );

    // Gemini 將所有提示合併到 user message
    $full_content =
      ($params["system_prompt"] ? $params["system_prompt"] . "\n\n" : "") .
      $params["lang_hint"] .
      "\n\n" .
      $params["user_prompt"];

    $body = [
      "contents" => [
        [
          "role" => "user",
          "parts" => [["text" => $full_content]],
        ],
      ],
      "generationConfig" => [
        "temperature" => $params["temperature"],
      ],
    ];

    $args = [
      "headers" => [
        "Content-Type" => "application/json",
      ],
      "body" => wp_json_encode($body),
      "timeout" => self::TIMEOUT,
      "method" => "POST",
    ];

    // 執行請求(含重試)
    $response = $this->request_with_retry($endpoint, $args);

    if (is_wp_error($response)) {
      Moelog_AIQnA_Debug::log_error(
        "Gemini HTTP Error",
        $response
      );
      return __("呼叫 Google Gemini 失敗,請稍後再試。", "moelog-ai-qna");
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    // PHP 8.1+: 確保 $json 是陣列
    if (!is_array($json)) {
      $json = [];
    }

    // 處理成功回應
    if ($code >= 200 && $code < 300) {
      $content = $json["candidates"][0]["content"]["parts"][0]["text"] ?? "";
      if ($content !== "") {
        return trim((string) $content);
      }
    }

    // 處理錯誤
    return $this->handle_gemini_error($code, $json);
  }

  /**
   * 處理 Gemini 錯誤
   *
   * @param int   $code HTTP 狀態碼
   * @param array $json 回應 JSON
   * @return string
   */
  private function handle_gemini_error($code, $json)
  {
    Moelog_AIQnA_Debug::logf(
      "Gemini Error: HTTP %d, Response: %s",
      $code,
      wp_json_encode($json)
    );

    switch ($code) {
      case 400:
      case 403:
        $message = $json["error"]["message"] ?? "未知錯誤";

        if (
          strpos($message, "API_KEY_INVALID") !== false ||
          (strpos($message, "model") !== false &&
            strpos($message, "not found") !== false)
        ) {
          return __(
            "服務暫時無法使用,請檢查 API Key 或模型名稱。",
            "moelog-ai-qna",
          );
        }

        if (
          strpos($message, "blocked") !== false ||
          strpos($message, "PROMPT_FILTERED") !== false ||
          strpos($message, "SAFETY") !== false
        ) {
          return __("問題或答案被安全過濾機制阻擋。", "moelog-ai-qna");
        }

        return __("AI 服務回傳異常,請稍後再試。", "moelog-ai-qna");

      case 429:
        return __("請求過於頻繁,請稍候再試。", "moelog-ai-qna");

      case 500:
      case 502:
      case 503:
        return __("Google Gemini 服務暫時不可用,請稍後再試。", "moelog-ai-qna");

      default:
        return __("Google Gemini 回傳異常,請稍後再試。", "moelog-ai-qna");
    }
  }
  // =========================================
  // API 呼叫 - Anthropic Messages API
  // =========================================
  /**
   * 呼叫 Anthropic Claude API
   *
   * @param array $params API 參數
   * @return string
   */
  private function call_anthropic($params)
  {
    $endpoint = "https://api.anthropic.com/v1/messages";

    $api_key = $params["api_key"] ?? "";
    $model = $params["model"] ?? Moelog_AIQnA_Model_Registry::get_default_model("anthropic");
    $temperature = isset($params["temperature"])
      ? floatval($params["temperature"])
      : 0.3;
    // ✅ max_tokens 可覆寫，限制範圍 1~8192
    $max_tokens = isset($params["max_tokens"])
      ? min(8192, max(1, (int) $params["max_tokens"]))
      : 1024;

    // system 要單獨在外面，不在 messages 裡
    $system_text = trim(
      ($params["system_prompt"] ?? "") . "\n\n" . ($params["lang_hint"] ?? ""),
    );
    $user_text = $params["user_prompt"] ?? "";

    if (empty($api_key)) {
      return __("尚未設定 Anthropic API Key。", "moelog-ai-qna");
    }

    if (empty($user_text)) {
      return __("問題為空。", "moelog-ai-qna");
    }

    // ✅ 正確的 Anthropic API 格式
    $body = [
      "model" => $model,
      "max_tokens" => $max_tokens,
      "temperature" => $temperature,
      "system" => $system_text, // system 獨立在外面
      "messages" => [
        // messages 只有 user
        [
          "role" => "user",
          "content" => $user_text,
        ],
      ],
    ];

    $args = [
      "headers" => [
        "Content-Type" => "application/json",
        "anthropic-version" => "2023-06-01",
        "x-api-key" => $api_key,
      ],
      "body" => wp_json_encode($body),
      "timeout" => self::TIMEOUT,
      "method" => "POST",
    ];

    // 使用專案已有的 request_with_retry()
    $response = $this->request_with_retry($endpoint, $args);

    if (is_wp_error($response)) {
      Moelog_AIQnA_Debug::log_error(
        "Anthropic HTTP Error",
        $response
      );
      return __("無法連線到 Anthropic 服務。", "moelog-ai-qna");
    }

    $code = wp_remote_retrieve_response_code($response);
    $body_raw = wp_remote_retrieve_body($response);
    $json = json_decode($body_raw, true);

    // PHP 8.1+: 確保 $json 是陣列
    if (!is_array($json)) {
      $json = [];
    }

    // 成功:從 content 陣列中提取文字
    if ($code >= 200 && $code < 300) {
      $text = "";

      // Anthropic 回傳格式: content 為陣列,每個 chunk 有 type/text
      if (!empty($json["content"]) && is_array($json["content"])) {
        foreach ($json["content"] as $chunk) {
          if (($chunk["type"] ?? "") === "text") {
            $text .= $chunk["text"] ?? "";
          }
        }
      }

      if (!empty($text)) {
        return trim((string) $text);
      }
    }

    // 錯誤情況
    return $this->handle_anthropic_error($code, $json);
  }

  /**
   * 處理 Anthropic API 錯誤
   *
   * @param int   $code HTTP 狀態碼
   * @param array $json 回應資料
   * @return string
   */
  private function handle_anthropic_error($code, $json)
  {
    Moelog_AIQnA_Debug::logf(
      "Anthropic Error: HTTP %d, Response: %s",
      $code,
      wp_json_encode($json)
    );

    switch ($code) {
      case 401:
        return __("API Key 無效或未授權。", "moelog-ai-qna");

      case 429:
        return __("請求過於頻繁,請稍候再試。", "moelog-ai-qna");

      case 400:
        $message = $json["error"]["message"] ?? ($json["message"] ?? "");
        if (strpos($message, "max_tokens") !== false) {
          return __("內容過長,請減少文章內容截斷長度。", "moelog-ai-qna");
        }
        return __("請求參數錯誤。", "moelog-ai-qna") . " " . $message;

      case 500:
      case 502:
      case 503:
        return __("Anthropic 服務暫時不可用,請稍後再試。", "moelog-ai-qna");

      default:
        return __("Anthropic 服務回傳異常,請稍後再試。", "moelog-ai-qna");
    }
  }

  // =========================================
  // 輔助方法 - API 呼叫
  // =========================================

  /**
   * 呼叫 API(主要路由)
   *
   * @param string $provider 供應商 (openai|gemini)
   * @param array  $params   API 參數
   * @return string
   */
  private function call_api($provider, $params)
  {
    // --- Provider / Model 自動矯正防呆 -----------------------------------
    if (
      $provider === "openai" &&
      stripos($params["model"], "gemini") !== false
    ) {
      $params["model"] = Moelog_AIQnA_Model_Registry::get_default_model("openai");
      Moelog_AIQnA_Debug::logf(
        "自動修正: OpenAI provider 不支持 Gemini 模型,改用 %s",
        $params["model"]
      );
    }

    if ($provider === "gemini" && stripos($params["model"], "gpt") !== false) {
      $params["model"] = Moelog_AIQnA_Model_Registry::get_default_model("gemini");
      Moelog_AIQnA_Debug::logf(
        "自動修正: Gemini provider 不支持 GPT 模型,改用 %s",
        $params["model"]
      );
    }

    // ✅ 新增: Anthropic 的防呆檢查
    if (
      $provider === "anthropic" &&
      (stripos($params["model"], "gpt") !== false ||
        stripos($params["model"], "gemini") !== false)
    ) {
      $params["model"] = Moelog_AIQnA_Model_Registry::get_default_model("anthropic");
      Moelog_AIQnA_Debug::logf(
        "自動修正: Anthropic provider 不支持 GPT/Gemini 模型,改用 %s",
        $params["model"]
      );
    }

    // ✅ 新增: OpenAI 使用 Claude 模型的防呆
    if (
      $provider === "openai" &&
      stripos($params["model"], "claude") !== false
    ) {
      $params["model"] = Moelog_AIQnA_Model_Registry::get_default_model("openai");
      Moelog_AIQnA_Debug::logf(
        "自動修正: OpenAI provider 不支持 Claude 模型,改用 %s",
        $params["model"]
      );
    }

    // ✅ 新增: Gemini 使用 Claude 模型的防呆
    if (
      $provider === "gemini" &&
      stripos($params["model"], "claude") !== false
    ) {
      $params["model"] = Moelog_AIQnA_Model_Registry::get_default_model("gemini");
      Moelog_AIQnA_Debug::logf(
        "自動修正: Gemini provider 不支持 Claude 模型,改用 %s",
        $params["model"]
      );
    }
    // --------------------------------------------------------------------

    switch ($provider) {
      case "gemini":
        return $this->call_gemini($params);

      case "anthropic":
        return $this->call_anthropic($params);

      case "openai":
      default:
        return $this->call_openai($params);
    }
  }

  /**
   * HTTP 請求(含重試機制)
   *
   * @param string $url  請求 URL
   * @param array  $args 請求參數
   * @return array|WP_Error
   */
  private function request_with_retry($url, $args)
  {
    $retries = 0;

    while ($retries <= self::MAX_RETRIES) {
      $response = wp_remote_request($url, $args);

      // 成功
      if (!is_wp_error($response)) {
        $code = wp_remote_retrieve_response_code($response);

        // 2xx 或 4xx(客戶端錯誤,不需重試)
        if ($code < 500) {
          return $response;
        }
      }

      // 重試前等待
      if ($retries < self::MAX_RETRIES) {
        $retries++;
        $delay = $retries * 2; // 2秒, 4秒

      Moelog_AIQnA_Debug::logf(
        "Retry %d/%d after %d seconds",
        $retries,
        self::MAX_RETRIES,
        $delay
      );

        sleep($delay);
      } else {
        break;
      }
    }

    return $response;
  }

  // =========================================
  // 輔助方法 - Prompt 管理
  // =========================================

  /**
   * 準備 API 請求參數
   *
   * @param array $params   使用者參數
   * @param array $settings 插件設定
   * @return array
   */
  private function prepare_api_params($params, $settings)
  {
    $provider = $settings["provider"] ?? "openai";
    $configured_model = isset($settings["model"])
      ? trim((string) $settings["model"])
      : "";
    if ($configured_model === "") {
      $configured_model = Moelog_AIQnA_Model_Registry::get_default_model($provider);
    }
    return [
      "api_key" => $this->get_api_key(),
      "model" => $configured_model,
      "temperature" => isset($settings["temperature"])
        ? floatval($settings["temperature"])
        : 0.3,
      "system_prompt" => $settings["system_prompt"] ?? "",
      "lang_hint" => $this->get_language_hint($params["lang"]),
      "user_prompt" => $this->build_user_prompt($params, $settings),
    ];
  }

  /**
   * 建立使用者 Prompt
   *
   * @param array $params 使用者參數
   * @return string
   */
  private function build_user_prompt($params)
  {
    $question = $params["question"];
    $context = $params["context"] ?? "";
    $post_url = get_permalink($params["post_id"]);

    // 檢查是否為本機 URL
    $is_local_url = $this->is_local_url($post_url);

    // 引用規則
    $citation_rules = $this->get_citation_rules();

    // 組合 Prompt
    $prompt = "問題:{$question}\n\n";

    if (!empty($context)) {
      $prompt .= "以下為原文脈絡(可能已截斷;僅供理解,禁止作為引用來源):\n{$context}\n\n";
    }

    $prompt .= $citation_rules . "\n";

    if (!$is_local_url && $post_url) {
      $prompt .= "原文連結(僅供理解,禁止作為引用來源):{$post_url}\n";
    }

    $prompt .= "\n【輸出格式要求】\n";
    $prompt .= "A) 先給出答案主體;\n";
    $prompt .= "B) 若有引用,最後加上小標題「參考資料」並逐行列出;\n";
    $prompt .= "C) 若沒有引用,就不要出現「參考資料」。\n";

    return $prompt;
  }

  /**
   * 取得語言提示
   *
   * @param string $lang 語言代碼
   * @return string
   */
  private function get_language_hint($lang)
  {
    switch ($lang) {
      case "ja":
        return "回答は日本語で簡潔に。外部の事実に依存する場合のみ、確実に覚えている正確なURLの出典を示してください。確信が持てない場合は、出典を記載しないでください。";

      case "zh":
        return "請以繁體中文回答,保持簡潔。只有在答案依賴外部事實且你「能確定正確網址」時,才在文末列出出處;若不確定,請不要引用。";

      case "en":
        return "Answer concisely in English. Only cite when you are sure of the exact URL; if uncertain, do not include any citation.";

      default:
        return "Answer concisely. Only cite when you are sure of the exact URL; if uncertain, do not include any citation.";
    }
  }

  /**
   * 取得引用規則
   *
   * @return string
   */
  private function get_citation_rules()
  {
    return "【引用規則(輕量)】\n" .
      "1) 僅在答案依賴外部可驗證事實,且你『能確定正確網址』時才引用;不得猜測或編造網址。\n" .
      "2) 引用格式:[網域名稱]完整網址,例如 [wikipedia.org]https://...\n" .
      "3) 不要引用本頁原文連結與 moelog.com;不要使用短網址。\n" .
      "4) 最多列 3 筆,優先權威來源(百科、官方、學術、主要媒體)。\n" .
      "5) 若沒有可確認的來源,完全不要輸出「參考資料」。\n";
  }

  // =========================================
  // 輔助方法 - 清理與驗證
  // =========================================

  /**
   * 清理引用格式
   *
   * @param string $markdown Markdown 內容
   * @return string
   */
  private function sanitize_citations($markdown)
  {
    $lines = preg_split("/\r?\n/", (string) $markdown);
    $output = [];
    $in_references = false;
    $kept_count = 0;

    // 不良網域清單
    $bad_domains = [
      "moelog.com",
      "bit.ly",
      "t.co",
      "goo.gl",
      "tinyurl.com",
      "ow.ly",
      "is.gd",
      "reurl.cc",
      "shorturl.at",
    ];

    foreach ($lines as $line) {
      $trimmed = trim($line);

      // 檢測參考資料標題
      if (!$in_references && preg_match('/^參考資料$/u', $trimmed)) {
        $in_references = true;
        $output[] = $line;
        continue;
      }

      // 處理參考資料行
      if ($in_references) {
        // 驗證格式: - [domain](url)
        if (
          !preg_match(
            '/^\s*-\s*\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)\s*$/i',
            $trimmed,
            $matches,
          )
        ) {
          $in_references = false;
          $output[] = $line;
          continue;
        }

        $url = $matches[2];
        $host = parse_url($url, PHP_URL_HOST);

        // 檢查是否為不良網域
        $is_bad = false;
        foreach ($bad_domains as $bad_domain) {
          if (
            $host === $bad_domain ||
            substr($host, -strlen("." . $bad_domain)) === "." . $bad_domain
          ) {
            $is_bad = true;
            break;
          }
        }

        if ($is_bad) {
          continue; // 跳過不良連結
        }

        // 保留(最多 3 筆)
        if ($kept_count < 3) {
          $output[] = $line;
          $kept_count++;
        }
        continue;
      }

      // 一般行
      $output[] = $line;
    }

    return implode("\n", $output);
  }

  /**
   * 檢查是否為錯誤訊息
   *
   * @param string $text 文字
   * @return bool
   */
  public function is_error_message($text)
  {
    // ✅ 改為 public
    $error_keywords = [
      "失敗",
      "錯誤",
      "無法",
      "暫時",
      "異常",
      "fail",
      "error",
      "unable",
      "unavailable",
    ];

    foreach ($error_keywords as $keyword) {
      if (stripos($text, $keyword) !== false) {
        return true;
      }
    }

    return false;
  }
  /**
   * 檢查是否為本機 URL
   *
   * @param string $url URL
   * @return bool
   */
  private function is_local_url($url)
  {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
      return false;
    }

    // localhost 或內網 IP
    if (in_array($host, ["localhost", "127.0.0.1", "::1"], true)) {
      return true;
    }

    // 私有 IP 段
    if (
      preg_match("/^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)/", $host)
    ) {
      return true;
    }

    return false;
  }

  // =========================================
  // 輔助方法 - 快取管理
  // =========================================

  /**
   * 生成快取鍵值
   *
   * @param array $params   使用者參數
   * @param array $settings 插件設定
   * @return string
   */
  private function generate_cache_key($params, $settings = null)
  {
    if ($settings === null) {
      $settings = Moelog_AIQnA_Settings::get();
    }
    $provider = $settings["provider"] ?? "openai";
    $configured_model = isset($settings["model"])
      ? trim((string) $settings["model"])
      : "";
    if ($configured_model === "") {
      $configured_model = Moelog_AIQnA_Model_Registry::get_default_model($provider);
    }
    $model = $configured_model;

    $context_hash = substr(hash("sha256", $params["context"] ?? ""), 0, 32);

    $key_data = implode("|", [
      $params["post_id"],
      $params["question"],
      $model,
      $context_hash,
      $params["lang"],
    ]);

    return "moe_aiqna_" . hash("sha256", $key_data);
  }

  /**
   * 取得 API Key (支援加密/混淆與常數定義)
   *
   * @return string
   */

  private function get_api_key()
  {
    // 先檢查請求級快取，避免重複解密與重複 log
    if ($this->api_key_cache !== null) {
      return $this->api_key_cache;
    }

    // 1) wp-config.php 常數優先
    if (defined("MOELOG_AIQNA_API_KEY") && MOELOG_AIQNA_API_KEY) {
      $key = trim((string) MOELOG_AIQNA_API_KEY);
      $this->api_key_cache = $key;
      Moelog_AIQnA_Debug::log_info("Using API Key from wp-config.php constant");
      return $key;
    }

    // 2) 從資料庫抓設定
    $stored_key = Moelog_AIQnA_Settings::get("api_key", "");

    if ($stored_key === "") {
      $this->api_key_cache = "";
      Moelog_AIQnA_Debug::log_warning("No API Key found in database");
      return "";
    }

    // 3) 若為加密/混淆格式 → 解密
    if (
      function_exists("moelog_aiqna_is_encrypted") &&
      function_exists("moelog_aiqna_decrypt_api_key")
    ) {
      if (moelog_aiqna_is_encrypted($stored_key)) {
        $decrypted = (string) moelog_aiqna_decrypt_api_key($stored_key);
        if ($decrypted !== "") {
          $this->api_key_cache = trim($decrypted);
          Moelog_AIQnA_Debug::log_info("API Key decrypted from database");
          return $this->api_key_cache;
        } else {
          Moelog_AIQnA_Debug::log_error("Failed to decrypt stored API Key");
          $this->api_key_cache = "";
          return "";
        }
      }
    }

    // 4) 回退：舊版/明文（理論上升級腳本已加密，不太會走到這）
    $this->api_key_cache = trim($stored_key);
    Moelog_AIQnA_Debug::log_warning("Using plaintext API Key from database");
    return $this->api_key_cache;
  }

  /**
   * 取得 Model
   *
   * @return string
   */
  private function get_model()
  {
    return Moelog_AIQnA_Settings::get_model();
  }

  // =========================================
  // 公開 API
  // =========================================

  /**
   * 測試 API 連線
   *
   * @param string $provider 供應商
   * @param string $api_key  API Key
   * @param string $model    模型名稱
   * @return array {
   *     @type bool   $success 是否成功
   *     @type string $message 訊息
   *     @type mixed  $data    額外資料
   * }
   */
  public function test_connection($provider, $api_key, $model = "")
  {
    // 若帶入的是加密/混淆格式，先嘗試解密
    if (
      !empty($api_key) &&
      function_exists("moelog_aiqna_is_encrypted") &&
      function_exists("moelog_aiqna_decrypt_api_key")
    ) {
      if (moelog_aiqna_is_encrypted($api_key)) {
        $dec = moelog_aiqna_decrypt_api_key($api_key);
        if (!empty($dec)) {
          $api_key = $dec;
        }
      }
    }

    if (empty($api_key)) {
      return [
        "success" => false,
        "message" => __("請提供 API Key", "moelog-ai-qna"),
      ];
    }

    // ✅ 修改這裡:根據 provider 使用對應的預設模型
    if (empty($model)) {
      $model = Moelog_AIQnA_Model_Registry::get_default_model($provider);
    }

    // 準備測試參數
    $test_params = [
      "api_key" => $api_key,
      "model" => $model,
      "temperature" => 0.3,
      "system_prompt" => "You are a helpful assistant.",
      "lang_hint" => "Answer in English.",
      "user_prompt" => 'Say "Hello World" and nothing else.',
    ];

    // 呼叫 API
    $answer = $this->call_api($provider, $test_params);

    // 判斷結果
    if ($this->is_error_message($answer)) {
      return [
        "success" => false,
        "message" => $answer,
      ];
    }

    return [
      "success" => true,
      "message" => __("連線測試成功!", "moelog-ai-qna"),
      "data" => [
        "response" => $answer,
      ],
    ];
  }

  /**
   * 清除特定問題的快取
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   * @return bool
   */
  public function clear_question_cache($post_id, $question)
  {
    $params = [
      "post_id" => $post_id,
      "question" => $question,
      "lang" => "auto",
      "context" => "",
    ];

    $cache_key = $this->generate_cache_key($params, Moelog_AIQnA_Settings::get());

    return Moelog_AIQnA_Cache::delete_transient($cache_key);
  }

  // =========================================
  // Debug 輔助
  // =========================================

  /**
   * 取得上次 API 呼叫資訊(除錯用)
   *
   * @return array
   */
  public function debug_last_request()
  {
    if (!Moelog_AIQnA_Debug::is_enabled()) {
      return [];
    }

    // 可以在這裡記錄上次請求的詳細資訊
    return [
      "timestamp" => time(),
      "message" => "Debug info not available",
    ];
  }
}