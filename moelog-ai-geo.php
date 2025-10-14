<?php
/**
 * Moelog AI Q&A GEO Module
 * Generative Engine Optimization Enhancement
 *
 * 讓 AI 問答內容更容易被 Google SGE、Bing Copilot 等生成式搜尋引擎引用
 *
 * @package Moelog_AIQnA
 * @since 1.6.2
 * @author Horlicks
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_GEO
{
    /** @var Moelog_AIQnA 主插件實例 */
    private $main_plugin = null;

public function __construct()
{
    // 取得主插件實例
    $this->main_plugin = $GLOBALS["moelog_aiqna_instance"] ?? null;
    if (!$this->main_plugin) {
        add_action("admin_notices", function () {
            echo '<div class="notice notice-error"><p>Moelog AI Q&A GEO 模組需要主插件支援。</p></div>';
        });
        return;
    }

    // 設定頁面
    add_action("admin_init", [$this, "register_settings"]);

    // 回答頁 head 區塊：由主外掛在 render_answer_html() 內呼叫
    add_action("moelog_aiqna_answer_head", [$this, "output_head"], 10, 4);

    // 回答頁 HTTP 標頭
    add_action("send_headers", [$this, "answer_headers"], 20);

    // 放行主流搜尋引擎 UA（避免被主外掛封鎖）
    add_filter("moelog_aiqna_blocked_bots", [$this, "allow_major_bots"]);

    // ✅ 修改:直接註冊 filter,不要包在 plugins_loaded 裡
    add_filter("moelog_aiqna_answer_robots", [$this, "override_robots_meta"]);

    // AI Sitemap
    add_action("init", [$this, "register_sitemap"]);
    add_action("template_redirect", [$this, "render_sitemap"]);

    // 發文後自動 Ping 搜尋引擎
    add_action("save_post", [$this, "maybe_ping_on_publish"], 999, 2);
    add_action("moelog_aiqna_ping_search_engines", [
        $this,
        "ping_search_engines",
    ]);

    // 後台提醒
    add_action("admin_notices", [$this, "admin_notices"]);
}

// ✅ 新增方法
public function override_robots_meta($default)
{
    if (!get_option("moelog_aiqna_geo_mode")) {
        return $default;
    }
    
    return "index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1";
}

    // =========================================================
    // 設定頁面
    // =========================================================

    public function register_settings()
    {
        register_setting("moelog_aiqna_settings", "moelog_aiqna_geo_mode", [
            "type" => "boolean",
            "default" => false,
            "sanitize_callback" => [$this, "sanitize_geo_mode"],
        ]);

        add_settings_section(
            "moelog_aiqna_geo_section",
            "🚀 GEO（Generative Engine Optimization）",
            [$this, "geo_section_callback"],
            "moelog_aiqna_settings"
        );

        add_settings_field(
            "moelog_aiqna_geo_mode",
            "啟用 GEO 模式",
            [$this, "geo_mode_field_callback"],
            "moelog_aiqna_settings",
            "moelog_aiqna_geo_section"
        );
    }

    public function geo_section_callback()
    {
        echo '<p style="color:#666;line-height:1.6">';
        echo "讓 AI 問答內容更容易被 Google SGE、Bing Copilot、Perplexity 等生成式搜尋引擎引用與展示。<br>";
        echo "啟用後將加入結構化資料標記、優化 SEO 設定，並提供專用的 AI 問答 Sitemap。";
        echo "</p>";
    }

    public function geo_mode_field_callback()
    {
        $enabled = get_option("moelog_aiqna_geo_mode", false);
        $sitemap_url = home_url("ai-qa-sitemap.xml");
        ?>
        <label style="display:block;margin-bottom:15px;">
            <input type="checkbox" name="moelog_aiqna_geo_mode" value="1" <?php checked(
                $enabled,
                true
            ); ?>>
            <strong>啟用結構化資料、SEO 優化與 AI Sitemap</strong>
        </label>

        <div style="background:#f9f9f9;border-left:4px solid #2271b1;padding:12px 15px;margin-top:10px;">
            <p style="margin:0 0 8px 0;"><strong>啟用後的功能：</strong></p>
            <ul style="margin:5px 0;padding-left:20px;line-height:1.8;">
                <li>✓ 加入 <code>Schema.org</code> QAPage 結構化資料標記</li>
                <li>✓ 加入完整的 Open Graph 與 Meta 標籤</li>
                <li>✓ 移除 <code>noindex</code> 限制，允許搜尋引擎索引</li>
                <li>✓ 優化快取策略（24 小時公開快取）</li>
                <li>✓ 提供麵包屑導航結構化資料</li>
                <li>✓ 提供 AI 問答專用 Sitemap</li>
                <li>✓ 自動通知 Google 和 Bing 更新 Sitemap</li>
            </ul>
            <?php if ($enabled): ?>
            <p style="margin:10px 0 5px;border-top:1px solid #ddd;padding-top:10px;">
                <strong>📍 AI 問答 Sitemap：</strong><br>
                <a href="<?php echo esc_url(
                    $sitemap_url
                ); ?>" target="_blank" style="word-break:break-all;">
                    <?php echo esc_html($sitemap_url); ?>
                </a>
            </p>
            <p style="margin:8px 0 0;color:#666;font-size:0.95em;">
                💡 建議提交至 Google Search Console 與 Bing Webmaster Tools
            </p>
            <?php endif; ?>
        </div>

        <p class="description" style="margin-top:12px;color:#d63638;">
            ⚠️ 啟用／停用後，請到
            <a href="<?php echo admin_url(
                "options-permalink.php"
            ); ?>">設定 → 永久連結</a>
            點「儲存變更」刷新規則。
        </p>
        <?php
    }

    public function sanitize_geo_mode($input)
    {
        $old = get_option("moelog_aiqna_geo_mode", false);
        $new = !empty($input);

        if ($old !== $new) {
            flush_rewrite_rules();
            if ($new) {
                wp_schedule_single_event(
                    time() + 30,
                    "moelog_aiqna_ping_search_engines"
                );
            }
        }

        return $new ? 1 : 0;
    }

    // =========================================================
    // 回答頁 <head> 輸出
    // =========================================================

    public function output_head($answer_url, $post_id, $question, $answer)
    {
        if (!get_option("moelog_aiqna_geo_mode")) {
            return;
        }

        // 標記：目前為回答頁，供 header/快取策略使用
        $GLOBALS["moe_aiqna_is_answer_page"] = true;

        // 輸出各種標記（注意：robots 由主外掛 filter 控制，不在此輸出）
        echo $this->meta_tags($answer_url, $post_id, $question, $answer);
        echo $this->schema_qa($answer_url, $post_id, $question, $answer);
        echo $this->schema_breadcrumb($post_id, $answer_url);
    }

    /**
     * Meta 標籤(SEO 與 Open Graph)
     * 注意:<meta name="robots"> 統一由主外掛的 moelog_aiqna_answer_robots filter 控制,不在此輸出
     */
    private function meta_tags($answer_url, $post_id, $question, $answer)
    {
        $desc = esc_attr(wp_trim_words(wp_strip_all_tags($answer), 30));
        $title = esc_attr($question);
        $site = esc_attr(get_bloginfo("name"));

        // 取一張圖:先走 filter,自訂不到再抓精選圖,最後退回站台圖示或空
        $image = apply_filters(
            "moelog_aiqna_answer_image",
            "",
            $post_id,
            $question
        );
        if (!$image && has_post_thumbnail($post_id)) {
            $src = wp_get_attachment_image_src(
                get_post_thumbnail_id($post_id),
                "full"
            );
            $image = $src ? $src[0] : "";
        }
        if (!$image) {
            $site_icon = get_site_icon_url(512);
            if ($site_icon) {
                $image = $site_icon;
            }
        }

        ob_start();
        ?>
<!-- Moelog AI Q&A GEO: Meta Tags -->
<meta name="description" content="<?php echo $desc; ?>">
<meta property="og:type" content="article">
<meta property="og:title" content="<?php echo $title; ?>">
<meta property="og:description" content="<?php echo $desc; ?>">
<meta property="og:url" content="<?php echo esc_url($answer_url); ?>">
<meta property="og:site_name" content="<?php echo $site; ?>">
<?php if ($image): ?>
<meta property="og:image" content="<?php echo esc_url($image); ?>">
<?php endif; ?>
<meta property="article:published_time" content="<?php echo esc_attr(
    current_time("c")
); ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo $title; ?>">
<meta name="twitter:description" content="<?php echo $desc; ?>">
<?php if ($image): ?>
<meta name="twitter:image" content="<?php echo esc_url($image); ?>">
<?php endif; ?>
<link rel="canonical" href="<?php echo esc_url($answer_url); ?>">
    <?php return ob_get_clean();
    }

    /**
     * Schema.org QAPage 結構化資料
     */
    private function schema_qa($answer_url, $post_id, $question, $answer)
    {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "QAPage",
            "url" => $answer_url,
            "name" => wp_strip_all_tags($question),
            "mainEntity" => [
                "@type" => "Question",
                "name" => wp_strip_all_tags($question),
                "text" => wp_strip_all_tags($question),
                "answerCount" => 1,
                "dateCreated" => current_time("c"),
                "author" => [
                    "@type" => "Organization",
                    "name" => get_bloginfo("name"),
                    "url" => home_url(),
                ],
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => wp_strip_all_tags($answer),
                    "dateCreated" => current_time("c"),
                    "author" => [
                        "@type" => "Organization",
                        "name" => get_bloginfo("name"),
                    ],
                    "url" => $answer_url,
                ],
            ],
            "about" => [
                "@type" => "Article",
                "headline" => get_the_title($post_id),
                "url" => get_permalink($post_id),
                "datePublished" => get_the_date("c", $post_id),
                "dateModified" => get_the_modified_date("c", $post_id),
            ],
        ];

        return "\n<!-- Moelog AI Q&A GEO: QAPage Schema -->\n" .
            '<script type="application/ld+json">' .
            wp_json_encode(
                $schema,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRETTY_PRINT
            ) .
            "</script>\n";
    }

    /**
     * Schema.org BreadcrumbList 結構化資料
     */
    private function schema_breadcrumb($post_id, $answer_url = "")
    {
        $list = [
            "@context" => "https://schema.org",
            "@type" => "BreadcrumbList",
            "itemListElement" => [
                [
                    "@type" => "ListItem",
                    "position" => 1,
                    "name" => get_bloginfo("name"),
                    "item" => home_url(),
                ],
                [
                    "@type" => "ListItem",
                    "position" => 2,
                    "name" => get_the_title($post_id),
                    "item" => get_permalink($post_id),
                ],
                [
                    "@type" => "ListItem",
                    "position" => 3,
                    "name" => "AI 解答",
                    "item" => $answer_url,
                ],
            ],
        ];

        return "\n<!-- Moelog AI Q&A GEO: Breadcrumb Schema -->\n" .
            '<script type="application/ld+json">' .
            wp_json_encode(
                $list,
                JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRETTY_PRINT
            ) .
            "</script>\n";
    }

    // =========================================================
    // 回答頁 HTTP Headers
    // =========================================================

    /**
     * 回答頁 HTTP Headers
     */
    public function answer_headers()
    {
        if (headers_sent()) {
            return;
        }

        if (!get_option("moelog_aiqna_geo_mode")) {
            return;
        }

        // ✅ 改用 URL 判斷,不依賴全域變數
        $req_uri = $_SERVER["REQUEST_URI"] ?? "";
        $is_answer =
            isset($_GET["moe_ai"]) ||
            strpos($req_uri, "/qna/") !== false ||
            strpos($req_uri, "/ai-answer/") !== false;

        if (!$is_answer) {
            return;
        }

        // 以下保持不變
        header_remove("Cache-Control");
        header(
            "Cache-Control: public, max-age=0, s-maxage=86400, stale-while-revalidate=604800"
        );
        header("X-Robots-Tag: index, follow");
        header("Vary: Accept-Encoding, User-Agent");
    }

    /**
     * 放行主流搜尋引擎 UA，避免被主外掛的封鎖名單擋掉
     */
    public function allow_major_bots(array $blocked): array
    {
        if (!get_option("moelog_aiqna_geo_mode")) {
            return $blocked;
        }

        // 需要放行的主流搜尋引擎 UA（小寫）
        $allow = ["googlebot", "bingbot", "duckduckbot", "yandexbot"];

        $out = [];
        foreach ($blocked as $pattern) {
            $keep = true;
            foreach ($allow as $ua) {
                if (stripos($pattern, $ua) !== false) {
                    $keep = false;
                    break;
                }
            }
            if ($keep) {
                $out[] = $pattern;
            }
        }

        return $out;
    }

    // =========================================================
    // AI Sitemap
    // =========================================================

    // 函數 1: 註冊 Sitemap URL 規則
    public function register_sitemap()
    {
        if (!get_option("moelog_aiqna_geo_mode")) {
            return;
        }
        add_rewrite_rule(
            '^ai-qa-sitemap\.xml$',
            "index.php?moe_aiqna_sitemap=1",
            "top"
        );
        add_rewrite_tag("%moe_aiqna_sitemap%", "1");
    }

    // 函數 2: 輸出 Sitemap XML 內容
    public function render_sitemap()
    {
        if (!get_option("moelog_aiqna_geo_mode")) {
            return;
        }

        if (get_query_var("moe_aiqna_sitemap") !== "1") {
            return;
        }

        nocache_headers();
        header("Content-Type: application/xml; charset=UTF-8");

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?xml-stylesheet type="text/xsl" href="' .
            esc_url(includes_url("css/sitemap.xsl")) .
            "\"?>\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' .
            "\n";

        $posts = get_posts([
            "numberposts" => -1,
            "post_type" => "post",
            "post_status" => "publish",
            "fields" => "ids",
        ]);

        $url_count = 0;

        foreach ($posts as $pid) {
            $questions_raw = get_post_meta(
                $pid,
                "_moelog_aiqna_questions",
                true
            );
            if (empty($questions_raw)) {
                continue;
            }

            $questions = array_filter(
                array_map("trim", preg_split('/\r\n|\r|\n/', $questions_raw))
            );
            if (empty($questions)) {
                continue;
            }

            foreach ($questions as $q) {
                // ✅ 修正:改用 if-elseif-else 結構
                if (method_exists($this->main_plugin, "build_answer_url")) {
                    $url = $this->main_plugin->build_answer_url($pid, $q);
                } elseif (
                    method_exists($this->main_plugin, "slugify_question_public")
                ) {
                    // 後備方案
                    $slug = $this->main_plugin->slugify_question_public(
                        $q,
                        $pid
                    );
                    $url = user_trailingslashit(home_url("qna/" . $slug));
                } else {
                    // 極端情況:兩個方法都不存在,跳過
                    if (defined("WP_DEBUG") && WP_DEBUG) {
                        error_log(
                            "[Moelog AIQnA GEO] Sitemap: Cannot generate URL for question: " .
                                substr($q, 0, 50)
                        );
                    }
                    continue;
                }

                $lastmod = get_post_modified_time("c", true, $pid);

                echo "  <url>\n";
                echo "    <loc>" . esc_url($url) . "</loc>\n";
                echo "    <lastmod>" . esc_html($lastmod) . "</lastmod>\n";
                echo "    <changefreq>weekly</changefreq>\n";
                echo "    <priority>0.6</priority>\n";
                echo "  </url>\n";

                $url_count++;
            }
        }

        echo "</urlset>\n";

        if (defined("WP_DEBUG") && WP_DEBUG) {
            error_log(
                "[Moelog AIQnA GEO] Sitemap generated with {$url_count} URLs"
            );
        }

        exit();
    }

    // =========================================================
    // 自動 Ping 搜尋引擎
    // =========================================================

    public function maybe_ping_on_publish($post_id, $post)
    {
        if (!get_option("moelog_aiqna_geo_mode")) {
            return;
        }

        if ($post->post_status !== "publish") {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }

        $questions = get_post_meta($post_id, "_moelog_aiqna_questions", true);
        if (empty($questions)) {
            return;
        }

        wp_schedule_single_event(
            time() + 60,
            "moelog_aiqna_ping_search_engines"
        );
    }

    public function ping_search_engines()
    {
        if (!get_option("moelog_aiqna_geo_mode")) {
            return;
        }

        $sitemap_url = home_url("ai-qa-sitemap.xml");
        $google_ping =
            "https://www.google.com/ping?sitemap=" . urlencode($sitemap_url);
        $bing_ping =
            "https://www.bing.com/ping?sitemap=" . urlencode($sitemap_url);

        $google_response = wp_remote_get($google_ping, [
            "timeout" => 5,
            "sslverify" => true,
        ]);
        $bing_response = wp_remote_get($bing_ping, [
            "timeout" => 5,
            "sslverify" => true,
        ]);

        if (defined("WP_DEBUG") && WP_DEBUG) {
            $google_code = is_wp_error($google_response)
                ? "Error"
                : wp_remote_retrieve_response_code($google_response);
            $bing_code = is_wp_error($bing_response)
                ? "Error"
                : wp_remote_retrieve_response_code($bing_response);
            error_log(
                "[Moelog AIQnA GEO] Sitemap ping - Google: {$google_code}, Bing: {$bing_code}"
            );
        }
    }

    // =========================================================
    // 後台提醒
    // =========================================================

    public function admin_notices()
    {
        $screen = function_exists("get_current_screen")
            ? get_current_screen()
            : null;
        if (!$screen || $screen->id !== "settings_page_moelog_aiqna") {
            return;
        }

        if (get_option("moelog_aiqna_geo_mode")) {
            $rules = get_option("rewrite_rules");
            if (empty($rules) || !isset($rules['^ai-qa-sitemap\.xml$'])) { ?>
                <div class="notice notice-warning">
                    <p>
                        <strong>Moelog AI Q&A GEO：</strong>
                        偵測到路由規則可能未正確設定。請至
                        <a href="<?php echo admin_url(
                            "options-permalink.php"
                        ); ?>">設定 → 永久連結</a>
                        點擊「儲存變更」以重新整理規則。
                    </p>
                </div>
                <?php }
        }
    }
}

// =========================================================
// 啟動 GEO 模組
// =========================================================

add_action(
    "plugins_loaded",
    function () {
        if (isset($GLOBALS["moelog_aiqna_instance"])) {
            new Moelog_AIQnA_GEO();
        }
    },
    20
);
