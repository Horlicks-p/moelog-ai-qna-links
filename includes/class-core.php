<?php
/**
 * Moelog AI Q&A Core Class
 *
 * 核心協調者,負責初始化所有模組並協調它們之間的互動
 *
 * @package Moelog_AIQnA
 * @since   1.8.1
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
     * 各模組實例
     */
    private $router;
    private $renderer;
    private $admin;
    private $metabox;
    private $assets;
    private $ai_client;
    private $pregenerate;
    private $prefetch_injected = false;

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
     * 建構函數
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
        add_action(
            "publish_post",
            [$this->pregenerate, "schedule_pregenerate"],
            10,
            2
        );
        add_action(
            "moelog_aiqna_pregenerate",
            [$this->pregenerate, "pregenerate_answer"],
            10,
            2
        );

        // === 清理掛鉤 ===
        add_action("save_post", [$this, "clear_post_cache"], 999, 1);
        add_action("delete_post", [$this, "clear_post_cache"], 10, 1);
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
        if (!empty($GLOBALS["moelog_aiqna_shortcode_used"])) {
            return $content;
        }
        if (has_shortcode($content, "moelog_aiqna")) {
            return $content;
        }
        return $content . $this->get_questions_block(get_the_ID());
    }

    /**
     * Shortcode 處理函數
     *
     * 用法:
     * [moelog_aiqna] - 顯示完整問題清單
     * [moelog_aiqna index="1"] - 只顯示第 1 題
     *
     * @param array $atts Shortcode 屬性
     * @return string
     */
    public function shortcode_questions_block(
        $atts,
        $content = "",
        $tag = "moelog_aiqna"
    ) {
        $post_id = get_the_ID();
        if (!$post_id) {
            return "";
        }

        // 本文已使用短碼 → 立旗標，避免 append_questions_block 再自動附加清單
        $GLOBALS["moelog_aiqna_shortcode_used"] = true;

        $atts = shortcode_atts(
            [
                "index" => "", // 1..8；空白=整份清單
            ],
            $atts,
            $tag
        );

        $idx = intval($atts["index"]);
        if ($idx >= 1 && $idx <= 8) {
            return $this->shortcode_single_question($post_id, $idx);
        }

        // 整份清單
        return $this->get_questions_block($post_id);
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
        $questions = array_filter(
            array_map("trim", preg_split('/\r\n|\n|\r/', $raw_questions))
        );

        if (empty($questions)) {
            return "";
        }

        // 取得標題 (保留作為 title 屬性用,但不顯示)
        $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
        $default_heading = __(
            "Have more questions? Ask the AI below.",
            "moelog-ai-qna"
        );
        $heading =
            isset($settings["list_heading"]) &&
            trim($settings["list_heading"]) !== ""
                ? trim($settings["list_heading"])
                : $default_heading;
        $heading = apply_filters("moelog_aiqna_list_heading", $heading);

        // 生成問題列表
        $items = "";
        foreach ($questions as $idx => $question) {
            $url = $this->build_answer_url($post_id, $question);
            $lang = $langs[$idx] ?? "auto";

            $items .= sprintf(
                '<li><a class="moe-aiqna-link" target="_blank" rel="noopener" href="%s" data-lang="%s"> %s</a></li>',
                esc_url($url),
                esc_attr($lang),
                esc_html($question)
            );
        }

        // 預抓取腳本
        $prefetch_js = $this->get_prefetch_script();

        // 修正版本: 不顯示 h3 標題,只保留作為 title 屬性
        return sprintf(
            '<p class="ask_chatgpt" title="%s"></p><div class="moe-aiqna-block"><ul>%s</ul></div>%s',
            esc_attr($heading),
            $items,
            $prefetch_js
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

        $questions = array_values(
            array_filter(array_map("trim", preg_split('/\r\n|\n|\r/', $raw)))
        );
        if ($index < 1 || $index > count($questions)) {
            return "";
        }

        $q = $questions[$index - 1];

        // 讀語言（如有）
        $langs = get_post_meta($post_id, MOELOG_AIQNA_META_LANG_KEY, true);
        $langs = is_array($langs) ? $langs : [];
        $lang = $langs[$index - 1] ?? "auto";

        // 產生答案頁 URL —— 換成你的方法：
        // 若有 Router：
        // $url = $this->router->build_url($post_id, $q);
        // 若你仍保有舊方法：
        $url = method_exists($this, "build_answer_url")
            ? $this->build_answer_url($post_id, $q)
            : home_url("/"); // 最差情況的後備

        // 輸出單題連結（沿用 .moe-aiqna-link class，樣式/預抓取一致）
        $html = sprintf(
            '<a class="moe-aiqna-link" target="_blank" rel="noopener" href="%s" data-lang="%s">%s</a>',
            esc_url($url),
            esc_attr($lang),
            esc_html($q)
        );

        // 確保頁面上至少注入一次預抓取腳本（避免只放單題時未載入）
        $html .= $this->ensure_prefetch_script_once();

        return $html;
    }

    private function ensure_prefetch_script_once()
    {
        if ($this->prefetch_injected) {
            return "";
        }
        $this->prefetch_injected = true;

        return <<<HTML
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
    /**
     * 取得預抓取 JavaScript
     *
     * @return string
     */
    private function get_prefetch_script()
    {
        static $injected = false;

        if ($injected) {
            return "";
        }

        $injected = true;

        return <<<HTML
<script>
(function(){
  if (navigator.connection && navigator.connection.saveData) return;

  function prefetch(url){
    try{
      var u = new URL(url);
      u.searchParams.set('pf','1');
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
    a.addEventListener(enterEvt, function(){ 
      timer = setTimeout(function(){ prefetch(a.href); }, 100); 
    });
    a.addEventListener(leaveEvt, function(){ 
      if (timer) clearTimeout(timer); 
    });
    a.__moeBound = true;
  }

  // 綁定現有連結
  document.querySelectorAll('.moe-aiqna-link').forEach(bind);

  // 監聽動態新增的連結
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
     * 清除文章的所有快取
     *
     * @param int $post_id 文章 ID
     */
    public function clear_post_cache($post_id)
    {
        // 忽略自動儲存和修訂版本
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // 刪除該文章的所有靜態快取
        Moelog_AIQnA_Cache::delete($post_id);

        // 清除相關的 transient
        global $wpdb;
        $pattern = "%moe_aiqna_%" . $post_id . "%";
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                    OR option_name LIKE %s",
                "_transient_" . $pattern,
                "_transient_timeout_" . $pattern
            )
        );

        if (defined("WP_DEBUG") && WP_DEBUG) {
            error_log(
                sprintf("[Moelog AIQnA] Cleared cache for post %d", $post_id)
            );
        }
    }

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
