<?php

/**
 * Moelog AI Q&A Admin Settings Handler
 *
 * 負責設定頁面的註冊、欄位渲染和驗證
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
  exit();
}

class Moelog_AIQnA_Admin_Settings
{
  public const PAGE_GENERAL = "moelog_aiqna_page_general";
  public const PAGE_DISPLAY = "moelog_aiqna_page_display";
  public const PAGE_CACHE = "moelog_aiqna_page_cache";
  public const PAGE_CACHE_TOOLS = "moelog_aiqna_page_cache_tools";
  public const PAGE_INFO = "moelog_aiqna_page_info";

  /**
   * 取得設定分頁資訊
   *
   * @return array
   */
  public static function get_tabs(): array
  {
    return [
      "general" => [
        "label" => __("⚙️ 一般設定 (AI/內容)", "moelog-ai-qna"),
        "page" => self::PAGE_GENERAL,
      ],
      "display" => [
        "label" => __("🎨 顯示設定 (顯示/介面)", "moelog-ai-qna"),
        "page" => self::PAGE_DISPLAY,
      ],
      "cache" => [
        "label" => __("💾 快取設定", "moelog-ai-qna"),
        "page" => self::PAGE_CACHE,
      ],
      "cache_tools" => [
        "label" => __("🗑️ 快取管理", "moelog-ai-qna"),
        "page" => self::PAGE_CACHE_TOOLS,
      ],
      "info" => [
        "label" => __("ℹ️ 系統資訊 / 說明", "moelog-ai-qna"),
        "page" => self::PAGE_INFO,
      ],
    ];
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
      __("⚙️ 一般設定", "moelog-ai-qna"),
      [$this, "render_general_section"],
      self::PAGE_GENERAL,
    );

    // AI 供應商
    add_settings_field(
      "provider",
      __("AI 供應商", "moelog-ai-qna"),
      [$this, "render_provider_field"],
      self::PAGE_GENERAL,
      "general",
    );

    // API Key
    add_settings_field(
      "api_key",
      __("API Key", "moelog-ai-qna"),
      [$this, "render_api_key_field"],
      self::PAGE_GENERAL,
      "general",
    );

    // 模型
    add_settings_field(
      "model",
      __("模型", "moelog-ai-qna"),
      [$this, "render_model_field"],
      self::PAGE_GENERAL,
      "general",
    );

    // Temperature
    add_settings_field(
      "temperature",
      __("Temperature", "moelog-ai-qna"),
      [$this, "render_temperature_field"],
      self::PAGE_GENERAL,
      "general",
    );

    // === 內容設定區段 ===
    add_settings_section(
      "content",
      __("📝 內容設定", "moelog-ai-qna"),
      [$this, "render_content_section"],
      self::PAGE_GENERAL,
    );

    // 是否附上文章內容
    add_settings_field(
      "include_content",
      __("附上文章內容", "moelog-ai-qna"),
      [$this, "render_include_content_field"],
      self::PAGE_GENERAL,
      "content",
    );

    // 文章內容截斷長度
    add_settings_field(
      "max_chars",
      __("內容截斷長度", "moelog-ai-qna"),
      [$this, "render_max_chars_field"],
      self::PAGE_GENERAL,
      "content",
    );

    // System Prompt
    add_settings_field(
      "system_prompt",
      __("System Prompt", "moelog-ai-qna"),
      [$this, "render_system_prompt_field"],
      self::PAGE_GENERAL,
      "content",
    );

    // === 顯示設定區段 ===
    add_settings_section(
      "display",
      __("🎨 顯示設定", "moelog-ai-qna"),
      [$this, "render_display_section"],
      self::PAGE_DISPLAY,
    );

    // 問題清單抬頭
    add_settings_field(
      "list_heading",
      __("問題清單抬頭", "moelog-ai-qna"),
      [$this, "render_list_heading_field"],
      self::PAGE_DISPLAY,
      "display",
    );

    // 免責聲明
    add_settings_field(
      "disclaimer_text",
      __("免責聲明", "moelog-ai-qna"),
      [$this, "render_disclaimer_field"],
      self::PAGE_DISPLAY,
      "display",
    );

    // 啟用回饋功能
    add_settings_field(
      "feedback_enabled",
      __("回饋功能", "moelog-ai-qna"),
      [$this, "render_feedback_enabled_field"],
      self::PAGE_DISPLAY,
      "display",
    );

    // 自訂 Banner
    add_settings_field(
      "banner_url",
      __("自訂 Banner", "moelog-ai-qna"),
      [$this, "render_banner_field"],
      self::PAGE_DISPLAY,
      "display",
    );

    // === 快取設定區段 ===
    add_settings_section(
      "cache",
      __("💾 快取設定", "moelog-ai-qna"),
      [$this, "render_cache_section"],
      self::PAGE_CACHE,
    );

    // 快取有效期限
    add_settings_field(
      "cache_ttl_days",
      __("快取有效期限", "moelog-ai-qna"),
      [$this, "render_cache_ttl_field"],
      self::PAGE_CACHE,
      "cache",
    );

    // === 進階設定區塊 ===
    add_settings_section(
      "advanced",
      __("🔧 進階設定", "moelog-ai-qna"),
      [$this, "render_advanced_section"],
      self::PAGE_CACHE,
    );

    // URL 路徑前綴
    add_settings_field(
      "pretty_base",
      __("URL 路徑前綴", "moelog-ai-qna"),
      [$this, "render_pretty_base_field"],
      self::PAGE_CACHE,
      "advanced",
    );

    // 快取目錄名稱
    add_settings_field(
      "static_dir",
      __("快取目錄名稱", "moelog-ai-qna"),
      [$this, "render_static_dir_field"],
      self::PAGE_CACHE,
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

  // =========================================
  // 欄位渲染
  // =========================================

  /**
   * 渲染 AI 供應商欄位
   */
  public function render_provider_field()
  {
    $value = Moelog_AIQnA_Settings::get_provider();
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
    $masked = defined("MOELOG_AIQNA_API_KEY") && constant("MOELOG_AIQNA_API_KEY");

    if ($masked) {
      // 使用常數定義 - 但仍然顯示測試按鈕
    ?>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <input type="password" class="regular-text" value="********" disabled style="flex:1;min-width:300px;">
        <button type="button" class="button" id="test-api-key">
          <?php esc_html_e('測試連線', 'moelog-ai-qna'); ?>
        </button>
      </div>
      <div id="test-result" style="margin-top:8px;"></div>

      <p class="description">
        已使用 wp-config.php 設定 MOELOG_AIQNA_API_KEY。
      </p>

      <input type="hidden" id="api_key" value="from_constant">

    <?php } else {
      // 使用資料庫設定
      $has_saved = !empty(Moelog_AIQnA_Settings::get("api_key"));
      $display = $has_saved ? str_repeat("*", 20) : "";
    ?>
      <input type="password"
        name="<?php echo esc_attr(MOELOG_AIQNA_OPT_KEY); ?>[api_key]"
        id="api_key"
        class="regular-text"
        value="<?php echo esc_attr($display); ?>"
        placeholder="sk-... / AIza..."
        style="width:80%;max-width:500px;margin-bottom:8px;">
      <div style="display:flex;justify-content:space-between;align-items:center;width:80%;max-width:500px;">
        <button type="button" class="button" id="toggle-api-key"><?php esc_html_e("Show", "moelog-ai-qna"); ?></button>
        <button type="button" class="button" id="test-api-key">
          <?php esc_html_e("測試連線", "moelog-ai-qna"); ?>
        </button>
      </div>
      <div id="test-result" style="margin-top:8px;"></div>

      <p class="description">
        <?php esc_html_e(
          "If set, the mask is displayed for security reasons; to replace it, please enter the new Key directly.",
          "moelog-ai-qna",
        ); ?>
      </p>
      <p class="description">
        <strong><?php esc_html_e(
          "Suggestion:",
          "moelog-ai-qna",
        ); ?></strong> <?php esc_html_e(
   "Defining MOELOG_AIQNA_API_KEY in wp-config.php is more secure.",
   "moelog-ai-qna",
 ); ?></br>
        <strong><?php esc_html_e("Code:", "moelog-ai-qna"); ?></strong> define('MOELOG_AIQNA_API_KEY', 'sk-xxxx...');
      </p>
    <?php }
  }

  /**
   * 渲染模型欄位
   */
  public function render_model_field()
  {
    $provider = Moelog_AIQnA_Settings::get_provider();
    $default = Moelog_AIQnA_Model_Registry::get_default_model($provider);
    $value = Moelog_AIQnA_Settings::get("model", "");
    $registry = Moelog_AIQnA_Model_Registry::get_registry();
    $models = Moelog_AIQnA_Model_Registry::get_models_for_provider($provider);
    $all_models = Moelog_AIQnA_Model_Registry::get_all_models();
    $defaults = [];
    $hints = [];
    foreach ($registry as $key => $data) {
      $defaults[$key] = $data["default"] ?? "";
      $hints[$key] = $data["hint"] ?? "";
    }
    $model_ids = array_column($models, "id");
    $is_custom = $value !== "" && !in_array($value, $model_ids, true);
    ?>
    <input type="hidden"
      name="<?php echo esc_attr(MOELOG_AIQNA_OPT_KEY); ?>[model]"
      id="model"
      value="<?php echo esc_attr($value); ?>">

    <select id="model-picker" class="regular-text">
      <option value=""><?php printf(
                          esc_html__("使用預設 (%s)", "moelog-ai-qna"),
                          esc_html($default)
                        ); ?></option>
      <?php foreach ($models as $option): ?>
        <option value="<?php echo esc_attr($option["id"]); ?>">
          <?php echo esc_html($option["label"] ?? $option["id"]); ?>
        </option>
      <?php endforeach; ?>
      <option value="__custom"><?php esc_html_e("自訂模型…", "moelog-ai-qna"); ?></option>
    </select>

    <p class="description">
      <?php esc_html_e("可直接選擇建議模型，或選擇「自訂模型」輸入完整 ID。", "moelog-ai-qna"); ?>
    </p>
    <p class="description" id="model-hint">
      <?php echo esc_html(Moelog_AIQnA_Model_Registry::get_provider_hint($provider)); ?>
    </p>

    <div id="model-custom-wrap" style="display:<?php echo $is_custom ? "block" : "none"; ?>;margin-top:8px;">
      <label for="model-custom-input"><?php esc_html_e("自訂模型 ID", "moelog-ai-qna"); ?></label>
      <input type="text"
        id="model-custom-input"
        class="regular-text"
        value="<?php echo esc_attr($is_custom ? $value : ""); ?>"
        placeholder="<?php esc_attr_e("輸入供應商提供的完整模型 ID", "moelog-ai-qna"); ?>">
    </div>

    <script>
      (function() {
        const registry = <?php echo wp_json_encode($all_models, JSON_UNESCAPED_UNICODE); ?>;
        const defaults = <?php echo wp_json_encode($defaults, JSON_UNESCAPED_UNICODE); ?>;
        const hints = <?php echo wp_json_encode($hints, JSON_UNESCAPED_UNICODE); ?>;
        const valueInput = document.getElementById('model');
        const picker = document.getElementById('model-picker');
        const providerSelect = document.getElementById('provider');
        const customWrap = document.getElementById('model-custom-wrap');
        const customInput = document.getElementById('model-custom-input');
        const hintEl = document.getElementById('model-hint');

        if (!picker || !valueInput || !providerSelect) {
          return;
        }

        function buildOptions(provider) {
          const list = registry[provider] || [];
          const fragment = document.createDocumentFragment();
          const defaultLabel = defaults[provider] ?
            '<?php echo esc_js(__("使用預設", "moelog-ai-qna")); ?>' + ' (' + defaults[provider] + ')' :
            '<?php echo esc_js(__("使用預設", "moelog-ai-qna")); ?>';
          fragment.appendChild(new Option(defaultLabel, ''));
          list.forEach(function(item) {
            fragment.appendChild(new Option(item.label || item.id, item.id));
          });
          fragment.appendChild(new Option('<?php echo esc_js(__("自訂模型…", "moelog-ai-qna")); ?>', '__custom'));
          picker.innerHTML = '';
          picker.appendChild(fragment);
        }

        function toggleCustom(show) {
          if (!customWrap) return;
          customWrap.style.display = show ? 'block' : 'none';
          if (!show && customInput) {
            customInput.value = '';
          }
        }

        function updateHint(provider) {
          if (!hintEl) return;
          hintEl.textContent = hints[provider] || '<?php echo esc_js(__("可輸入供應商提供的模型 ID。", "moelog-ai-qna")); ?>';
        }

        function syncSelection() {
          const provider = providerSelect.value;
          const current = (valueInput.value || '').trim();
          const list = (registry[provider] || []).map(function(item) {
            return item.id;
          });

          if (!current) {
            picker.value = '';
            toggleCustom(false);
          } else if (list.indexOf(current) !== -1) {
            picker.value = current;
            toggleCustom(false);
          } else {
            picker.value = '__custom';
            toggleCustom(true);
            if (customInput && customInput.value !== current) {
              customInput.value = current;
            }
          }

          updateHint(provider);
        }

        picker.addEventListener('change', function() {
          if (this.value === '__custom') {
            toggleCustom(true);
            if (customInput) {
              customInput.focus();
              valueInput.value = customInput.value.trim();
            }
          } else {
            toggleCustom(false);
            valueInput.value = this.value;
          }
        });

        if (customInput) {
          customInput.addEventListener('input', function() {
            valueInput.value = this.value.trim();
          });
        }

        providerSelect.addEventListener('change', function() {
          buildOptions(this.value);
          valueInput.value = '';
          if (customInput) {
            customInput.value = '';
          }
          picker.value = '';
          toggleCustom(false);
          syncSelection();
        });

        buildOptions(providerSelect.value);
        syncSelection();
      })();
    </script>
  <?php
  }

  /**
   * 渲染 Temperature 欄位
   */
  public function render_temperature_field()
  {
    $value = Moelog_AIQnA_Settings::get_temperature();
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
    $checked = Moelog_AIQnA_Settings::include_content();
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
    $value = Moelog_AIQnA_Settings::get_max_chars();
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
    $default = __("你是嚴謹的專業編輯，提供簡潔準確的答案。", "moelog-ai-qna");
    $value = Moelog_AIQnA_Settings::get("system_prompt", $default);
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
    $default = __("Have more questions? Ask the AI below.", "moelog-ai-qna");
    $value = Moelog_AIQnA_Settings::get("list_heading", $default);
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
    $default =
      "本頁面由 AI 生成，可能會發生錯誤，請查核重要資訊。\n" .
      "使用本 AI 生成內容服務即表示您同意此內容僅供個人參考，且您了解輸出內容可能不準確。\n" .
      "所有爭議內容 {site} 保有最終解釋權。";
    $value = Moelog_AIQnA_Settings::get("disclaimer_text", $default);
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
   * 渲染「回饋功能」開關欄位
   */
  public function render_feedback_enabled_field()
  {
    $enabled = Moelog_AIQnA_Settings::get("feedback_enabled", true);
  ?>
    <label style="display:block;margin-bottom:8px;">
      <input type="checkbox"
        name="<?php echo esc_attr(MOELOG_AIQNA_OPT_KEY); ?>[feedback_enabled]"
        value="1"
        <?php checked($enabled, true); ?>>
      <strong><?php esc_html_e("啟用互動回饋功能", "moelog-ai-qna"); ?></strong>
    </label>
    <p class="description">
      <?php esc_html_e(
        "在 AI 回答頁底部顯示「正確/錯誤」投票按鈕、問題回報功能和瀏覽統計。",
        "moelog-ai-qna",
      ); ?>
    </p>
    <p class="description" style="color:#666;">
      <?php esc_html_e(
        "停用後，回答頁將不顯示任何互動元素，僅顯示 AI 回答內容。",
        "moelog-ai-qna",
      ); ?>
    </p>

    <hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">

    <p style="margin-bottom: 8px;">
      <strong><?php esc_html_e("🗑️ 清除回饋統計", "moelog-ai-qna"); ?></strong>
    </p>
    <p class="description" style="margin-bottom: 10px;">
      <?php esc_html_e(
        "刪除所有文章的瀏覽次數、好評、差評統計。此操作無法復原。",
        "moelog-ai-qna",
      ); ?>
    </p>
    <button type="button"
      id="moelog-clear-feedback-stats"
      class="button button-secondary"
      style="color: #b32d2e;">
      <?php esc_html_e("清除所有回饋統計", "moelog-ai-qna"); ?>
    </button>
    <span id="moelog-clear-feedback-result" style="margin-left: 10px;"></span>

    <script>
      jQuery(document).ready(function($) {
        $('#moelog-clear-feedback-stats').on('click', function() {
          var $btn = $(this);
          var $result = $('#moelog-clear-feedback-result');

          if (!confirm('<?php echo esc_js(__("確定要清除所有回饋統計嗎？此操作無法復原。", "moelog-ai-qna")); ?>')) {
            return;
          }

          $btn.prop('disabled', true).text('<?php echo esc_js(__("處理中...", "moelog-ai-qna")); ?>');
          $result.text('');

          $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
              action: 'moelog_aiqna_clear_feedback_stats',
              nonce: '<?php echo wp_create_nonce("moelog_aiqna_clear_feedback"); ?>'
            },
            success: function(response) {
              if (response.success) {
                $result.css('color', 'green').text('✅ ' + response.data.message);
              } else {
                $result.css('color', 'red').text('❌ ' + (response.data.message || '<?php echo esc_js(__("發生錯誤", "moelog-ai-qna")); ?>'));
              }
            },
            error: function() {
              $result.css('color', 'red').text('❌ <?php echo esc_js(__("請求失敗", "moelog-ai-qna")); ?>');
            },
            complete: function() {
              $btn.prop('disabled', false).text('<?php echo esc_js(__("清除所有回饋統計", "moelog-ai-qna")); ?>');
            }
          });
        });
      });
    </script>
  <?php
  }

  /**
   * 渲染「自訂 Banner」欄位
   */
  public function render_banner_field()
  {
    $value = Moelog_AIQnA_Settings::get("banner_url", "");
    $default_url = plugins_url("includes/assets/images/moe-banner.jpg", dirname(__FILE__));
    $preview_url = $value ?: $default_url;
  ?>
    <div id="moe-banner-wrap" style="max-width:880px;">

      <div id="moe-banner-preview" style="margin-bottom:12px;">
        <img src="<?php echo esc_url($preview_url); ?>"
          id="moe-banner-img"
          style="width:100%;max-width:880px;height:auto;border:1px solid #ddd;border-radius:4px;display:block;"
          alt="Banner Preview">
      </div>

      <input type="hidden"
        name="<?php echo esc_attr(MOELOG_AIQNA_OPT_KEY); ?>[banner_url]"
        id="moe-banner-url"
        value="<?php echo esc_attr($value); ?>">

      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <button type="button" class="button" id="moe-banner-upload">
          <?php esc_html_e("選擇圖片", "moelog-ai-qna"); ?>
        </button>
        <button type="button" class="button button-secondary" id="moe-banner-remove"
          style="<?php echo $value ? '' : 'display:none;'; ?>">
          <?php esc_html_e("移除（恢復預設）", "moelog-ai-qna"); ?>
        </button>
      </div>

      <p class="description" style="margin-top:8px;">
        <?php esc_html_e("建議尺寸：880 × 240 px。留空則使用預設 Banner。", "moelog-ai-qna"); ?>
      </p>
    </div>

    <script>
    jQuery(document).ready(function($) {
      var frame;
      var defaultUrl = <?php echo wp_json_encode($default_url); ?>;

      $('#moe-banner-upload').on('click', function(e) {
        e.preventDefault();
        if (frame) { frame.open(); return; }
        frame = wp.media({
          title: <?php echo wp_json_encode(__('選擇 Banner 圖片', 'moelog-ai-qna')); ?>,
          button: { text: <?php echo wp_json_encode(__('使用此圖片', 'moelog-ai-qna')); ?> },
          multiple: false,
          library: { type: 'image' }
        });
        frame.on('select', function() {
          var attachment = frame.state().get('selection').first().toJSON();
          $('#moe-banner-url').val(attachment.url);
          $('#moe-banner-img').attr('src', attachment.url);
          $('#moe-banner-remove').show();
        });
        frame.open();
      });

      $('#moe-banner-remove').on('click', function() {
        $('#moe-banner-url').val('');
        $('#moe-banner-img').attr('src', defaultUrl);
        $(this).hide();
      });
    });
    </script>
  <?php
  }

  /**
   * 渲染快取有效期限欄位
   */
  public function render_cache_ttl_field()
  {
    $ttl_days = Moelog_AIQnA_Settings::get_cache_ttl_days();

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
    $ttl_seconds = Moelog_AIQnA_Settings::get_cache_ttl_seconds();
    echo '<p class="description" style="color: #666;">';
    printf(
      esc_html__("相當於 %s 秒", "moelog-ai-qna"),
      "<code>" . number_format($ttl_seconds) . "</code>",
    );
    echo "</p>";
  }

  /**
   * 渲染 URL 路徑前綴欄位
   */
  public function render_pretty_base_field()
  {
    $value = Moelog_AIQnA_Settings::get_pretty_base();
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
    $value = Moelog_AIQnA_Settings::get_static_dir();
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
    $previous = Moelog_AIQnA_Settings::get();

    // 初始化輸出陣列 - 先複製現有設定，避免其他分頁儲存時重置
    $output = is_array($previous) ? $previous : [];

    // =========================================
    // 判斷目前是哪個分頁提交
    // =========================================
    $is_general_tab = isset($input["provider"]) || isset($input["api_key"]) || isset($input["temperature"]);
    $is_display_tab = isset($input["list_heading"]) || isset($input["disclaimer_text"]) || isset($input["banner_url"]);
    $is_cache_tab = isset($input["cache_ttl_days"]) || isset($input["pretty_base"]) || isset($input["static_dir"]);

    // =========================================
    // 1. Provider (AI 供應商) - 只在一般設定分頁處理
    // =========================================
    if ($is_general_tab && isset($input["provider"])) {
      $input_provider = moelog_aiqna_array_get($input, "provider", "openai");
      $output["provider"] = Moelog_AIQnA_Settings::is_valid_provider($input_provider)
        ? $input_provider
        : "openai";
    }

    // =========================================
    // 2. Model (模型名稱) - 只在一般設定分頁處理
    // =========================================
    if ($is_general_tab && isset($input["model"])) {
      $model_value = sanitize_text_field($input["model"]);
      if ($model_value !== "") {
        $output["model"] = $model_value;
      } else {
        unset($output["model"]);
      }
    }

    // =========================================
    // 3. Temperature (溫度參數) - 只在一般設定分頁處理
    // =========================================
    if ($is_general_tab && isset($input["temperature"])) {
      $temp = floatval($input["temperature"]);
      $output["temperature"] = max(MOELOG_AIQNA_MIN_TEMPERATURE, min(MOELOG_AIQNA_MAX_TEMPERATURE, $temp));
    }

    // =========================================
    // 4. Include Content (是否附上文章內容) - 只在一般設定分頁處理
    // =========================================
    if ($is_general_tab) {
      $output["include_content"] = !empty($input["include_content"]) ? 1 : 0;
    }

    // =========================================
    // 5. Block Enabled (是否在文章底部顯示問題區塊) - 只在一般設定分頁處理
    // =========================================
    if ($is_general_tab) {
      $output["block_enabled"] = !empty($input["block_enabled"]) ? 1 : 0;
    }

    // =========================================
    // 6. Max Chars (內容截斷長度) - 只在一般設定分頁處理
    // =========================================
    if ($is_general_tab && isset($input["max_chars"])) {
      $max_chars = absint($input["max_chars"]);
      $output["max_chars"] = max(MOELOG_AIQNA_MIN_MAX_CHARS, min(MOELOG_AIQNA_MAX_MAX_CHARS, $max_chars));
    }

    // =========================================
    // 7. System Prompt / Custom Prompt (自訂提示詞) - 只在一般設定分頁處理
    // =========================================
    if ($is_general_tab) {
      if (isset($input["custom_prompt"])) {
        $output["custom_prompt"] = sanitize_textarea_field($input["custom_prompt"]);
      }
      if (isset($input["system_prompt"])) {
        $output["system_prompt"] = wp_kses_post($input["system_prompt"]);
      }
    }

    // =========================================
    // 8. List Heading (問題清單標題) - 只在顯示設定分頁處理
    // =========================================
    if ($is_display_tab && isset($input["list_heading"])) {
      $default_heading = __(
        "還有其他問題嗎?以下是 AI 可以回答的問題",
        "moelog-ai-qna",
      );
      $output["list_heading"] = sanitize_text_field($input["list_heading"]);
      if (empty($output["list_heading"])) {
        $output["list_heading"] = $default_heading;
      }
    }

    // =========================================
    // 9. Disclaimer (免責聲明) - 只在顯示設定分頁處理
    // =========================================
    if ($is_display_tab && isset($input["disclaimer_text"])) {
      $default_disclaimer =
        "使用本 AI 生成內容服務即表示您同意此內容僅供個人參考,且您了解輸出內容可能不準確。\n" .
        "所有爭議內容 {site} 保有最終解釋權。";
      $output["disclaimer_text"] = sanitize_textarea_field($input["disclaimer_text"]);
      if (empty($output["disclaimer_text"])) {
        $output["disclaimer_text"] = $default_disclaimer;
      }
    }

    // =========================================
    // 9.5 Feedback Enabled (回饋功能開關) - 只在顯示設定分頁處理
    // =========================================
    if ($is_display_tab) {
      $output["feedback_enabled"] = !empty($input["feedback_enabled"]) ? 1 : 0;
    }

    // =========================================
    // 9.6 Banner URL - 只在顯示設定分頁處理
    // =========================================
    if ($is_display_tab) {
      $banner_url = isset($input["banner_url"]) ? trim($input["banner_url"]) : "";
      $output["banner_url"] = $banner_url ? esc_url_raw($banner_url) : "";
    }

    // =========================================
    // 10. API Key 處理 (★★★ 核心安全邏輯 ★★★) - 只在一般設定分頁處理
    // =========================================
    if ($is_general_tab) {
      // 步驟 1: 檢查是否使用 wp-config.php 常數定義
      if (defined("MOELOG_AIQNA_API_KEY") && constant("MOELOG_AIQNA_API_KEY")) {
        // 使用常數,不儲存到資料庫 (最安全的方式)
        $output["api_key"] = "";

        Moelog_AIQnA_Debug::log_info("Using API Key from wp-config.php constant");
      } else {
        // 步驟 2: 使用資料庫儲存 (需要加密)
        $input_key = trim(moelog_aiqna_array_get($input, "api_key", ""));

        // 步驟 2a: 檢查是否為遮罩值或空值
        if (empty($input_key) || preg_match('/^\*+$/', $input_key)) {
          // 保留原有的 Key (不變更)
          $output["api_key"] = moelog_aiqna_array_get($previous, "api_key", "");

          Moelog_AIQnA_Debug::log_info("API Key unchanged (masked input)");
        } else {
          // 步驟 2b: 有新的 API Key 輸入

          // 檢查是否已經是加密格式 (避免重複加密)
          if (
            function_exists("moelog_aiqna_is_encrypted") &&
            moelog_aiqna_is_encrypted($input_key)
          ) {
            // 已經是加密格式,直接儲存
            $output["api_key"] = $input_key;

            Moelog_AIQnA_Debug::log_info("API Key already encrypted, saved as-is");
          } else {
            // 步驟 2c: 新的明文 API Key - 進行加密
            if (function_exists("moelog_aiqna_encrypt_api_key")) {
              $encrypted_key = moelog_aiqna_encrypt_api_key($input_key);

              if (!empty($encrypted_key)) {
                $output["api_key"] = $encrypted_key;

                Moelog_AIQnA_Debug::log_info("New API Key encrypted and saved");

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

              Moelog_AIQnA_Debug::log_warning("Encryption function not available, storing plaintext");
            }
          }
        }
      }
    }

    // =========================================
    // 11. Cache TTL (快取有效期限 - 天數) - 只在快取設定分頁處理
    // =========================================
    if ($is_cache_tab && isset($input["cache_ttl_days"])) {
      $cache_ttl_days = absint($input["cache_ttl_days"]);
      // 限制在有效範圍內
      $cache_ttl_days = max(MOELOG_AIQNA_MIN_CACHE_TTL_DAYS, min(MOELOG_AIQNA_MAX_CACHE_TTL_DAYS, $cache_ttl_days));
      $output["cache_ttl_days"] = $cache_ttl_days;
    }

    // =========================================
    // 12. 進階設定 - 只在快取設定分頁處理
    // =========================================

    // ✅ 驗證 pretty_base
    if ($is_cache_tab && isset($input["pretty_base"])) {
      $pretty_base = sanitize_title($input["pretty_base"]);
      // PHP 8.1+: 確保 preg_replace 不返回 null
      $pretty_base = preg_replace("/[^a-z0-9\-]/", "", $pretty_base) ?? "";

      if (empty($pretty_base)) {
        $pretty_base = "qna";
      }

      // 檢查是否變更
      $old_value = Moelog_AIQnA_Settings::get_pretty_base();

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
    if ($is_cache_tab && isset($input["static_dir"])) {
      $static_dir = sanitize_title($input["static_dir"]);
      // PHP 8.1+: 確保 preg_replace 不返回 null
      $static_dir = preg_replace("/[^a-z0-9\-]/", "", $static_dir) ?? "";

      if (empty($static_dir)) {
        $static_dir = "ai-answers";
      }

      // 檢查是否變更
      $old_value = Moelog_AIQnA_Settings::get_static_dir();

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

    // 儲存同時就把新目錄與保護檔建好（掛在 wp-content）
    if (
      class_exists("Moelog_AIQnA_Cache") &&
      method_exists("Moelog_AIQnA_Cache", "prepare_static_root")
    ) {
      Moelog_AIQnA_Cache::prepare_static_root();
    }

    // =========================================
    // 13. STM (Structured Data Mode) - 獨立 option
    // =========================================
    // ✅ 修正: geo_mode 是獨立的 option，只在「顯示設定」分頁處理
    // 注意：checkbox 取消勾選時不會出現在 POST 中，所以用 $is_display_tab 判斷
    if ($is_display_tab) {
      $old_geo = (bool) get_option("moelog_aiqna_geo_mode", false);
      $new_geo = !empty($_POST["moelog_aiqna_geo_mode"]);

      update_option("moelog_aiqna_geo_mode", $new_geo ? 1 : 0);

      // 如果狀態變更，刷新 rewrite rules
      if ($old_geo !== $new_geo) {
        flush_rewrite_rules(false);
      }
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

    // =========================================
    // 返回清理後的設定
    // =========================================
    return $output;
  }
}
