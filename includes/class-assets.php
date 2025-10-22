<?php
/**
 * Moelog AI Q&A Assets Class
 *
 * 負責 CSS/JS 資源載入 (路徑已修正)
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Assets
{
    /**
     * 建構函數
     * 
     * ✅ 優化: 移除建構子中的 enqueue_front() 掛鉤
     * 統一由 Core::register_hooks() 透過 enqueue_frontend_assets() 載入
     */
    public function __construct()
    {
        // 註冊鉤子已在 Core 中處理,這裡不再重複掛載
        // 原本的: add_action("wp_enqueue_scripts", [$this, "enqueue_front"], 20);
    }

    // =========================================
    // 前台資源
    // =========================================

public function enqueue_frontend_assets()
{
    // ✅ 修正:使用更精準的判斷,避免在所有文章頁載入資源
    if (!$this->should_load_frontend_assets()) {
        return;
    }

    // 載入主要樣式表
    $this->enqueue_main_styles();

    // 載入前台腳本
    $this->enqueue_frontend_scripts();
}

    /**
     * 判斷是否需要載入前台資源
     *
     * ✅ 優化: 整併原 enqueue_front() 的判斷邏輯
     * 
     * @return bool
     */
    private function should_load_frontend_assets()
    {
        // 回答頁一定載入
        if ($this->is_answer_page()) {
            return true;
        }

        // 單篇文章頁面
        if (is_singular() && in_the_loop() && is_main_query()) {
            $post_id = get_the_ID();
            if ($post_id && $this->post_has_questions($post_id)) {
                return true;
            }
        }

        // 有使用 shortcode
        global $post;
        if ($post && has_shortcode($post->post_content, "moelog_aiqna")) {
            return true;
        }

        return false;
    }

    /**
     * 載入主要樣式
     */
    private function enqueue_main_styles()
    {
        $css_url = MOELOG_AIQNA_URL . "includes/assets/css/style.css";
        $css_path = MOELOG_AIQNA_DIR . "includes/assets/css/style.css";
        $css_ver = file_exists($css_path)
            ? (string) filemtime($css_path)
            : MOELOG_AIQNA_VERSION;

        wp_enqueue_style("moelog-aiqna-style", $css_url, [], $css_ver, "all");

        // 允許主題覆蓋樣式
        $custom_css_path =
            get_stylesheet_directory() . "/moelog-aiqna-custom.css";
        $custom_css_url =
            get_stylesheet_directory_uri() . "/moelog-aiqna-custom.css";

        if (file_exists($custom_css_path)) {
            wp_enqueue_style(
                "moelog-aiqna-custom",
                $custom_css_url,
                ["moelog-aiqna-style"],
                filemtime($custom_css_path),
                "all"
            );
        }
    }

    /**
     * 載入前台腳本
     */
    private function enqueue_frontend_scripts()
    {
        // 預抓取腳本已內嵌在 HTML 中,這裡不需要額外載入
    }

    // =========================================
    // 輔助方法
    // =========================================

    /**
     * 檢查是否為回答頁
     * 
     * @return bool
     */
    private function is_answer_page()
    {
        // 檢查全域標記
        if (!empty($GLOBALS["moe_aiqna_is_answer_page"])) {
            return true;
        }

        // 檢查 query var
        if (get_query_var("moe_ai") === "1") {
            return true;
        }

        // 檢查 URL 路徑 - 動態讀取設定值
        $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
        $pretty_base = moelog_aiqna_array_get($settings, "pretty_base", "qna");
        $request_uri = $_SERVER["REQUEST_URI"] ?? "";
        
        if (strpos($request_uri, "/" . $pretty_base . "/") !== false) {
            return true;
        }
        
        // 保留舊路徑檢查以向下相容
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
     * 檢查文章是否有問題
     * 
     * @param int $post_id 文章 ID
     * @return bool
     */
    private function post_has_questions($post_id)
    {
        $questions = get_post_meta($post_id, MOELOG_AIQNA_META_KEY, true);
        
        if (function_exists("moelog_aiqna_parse_questions")) {
            $parsed = moelog_aiqna_parse_questions($questions);
            return !empty($parsed);
        }
        
        return !empty($questions);
    }

    // =========================================
    // 後台資源
    // =========================================

    /**
     * 載入後台資源
     *
     * @param string $hook 當前頁面鉤子
     */
    public function enqueue_admin_assets($hook)
    {
        // 只在特定頁面載入
        if (!$this->should_load_admin_assets($hook)) {
            return;
        }

        // 載入後台樣式
        $this->enqueue_admin_styles($hook);

        // 載入後台腳本
        $this->enqueue_admin_scripts($hook);
    }

    /**
     * 判斷是否需要載入後台資源
     *
     * @param string $hook 當前頁面鉤子
     * @return bool
     */
    private function should_load_admin_assets($hook)
    {
        // 設定頁面
        if ($hook === "settings_page_moelog_aiqna") {
            return true;
        }

        // 文章編輯頁面
        if (in_array($hook, ["post.php", "post-new.php"], true)) {
            // 檢查文章類型
            global $post_type;
            $allowed_types = apply_filters("moelog_aiqna_post_types", [
                "post",
                "page",
            ]);
            return in_array($post_type, $allowed_types, true);
        }

        return false;
    }

    /**
     * 載入後台樣式
     *
     * @param string $hook 當前頁面鉤子
     */
    private function enqueue_admin_styles($hook)
    {
        // 設定頁面樣式
        if ($hook === "settings_page_moelog_aiqna") {
            wp_enqueue_style(
                "moelog-aiqna-admin",
                MOELOG_AIQNA_URL . "includes/assets/css/admin.css",
                [],
                MOELOG_AIQNA_VERSION,
                "all"
            );
        }
    }

    /**
     * 載入後台腳本
     *
     * @param string $hook 當前頁面鉤子
     */
    private function enqueue_admin_scripts($hook)
    {
        // 設定頁面腳本
        if ($hook === "settings_page_moelog_aiqna") {
            wp_enqueue_script("jquery");
        }

        // 文章編輯頁面
        if (in_array($hook, ["post.php", "post-new.php"], true)) {
            wp_enqueue_script("jquery-ui-sortable");
        }
    }

    /**
     * 取得資源版本號
     *
     * @param string $file 檔案路徑
     * @return string
     */
    public function get_asset_version($file)
    {
        // 開發模式使用檔案修改時間
        if (defined("WP_DEBUG") && WP_DEBUG) {
            $file_path = MOELOG_AIQNA_DIR . $file;
            if (file_exists($file_path)) {
                return filemtime($file_path);
            }
        }

        // 生產模式使用插件版本
        return MOELOG_AIQNA_VERSION;
    }

    // =========================================
    // 資源預載入
    // =========================================

    /**
     * 新增資源預載入提示
     */
    public function add_resource_hints()
    {
        add_action(
            "wp_head",
            function () {
                // 預連線 Google Fonts
                if ($this->is_answer_page()) {
                    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' .
                        "\n";
                    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' .
                        "\n";
                }

                // 預載入關鍵 CSS
                $critical_css = apply_filters(
                    "moelog_aiqna_critical_css_url",
                    ""
                );
                if ($critical_css) {
                    echo '<link rel="preload" href="' .
                        esc_url($critical_css) .
                        '" as="style">' .
                        "\n";
                }
            },
            1
        );
    }

    // =========================================
    // 條件式載入輔助
    // =========================================

    /**
     * 註冊條件式載入規則
     */
    public function register_conditional_assets()
    {
        // 只在有問題的文章載入
        add_filter(
            "moelog_aiqna_load_assets",
            function ($load, $post_id) {
                if ($post_id && $this->post_has_questions($post_id)) {
                    return true;
                }
                return $load;
            },
            10,
            2
        );

        // 允許通過 filter 強制載入/不載入
        add_filter("moelog_aiqna_force_load_assets", "__return_false");
    }

    // =========================================
    // 效能監控
    // =========================================

    /**
     * 記錄資源載入時間(除錯用)
     */
    public function log_asset_loading()
    {
        if (!defined("WP_DEBUG") || !WP_DEBUG) {
            return;
        }

        add_action(
            "wp_footer",
            function () {
                $styles = wp_styles();
                $scripts = wp_scripts();

                $loaded_styles = [];
                $loaded_scripts = [];

                foreach ($styles->done as $handle) {
                    if (strpos($handle, "moelog-aiqna") !== false) {
                        $loaded_styles[] = $handle;
                    }
                }

                foreach ($scripts->done as $handle) {
                    if (strpos($handle, "moelog-aiqna") !== false) {
                        $loaded_scripts[] = $handle;
                    }
                }

                if (!empty($loaded_styles) || !empty($loaded_scripts)) {
                    if (function_exists("moelog_aiqna_log")) {
                        moelog_aiqna_log(
                            "Assets loaded - Styles: " .
                                implode(", ", $loaded_styles) .
                                " | Scripts: " .
                                implode(", ", $loaded_scripts)
                        );
                    }
                }
            },
            9999
        );
    }

    // =========================================
    // 公開 API
    // =========================================

    /**
     * 手動載入樣式(供其他模組使用)
     *
     * @param string $handle 樣式句柄
     * @param bool   $force  是否強制載入
     */
    public function load_style($handle = "moelog-aiqna-style", $force = false)
    {
        if ($force || !wp_style_is($handle, "enqueued")) {
            $this->enqueue_main_styles();
        }
    }

    /**
     * 手動載入腳本(供其他模組使用)
     *
     * @param string $handle 腳本句柄
     * @param bool   $force  是否強制載入
     */
    public function load_script($handle = "jquery", $force = false)
    {
        if ($force || !wp_script_is($handle, "enqueued")) {
            wp_enqueue_script($handle);
        }
    }

    /**
     * 取得已載入的資源列表(除錯用)
     *
     * @return array
     */
    public function get_loaded_assets()
    {
        if (!defined("WP_DEBUG") || !WP_DEBUG) {
            return [];
        }

        $styles = wp_styles();
        $scripts = wp_scripts();

        return [
            "styles" => array_filter($styles->done, function ($handle) {
                return strpos($handle, "moelog-aiqna") !== false;
            }),
            "scripts" => array_filter($scripts->done, function ($handle) {
                return strpos($handle, "moelog-aiqna") !== false;
            }),
        ];
    }
}
