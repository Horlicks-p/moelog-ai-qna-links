<?php
/**
 * Plugin Name: Moelog AI Q&A Links
 * Description: 在每篇文章底部顯示作者預設的問題清單,點擊後開新分頁,由 AI 生成答案的靜態HTML。支持 OpenAI/Gemini,可自訂模型與提示。
 * Version: 1.8.3
 * Author: Horlicks (moelog.com)
 * Text Domain: moelog-ai-qna
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined("ABSPATH")) {
    exit();
}

// =========================================
// 定義常數
// =========================================
define("MOELOG_AIQNA_VERSION", "1.8.3");
define("MOELOG_AIQNA_FILE", __FILE__);
define("MOELOG_AIQNA_DIR", plugin_dir_path(__FILE__));
define("MOELOG_AIQNA_URL", plugin_dir_url(__FILE__));
define("MOELOG_AIQNA_BASENAME", plugin_basename(__FILE__));

// 插件設定鍵值
define("MOELOG_AIQNA_OPT_KEY", "moelog_aiqna_settings");
define("MOELOG_AIQNA_SECRET_KEY", "moelog_aiqna_secret");
define("MOELOG_AIQNA_META_KEY", "_moelog_aiqna_questions");
define("MOELOG_AIQNA_META_LANG_KEY", "_moelog_aiqna_questions_lang");

// 路由與快取
define("MOELOG_AIQNA_PRETTY_BASE", "qna");
define("MOELOG_AIQNA_STATIC_DIR", "ai-answers");
//define('MOELOG_AIQNA_CACHE_TTL', 86400);
// 改為:
// 快取 TTL 改由 moelog_aiqna_get_cache_ttl() 函數動態取得

// AI 預設模型
define("MOELOG_AIQNA_DEFAULT_MODEL_OPENAI", "gpt-4o-mini");
define("MOELOG_AIQNA_DEFAULT_MODEL_GEMINI", "gemini-2.5-flash");

// =========================================
// 自動載入類別
// =========================================
spl_autoload_register(function ($class_name) {
    // 只載入本插件的類別
    if (strpos($class_name, "Moelog_AIQnA_") !== 0) {
        return;
    }

    // 轉換類別名稱為檔案名稱
    // Moelog_AIQnA_Cache -> class-cache.php
    // Moelog_AIQnA_AI_Client -> class-ai-client.php
    $class_file = str_replace("Moelog_AIQnA_", "", $class_name);
    $class_file = strtolower(str_replace("_", "-", $class_file));
    $file_path = MOELOG_AIQNA_DIR . "includes/class-" . $class_file . ".php";

    if (file_exists($file_path)) {
        require_once $file_path;
    }
});

// =========================================
// 載入輔助函數
// =========================================
require_once MOELOG_AIQNA_DIR . 'includes/helpers-utils.php';
require_once MOELOG_AIQNA_DIR . 'includes/helpers-encryption.php'; // 新增

// =========================================
// 初始化核心類別
// =========================================
function moelog_aiqna_init()
{
    // 檢查 WordPress 版本
    if (version_compare(get_bloginfo("version"), "5.0", "<")) {
        add_action("admin_notices", function () {
            printf(
                '<div class="error"><p>%s</p></div>',
                esc_html__(
                    "Moelog AI Q&A 需 WordPress 5.0 或以上版本。",
                    "moelog-ai-qna"
                )
            );
        });
        return;
    }

    // 載入核心
    require_once MOELOG_AIQNA_DIR . "includes/class-core.php";

    // 建立全域實例
    global $moelog_aiqna_instance;
    $moelog_aiqna_instance = new Moelog_AIQnA_Core();
    $GLOBALS["moelog_aiqna_instance"] = $moelog_aiqna_instance;

    // 載入 GEO 模組(如果存在)
    if (file_exists(MOELOG_AIQNA_DIR . "moelog-ai-geo.php")) {
        require_once MOELOG_AIQNA_DIR . "moelog-ai-geo.php";
    }
}
add_action("plugins_loaded", "moelog_aiqna_init", 5);

function moelog_aiqna_check_upgrade()
{
    $db_version = get_option('moelog_aiqna_db_version', '0');

    // === 1.8.3: 加密現有 API Key ===
    if (version_compare($db_version, '1.8.3', '<')) {
        moelog_aiqna_migrate_api_key_encryption();
        update_option('moelog_aiqna_db_version', '1.8.3');
    }

    // === 1.8.0: 設定預設 TTL ===
    if (version_compare($db_version, '1.8.0', '<')) {
        $options = get_option(MOELOG_AIQNA_OPT_KEY, []);

        if (!isset($options['cache_ttl_days'])) {
            $options['cache_ttl_days'] = 30;
            update_option(MOELOG_AIQNA_OPT_KEY, $options);
        }

        update_option('moelog_aiqna_db_version', '1.8.0');
    }
}
add_action('admin_init', 'moelog_aiqna_check_upgrade');

/**
 * 遷移:加密現有的明文 API Key
 */
function moelog_aiqna_migrate_api_key_encryption()
{
    // 跳過使用常數的情況
    if (defined('MOELOG_AIQNA_API_KEY') && MOELOG_AIQNA_API_KEY) {
        return;
    }

    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $current_key = moelog_aiqna_array_get($settings, 'api_key', '');

    // 沒有 API Key 或已經是加密格式
    if (empty($current_key) || moelog_aiqna_is_encrypted($current_key)) {
        return;
    }

    // 加密並更新
    $encrypted_key = moelog_aiqna_encrypt_api_key($current_key);
    
    if (!empty($encrypted_key)) {
        $settings['api_key'] = $encrypted_key;
        update_option(MOELOG_AIQNA_OPT_KEY, $settings);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Moelog AIQnA] Successfully migrated API Key to encrypted format');
        }
    }
}

// =========================================
// 語系載入
// =========================================
function moelog_aiqna_load_textdomain()
{
    load_plugin_textdomain(
        "moelog-ai-qna",
        false,
        dirname(MOELOG_AIQNA_BASENAME) . "/languages/"
    );
}
add_action("plugins_loaded", "moelog_aiqna_load_textdomain");

// =========================================
// 啟用掛鉤
// =========================================
register_activation_hook(__FILE__, "moelog_aiqna_activate");
function moelog_aiqna_activate()
{
    // 標記需要刷新 rewrite rules
    update_option("moe_aiqna_needs_flush", "1", false);

    // 初始化 HMAC secret(如果尚未存在)
    if (!get_option(MOELOG_AIQNA_SECRET_KEY)) {
        try {
            $secret = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $secret = hash("sha256", microtime(true) . wp_salt() . rand());
        }
        add_option(MOELOG_AIQNA_SECRET_KEY, $secret, "", false);
    }

    // 檢查 PHP 版本
    if (version_compare(PHP_VERSION, "7.4", "<")) {
        deactivate_plugins(MOELOG_AIQNA_BASENAME);
        wp_die(
            esc_html__(
                "Moelog AI Q&A 需要 PHP 7.4 或更高版本。",
                "moelog-ai-qna"
            ),
            esc_html__("插件啟用失敗", "moelog-ai-qna"),
            ["back_link" => true]
        );
    }
}

// =========================================
// 停用掛鉤
// =========================================
register_deactivation_hook(__FILE__, "moelog_aiqna_deactivate");
function moelog_aiqna_deactivate()
{
    // 清除 rewrite rules
    flush_rewrite_rules(false);

    // 清除排程任務
    $timestamp = wp_next_scheduled("moelog_aiqna_ping_search_engines");
    if ($timestamp) {
        wp_unschedule_event($timestamp, "moelog_aiqna_ping_search_engines");
    }
}

// =========================================
// 卸載掛鉤
// =========================================
register_uninstall_hook(__FILE__, "moelog_aiqna_uninstall");
function moelog_aiqna_uninstall()
{
    // 刪除設定
    delete_option(MOELOG_AIQNA_OPT_KEY);
    delete_option(MOELOG_AIQNA_SECRET_KEY);
    delete_option("moelog_aiqna_geo_mode");
    delete_option("moe_aiqna_needs_flush");

    // 刪除所有文章 meta
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s OR meta_key = %s",
            MOELOG_AIQNA_META_KEY,
            MOELOG_AIQNA_META_LANG_KEY
        )
    );

    // 清除所有 transient 快取
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_moe_aiqna_%' 
            OR option_name LIKE '_transient_timeout_moe_aiqna_%'"
    );

    // 刪除靜態檔案目錄(可選)
    $static_dir = WP_CONTENT_DIR . "/" . MOELOG_AIQNA_STATIC_DIR;
    if (file_exists($static_dir)) {
        $files = glob($static_dir . "/*.html");
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        // 刪除 .htaccess
        if (file_exists($static_dir . "/.htaccess")) {
            unlink($static_dir . "/.htaccess");
        }
        @rmdir($static_dir);
    }
}

// =========================================
// 延遲刷新 Rewrite Rules
// =========================================
function moelog_aiqna_maybe_flush_rewrite()
{
    if (get_option("moe_aiqna_needs_flush") === "1") {
        flush_rewrite_rules(false); // 不修改 .htaccess
        delete_option("moe_aiqna_needs_flush");
    }
}
add_action("init", "moelog_aiqna_maybe_flush_rewrite", 20);

// =========================================
// 設定頁連結
// =========================================
function moelog_aiqna_add_settings_link($links)
{
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url("options-general.php?page=moelog_aiqna")),
        esc_html__("設定", "moelog-ai-qna")
    );
    array_unshift($links, $settings_link);
    return $links;
}
add_filter(
    "plugin_action_links_" . MOELOG_AIQNA_BASENAME,
    "moelog_aiqna_add_settings_link"
);

// =========================================
// 公開 API 函數
// =========================================

/**
 * 取得插件核心實例
 *
 * @return Moelog_AIQnA_Core|null
 */
function moelog_aiqna_instance()
{
    return $GLOBALS["moelog_aiqna_instance"] ?? null;
}

/**
 * 建立問題的回答 URL
 *
 * @param int    $post_id  文章 ID
 * @param string $question 問題文字
 * @return string
 */
function moelog_aiqna_build_url($post_id, $question)
{
    $instance = moelog_aiqna_instance();
    if (!$instance) {
        return "";
    }
    return $instance->build_answer_url($post_id, $question);
}

/**
 * 檢查靜態快取是否存在
 *
 * @param int    $post_id  文章 ID
 * @param string $question 問題文字
 * @return bool
 */
function moelog_aiqna_cache_exists($post_id, $question)
{
    if (!class_exists("Moelog_AIQnA_Cache")) {
        return false;
    }
    return Moelog_AIQnA_Cache::exists($post_id, $question);
}

/**
 * 清除特定文章的快取
 *
 * @param int         $post_id  文章 ID
 * @param string|null $question 問題文字(null = 清除該文章所有問題)
 * @return bool
 */
function moelog_aiqna_clear_cache($post_id, $question = null)
{
    if (!class_exists("Moelog_AIQnA_Cache")) {
        return false;
    }
    return Moelog_AIQnA_Cache::delete($post_id, $question);
}

// =========================================
// Debug 輔助(僅在 WP_DEBUG 時載入)
// =========================================
if (defined("WP_DEBUG") && WP_DEBUG) {
    /**
     * 在管理工具列顯示插件狀態
     */
    function moelog_aiqna_debug_admin_bar($wp_admin_bar)
    {
        if (!current_user_can("manage_options")) {
            return;
        }

        $cache_dir = WP_CONTENT_DIR . "/" . MOELOG_AIQNA_STATIC_DIR;
        $cache_count = 0;
        if (file_exists($cache_dir)) {
            $cache_count = count(glob($cache_dir . "/*.html"));
        }

        $wp_admin_bar->add_node([
            "id" => "moelog-aiqna-debug",
            "title" => sprintf("AI Q&A: %d 個快取", $cache_count),
            "href" => admin_url("options-general.php?page=moelog_aiqna"),
        ]);
    }
    add_action("admin_bar_menu", "moelog_aiqna_debug_admin_bar", 999);
}

// =========================================
// 相容性檢查
// =========================================
function moelog_aiqna_check_compatibility()
{
    $errors = [];

    // 檢查必要的 PHP 擴充
    if (!function_exists("json_encode")) {
        $errors[] = __("需要 PHP JSON 擴充。", "moelog-ai-qna");
    }

    if (!function_exists("hash_hmac")) {
        $errors[] = __("需要 PHP hash 擴充。", "moelog-ai-qna");
    }

    if (!function_exists("mb_strlen")) {
        $errors[] = __(
            "建議啟用 PHP mbstring 擴充以獲得更好的多語言支援。",
            "moelog-ai-qna"
        );
    }

    // 檢查寫入權限
    $upload_dir = wp_upload_dir();
    if (!wp_is_writable($upload_dir["basedir"])) {
        $errors[] = __("WordPress 上傳目錄不可寫入。", "moelog-ai-qna");
    }

    if (!empty($errors)) {
        add_action("admin_notices", function () use ($errors) {
            echo '<div class="notice notice-warning"><p><strong>Moelog AI Q&A:</strong></p><ul>';
            foreach ($errors as $error) {
                echo "<li>" . esc_html($error) . "</li>";
            }
            echo "</ul></div>";
        });
    }
}
add_action("admin_init", "moelog_aiqna_check_compatibility");

// =========================================
// 結束標記
// =========================================
// EOF - Moelog AI Q&A v1.8.1
