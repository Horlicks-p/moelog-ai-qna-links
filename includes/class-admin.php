<?php

/**
 * Moelog AI Q&A Admin Class
 *
 * 負責後台管理介面協調:
 * - 設定頁面
 * - 模組協調
 * - 系統資訊顯示
 * - 通知管理
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
   * 設定處理器
   * @var Moelog_AIQnA_Admin_Settings
   */
  private $settings;

  /**
   * 快取管理器
   * @var Moelog_AIQnA_Admin_Cache
   */
  private $cache_manager;

  /**
   * 建構函數
   */
  public function __construct()
  {
    $this->settings = new Moelog_AIQnA_Admin_Settings();
    $this->cache_manager = new Moelog_AIQnA_Admin_Cache();

    add_action("admin_notices", [$this, "show_flush_rewrite_notice"]);

    // 自訂 Banner：將設定值注入 filter
    add_filter("moelog_aiqna_banner_url", function ($url) {
      $saved = Moelog_AIQnA_Settings::get("banner_url", "");
      return $saved ?: $url;
    });

    // 在顯示設定分頁載入 WP Media Library
    add_action("admin_enqueue_scripts", function ($hook) {
      if ($hook !== "settings_page_moelog_aiqna") {
        return;
      }
      $tab = isset($_GET["tab"]) ? sanitize_key($_GET["tab"]) : "general";
      if ($tab === "display") {
        wp_enqueue_media();
      }
    });
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
   * 註冊設定（委派給設定處理器）
   */
  public function register_settings()
  {
    $this->settings->register_settings();
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
    $this->cache_manager->handle_cache_actions();
    $tabs = Moelog_AIQnA_Admin_Settings::get_tabs();
    $current_tab = isset($_GET["tab"]) ? sanitize_key($_GET["tab"]) : "general";
    if (!isset($tabs[$current_tab])) {
      $current_tab = "general";
    }
    $current_page_slug = $tabs[$current_tab]["page"] ?? "";
    $form_tabs = ["general", "display", "cache"];
    $is_form_tab = in_array($current_tab, $form_tabs, true);
    $base_url = add_query_arg(
      [
        "page" => "moelog_aiqna",
      ],
      admin_url("options-general.php"),
    );
?>
    <div class="wrap moelog-aiqna-wrap">
      <h2>
        <?php echo esc_html(get_admin_page_title()); ?>
        <span style="font-size:0.6em;color:#999;">
          v<?php echo esc_html(MOELOG_AIQNA_VERSION); ?>
        </span>
      </h2>

      <?php settings_errors("moelog_aiqna_messages", false, true); ?>

      <div class="moelog-aiqna-main-layout">
        <!-- 左側: 主要設定 -->
        <div class="moelog-aiqna-section">
          
          <div class="moelog-aiqna-tabs nav-tab-wrapper">
            <?php foreach ($tabs as $slug => $tab):
              $url = esc_url(add_query_arg("tab", $slug, $base_url));
              $active_class = $slug === $current_tab ? " nav-tab-active" : "";
            ?>
              <a href="<?php echo $url; ?>" class="nav-tab<?php echo esc_attr($active_class); ?>">
                <?php echo esc_html($tab["label"]); ?>
              </a>
            <?php endforeach; ?>
          </div>

          <?php if ($is_form_tab): ?>
            <form method="post" action="options.php">
              <?php
              settings_fields(MOELOG_AIQNA_OPT_KEY);
              do_settings_sections($current_page_slug);
              submit_button();
              ?>
            </form>
          <?php elseif ($current_tab === "cache_tools"): ?>
            <?php $this->cache_manager->render_cache_management(); ?>
          <?php else: ?>
            <div class="moelog-settings-card">
              <?php
              $this->render_usage_guide();
              echo '<hr>';
              $this->render_release_notes();
              echo '<hr>';
              $this->render_system_info();
              ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- 右側: 側邊欄 -->
        <div class="moelog-aiqna-sidebar">
          <?php $this->render_sidebar(); ?>
        </div>
      </div>

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
        <code>[moelog_aiqna index="1"]</code>
        <p style="margin:10px 0 5px;font-size:0.9em;color:#666;">
          <?php esc_html_e(
            "顯示第 1 題（index 範圍：1-8）",
            "moelog-ai-qna",
          ); ?>
        </p>
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
   * 使用說明
   */
  private function render_usage_guide()
  {
  ?>
    <div class="moelog-info-section">
      <h4><?php esc_html_e("ℹ️ 使用說明", "moelog-ai-qna"); ?></h4>
      <ol style="line-height:1.8; margin:0; padding-left:20px;">
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
          <code>[moelog_aiqna index="1"]</code>
          <?php esc_html_e(
            "將指定問題單獨放在任意段落（index 範圍 1–8）。",
            "moelog-ai-qna",
          ); ?>
        </li>
      </ol>
    </div>
  <?php
  }

  /**
   * 更新內容
   */
  private function render_release_notes()
  {
  ?>
    <div class="moelog-info-section">
      <h4>
        <?php
        printf(
          /* translators: %s: plugin version */
          esc_html__("🧾 v%s 更新內容", "moelog-ai-qna"),
          esc_html(MOELOG_AIQNA_VERSION),
        );
        ?>
      </h4>
      <ul style="list-style-type:circle;margin:0;padding-left:20px;line-height:1.8;">
        <li>📝 <?php esc_html_e(
            "Markdown Support: Introduced Parsedown to correctly convert Markdown before rendering answers.",
            "moelog-ai-qna",
          ); ?></li>
        <li>🎨 <?php esc_html_e(
            "Style Fixes: Fixed answer page CSS to better support Markdown content.",
            "moelog-ai-qna",
          ); ?></li>
        <li>🎨 <?php esc_html_e(
            "UI Redesign: Admin interface redesigned with a clean, elegant style.",
            "moelog-ai-qna",
          ); ?></li>
      </ul>
    </div>
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
    <div class="moelog-info-section">
      <h4><?php esc_html_e("🛠️ 系統資訊", "moelog-ai-qna"); ?></h4>
      <table class="widefat" style="max-width:600px;">
        <tr>
          <th style="width:40%;"><?php esc_html_e(
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
  <?php
  }

  /**
   * 渲染管理腳本
   */
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
          if (!apiKey || (apiKey !== 'from_constant' && (apiKey === '' || apiKey === '********************'))) {
            $result.html('<span style="color:red;">✗ 請先輸入 API Key</span>');
            return;
          }

          $btn.prop('disabled', true).text('測試中...');
          $result.html('<span style="color:#999;">⏳ 連線中...</span>');

          $.ajax({
            url: '<?php echo esc_js(admin_url("admin-ajax.php")); ?>',
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
              $btn.prop('disabled', false).text('<?php echo esc_js(__("測試連線", "moelog-ai-qna")); ?>');
            }
          });
        });

      });
    </script>
  <?php
  }

  // =========================================
  // 通知管理
  // =========================================

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
    $cache_stats = Moelog_AIQnA_Cache::get_stats();

    // 檢查 Rewrite Rules
    $pretty_base = Moelog_AIQnA_Settings::get_pretty_base();
    $rules = get_option('rewrite_rules');
    $pattern = '^' . $pretty_base . '/([a-z0-9]+)-([a-f0-9]{3})-([0-9]+)/?$';
    $rewrite_ok = is_array($rules) && isset($rules[$pattern]);

    // API Key 狀態
    $api_key_from_constant =
      defined("MOELOG_AIQNA_API_KEY") && constant("MOELOG_AIQNA_API_KEY");
    $api_key_set = $api_key_from_constant || !empty(Moelog_AIQnA_Settings::get("api_key"));

    return [
      "plugin_version" => MOELOG_AIQNA_VERSION,
      "wp_version" => get_bloginfo("version"),
      "php_version" => PHP_VERSION,
      "mb_support" => function_exists("mb_strlen"),
      "geo_enabled" => (bool) get_option("moelog_aiqna_geo_mode"),
      "provider" => Moelog_AIQnA_Settings::get_provider(),
      "api_key_set" => $api_key_set,
      "api_key_from_constant" => $api_key_from_constant,
      "cache_writable" => $cache_stats["directory_writable"],
      "rewrite_rules_ok" => $rewrite_ok,
      "memory_limit" => ini_get("memory_limit"),
      "upload_max_size" => ini_get("upload_max_filesize"),
      "cache_stats" => $cache_stats,
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
    $api_key_set = Moelog_AIQnA_Settings::get_api_key();

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
}
