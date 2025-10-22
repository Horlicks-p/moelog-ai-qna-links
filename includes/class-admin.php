<?php
/**
 * Moelog AI Q&A Admin Class
 *
 * 負責後台管理介面:
 * - 設定頁面
 * - 選項註冊與驗證
 * - 快取管理介面
 * - API 連線測試
 * - 系統資訊顯示
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
  exit();
}

class Moelog_AIQnA_Admin
{
  /**
   * 建構函數
   */
  public function __construct()
  {
    add_action("admin_notices", [$this, "show_flush_rewrite_notice"]);
  }

  // =========================================
  // 選單與頁面
  // =========================================

  /**
   * 新增設定頁面
   */
  public function add_settings_page()
  {
    add_options_page(
      __("Moelog AI Q&A 設定", "moelog-ai-qna"),
      __("Moelog AI Q&A", "moelog-ai-qna"),
      "manage_options",
      "moelog_aiqna",
      [$this, "render_settings_page"],
    );
  }

  /**
   * 註冊設定
   */
  public function register_settings()
  {
    // 註冊設定組
    register_setting(MOELOG_AIQNA_OPT_KEY, MOELOG_AIQNA_OPT_KEY, [
      $this,
      "sanitize_settings",
    ]);

    // === 一般設定區段 ===
    add_settings_section(
      "general",
      __("一般設定", "moelog-ai-qna"),
      [$this, "render_general_section"],
      MOELOG_AIQNA_OPT_KEY,
    );

    // AI 供應商
    add_settings_field(
      "provider",
      __("AI 供應商", "moelog-ai-qna"),
      [$this, "render_provider_field"],
      MOELOG_AIQNA_OPT_KEY,
      "general",
    );

    // API Key
    add_settings_field(
      "api_key",
      __("API Key", "moelog-ai-qna"),
      [$this, "render_api_key_field"],
      MOELOG_AIQNA_OPT_KEY,
      "general",
    );

    // 模型
    add_settings_field(
      "model",
      __("模型", "moelog-ai-qna"),
      [$this, "render_model_field"],
      MOELOG_AIQNA_OPT_KEY,
      "general",
    );

    // Temperature
    add_settings_field(
      "temperature",
      __("Temperature", "moelog-ai-qna"),
      [$this, "render_temperature_field"],
      MOELOG_AIQNA_OPT_KEY,
      "general",
    );

    // === 內容設定區段 ===
    add_settings_section(
      "content",
      __("內容設定", "moelog-ai-qna"),
      [$this, "render_content_section"],
      MOELOG_AIQNA_OPT_KEY,
    );

    // 是否附上文章內容
    add_settings_field(
      "include_content",
      __("附上文章內容", "moelog-ai-qna"),
      [$this, "render_include_content_field"],
      MOELOG_AIQNA_OPT_KEY,
      "content",
    );

    // 文章內容截斷長度
    add_settings_field(
      "max_chars",
      __("內容截斷長度", "moelog-ai-qna"),
      [$this, "render_max_chars_field"],
      MOELOG_AIQNA_OPT_KEY,
      "content",
    );

    // System Prompt
    add_settings_field(
      "system_prompt",
      __("System Prompt", "moelog-ai-qna"),
      [$this, "render_system_prompt_field"],
      MOELOG_AIQNA_OPT_KEY,
      "content",
    );

    // === 顯示設定區段 ===
    add_settings_section(
      "display",
      __("顯示設定", "moelog-ai-qna"),
      [$this, "render_display_section"],
      MOELOG_AIQNA_OPT_KEY,
    );

    // 問題清單抬頭
    add_settings_field(
      "list_heading",
      __("問題清單抬頭", "moelog-ai-qna"),
      [$this, "render_list_heading_field"],
      MOELOG_AIQNA_OPT_KEY,
      "display",
    );

    // 免責聲明
    add_settings_field(
      "disclaimer_text",
      __("免責聲明", "moelog-ai-qna"),
      [$this, "render_disclaimer_field"],
      MOELOG_AIQNA_OPT_KEY,
      "display",
    );

    // === 快取設定區段 ===
    add_settings_section(
      "cache",
      __("快取設定", "moelog-ai-qna"),
      [$this, "render_cache_section"],
      MOELOG_AIQNA_OPT_KEY,
    );

    // 快取有效期限
    add_settings_field(
      "cache_ttl_days",
      __("快取有效期限", "moelog-ai-qna"),
      [$this, "render_cache_ttl_field"],
      MOELOG_AIQNA_OPT_KEY,
      "cache",
    );

    // ✅ 新增: 進階設定區塊
    add_settings_section(
      "advanced",
      __("進階設定", "moelog-ai-qna"),
      [$this, "render_advanced_section"],
      MOELOG_AIQNA_OPT_KEY,
    );

    // ✅ URL 路徑前綴
    add_settings_field(
      "pretty_base",
      __("URL 路徑前綴", "moelog-ai-qna"),
      [$this, "render_pretty_base_field"],
      MOELOG_AIQNA_OPT_KEY,
      "advanced",
    );

    // ✅ 快取目錄名稱
    add_settings_field(
      "static_dir",
      __("快取目錄名稱", "moelog-ai-qna"),
      [$this, "render_static_dir_field"],
      MOELOG_AIQNA_OPT_KEY,
      "advanced",
    );
  }
  // =========================================
  // 區段渲染
  // =========================================

  /**
   * 渲染一般設定區段說明
   */
  public function render_general_section()
  {
    echo '<p class="description">';
    esc_html_e("設定 AI 供應商與 API 連線資訊。", "moelog-ai-qna");
    echo "</p>";
  }

  /**
   * 渲染內容設定區段說明
   */
  public function render_content_section()
  {
    echo '<p class="description">';
    esc_html_e("調整 AI 如何處理文章內容與生成答案。", "moelog-ai-qna");
    echo "</p>";
  }

  /**
   * 渲染顯示設定區段說明
   */
  public function render_display_section()
  {
    echo '<p class="description">';
    esc_html_e("自訂前台顯示的文字內容。", "moelog-ai-qna");
    echo "</p>";
  }

  // =========================================
  // 欄位渲染
  // =========================================

  /**
   * 渲染 AI 供應商欄位
   */
  public function render_provider_field()
  {
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $value = moelog_aiqna_array_get($settings, "provider", "openai");
    ?>
        <select name="<?php echo esc_attr(
          MOELOG_AIQNA_OPT_KEY,
        ); ?>[provider]" id="provider">
            <option value="openai" <?php selected($value, "openai"); ?>>
                <?php esc_html_e("OpenAI", "moelog-ai-qna"); ?>
            </option>
            <option value="gemini" <?php selected($value, "gemini"); ?>>
                <?php esc_html_e("Google Gemini", "moelog-ai-qna"); ?>
            </option>
            <!-- 在現有 select 的 option 區塊中加入 -->
           <option value="anthropic" <?php selected($value, "anthropic"); ?>>
             <?php esc_html_e("Anthropic (Claude)", "moelog-ai-qna"); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e(
              "選擇要使用的 AI 服務供應商。",
              "moelog-ai-qna",
            ); ?>
        </p>
        <?php
  }

  /**
   * 渲染 API Key 欄位
   */
  public function render_api_key_field()
  {
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $masked = defined("MOELOG_AIQNA_API_KEY") && MOELOG_AIQNA_API_KEY;

    if ($masked) {<?php
      // 使用常數定義 - 但仍然顯示測試按鈕
      ?>
        <input type="password" class="regular-text" value="********" disabled>
        <!-- ✅ 新增測試按鈕 -->
        <button type="button" class="button" id="test-api-key">
            <?php esc_html_e("測試連線", "moelog-ai-qna"); ?>
        </button>
        <span id="test-result" style="margin-left:10px;"></span>
        
        <p class="description">
            已使用 wp-config.php 設定 MOELOG_AIQNA_API_KEY。
        </p>
        
        <!-- ✅ 新增隱藏欄位供 JavaScript 讀取 -->
        <input type="hidden" id="api_key" value="from_constant">
        
        <?php } else {
      // 使用資料庫設定
      $has_saved = !empty($settings["api_key"]);
      $display = $has_saved ? str_repeat("*", 20) : "";
      ?>
        <input type="password"
               name="<?php echo esc_attr(MOELOG_AIQNA_OPT_KEY); ?>[api_key]"
               id="api_key"
               class="regular-text"
               value="<?php echo esc_attr($display); ?>"
               placeholder="sk-... / AIza...">
        <button type="button" class="button" id="toggle-api-key">顯示</button>
        <button type="button" class="button" id="test-api-key">
            <?php esc_html_e("測試連線", "moelog-ai-qna"); ?>
        </button>
        <span id="test-result" style="margin-left:10px;"></span>
        
        <p class="description">
            如已設定，出於安全僅顯示遮罩；要更換請直接輸入新 Key。
        </p>
        <p class="description">
            <strong>建議:</strong> 在 wp-config.php 定義 MOELOG_AIQNA_API_KEY 更安全。</br>
            <strong>代碼:</strong> define('MOELOG_AIQNA_API_KEY', 'sk-xxxx...');
        </p>
        <?php }
  }

  /**
   * 渲染模型欄位
   */
  public function render_model_field()
  {
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $provider = moelog_aiqna_array_get($settings, "provider", "openai");

    // ✅ 依 provider 取得預設模型（補上 anthropic 分支）
    if ($provider === "gemini") {
      $default = Moelog_AIQnA_AI_Client::DEFAULT_MODEL_GEMINI;
    } elseif ($provider === "anthropic") {
      $default = Moelog_AIQnA_AI_Client::DEFAULT_MODEL_ANTHROPIC;
    } else {
      $default = Moelog_AIQnA_AI_Client::DEFAULT_MODEL_OPENAI;
    }

    $value = moelog_aiqna_array_get($settings, "model", $default);
    ?>
    <input type="text"
           name="<?php echo esc_attr(MOELOG_AIQNA_OPT_KEY); ?>[model]"
           id="model"
           class="regular-text"
           value="<?php echo esc_attr($value); ?>"
           placeholder="<?php echo esc_attr($default); ?>"
           data-default-openai="<?php echo esc_attr(
             Moelog_AIQnA_AI_Client::DEFAULT_MODEL_OPENAI,
           ); ?>"
           data-default-gemini="<?php echo esc_attr(
             Moelog_AIQnA_AI_Client::DEFAULT_MODEL_GEMINI,
           ); ?>"
           data-default-anthropic="<?php echo esc_attr(
             Moelog_AIQnA_AI_Client::DEFAULT_MODEL_ANTHROPIC,
           ); ?>">

    <p class="description">
        <?php printf(
          /* translators: %s: 預設模型名稱 */
          esc_html__("留空使用預設模型: %s", "moelog-ai-qna"),
          "<code>" . esc_html($default) . "</code>",
        ); ?>
    </p>

    <p class="description" id="model-hint-openai" style="display:none;">
        <?php esc_html_e(
          "OpenAI 模型範例: gpt-4o-mini, gpt-4o, gpt-4.1, gpt-4.1-mini",
          "moelog-ai-qna",
        ); ?>
    </p>
    <p class="description" id="model-hint-gemini" style="display:none;">
        <?php esc_html_e(
          "Gemini 模型範例: gemini-2.5-flash, gemini-1.5-pro",
          "moelog-ai-qna",
        ); ?>
    </p>
    <p class="description" id="model-hint-anthropic" style="display:none;">
        <?php esc_html_e(
          "Anthropic 模型範例: claude-sonnet-4-5-20250929, claude-opus-4-1",
          "moelog-ai-qna",
        ); ?>
    </p>

    <script>
    (function() {
        // 依 provider 顯示對應提示 & 更新 placeholder
        function updateModelUI() {
            var provider = document.getElementById('provider');
            var model    = document.getElementById('model');
            if (!provider || !model) return;

            var pv = provider.value;
            // 切換說明塊
            ['openai','gemini','anthropic'].forEach(function(k) {
                var el = document.getElementById('model-hint-' + k);
                if (el) el.style.display = (k === pv) ? 'block' : 'none';
            });
            // 更新 placeholder（不覆蓋使用者已填值）
            var ph = model.getAttribute('data-default-' + pv);
            if (ph) model.setAttribute('placeholder', ph);
        }

        document.addEventListener('DOMContentLoaded', updateModelUI);
        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'provider') updateModelUI();
        });
    })();
    </script>
    <?php
  }

  /**
   * 渲染 Temperature 欄位
   */
  public function render_temperature_field()
  {
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $value = isset($settings["temperature"])
      ? floatval($settings["temperature"])
      : 0.3;
    ?>
        <input type="number"
               name="<?php echo esc_attr(MOELOG_AIQNA_OPT_KEY); ?>[temperature]"
               id="temperature"
               step="0.1"
               min="0"
               max="2"
               value="<?php echo esc_attr($value); ?>">
        <p class="description">
            <?php esc_html_e(
              "控制回答的隨機性。0 = 確定性，2 = 創意性。建議: 0.3",
              "moelog-ai-qna",
            ); ?>
        </p>
        <?php
  }

  /**
   * 渲染「附上文章內容」欄位
   */
  public function render_include_content_field()
  {
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $checked = !empty($settings["include_content"]);
    ?>
        <label>
            <input type="checkbox"
                   name="<?php echo esc_attr(
                     MOELOG_AIQNA_OPT_KEY,
                   ); ?>[include_content]"
                   value="1"
                   <?php checked($checked, true); ?>>
            <?php esc_html_e("將文章內容附加到 AI 請求中", "moelog-ai-qna"); ?>
        </label>
        <p class="description">
            <?php esc_html_e(
              "啟用後 AI 可根據文章內容提供更準確的答案，但會消耗更多 Token。",
              "moelog-ai-qna",
            ); ?>
        </p>
        <?php
  }

  /**
   * 渲染「內容截斷長度」欄位
   */
  public function render_max_chars_field()
  {
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $value = isset($settings["max_chars"])
      ? intval($settings["max_chars"])
      : 6000;
    ?>
        <input type="number"
               name="<?php echo esc_attr(MOELOG_AIQNA_OPT_KEY); ?>[max_chars]"
               min="500"
               max="20000"
               step="100"
               value="<?php echo esc_attr($value); ?>">
        <span class="description"><?php esc_html_e(
          "字元",
          "moelog-ai-qna",
        ); ?></span>
        <p class="description">
            <?php esc_html_e(
              "文章內容超過此長度將被截斷。建議: 6000 字元（約 1500–2000 Token）",
              "moelog-ai-qna",
            ); ?>
        </p>
        <?php
  }

  /**
   * 渲染 System Prompt 欄位
   */
  public function render_system_prompt_field()
  {
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $default = __("你是嚴謹的專業編輯，提供簡潔準確的答案。", "moelog-ai-qna");
    $value = moelog_aiqna_array_get($settings, "system_prompt", $default);
    ?>
        <textarea name="<?php echo esc_attr(
          MOELOG_AIQNA_OPT_KEY,
        ); ?>[system_prompt]"
                  rows="4"
                  class="large-text"
                  placeholder="<?php echo esc_attr(
                    $default,
                  ); ?>"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e(
              "定義 AI 的角色與行為準則。留空使用預設值。",
              "moelog-ai-qna",
            ); ?>
        </p>
        <?php
  }

  /**
   * 渲染「問題清單抬頭」欄位
   */
  public function render_list_heading_field()
  {
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $default = __("Have more questions? Ask the AI below.", "moelog-ai-qna");
    $value = moelog_aiqna_array_get($settings, "list_heading", $default);
    ?>
        <input type="text"
               name="<?php echo esc_attr(
                 MOELOG_AIQNA_OPT_KEY,
               ); ?>[list_heading]"
               class="large-text"
               value="<?php echo esc_attr($value); ?>"
               placeholder="<?php echo esc_attr($default); ?>">
        <p class="description">
            <?php esc_html_e(
              "顯示在文章底部問題清單上方的標題。支援任意語言。",
              "moelog-ai-qna",
            ); ?>
        </p>
        <?php
  }

  /**
   * 渲染「免責聲明」欄位
   */
  public function render_disclaimer_field()
  {
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $default =
      "本頁面由 AI 生成，可能會發生錯誤，請查核重要資訊。\n" .
      "使用本 AI 生成內容服務即表示您同意此內容僅供個人參考，且您了解輸出內容可能不準確。\n" .
      "所有爭議內容 {site} 保有最終解釋權。";
    $value = moelog_aiqna_array_get($settings, "disclaimer_text", $default);
    ?>
        <textarea name="<?php echo esc_attr(
          MOELOG_AIQNA_OPT_KEY,
        ); ?>[disclaimer_text]"
                  rows="5"
                  class="large-text"
                  placeholder="<?php echo esc_attr(
                    $default,
                  ); ?>"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e(
              "顯示在 AI 答案頁底部。支援 {site} 代表網站名稱，亦相容舊式 %s。可多行。",
              "moelog-ai-qna",
            ); ?>
        </p>
        <?php
  }

  /**
   * 渲染快取設定區段說明
   */
  public function render_cache_section()
  {
    echo '<p class="description">';
    echo esc_html__("設定靜態 HTML 快取檔案的保存時間。", "moelog-ai-qna");
    echo "</p>";
  }

  /**
   * 渲染快取有效期限欄位
   */
  public function render_cache_ttl_field()
  {
    $options = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $ttl_days = isset($options["cache_ttl_days"])
      ? absint($options["cache_ttl_days"])
      : 30; // 預設 30 天

    printf(
      '<input type="number" name="%s[cache_ttl_days]" value="%d" min="1" max="365" class="small-text" /> %s',
      esc_attr(MOELOG_AIQNA_OPT_KEY),
      esc_attr($ttl_days),
      esc_html__("天", "moelog-ai-qna"),
    );

    echo '<p class="description">';
    echo esc_html__(
      "靜態 HTML 檔案的保存天數（1–365 天）。預設 30 天。",
      "moelog-ai-qna",
    );
    echo "</p>";

    // 顯示換算秒數
    $ttl_seconds = $ttl_days * 86400;
    echo '<p class="description" style="color: #666;">';
    printf(
      esc_html__("相當於 %s 秒", "moelog-ai-qna"),
      "<code>" . number_format($ttl_seconds) . "</code>",
    );
    echo "</p>";
  }
  /**
   * 渲染進階設定區塊說明
   */
  public function render_advanced_section()
  {
    echo '<p class="description">';
    esc_html_e(
      "進階設定選項,修改後需要重新儲存固定網址設定。",
      "moelog-ai-qna",
    );
    echo "</p>";
  }

  /**
   * 渲染 URL 路徑前綴欄位
   */
  public function render_pretty_base_field()
  {
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $value = isset($settings["pretty_base"]) ? $settings["pretty_base"] : "qna";
    ?>
    <input type="text" 
           name="<?php echo esc_attr(MOELOG_AIQNA_OPT_KEY); ?>[pretty_base]" 
           value="<?php echo esc_attr($value); ?>" 
           class="regular-text"
           pattern="[a-z0-9\-]+"
           placeholder="qna">
    <p class="description">
        <?php esc_html_e("回答頁面的 URL 路徑前綴,例如: ", "moelog-ai-qna"); ?>
        <code>https://yoursite.com/<strong><?php echo esc_html(
          $value,
        ); ?></strong>/...</code><br>
        <?php esc_html_e(
          "只能使用小寫英文、數字和連字號 (-)。",
          "moelog-ai-qna",
        ); ?><br>
        <strong style="color: #d63638;">
            <?php esc_html_e(
              "⚠️ 修改後必須到「設定 → 固定網址」重新儲存!",
              "moelog-ai-qna",
            ); ?>
        </strong>
    </p>
    <?php
  }

  /**
   * 渲染快取目錄名稱欄位
   */
  public function render_static_dir_field()
  {
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $value = isset($settings["static_dir"])
      ? $settings["static_dir"]
      : "ai-answers";
    ?>
    <input type="text" 
           name="<?php echo esc_attr(MOELOG_AIQNA_OPT_KEY); ?>[static_dir]" 
           value="<?php echo esc_attr($value); ?>" 
           class="regular-text"
           pattern="[a-z0-9\-]+"
           placeholder="ai-answers">
    <p class="description">
        <?php esc_html_e("快取檔案儲存的目錄名稱,位於: ", "moelog-ai-qna"); ?>
        <code>wp-content/<strong><?php echo esc_html(
          $value,
        ); ?></strong>/</code><br>
        <?php esc_html_e(
          "只能使用小寫英文、數字和連字號 (-)。",
          "moelog-ai-qna",
        ); ?><br>
        <strong style="color: #d63638;">
            <?php esc_html_e(
              "⚠️ 修改後會建立新目錄,舊目錄的快取需手動刪除!",
              "moelog-ai-qna",
            ); ?>
        </strong>
    </p>
    <?php
  }
  // =========================================
  // 設定驗證
  // =========================================

  /**
   * 清理與驗證設定 (完整版 - 方案三:支援加密與 wp-config.php 並存)
   *
   * @param array $input 輸入的設定值
   * @return array 清理後的設定值
   */
  public function sanitize_settings($input)
  {
    // 取得目前儲存的設定 (用於保留未修改的值)
    $previous = get_option(MOELOG_AIQNA_OPT_KEY, []);

    // 初始化輸出陣列
    $output = [];

    // =========================================
    // 1. Provider (AI 供應商)
    // =========================================
    $valid_providers = ["openai", "gemini", "anthropic"];
    $input_provider = moelog_aiqna_array_get($input, "provider", "openai");

    $output["provider"] = in_array($input_provider, $valid_providers, true)
      ? $input_provider
      : "openai";

    // =========================================
    // 2. Model (模型名稱)
    // =========================================
    $output["model"] = sanitize_text_field(
      moelog_aiqna_array_get($input, "model", ""),
    );

    // =========================================
    // 3. Temperature (溫度參數)
    // =========================================
    $temp = floatval(moelog_aiqna_array_get($input, "temperature", 0.3));
    $output["temperature"] = max(0, min(2, $temp)); // 限制在 0-2 之間

    // =========================================
    // 4. Include Content (是否附上文章內容)
    // =========================================
    $output["include_content"] = !empty($input["include_content"]) ? 1 : 0;

    // =========================================
    // 5. Block Enabled (是否在文章底部顯示問題區塊)
    // =========================================
    $output["block_enabled"] = !empty($input["block_enabled"]) ? 1 : 0;

    // =========================================
    // 6. Max Chars (內容截斷長度)
    // =========================================
    $max_chars = absint(moelog_aiqna_array_get($input, "max_chars", 6000));
    $output["max_chars"] = max(1000, min(20000, $max_chars)); // 限制 1000-20000

    // =========================================
    // 7. System Prompt / Custom Prompt (自訂提示詞)
    // =========================================
    $output["custom_prompt"] = sanitize_textarea_field(
      moelog_aiqna_array_get($input, "custom_prompt", ""),
    );

    // 也可能是 system_prompt,根據您的欄位名稱調整
    if (isset($input["system_prompt"])) {
      $output["system_prompt"] = wp_kses_post(
        moelog_aiqna_array_get($input, "system_prompt", ""),
      );
    }

    // =========================================
    // 8. List Heading (問題清單標題)
    // =========================================
    $default_heading = __(
      "還有其他問題嗎?以下是 AI 可以回答的問題",
      "moelog-ai-qna",
    );
    $output["list_heading"] = sanitize_text_field(
      moelog_aiqna_array_get($input, "list_heading", $default_heading),
    );

    if (empty($output["list_heading"])) {
      $output["list_heading"] = $default_heading;
    }

    // =========================================
    // 9. Disclaimer (免責聲明)
    // =========================================
    $default_disclaimer =
      "使用本 AI 生成內容服務即表示您同意此內容僅供個人參考,且您了解輸出內容可能不準確。\n" .
      "所有爭議內容 {site} 保有最終解釋權。";

    $output["disclaimer_text"] = sanitize_textarea_field(
      moelog_aiqna_array_get($input, "disclaimer_text", $default_disclaimer),
    );

    if (empty($output["disclaimer_text"])) {
      $output["disclaimer_text"] = $default_disclaimer;
    }

    // =========================================
    // 10. API Key 處理 (★★★ 核心安全邏輯 ★★★)
    // =========================================

    // 步驟 1: 檢查是否使用 wp-config.php 常數定義
    if (defined("MOELOG_AIQNA_API_KEY") && MOELOG_AIQNA_API_KEY) {
      // 使用常數,不儲存到資料庫 (最安全的方式)
      $output["api_key"] = "";

      if (defined("WP_DEBUG") && WP_DEBUG) {
        error_log("[Moelog AIQnA] Using API Key from wp-config.php constant");
      }
    } else {
      // 步驟 2: 使用資料庫儲存 (需要加密)
      $input_key = trim(moelog_aiqna_array_get($input, "api_key", ""));

      // 步驟 2a: 檢查是否為遮罩值或空值
      if (empty($input_key) || preg_match('/^\*+$/', $input_key)) {
        // 保留原有的 Key (不變更)
        $output["api_key"] = moelog_aiqna_array_get($previous, "api_key", "");

        if (defined("WP_DEBUG") && WP_DEBUG) {
          error_log("[Moelog AIQnA] API Key unchanged (masked input)");
        }
      } else {
        // 步驟 2b: 有新的 API Key 輸入

        // 檢查是否已經是加密格式 (避免重複加密)
        if (
          function_exists("moelog_aiqna_is_encrypted") &&
          moelog_aiqna_is_encrypted($input_key)
        ) {
          // 已經是加密格式,直接儲存
          $output["api_key"] = $input_key;

          if (defined("WP_DEBUG") && WP_DEBUG) {
            error_log("[Moelog AIQnA] API Key already encrypted, saved as-is");
          }
        } else {
          // 步驟 2c: 新的明文 API Key - 進行加密
          if (function_exists("moelog_aiqna_encrypt_api_key")) {
            $encrypted_key = moelog_aiqna_encrypt_api_key($input_key);

            if (!empty($encrypted_key)) {
              $output["api_key"] = $encrypted_key;

              if (defined("WP_DEBUG") && WP_DEBUG) {
                error_log("[Moelog AIQnA] New API Key encrypted and saved");
              }

              // 顯示成功訊息
              add_settings_error(
                "moelog_aiqna_messages",
                "api_key_encrypted",
                __("✓ API Key 已加密儲存", "moelog-ai-qna"),
                "success",
              );
            } else {
              // 加密失敗,使用明文 (降級處理)
              $output["api_key"] = sanitize_text_field($input_key);

              add_settings_error(
                "moelog_aiqna_messages",
                "api_key_encryption_failed",
                __(
                  "⚠ API Key 加密失敗,已使用明文儲存。建議使用 wp-config.php 常數定義。",
                  "moelog-ai-qna",
                ),
                "warning",
              );
            }
          } else {
            // 加密函數不存在,降級為明文 (舊版相容)
            $output["api_key"] = sanitize_text_field($input_key);

            if (defined("WP_DEBUG") && WP_DEBUG) {
              error_log(
                "[Moelog AIQnA] Encryption function not available, storing plaintext",
              );
            }
          }
        }
      }
    }

    // =========================================
    // 11. Cache TTL (快取有效期限 - 天數)
    // =========================================
    $cache_ttl_days = absint(
      moelog_aiqna_array_get($input, "cache_ttl_days", 30),
    );

    // 限制在 1-365 天之間
    if ($cache_ttl_days < 1) {
      $cache_ttl_days = 1;
    } elseif ($cache_ttl_days > 365) {
      $cache_ttl_days = 365;
    }

    $output["cache_ttl_days"] = $cache_ttl_days;

    // =========================================
    // 12. 其他可能的設定欄位
    // =========================================

    // ✅ 驗證 pretty_base
    if (isset($input["pretty_base"])) {
      $pretty_base = sanitize_title($input["pretty_base"]);
      $pretty_base = preg_replace("/[^a-z0-9\-]/", "", $pretty_base);

      if (empty($pretty_base)) {
        $pretty_base = "qna";
      }

      // 檢查是否變更
      $old_settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
      $old_value = isset($old_settings["pretty_base"])
        ? $old_settings["pretty_base"]
        : "qna";

      if ($pretty_base !== $old_value) {
        // 標記需要刷新 rewrite rules
        update_option("moe_aiqna_needs_flush", "1", false);

        add_settings_error(
          "moelog_aiqna_messages",
          "pretty_base_changed",
          __(
            "✅ URL 路徑前綴已變更! 請立即到「設定 → 固定網址」重新儲存,否則舊連結會失效!",
            "moelog-ai-qna",
          ),
          "warning",
        );
      }

      $output["pretty_base"] = $pretty_base;
    }

    // ✅ 驗證 static_dir
    if (isset($input["static_dir"])) {
      $static_dir = sanitize_title($input["static_dir"]);
      $static_dir = preg_replace("/[^a-z0-9\-]/", "", $static_dir);

      if (empty($static_dir)) {
        $static_dir = "ai-answers";
      }

      // 檢查是否變更
      $old_settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
      $old_value = isset($old_settings["static_dir"])
        ? $old_settings["static_dir"]
        : "ai-answers";

      if ($static_dir !== $old_value) {
        add_settings_error(
          "moelog_aiqna_messages",
          "static_dir_changed",
          sprintf(
            __(
              "✅ 快取目錄已變更為: %s。舊目錄 (%s) 的快取不會自動刪除,如需清理請手動刪除。",
              "moelog-ai-qna",
            ),
            "<code>wp-content/" . $static_dir . "/</code>",
            "<code>wp-content/" . $old_value . "/</code>",
          ),
          "info",
        );
      }

      $output["static_dir"] = $static_dir;
    }
    $output = array_merge($previous, $output);
    // 儲存同時就把新目錄與保護檔建好（掛在 wp-content）
    if (
      class_exists("Moelog_AIQnA_Cache") &&
      method_exists("Moelog_AIQnA_Cache", "prepare_static_root")
    ) {
      Moelog_AIQnA_Cache::prepare_static_root();
    }

    // ✅ 確保有成功訊息
    if (empty(get_settings_errors("moelog_aiqna_messages"))) {
      add_settings_error(
        "moelog_aiqna_messages",
        "settings_updated",
        __("✅ 設定已成功儲存!", "moelog-ai-qna"),
        "success",
      );
    }

    return $output;
    // =========================================
    // 返回清理後的設定
    // =========================================
    return $output;
  }

  // =========================================
  // 設定頁面渲染
  // =========================================

  /**
   * 渲染設定頁面
   */
  public function render_settings_page()
  {
    if (!current_user_can("manage_options")) {
      return;
    }

    // 處理快取清除
    $this->handle_cache_actions();
    ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
                <span style="font-size:0.6em;color:#999;">
                    v<?php echo esc_html(MOELOG_AIQNA_VERSION); ?>
                </span>
            </h1>

            <?php settings_errors("moelog_aiqna_messages"); ?>

            <div class="moelog-aiqna-admin-wrapper" style="display:flex;gap:20px;margin-top:20px;">
                <!-- 左側: 主要設定 -->
                <div style="flex:1;max-width:800px;">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields(MOELOG_AIQNA_OPT_KEY);
                        do_settings_sections(MOELOG_AIQNA_OPT_KEY);
                        submit_button();
                        ?>
                    </form>
                </div>

                <!-- 右側: 側邊欄 -->
                <div style="width:300px;">
                    <?php $this->render_sidebar(); ?>
                </div>
            </div>

            <!-- 快取管理 -->
            <?php $this->render_cache_management(); ?>

            <!-- 使用說明 -->
            <?php $this->render_usage_guide(); ?>

            <!-- 系統資訊 -->
            <?php $this->render_system_info(); ?>
        </div>

        <!-- JavaScript -->
        <?php $this->render_admin_scripts(); ?>
        <?php
  }

  /**
   * 渲染側邊欄
   */
  private function render_sidebar()
  {
    ?>
        <!-- 快速連結 -->
       <div class="postbox">
        <h2 class="hndle"><?php esc_html_e(
          "🔗 快速連結",
          "moelog-ai-qna",
        ); ?></h2>
        <div class="inside">
            <ul style="margin:0;padding-left:20px;line-height:1.8;">
                <li>
                    <strong>OpenAI：</strong>
                    <a href="https://platform.openai.com/api-keys" target="_blank">API Keys</a> ／
                    <a href="https://platform.openai.com/docs" target="_blank">Docs</a>
                </li>
                <li>
                    <strong>Google Gemini：</strong>
                    <a href="https://aistudio.google.com/app/apikey" target="_blank">AI Studio</a> ／
                    <a href="https://ai.google.dev/docs" target="_blank">Docs</a>
                </li>
                <li>
                    <strong>Anthropic (Claude)：</strong>
                    <a href="https://console.anthropic.com/account/keys" target="_blank">API Keys</a> ／
                    <a href="https://docs.anthropic.com/en/api/messages" target="_blank">Docs</a>
                </li>
                <li>
                    <a href="<?php echo esc_url(
                      admin_url("options-permalink.php"),
                    ); ?>">
                        <?php esc_html_e(
                          "🔄 重新整理連結規則",
                          "moelog-ai-qna",
                        ); ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>

        <!-- 文件 -->
        <div class="postbox">
            <h2 class="hndle"><?php esc_html_e(
              "📖 文件",
              "moelog-ai-qna",
            ); ?></h2>
            <div class="inside">
                <p style="margin:10px 0;"><?php esc_html_e(
                  "使用 Shortcode:",
                  "moelog-ai-qna",
                ); ?></p>
                <code>[moelog_aiqna]</code>
                <p style="margin:10px 0 5px;"><?php esc_html_e(
                  "單一問題:",
                  "moelog-ai-qna",
                ); ?></p>
                <code>[moelog_aiqna index="1"]</code>
            </div>
        </div>

        <!-- 支援 -->
        <div class="postbox">
            <h2 class="hndle"><?php esc_html_e(
              "💬 支援",
              "moelog-ai-qna",
            ); ?></h2>
            <div class="inside">
                <p><?php esc_html_e("遇到問題?", "moelog-ai-qna"); ?></p>
                <p>
                    <a href="https://moelog.com/" target="_blank" class="button">
                        <?php esc_html_e("訪問網站", "moelog-ai-qna"); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
  }

  /**
   * 渲染快取管理區塊
   */
  private function render_cache_management()
  {
    $stats = Moelog_AIQnA_Cache::get_stats();
    $ttl_days = moelog_aiqna_get_cache_ttl_days();
    ?>
        <hr style="margin:30px 0;">
        <h2><?php esc_html_e("🗑️ 快取管理", "moelog-ai-qna"); ?></h2>

        <div style="background:#f9f9f9;border-left:4px solid #2271b1;padding:15px;margin-bottom:20px;">
            <p style="margin:0;">
                <strong><?php esc_html_e("說明:", "moelog-ai-qna"); ?></strong>
                <?php printf(
                  esc_html__(
                    "AI 回答會快取 %d 天並生成靜態 HTML 檔案。如果發現回答有誤或需要重新生成，可以清除快取。",
                    "moelog-ai-qna",
                  ),
                  $ttl_days,
                ); ?>
            </p>
        </div>

        <!-- 快取統計 -->
        <h3><?php esc_html_e("📊 快取統計", "moelog-ai-qna"); ?></h3>
        <table class="widefat" style="max-width:600px;">
            <tr>
                <th style="width:40%;"><?php esc_html_e(
                  "靜態檔案數量",
                  "moelog-ai-qna",
                ); ?></th>
                <td><strong><?php echo esc_html(
                  moelog_aiqna_format_number($stats["static_count"]),
                ); ?></strong> 個</td>
            </tr>
            <tr>
                <th><?php esc_html_e("佔用空間", "moelog-ai-qna"); ?></th>
                <td><strong><?php echo esc_html(
                  moelog_aiqna_format_bytes($stats["static_size"]),
                ); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e("Transient 數量", "moelog-ai-qna"); ?></th>
                <td><strong><?php echo esc_html(
                  moelog_aiqna_format_number($stats["transient_count"]),
                ); ?></strong> 筆</td>
            </tr>
            <tr>
                <th><?php esc_html_e("快取目錄", "moelog-ai-qna"); ?></th>
                <td>
                    <code><?php echo esc_html($stats["directory"]); ?></code>
                    <?php if ($stats["directory_writable"]): ?>
                        <span style="color:green;">✓ <?php esc_html_e(
                          "可寫",
                          "moelog-ai-qna",
                        ); ?></span>
                    <?php else: ?>
                        <span style="color:red;">✗ <?php esc_html_e(
                          "不可寫",
                          "moelog-ai-qna",
                        ); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- 清除所有快取 -->
        <h3><?php esc_html_e("清除所有快取", "moelog-ai-qna"); ?></h3>
        <form method="post" action="" style="margin-bottom:30px;">
            <?php wp_nonce_field(
              "moelog_aiqna_clear_cache",
              "moelog_aiqna_clear_cache_nonce",
            ); ?>
            <p><?php esc_html_e(
              "這會清除所有文章所有問題的 AI 回答快取與靜態檔案。",
              "moelog-ai-qna",
            ); ?></p>
            <button type="submit"
                    name="moelog_aiqna_clear_cache"
                    class="button button-secondary"
                    onclick="return confirm('<?php echo esc_js(
                      __(
                        "確定要清除所有 AI 回答快取與靜態檔案嗎？此操作無法復原。",
                        "moelog-ai-qna",
                      ),
                    ); ?>');">
                🗑️ <?php esc_html_e("清除所有快取", "moelog-ai-qna"); ?>
            </button>
        </form>

        <!-- 清除單一快取 -->
        <hr style="margin:20px 0;">
        <h3><?php esc_html_e("清除單一問題快取", "moelog-ai-qna"); ?></h3>
        <form method="post" action="">
            <?php wp_nonce_field(
              "moelog_aiqna_clear_single",
              "moelog_aiqna_clear_single_nonce",
            ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="clear_post_id"><?php esc_html_e(
                          "文章 ID",
                          "moelog-ai-qna",
                        ); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="clear_post_id"
                               name="post_id"
                               required
                               style="width:150px;"
                               min="1">
                        <p class="description">
                            <?php esc_html_e(
                              "要清除快取的文章 ID（可在文章列表或網址列看到）",
                              "moelog-ai-qna",
                            ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="clear_question"><?php esc_html_e(
                          "問題文字",
                          "moelog-ai-qna",
                        ); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="clear_question"
                               name="question"
                               required
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e(
                              "輸入完整的問題文字（需與文章中設定的問題完全一致）",
                              "moelog-ai-qna",
                            ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="moelog_aiqna_clear_single" class="button button-secondary">
                    🗑️ <?php esc_html_e("清除此問題快取", "moelog-ai-qna"); ?>
                </button>
            </p>
        </form>
        <?php
  }

  /**
   * 使用說明
   */
  private function render_usage_guide()
  {
    ?>
        <hr style="margin:30px 0;">
        <h2><?php esc_html_e("ℹ️ 使用說明", "moelog-ai-qna"); ?></h2>
        <ol style="line-height:1.8;">
            <li><?php esc_html_e(
              "在「設定 → Moelog AI Q&A」填入 API Key / 模型等。",
              "moelog-ai-qna",
            ); ?></li>
            <li><?php esc_html_e(
              "編輯文章時，於右側/下方的「AI 問題清單」每行輸入一題並選擇語言（可選自動）。",
              "moelog-ai-qna",
            ); ?></li>
            <li><?php esc_html_e(
              "前台文章底部會顯示問題列表（抬頭可自訂）。點擊後開新分頁顯示 AI 答案與免責聲明（可自訂）。",
              "moelog-ai-qna",
            ); ?></li>
            <li>
                <?php esc_html_e("或使用短碼", "moelog-ai-qna"); ?>
                <code>[moelog_aiqna]</code>
                <?php esc_html_e("手動插入問題清單。", "moelog-ai-qna"); ?>
            </li>
            <li>
                <?php esc_html_e("也可用", "moelog-ai-qna"); ?>
                <code>[moelog_aiqna index="3"]</code>
                <?php esc_html_e(
                  "將第 3 題單獨放在任意段落（index 範圍 1–8）。",
                  "moelog-ai-qna",
                ); ?>
            </li>
        </ol>

        <h3><?php esc_html_e("🧾 v1.8.3 更新", "moelog-ai-qna"); ?></h3>
        <ul style="list-style-type:circle;margin-left:20px;line-height:1.8;">
            <li>✅ <?php esc_html_e(
              "加密 API Key 儲存",
              "moelog-ai-qna",
            ); ?></li>
            <li>✅ <?php esc_html_e("智慧預生成機制", "moelog-ai-qna"); ?></li>
            <li>✅ <?php esc_html_e(
              "靜態 HTML 快取機制",
              "moelog-ai-qna",
            ); ?></li>
            <li>✅ <?php esc_html_e(
              "CDN 完全快取支援",
              "moelog-ai-qna",
            ); ?></li>
            <li>✅ <?php esc_html_e("模組化架構", "moelog-ai-qna"); ?></li>
        </ul>
        <?php
  }

  /**
   * 渲染系統資訊
   */
  private function render_system_info()
  {
    if (!current_user_can("manage_options")) {
      return;
    }

    $info = $this->get_system_info();
    ?>
        <hr style="margin:30px 0;">
        <details style="margin-bottom:30px;">
            <summary style="cursor:pointer;font-size:1.1em;font-weight:600;">
                <?php esc_html_e("🛠️ 系統資訊", "moelog-ai-qna"); ?>
            </summary>
            <div style="margin-top:15px;">
                <table class="widefat" style="max-width:800px;">
                    <tr>
                        <th style="width:30%;"><?php esc_html_e(
                          "插件版本",
                          "moelog-ai-qna",
                        ); ?></th>
                        <td><code><?php echo esc_html(
                          $info["plugin_version"],
                        ); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e(
                          "WordPress 版本",
                          "moelog-ai-qna",
                        ); ?></th>
                        <td><code><?php echo esc_html(
                          $info["wp_version"],
                        ); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e(
                          "PHP 版本",
                          "moelog-ai-qna",
                        ); ?></th>
                        <td><code><?php echo esc_html(
                          $info["php_version"],
                        ); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e(
                          "多位元組支援",
                          "moelog-ai-qna",
                        ); ?></th>
                        <td>
                            <?php if ($info["mb_support"]): ?>
                                <span style="color:green;">✓ <?php esc_html_e(
                                  "已啟用",
                                  "moelog-ai-qna",
                                ); ?></span>
                            <?php else: ?>
                                <span style="color:orange;">✗ <?php esc_html_e(
                                  "未啟用",
                                  "moelog-ai-qna",
                                ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e(
                          "結構化資料模式",
                          "moelog-ai-qna",
                        ); ?></th>
                        <td>
                            <?php if ($info["geo_enabled"]): ?>
                                <span style="color:green;">✓ <?php esc_html_e(
                                  "已啟用",
                                  "moelog-ai-qna",
                                ); ?></span>
                            <?php else: ?>
                                <span style="color:#999;">✗ <?php esc_html_e(
                                  "未啟用",
                                  "moelog-ai-qna",
                                ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e(
                          "API 供應商",
                          "moelog-ai-qna",
                        ); ?></th>
                        <td>
                            <code><?php echo esc_html(
                              $info["provider"],
                            ); ?></code>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e(
                          "API Key 狀態",
                          "moelog-ai-qna",
                        ); ?></th>
                        <td>
                            <?php if ($info["api_key_set"]): ?>
                                <span style="color:green;">✓ <?php esc_html_e(
                                  "已設定",
                                  "moelog-ai-qna",
                                ); ?></span>
                                <?php if ($info["api_key_from_constant"]): ?>
                                    <span style="color:#2271b1;">(<?php esc_html_e(
                                      "來自常數",
                                      "moelog-ai-qna",
                                    ); ?>)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:red;">✗ <?php esc_html_e(
                                  "未設定",
                                  "moelog-ai-qna",
                                ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e(
                          "快取目錄權限",
                          "moelog-ai-qna",
                        ); ?></th>
                        <td>
                            <?php if ($info["cache_writable"]): ?>
                                <span style="color:green;">✓ <?php esc_html_e(
                                  "可寫",
                                  "moelog-ai-qna",
                                ); ?></span>
                            <?php else: ?>
                                <span style="color:red;">✗ <?php esc_html_e(
                                  "不可寫",
                                  "moelog-ai-qna",
                                ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e(
                          "Rewrite Rules",
                          "moelog-ai-qna",
                        ); ?></th>
                        <td>
                            <?php if ($info["rewrite_rules_ok"]): ?>
                                <span style="color:green;">✓ <?php esc_html_e(
                                  "正常",
                                  "moelog-ai-qna",
                                ); ?></span>
                            <?php else: ?>
                                <span style="color:orange;">⚠ <?php esc_html_e(
                                  "需要刷新",
                                  "moelog-ai-qna",
                                ); ?></span>
                                <a href="<?php echo esc_url(
                                  admin_url("options-permalink.php"),
                                ); ?>" class="button button-small">
                                    <?php esc_html_e(
                                      "前往刷新",
                                      "moelog-ai-qna",
                                    ); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e(
                          "記憶體限制",
                          "moelog-ai-qna",
                        ); ?></th>
                        <td><code><?php echo esc_html(
                          $info["memory_limit"],
                        ); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e(
                          "最大上傳大小",
                          "moelog-ai-qna",
                        ); ?></th>
                        <td><code><?php echo esc_html(
                          $info["upload_max_size"],
                        ); ?></code></td>
                    </tr>
                </table>
            </div>
        </details>
        <?php
  }

  /**渲染管理腳本*/
  private function render_admin_scripts()
  {
    $nonce = wp_create_nonce("moelog_aiqna_test_api"); ?>
    <script>
    jQuery(document).ready(function($) {
        // 切換 API Key 顯示/隱藏
        $('#toggle-api-key').on('click', function() {
            var $input = $('#api_key');
            var $btn = $(this);

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $btn.text('<?php echo esc_js(__("隱藏", "moelog-ai-qna")); ?>');
            } else {
                $input.attr('type', 'password');
                $btn.text('<?php echo esc_js(__("顯示", "moelog-ai-qna")); ?>');
            }
        });

// 測試 API 連線
$('#test-api-key').on('click', function() {
    var $btn = $(this);
    var $result = $('#test-result');
    var provider = $('#provider').val();
    var apiKey = $('#api_key').val();
    var model = $('#model').val();

    // ✅ 修改:允許使用常數定義的情況
    if (!apiKey || (apiKey !== 'from_constant' && apiKey === '********************')) {
        $result.html('<span style="color:red;">✗ 請先輸入 API Key</span>');
        return;
    }

    $btn.prop('disabled', true).text('測試中...');
    $result.html('<span style="color:#999;">⏳ 連線中...</span>');

    $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'moelog_aiqna_test_api',
            nonce: '<?php echo esc_js($nonce); ?>',
            provider: provider,
            api_key: apiKey, // 如果是 'from_constant',後端會自動讀取常數
            model: model
        },
        timeout: 30000,
        success: function(response) {
            if (response.success) {
                $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
            } else {
                $result.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
            }
        },
        error: function(xhr, status, error) {
            $result.html('<span style="color:red;">✗ 請求失敗: ' + error + '</span>');
        },
        complete: function() {
            $btn.prop('disabled', false).text('測試連線');
        }
    });
});

        // 根據供應商顯示對應的模型提示
        function updateModelHint() {
            var provider = $('#provider').val();
            $('#model-hint-openai, #model-hint-gemini, #model-hint-anthropic').hide();
            $('#model-hint-' + provider).show();
        }
        $('#provider').on('change', updateModelHint);
        updateModelHint();
    }); // 修正：正確關閉 jQuery(document).ready
    </script>
    <?php
  }

  // =========================================
  // AJAX 處理
  // =========================================

  /**
   * 處理 API 測試 AJAX 請求
   */
  public function ajax_test_api()
  {
    check_ajax_referer("moelog_aiqna_test_api", "nonce");

    if (!current_user_can("manage_options")) {
      wp_send_json_error(["message" => __("權限不足", "moelog-ai-qna")]);
    }

    $provider = sanitize_text_field($_POST["provider"] ?? "openai");
    $api_key = sanitize_text_field($_POST["api_key"] ?? "");
    $model = sanitize_text_field($_POST["model"] ?? "");

    if (empty($api_key)) {
      wp_send_json_error(["message" => __("請提供 API Key", "moelog-ai-qna")]);
    }

    // 使用 AI Client 測試連線
    $ai_client = new Moelog_AIQnA_AI_Client();
    $result = $ai_client->test_connection($provider, $api_key, $model);

    if ($result["success"]) {
      wp_send_json_success(["message" => $result["message"]]);
    } else {
      wp_send_json_error(["message" => $result["message"]]);
    }
  }

  // =========================================
  // 快取操作處理
  // =========================================

  /**
   * 處理快取清除動作
   */
  private function handle_cache_actions()
  {
    // 清除所有快取
    if (
      isset($_POST["moelog_aiqna_clear_cache"]) &&
      check_admin_referer(
        "moelog_aiqna_clear_cache",
        "moelog_aiqna_clear_cache_nonce",
      )
    ) {
      $result = moelog_aiqna_instance()->clear_all_cache();

      add_settings_error(
        "moelog_aiqna_messages",
        "cache_cleared",
        sprintf(
          __("✅ 成功清除 %d 筆快取記錄與 %d 個靜態檔案!", "moelog-ai-qna"),
          $result["transient"],
          $result["static"],
        ),
        "success",
      );
    }

    // 清除單一快取
    if (
      isset($_POST["moelog_aiqna_clear_single"]) &&
      check_admin_referer(
        "moelog_aiqna_clear_single",
        "moelog_aiqna_clear_single_nonce",
      )
    ) {
      $post_id = intval($_POST["post_id"] ?? 0);
      $question = sanitize_text_field($_POST["question"] ?? "");

      if ($post_id && $question) {
        global $wpdb;

        // 清除 transient
        $partial_hash = substr(
          hash("sha256", $post_id . "|" . $question),
          0,
          32,
        );
        $pattern = "%moe_aiqna_%" . $partial_hash . "%";
        $count = $wpdb->query(
          $wpdb->prepare(
            "DELETE FROM {$wpdb->options}
                         WHERE option_name LIKE %s
                            OR option_name LIKE %s",
            "_transient" . $pattern,
            "_transient_timeout" . $pattern,
          ),
        );

        // 清除靜態檔案
        $static_deleted = Moelog_AIQnA_Cache::delete($post_id, $question);

        if ($count > 0 || $static_deleted) {
          add_settings_error(
            "moelog_aiqna_messages",
            "single_cache_cleared",
            sprintf(
              __("✅ 成功清除 %d 筆相關快取!（文章 ID: %d）", "moelog-ai-qna"),
              $count,
              $post_id,
            ) .
              "<br>" .
              sprintf(
                __("問題: %s", "moelog-ai-qna"),
                "<code>" . esc_html($question) . "</code>",
              ) .
              ($static_deleted
                ? "<br>" . __("✅ 靜態檔案已刪除", "moelog-ai-qna")
                : ""),
            "success",
          );
        } else {
          add_settings_error(
            "moelog_aiqna_messages",
            "no_cache_found",
            __(
              "⚠️ 未找到相關快取。可能原因：快取已過期、問題文字不符、或該問題從未被訪問過。",
              "moelog-ai-qna",
            ),
            "warning",
          );
        }
      } else {
        add_settings_error(
          "moelog_aiqna_messages",
          "invalid_input",
          __("❌ 請填寫完整的文章 ID 和問題文字。", "moelog-ai-qna"),
          "error",
        );
      }
    }
  }
  /**
   * ✅ 顯示刷新 rewrite rules 的提示
   */
  public function show_flush_rewrite_notice()
  {
    // 只在需要時顯示
    if (get_option("moe_aiqna_needs_flush") !== "1") {
      return;
    }

    // 不在固定網址頁面顯示
    $screen = get_current_screen();
    if ($screen && $screen->id === "options-permalink") {
      return;
    }
    ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <strong><?php esc_html_e(
              "Moelog AI Q&A:",
              "moelog-ai-qna",
            ); ?></strong>
            <?php esc_html_e("URL 路徑前綴已變更,請立即到", "moelog-ai-qna"); ?>
            <a href="<?php echo esc_url(
              admin_url("options-permalink.php"),
            ); ?>">
                <?php esc_html_e("設定 → 固定網址", "moelog-ai-qna"); ?>
            </a>
            <?php esc_html_e(
              "重新儲存,否則回答頁面連結會失效!",
              "moelog-ai-qna",
            ); ?>
        </p>
    </div>
    <?php
  }
  // =========================================
  // 系統資訊
  // =========================================

  /**
   * 取得系統資訊
   *
   * @return array
   */
  private function get_system_info()
  {
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $stats = Moelog_AIQnA_Cache::get_stats();

    // 檢查 Rewrite Rules
    $rules = get_option("rewrite_rules");
    $rewrite_ok =
      is_array($rules) &&
      isset(
        $rules[
          "^" .
            Moelog_AIQnA_Router::PRETTY_BASE .
            '/([a-z0-9]+)-([a-f0-9]{3})-([0-9]+)/?$'
        ],
      );

    // API Key 狀態
    $api_key_from_constant =
      defined("MOELOG_AIQNA_API_KEY") && MOELOG_AIQNA_API_KEY;
    $api_key_set = $api_key_from_constant || !empty($settings["api_key"]);

    return [
      "plugin_version" => MOELOG_AIQNA_VERSION,
      "wp_version" => get_bloginfo("version"),
      "php_version" => PHP_VERSION,
      "mb_support" => function_exists("mb_strlen"),
      "geo_enabled" => (bool) get_option("moelog_aiqna_geo_mode"),
      "provider" => moelog_aiqna_array_get($settings, "provider", "openai"),
      "api_key_set" => $api_key_set,
      "api_key_from_constant" => $api_key_from_constant,
      "cache_writable" => $stats["directory_writable"],
      "rewrite_rules_ok" => $rewrite_ok,
      "memory_limit" => ini_get("memory_limit"),
      "upload_max_size" => ini_get("upload_max_filesize"),
    ];
  }

  // =========================================
  // 通知管理
  // =========================================

  /**
   * 顯示管理通知（僅在設定頁）
   */
  public function show_notices()
  {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== "settings_page_moelog_aiqna") {
      return;
    }

    // API Key 未設定警告
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $api_key_set = defined("MOELOG_AIQNA_API_KEY")
      ? MOELOG_AIQNA_API_KEY
      : $settings["api_key"] ?? "";

    if (empty($api_key_set)) {
      echo '<div class="notice notice-warning"><p><strong>';
      esc_html_e("Moelog AI Q&A:", "moelog-ai-qna");
      echo "</strong> ";
      esc_html_e(
        "尚未設定 API Key，請完成設定後才能使用 AI 功能。",
        "moelog-ai-qna",
      );
      echo "</p></div>";
    }

    // Rewrite Rules 警告（GEO 模式）
    if (get_option("moelog_aiqna_geo_mode")) {
      $rules = get_option("rewrite_rules");
      $ok1 = is_array($rules) && isset($rules['^ai-qa-sitemap\.php$']);
      $ok2 = false;

      if (is_array($rules)) {
        foreach ($rules as $pattern => $dest) {
          if (strpos($pattern, '^ai-qa-sitemap-([0-9]+)\.php$') !== false) {
            $ok2 = true;
            break;
          }
        }
      }

      if (!$ok1 || !$ok2) {
        echo '<div class="notice notice-warning"><p><strong>';
        esc_html_e("Moelog AI Q&A:", "moelog-ai-qna");
        echo "</strong> ";
        esc_html_e("偵測到路由規則可能未正確設定。請至", "moelog-ai-qna");
        echo ' <a href="' . esc_url(admin_url("options-permalink.php")) . '">';
        esc_html_e("設定 → 永久連結", "moelog-ai-qna");
        echo "</a> ";
        esc_html_e("點擊「儲存變更」以重新整理規則。", "moelog-ai-qna");
        echo "</p></div>";
      }
    }

    // 快取目錄權限警告
    $stats = Moelog_AIQnA_Cache::get_stats();
    if (!$stats["directory_writable"]) {
      echo '<div class="notice notice-error"><p><strong>';
      esc_html_e("Moelog AI Q&A:", "moelog-ai-qna");
      echo "</strong> ";
      printf(
        esc_html__("快取目錄不可寫: %s。請檢查目錄權限。", "moelog-ai-qna"),
        "<code>" . esc_html($stats["directory"]) . "</code>",
      );
      echo "</p></div>";
    }
  }
} // <-- 這是 Moelog_AIQnA_Admin 類別定義的正確結尾

// 註冊 AJAX 處理
add_action("wp_ajax_moelog_aiqna_test_api", function () {
  $admin = new Moelog_AIQnA_Admin();
  $admin->ajax_test_api();
});
