<?php
/**
 * Moelog AI Q&A Core Class
 *
 * 核心協調者,負責初始化所有模組並協調它們之間的互動
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Core
{
    /**
     * 插件版本
     */
    const VERSION = MOELOG_AIQNA_VERSION;

    /**
     * 單例實例
     * @var Moelog_AIQnA_Core|null
     */
    private static $instance = null;

    /**
     * 各模組實例
     * @var Moelog_AIQnA_Router
     */
    private $router;

    /**
     * @var Moelog_AIQnA_Renderer
     */
    private $renderer;

    /**
     * @var Moelog_AIQnA_Admin|null
     */
    private $admin;

    /**
     * @var Moelog_AIQnA_Metabox|null
     */
    private $metabox;

    /**
     * @var Moelog_AIQnA_Assets
     */
    private $assets;

    /**
     * @var Moelog_AIQnA_AI_Client
     */
    private $ai_client;

    /**
     * @var Moelog_AIQnA_Pregenerate
     */
    private $pregenerate;

    /**
     * 是否已注入預抓取腳本
     * @var bool
     */
    private $prefetch_injected = false;

    /**
     * 是否需要預抓取腳本
     * @var bool
     */
    private $prefetch_needed = false;

    /**
     * HMAC 密鑰
     * @var string
     */
    private $secret = "";

    /**
     * 是否已初始化
     * @var bool
     */
    private $initialized = false;

    /**
     * 取得單例實例
     *
     * @return Moelog_AIQnA_Core
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 建構函數
     * 
     * ✅ 優化: 雖然保留 public 以保持向下相容，但建議使用 get_instance()
     */
    public function __construct()
    {
        // 初始化 HMAC secret
        $this->init_secret();

        // 載入依賴
        $this->load_dependencies();

        // 註冊掛鉤
        $this->register_hooks();

        $this->initialized = true;
    }

    /**
     * 初始化 HMAC 密鑰
     *
     * @return void
     */
    private function init_secret()
    {
        $secret = get_option(MOELOG_AIQNA_SECRET_KEY, "");

        if (empty($secret)) {
            try {
                $secret = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $secret = hash("sha256", microtime(true) . wp_salt() . rand());
            }
            add_option(MOELOG_AIQNA_SECRET_KEY, $secret, "", false);
        }

        $this->secret = (string) $secret;
    }

    /**
     * 載入所有依賴模組
     *
     * @return void
     */
    private function load_dependencies()
    {
        // 路由模組
        $this->router = new Moelog_AIQnA_Router($this->secret);

        // AI 客戶端
        $this->ai_client = new Moelog_AIQnA_AI_Client();

        // 渲染模組
        $this->renderer = new Moelog_AIQnA_Renderer(
            $this->router,
            $this->ai_client,
            $this->secret
        );

        // 管理後台
        if (is_admin()) {
            $this->admin = new Moelog_AIQnA_Admin();
            $this->metabox = new Moelog_AIQnA_Metabox();
        }

        // 資源載入
        $this->assets = new Moelog_AIQnA_Assets();

        // 預生成模組
        $this->pregenerate = new Moelog_AIQnA_Pregenerate(
            $this->ai_client,
            $this->renderer
        );
    }

    /**
     * 註冊所有 WordPress 掛鉤
     *
     * @return void
     */
    private function register_hooks()
    {
        // === 路由相關 ===
        add_action("init", [$this->router, "register_routes"], 10);
        add_action(
            "template_redirect",
            [$this->renderer, "render_answer_page"],
            10
        );

        // === 前台內容 ===
        add_filter("the_content", [$this, "append_questions_block"], 20);
        add_shortcode("moelog_aiqna", [$this, "shortcode_questions_block"]);

        // === 資源載入 ===
        add_action(
            "wp_enqueue_scripts",
            [$this->assets, "enqueue_frontend_assets"],
            10
        );

        // === 預抓取腳本注入 ===
        add_action("wp_footer", [$this, "inject_prefetch_script"], 10);

        // === 管理後台(僅在後台載入) ===
        if (is_admin()) {
            add_action("admin_menu", [$this->admin, "add_settings_page"], 10);
            add_action("admin_init", [$this->admin, "register_settings"], 10);
            add_action("admin_notices", [$this->admin, "show_notices"], 10);
            add_action(
                "admin_enqueue_scripts",
                [$this->assets, "enqueue_admin_assets"],
                10
            );

            // Meta Box
            add_action("add_meta_boxes", [$this->metabox, "add_metabox"], 10);
            add_action("save_post", [$this->metabox, "save_metabox"], 10, 2);
        }
        // === 預生成任務 ===
        add_action("save_post", [$this, "handle_save_post_pregenerate"], 10, 2);
        add_action(
            "moelog_aiqna_pregenerate",
            [$this->pregenerate, "pregenerate_answer"],
            10,
            2
        );

        // === 清理掛鉤 ===
        add_action("save_post", [$this, "clear_post_cache"], 999, 1);
        add_action("delete_post", [$this, "clear_post_cache"], 10, 1);
        add_action(
            "moelog_aiqna_clear_cache_async",
            [$this, "clear_cache_async_handler"],
            10,
            1
        );
    }
    /**
     * 清除文章的所有快取 (修改為排程非同步執行)
     *
     * @param int $post_id 文章 ID
     * @return void
     */
    public function clear_post_cache($post_id)
    {
        // ✅ 清除 Post 物件快取和 Meta 快取
        Moelog_AIQnA_Post_Cache::clear($post_id);
        Moelog_AIQnA_Meta_Cache::clear($post_id);

        // === delete_post hook 觸發時, 保持同步刪除 ===
        if (current_action() === "delete_post") {
            $this->perform_cache_clearing($post_id);
            return;
        }

        // === save_post hook 觸發時, 執行非同步邏輯 ===

        // 忽略自動儲存和修訂版本
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // 檢查 skip_clear 標記
        $skip_clear_key = "moelog_aiqna_skip_clear_" . $post_id;
        if (get_transient($skip_clear_key)) {
            delete_transient($skip_clear_key); // 用完即刪
            Moelog_AIQnA_Debug::logf("Skipped cache clearing for post %d (no changes)", $post_id);
            return;
        }

        // ✅ 核心修改: 排程一個 5 秒後執行的非同步任務來清除快取
        // 避免重複排程
        if (!wp_next_scheduled("moelog_aiqna_clear_cache_async", [$post_id])) {
            wp_schedule_single_event(
                time() + 5,
                "moelog_aiqna_clear_cache_async",
                [$post_id]
            );
            Moelog_AIQnA_Debug::logf("Scheduled ASYNC cache clearing for post %d", $post_id);
        }
    }
    /**
     * ✅ 新增: 處理非同步快取清除的函式
     *
     * @param int $post_id 文章 ID
     * @return void
     */
    public function clear_cache_async_handler($post_id)
    {
        $this->perform_cache_clearing($post_id);
    }

    /**
     * ✅ 新增: 實際執行快取清除的私有方法
     *
     * @param int $post_id 文章 ID
     * @return void
     */
    private function perform_cache_clearing($post_id)
    {
        // 刪除靜態快取
        $static_deleted = Moelog_AIQnA_Cache::delete($post_id);

        // 清除相關 Transient
        global $wpdb;
        $pattern = "%moe_aiqna_%" . $post_id . "%";
        $transient_deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                    OR option_name LIKE %s",
                "_transient_" . $pattern,
                "_transient_timeout_" . $pattern
            )
        );

        Moelog_AIQnA_Debug::logf(
            "Performed cache clearing for post %d. Static deleted: %s, Transients deleted: %d",
            $post_id,
            $static_deleted ? "Yes" : "No",
            $transient_deleted
        );
    }

    /**
     * 處理文章儲存時的預生成邏輯
     *
     * @param int     $post_id 文章 ID
     * @param WP_Post $post    文章物件
     * @return void
     */
    public function handle_save_post_pregenerate($post_id, $post)
    {
        // 1) 基本防呆
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }
        if (!($post instanceof WP_Post)) {
            return;
        }

        // 2) 只處理 post/page(可用 filter 擴充)
        $allowed_types = apply_filters("moelog_aiqna_post_types", [
            "post",
            "page",
        ]);
        if (!in_array($post->post_type, $allowed_types, true)) {
            return;
        }

        // 3) 只在已發佈時才預生成
        if ($post->post_status !== "publish") {
            return;
        }

        // ✅ 快取預熱：預載入 Post 物件到快取
        Moelog_AIQnA_Post_Cache::preload([$post_id]);

        // 4) 讀取題目清單(若無,直接跳過) - 使用快取版本
        $questions = Moelog_AIQnA_Meta_Cache::get($post_id, MOELOG_AIQNA_META_KEY, true);
        if (empty($questions)) {
            return;
        }

        // 5) 內容/題目雜湊比對(只在有變動時才繼續)
        $q_flat = is_array($questions)
            ? implode("\n", array_map("strval", $questions))
            : (string) $questions;
        $new_hash = md5($post->post_content . "|" . $q_flat);
        $old_hash = Moelog_AIQnA_Meta_Cache::get($post_id, "_moelog_aiqna_content_hash", true);

        if ($old_hash === $new_hash) {
            Moelog_AIQnA_Debug::logf("SKIP pregenerate: unchanged. post=%d", $post_id);
            // 設定標記,告訴 clear_post_cache 不要清除
            set_transient("moelog_aiqna_skip_clear_" . $post_id, 1, 10);
            return;
        }

        // 6) 更新 hash 後再排程
        update_post_meta($post_id, "_moelog_aiqna_content_hash", $new_hash);
        
        // ✅ 清除相關快取
        Moelog_AIQnA_Meta_Cache::clear($post_id, "_moelog_aiqna_content_hash");

        // 7) 交由預生成邏輯排程
        if (
            isset($this->pregenerate) &&
            method_exists($this->pregenerate, "schedule_pregenerate")
        ) {
            $this->pregenerate->schedule_pregenerate($post_id, $post);
            Moelog_AIQnA_Debug::logf("QUEUED pregenerate: post=%d", $post_id);
        }
    }
    // =========================================
    // 公開 API 方法
    // =========================================

    /**
     * 建立回答頁 URL
     *
     * @param int    $post_id  文章 ID
     * @param string $question 問題文字
     * @return string
     */
    public function build_answer_url($post_id, $question)
    {
        return $this->router->build_url($post_id, $question);
    }

    /**
     * 取得 Router 實例
     *
     * @return Moelog_AIQnA_Router
     */
    public function get_router()
    {
        return $this->router;
    }

    /**
     * 取得 AI Client 實例
     *
     * @return Moelog_AIQnA_AI_Client
     */
    public function get_ai_client()
    {
        return $this->ai_client;
    }

    /**
     * 取得 Renderer 實例
     *
     * @return Moelog_AIQnA_Renderer
     */
    public function get_renderer()
    {
        return $this->renderer;
    }

    /**
     * 取得 Pregenerate 實例
     *
     * @return Moelog_AIQnA_Pregenerate
     */
    public function get_pregenerate()
    {
        return $this->pregenerate;
    }

    /**
     * 取得 Admin 實例
     *
     * @return Moelog_AIQnA_Admin|null
     */
    public function get_admin()
    {
        return $this->admin;
    }

    /**
     * 取得 Metabox 實例
     *
     * @return Moelog_AIQnA_Metabox|null
     */
    public function get_metabox()
    {
        return $this->metabox;
    }

    /**
     * 取得 Assets 實例
     *
     * @return Moelog_AIQnA_Assets
     */
    public function get_assets()
    {
        return $this->assets;
    }

    /**
     * 取得 HMAC 密鑰
     *
     * @return string
     */
    public function get_secret()
    {
        return $this->secret;
    }

    /**
     * 檢查是否已初始化
     *
     * @return bool
     */
    public function is_initialized()
    {
        return $this->initialized;
    }

    // =========================================
    // 前台內容處理
    // =========================================

    /**
     * 在文章底部附加問題清單
     *
     * @param string $content 文章內容
     * @return string
     */
    public function append_questions_block($content)
    {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        // 移除短碼檢查：現在短碼只顯示單一問題，與完整清單不衝突
        // 完整清單會自動附加在文章底部
        $block = $this->get_questions_block(get_the_ID());
        if (!empty($block)) {
            // 標記需要注入預抓取腳本
            $this->prefetch_needed = true;
        }
        return $content . $block;
    }

    /**
     * Shortcode 處理函數
     *
     * 用法:
     * [moelog_aiqna index="1"] - 顯示第 1 題
     * [moelog_aiqna index="2"] - 顯示第 2 題
     * ...以此類推 (index 範圍: 1-8)
     *
     * @param array $atts Shortcode 屬性
     * @return string
     */
    public function shortcode_questions_block(
        $atts,
        $content = "",
        $tag = "moelog_aiqna"
    ) {
        // 解析短碼屬性
        $atts = shortcode_atts(
            [
                "index" => "", // 必填：1..8
                "post_id" => "", // 可選：指定文章 ID
            ],
            $atts,
            $tag
        );

        // 必須提供 index 屬性
        $idx = intval($atts["index"]);
        if ($idx < 1 || $idx > 8) {
            return "";
        }

        // 獲取 post_id：優先使用屬性中的 post_id，否則嘗試從當前上下文獲取
        $post_id = 0;
        
        if (!empty($atts["post_id"])) {
            $post_id = intval($atts["post_id"]);
        } else {
            // 嘗試多種方式獲取 post_id
            $post_id = get_the_ID();
            
            // 如果 get_the_ID() 失敗，嘗試從全域 $post 獲取
            if (!$post_id) {
                global $post;
                if ($post && isset($post->ID)) {
                    $post_id = $post->ID;
                }
            }
        }
        
        if (!$post_id) {
            return "";
        }

        // 記錄已通過短碼顯示的問題 index，避免在完整清單中重複顯示
        if (!isset($GLOBALS["moelog_aiqna_shortcode_indexes"])) {
            $GLOBALS["moelog_aiqna_shortcode_indexes"] = [];
        }
        $GLOBALS["moelog_aiqna_shortcode_indexes"][$post_id][] = $idx;

        // 短碼只顯示單一問題，不影響自動附加的完整清單
        return $this->shortcode_single_question($post_id, $idx);
    }

    /**
     * 取得完整問題清單 HTML
     *
     * @param int $post_id 文章 ID
     * @return string
     */
    private function get_questions_block($post_id)
    {
        $raw_questions = get_post_meta($post_id, MOELOG_AIQNA_META_KEY, true);
        $langs = get_post_meta($post_id, MOELOG_AIQNA_META_LANG_KEY, true);
        $langs = is_array($langs) ? $langs : [];

        if (!$raw_questions) {
            return "";
        }

        // 解析問題
        $questions = moelog_aiqna_parse_questions($raw_questions);

        if (empty($questions)) {
            return "";
        }

        // 取得標題 (保留作為 title 屬性用,但不顯示)
        $heading = Moelog_AIQnA_Settings::get_list_heading();
        $heading = apply_filters("moelog_aiqna_list_heading", $heading);

        // 取得已通過短碼顯示的問題 index（1-based）
        $excluded_indexes = [];
        if (isset($GLOBALS["moelog_aiqna_shortcode_indexes"][$post_id])) {
            $excluded_indexes = $GLOBALS["moelog_aiqna_shortcode_indexes"][$post_id];
        }

        // 生成問題列表（排除已通過短碼顯示的問題）
        $items = "";
        foreach ($questions as $idx => $question) {
            // $idx 是 0-based，轉換為 1-based 來比對
            $question_index = $idx + 1;
            
            // 如果這個問題已經通過短碼顯示，則跳過
            if (in_array($question_index, $excluded_indexes, true)) {
                continue;
            }

            $url = $this->build_answer_url($post_id, $question);
            $lang = $langs[$idx] ?? "auto";

            $items .= sprintf(
                '<li><a class="moe-aiqna-link" target="_blank" rel="noopener" href="%s" data-lang="%s"> %s</a></li>',
                esc_url($url),
                esc_attr($lang),
                esc_html($question)
            );
        }

        // 如果所有問題都通過短碼顯示了，返回空字串
        if (empty($items)) {
            return "";
        }

        // 標記需要注入預抓取腳本
        $this->prefetch_needed = true;

        // 修正版本: 不顯示 h3 標題,只保留作為 title 屬性
        return sprintf(
            '<p class="ask_chatgpt" title="%s"></p><div class="moe-aiqna-block"><ul>%s</ul></div>',
            esc_attr($heading),
            $items
        );
    }
    /**
     * 取得單一問題連結
     *
     * @param int $post_id 文章 ID
     * @param int $index   問題索引(1-8)
     * @return string
     */
    private function shortcode_single_question($post_id, $index)
    {
        $raw = get_post_meta($post_id, MOELOG_AIQNA_META_KEY, true);
        if (!$raw) {
            return "";
        }

        $questions = moelog_aiqna_parse_questions($raw);
        if ($index < 1 || $index > count($questions)) {
            return "";
        }

        $q = $questions[$index - 1];

        // 讀語言（如有）
        $langs = get_post_meta($post_id, MOELOG_AIQNA_META_LANG_KEY, true);
        $langs = is_array($langs) ? $langs : [];
        $lang = $langs[$index - 1] ?? "auto";

        $url = $this->build_answer_url($post_id, $q);

        // 標記需要注入預抓取腳本
        $this->prefetch_needed = true;

        // 輸出單題連結（沿用 .moe-aiqna-link class，樣式/預抓取一致）
        return sprintf(
            '<a class="moe-aiqna-link" target="_blank" rel="noopener" href="%s" data-lang="%s">%s</a>',
            esc_url($url),
            esc_attr($lang),
            esc_html($q)
        );
    }

    /**
     * 在 wp_footer 中注入預抓取腳本
     * 避免在短碼中直接輸出 <script> 標籤導致內容被截斷
     *
     * @return void
     */
    public function inject_prefetch_script()
    {
        // 只有在需要時才注入（有使用短碼或自動附加清單）
        if (!$this->prefetch_needed) {
            return;
        }

        // 確保只注入一次
        if ($this->prefetch_injected) {
            return;
        }
        $this->prefetch_injected = true;

        echo <<<HTML
<script>
(function(){
  if (navigator.connection && navigator.connection.saveData) return;

  function prefetch(url){
    try{
      var u = new URL(url);
      u.searchParams.set('pf','1'); // 204 預取端點
      var l = document.createElement('link');
      l.rel = 'prefetch';
      l.as  = 'document';
      l.href = u.toString();
      document.head.appendChild(l);
    }catch(e){}
  }

  var enterEvt = ('PointerEvent' in window) ? 'pointerenter' : 'mouseenter';
  var leaveEvt = ('PointerEvent' in window) ? 'pointerleave'  : 'mouseleave';
  var timer;

  function bind(a){
    if (a.__moeBound) return;
    a.addEventListener(enterEvt, function(){ timer = setTimeout(function(){ prefetch(a.href); }, 100); });
    a.addEventListener(leaveEvt,  function(){ if (timer) clearTimeout(timer); });
    a.__moeBound = true;
  }

  // 綁定現有
  document.querySelectorAll('.moe-aiqna-link').forEach(bind);

  // 動態加入時也嘗試綁定
  var mo = new MutationObserver(function(){
    document.querySelectorAll('.moe-aiqna-link').forEach(bind);
  });
  mo.observe(document.documentElement, {subtree:true, childList:true});
})();
</script>
HTML;
    }

    // =========================================
    // 快取管理
    // =========================================
    /**
     * 清除所有快取
     *
     * @return array ['transient' => int, 'static' => int]
     */
    public function clear_all_cache()
    {
        global $wpdb;

        // 清除 transient
        $transient_count = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_moe_aiqna_%' 
                OR option_name LIKE '_transient_timeout_moe_aiqna_%'"
        );

        // 清除靜態檔案
        $static_count = Moelog_AIQnA_Cache::clear_all();

        return [
            "transient" => (int) $transient_count,
            "static" => (int) $static_count,
        ];
    }

    // =========================================
    // 靜態方法(向後相容)
    // =========================================

    /**
     * 啟用時執行
     */
    public static function activate()
    {
        // 標記需要刷新 rewrite rules
        update_option("moe_aiqna_needs_flush", "1", false);
    }

    /**
     * 停用時執行
     */
    public static function deactivate()
    {
        flush_rewrite_rules(false);
    }

    /**
     * 卸載時執行
     */
    public static function uninstall()
    {
        // 由主檔的 moelog_aiqna_uninstall() 函數處理
    }
}
