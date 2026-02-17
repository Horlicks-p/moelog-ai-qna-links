<?php
/**
 * Moelog AI Q&A STM Module
 * Generative Engine Optimization Enhancement
 *
 * (版本 1.8.4+ - 已整合 answer-page.php 的 SEO 強化邏輯)
 *
 * @package Moelog_AIQnA
 * @since   1.8.3++
 * @author  Horlicks
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_GEO
{
    /** @var Moelog_AIQnA|null 主插件實例 */
    private $main_plugin = null;

    public function __construct()
    {
        // 取得主插件實例
        $this->main_plugin = $GLOBALS["moelog_aiqna_instance"] ?? null;
        if (!$this->main_plugin) {
            add_action("admin_notices", function () {
                echo '<div class="notice notice-error"><p>Moelog AI Q&A STM 模組需要主插件支援。</p></div>';
            });
            return;
        }

        // 設定頁
        add_action("admin_init", [$this, "register_settings"]);

        // ✅ 核心: 在 answer-page.php 的 <head> 中注入所有 SEO 標籤
        add_action("moelog_aiqna_answer_head", [$this, "output_head"], 10, 4);

        // 回答頁 HTTP headers(Robots / Cache)
        add_action("send_headers", [$this, "answer_headers"], 20);

        // 從主外掛封鎖清單中移除主流爬蟲
        add_filter("moelog_aiqna_blocked_bots", [$this, "allow_major_bots"]);

        // AI Sitemap(index + 分頁)
        add_action("init", [$this, "register_sitemap"]);
        add_action("template_redirect", [$this, "render_sitemap"]);
        add_filter("robots_txt", [$this, "robots_announce_sitemap"], 10, 2);

    }

    // ---------------------------------------
    // 工具: 回答頁判斷旗標
    // ---------------------------------------
    private function is_answer_page(): bool
    {
        if (!get_option("moelog_aiqna_geo_mode")) {
            return false;
        }
        if (!empty($GLOBALS["moe_aiqna_is_answer_page"])) {
            return true;
        }

        // 後備判斷(避免某些佈景/流程中 head 尚未設旗標)
        $req_uri = $_SERVER["REQUEST_URI"] ?? "";
        if (isset($_GET["moe_ai"])) {
            return true;
        }
        if (strpos($req_uri, "/qna/") !== false) {
            return true;
        }
        if (strpos($req_uri, "/ai-answer/") !== false) {
            return true;
        }

        return false;
    }

    // =========================================
    // 設定頁
    // =========================================
    public function register_settings()
    {
        // ✅ 注意: moelog_aiqna_geo_mode 是獨立的 option，不在主設定陣列中
        // 但我們不需要再次 register_setting，因為我們會用 AJAX 處理或直接 update_option

        add_settings_section(
            "moelog_aiqna_geo_section",
            "🚀 STM(Structured Data Mode)",
            [$this, "geo_section_callback"],
            class_exists("Moelog_AIQnA_Admin_Settings")
                ? Moelog_AIQnA_Admin_Settings::PAGE_DISPLAY
                : "moelog_aiqna_settings"
        );

        add_settings_field(
            "moelog_aiqna_geo_mode",
            "Enable STM Mode",
            [$this, "geo_mode_field_callback"],
            class_exists("Moelog_AIQnA_Admin_Settings")
                ? Moelog_AIQnA_Admin_Settings::PAGE_DISPLAY
                : "moelog_aiqna_settings",
            "moelog_aiqna_geo_section"
        );
    }

    public function geo_section_callback()
{
    echo '<p style="color:#666;line-height:1.6">';
    echo '<p style="color:#666;line-height:1.6">';
    esc_html_e(
        "Provide structured data (QAPage, Breadcrumb), Canonical/Robots and cache strategies to help search and AI crawlers \"correctly parse\" AI answer pages.",
        "moelog-ai-qna"
    );
    echo "<br>";
    esc_html_e(
        "This function does not guarantee indexing or ranking, default is noindex; index,follow is adopted only when STM mode is enabled, and corresponding metadata and Sitemap are output.",
        "moelog-ai-qna"
    );
    echo "</p>";
}


    public function geo_mode_field_callback()
    {
        $enabled = (bool) get_option("moelog_aiqna_geo_mode", false);
        $sitemap_url = home_url("/ai-qa-sitemap.php");
        // ✅ 使用 .php 避免被 Auctollo 攔截
        ?>
        <label style="display:block;margin-bottom:12px;">
            <input type="checkbox" name="moelog_aiqna_geo_mode" value="1" <?php checked($enabled, true); ?>>
            <strong><?php esc_html_e(
        "Enable structured data, SEO optimization and AI Sitemap",
        "moelog-ai-qna"
    ); ?></strong>
        </label>
        <div style="background:#f9f9f9;border-left:4px solid #2271b1;padding:12px 15px;margin-top:8px;">
            <p style="margin:0;"><strong><?php esc_html_e(
        "After enabling, this (STM) module will:",
        "moelog-ai-qna"
    ); ?></strong></p>
            <ul style="margin:0;padding-left:20px;line-height:1.8;">
                <li>✓ <?php esc_html_e(
                    "Inject `index, follow` (replace default `noindex`)",
                    "moelog-ai-qna"
                ); ?></li>
                <li>✓ <?php esc_html_e(
                    "Inject QAPage / Breadcrumb / OG / Twitter Card Meta Tags",
                    "moelog-ai-qna"
                ); ?></li>
                <li>✓ <?php esc_html_e(
                    "Inject Canonical Tag (point to **original article**)",
                    "moelog-ai-qna"
                ); ?></li>
                <li>✓ <?php esc_html_e(
                    "Output friendly CDN HTTP cache headers (ETag, 304, Last-Modified)",
                    "moelog-ai-qna"
                ); ?></li>
                <li>✓ <?php esc_html_e(
                    "Generate AI Q&A dedicated Sitemap (index + pagination)",
                    "moelog-ai-qna"
                ); ?></li>
                <li>✓ <?php esc_html_e(
                    "Automatically ping Google/Bing",
                    "moelog-ai-qna"
                ); ?></li>
            </ul>
            <?php if ($enabled): ?>
                <p style="margin:10px 0 0;">
                    <strong>📍 <?php esc_html_e(
        "AI Q&A Sitemap:",
        "moelog-ai-qna"
    ); ?></strong><br>
                    <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank" style="word-break:break-all;"><?php echo esc_html($sitemap_url); ?></a>
                </p>
            <?php endif; ?>
        </div>
        <p class="description" style="margin-top:10px;color:#d63638;">
            <?php esc_html_e(
        "⚠️ After enabling/disabling, please go to",
        "moelog-ai-qna"
    ); ?>
            <a href="<?php echo admin_url(
                "options-permalink.php"
            ); ?>"><?php esc_html_e(
    "Settings → Permalinks",
    "moelog-ai-qna"
); ?></a>
            <?php esc_html_e(
                "Click \"Save Changes\" to refresh rules.",
                "moelog-ai-qna"
            ); ?>
        </p>
        <?php
    }

    public function sanitize_geo_mode($input)
    {
        $old = (bool) get_option("moelog_aiqna_geo_mode", false);
        $new = !empty($input);
        if ($old !== $new) {
            flush_rewrite_rules(false);
            if ($new) {
                //wp_schedule_single_event(time() + 30, "moelog_aiqna_ping_search_engines");
            }
        }
        return $new ? 1 : 0;
    }

    // =========================================
    // 回答頁 <head>(Schema / OG / Canonical / Robots)
    // =========================================
    public function output_head($answer_url, $post_id, $question, $answer)
    {
        if (!get_option("moelog_aiqna_geo_mode")) {
            return;
        }

        // 標記: 目前為回答頁,供 headers/快取/放行使用
        $GLOBALS["moe_aiqna_is_answer_page"] = true;

        echo $this->meta_tags($answer_url, $post_id, $question, $answer);
        echo $this->schema_qa($answer_url, $post_id, $question, $answer);
        echo $this->schema_breadcrumb($post_id, $answer_url);
    }

    /** Meta 標籤(SEO 與 Open Graph) */
    private function meta_tags($answer_url, $post_id, $question, $answer)
    {
        $site_name = esc_attr(get_bloginfo("name"));
        $ai_label = esc_html__("AI 解答", "moelog-ai-qna");
        $lang = esc_attr(get_bloginfo("language")); // zh-TW / ja 等
        
        // ✅ SEO 優化: 使用「問題」作為標題
        $title = esc_attr(sprintf(
            __('%1$s - %2$s | %3$s', "moelog-ai-qna"),
            $question,
            $ai_label,
            $site_name
        ));

        // ✅ SEO 優化: 使用「回答內容」自動生成描述
        $description = '';
        if (!empty($answer)) {
            $clean_answer = $this->strip_markdown($answer);
            // PHP 8.1+: 確保 preg_replace 不返回 null
            $clean_answer = preg_replace('/\s+/', ' ', $clean_answer) ?? $clean_answer;
            $clean_answer = trim((string) $clean_answer);
            
            if (function_exists('mb_strlen') && mb_strlen($clean_answer, 'UTF-8') > 155) {
                $description = mb_substr($clean_answer, 0, 155, 'UTF-8') . '...';
            } else if (strlen($clean_answer) > 155) {
                 $description = substr($clean_answer, 0, 155) . '...';
            } else {
                $description = $clean_answer;
            }
        }
        $description = esc_attr($description);
        
        // 圖片: filter → 精選圖 → 站台 icon
        $image = apply_filters("moelog_aiqna_answer_image", "", $post_id, $question);
        if (!$image && has_post_thumbnail($post_id)) {
            $src = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), "large");
            $image = $src ? $src[0] : "";
        }
        if (!$image) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo_data = wp_get_attachment_image_src($custom_logo_id, 'full');
                if ($logo_data) {
                    $image = $logo_data[0];
                }
            }
        }
        if (!$image) {
             $site_icon = get_site_icon_url(512);
             if ($site_icon) {
                 $image = $site_icon;
             }
        }
        $now_iso = esc_attr(current_time("c"));
        ob_start();
        ?>
        <meta name="title" content="<?php echo $title; ?>">
        <meta name="description" content="<?php echo $description; ?>">
        <meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">
        <meta property="og:type" content="article">
        <meta property="og:title" content="<?php echo $title; ?>">
        <meta property="og:description" content="<?php echo $description; ?>">
        <meta property="og:url" content="<?php echo esc_url($answer_url); ?>">
        <meta property="og:site_name" content="<?php echo $site_name; ?>">
        <meta property="og:locale" content="<?php echo $lang; ?>">
        <?php if ($image): ?>
        <meta property="og:image" content="<?php echo esc_url($image); ?>">
        <meta property="og:image:alt" content="<?php echo esc_attr(get_the_title($post_id)); ?>">
        <?php endif; ?>
        <meta property="article:published_time" content="<?php echo esc_attr(get_the_date("c", $post_id)); ?>">
        <meta property="article:modified_time" content="<?php echo esc_attr(get_the_modified_date("c", $post_id)); ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?php echo $title; ?>">
        <meta name="twitter:description" content="<?php echo $description; ?>">
        <meta name="twitter:url" content="<?php echo esc_url($answer_url); ?>">
        <?php if ($image): ?>
        <meta name="twitter:image" content="<?php echo esc_url($image); ?>">
        <?php endif; ?>
        <link rel="canonical" href="<?php echo esc_url(get_permalink($post_id)); ?>" />
        <?php
        return ob_get_clean();
    }
    /** Schema.org QAPage 結構化資料 */
    private function schema_qa($answer_url, $post_id, $question, $answer)
    {
        $site_name = get_bloginfo("name");
        $clean_answer = $this->strip_markdown($answer);
        $description = '';
        if (function_exists('mb_strlen') && mb_strlen($clean_answer, 'UTF-8') > 155) {
            $description = mb_substr($clean_answer, 0, 155, 'UTF-8') . '...';
        } else if (strlen($clean_answer) > 155) {
             $description = substr($clean_answer, 0, 155) . '...';
        } else {
            $description = $clean_answer;
        }
        
        $ai_label = __("AI 解答", "moelog-ai-qna");
        $title = sprintf(
            __('%1$s - %2$s | %3$s', "moelog-ai-qna"),
            $question,
            $ai_label,
            $site_name
        );

        // 圖片
        $thumbnail_url = '';
        if (has_post_thumbnail($post_id)) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            $thumbnail_data = wp_get_attachment_image_src($thumbnail_id, 'large');
            if ($thumbnail_data) {
                $thumbnail_url = $thumbnail_data[0];
            }
        }
        if (empty($thumbnail_url)) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo_data = wp_get_attachment_image_src($custom_logo_id, 'full');
                if ($logo_data) {
                    $thumbnail_url = $logo_data[0];
                }
            }
        }
        
        // ✅ SEO 強化: 使用你新版的 QAPage 結構
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "QAPage",
            "mainEntity" => [
                "@type" => "Question",
                "name" => $question,
                "text" => $question,
                "answerCount" => 1,
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => $clean_answer,
                    "dateCreated" => current_time("c"),
                    "author" => [
                        "@type" => "Organization",
                        "name" => $site_name,
                    ],
                ],
            ],
            "url" => $answer_url,
            "headline" => $title,
            "description" => $description,
            "datePublished" => get_the_date("c", $post_id),
            "dateModified" => get_the_modified_date("c", $post_id),
            "author" => [
                "@type" => "Organization",
                "name" => $site_name,
                "url" => home_url(),
            ],
            "publisher" => [
                "@type" => "Organization",
                "name" => $site_name,
                "url" => home_url(),
            ],
            "mainEntityOfPage" => [
                "@type" => "WebPage",
                "@id" => $answer_url,
            ],
        ];
        
        if ($thumbnail_url) {
            $schema["image"] = $thumbnail_url;
            $schema["publisher"]["logo"] = [
                "@type" => "ImageObject",
                "url" => $thumbnail_url,
            ];
        }

        return "\n\n" .
            '<script type="application/ld+json">' .
            wp_json_encode(
                $schema,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) .
            "</script>\n";
    }

    /**
     * 將 Markdown 轉為純文字（用於 meta description / Schema.org）
     *
     * @param string $markdown Markdown 格式的文字
     * @return string 純文字
     */
    private function strip_markdown($markdown)
    {
        if (empty($markdown)) {
            return '';
        }

        // 先透過 Parsedown 將 Markdown → HTML，再用 wp_strip_all_tags 去除 HTML
        if (!class_exists('Parsedown')) {
            require_once MOELOG_AIQNA_DIR . 'includes/Parsedown.php';
        }

        $parsedown = new Parsedown();
        $parsedown->setSafeMode(true);
        $html = $parsedown->text($markdown);
        $text = wp_strip_all_tags($html);

        // 清理多餘空白
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return trim($text);
    }

    /** Schema.org BreadcrumbList 結構化資料 (保留) */
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
                    "item" => home_url("/"),
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
                    "name" => __("AI 解答", "moelog-ai-qna"), // 使用簡潔的名稱
                    "item" => $answer_url,
                ],
            ],
        ];
        return "\n\n" .
            '<script type="application/ld+json">' .
            wp_json_encode(
                $list,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) .
            "</script>\n";
    }

    // =========================================
    // 回答頁 HTTP Headers(Robots / Cache)
    // =========================================
    public function answer_headers()
    {
        if (headers_sent()) {
            return;
        }
        if (!get_option("moelog_aiqna_geo_mode")) {
            return;
        }
        if (!$this->is_answer_page()) {
            return;
        }

        // 先移除既有 Robots,再設我們的(HTTP 等級更硬)
        header_remove("X-Robots-Tag");
        header("X-Robots-Tag: index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1");

        // 勝善 CDN 的快取策略
        header_remove("Cache-Control");
        header("Cache-Control: public, max-age=3600, s-maxage=86400, stale-while-revalidate=604800");
        header("Vary: Accept-Encoding, User-Agent");

        // ✅ 提供 Last-Modified (優先從靜態檔案讀取,否則使用當前時間)
        if (!empty($GLOBALS["moe_aiqna_static_file"])) {
            $static_file = $GLOBALS["moe_aiqna_static_file"];
            if (file_exists($static_file)) {
                $mtime = filemtime($static_file);
                header("Last-Modified: " . gmdate("D, d M Y H:i:s", $mtime) . " GMT");

                // ✅ 可選: 支援條件式 GET (304 Not Modified)
                if (isset($_SERVER["HTTP_IF_MODIFIED_SINCE"])) {
                    $if_modified_since = strtotime($_SERVER["HTTP_IF_MODIFIED_SINCE"]);
                    if ($if_modified_since >= $mtime) {
                        status_header(304);
                        exit();
                    }
                }
            }
        } else {
            // 後備方案: 使用快取時間戳或當前時間
            $ts = $GLOBALS["moe_aiqna_answer_cache_ts"] ?? time();
            header("Last-Modified: " . gmdate("D, d M Y H:i:s", (int) $ts) . " GMT");
        }

        // ✅ 可選: 加入 ETag 支援 (基於檔案內容)
        if (!empty($GLOBALS["moe_aiqna_static_file"])) {
            $static_file = $GLOBALS["moe_aiqna_static_file"];
            if (file_exists($static_file)) {
                $etag = md5_file($static_file);
                header('ETag: "' . $etag . '"');
                // 檢查 If-None-Match
                if (isset($_SERVER["HTTP_IF_NONE_MATCH"])) {
                    $if_none_match = trim($_SERVER["HTTP_IF_NONE_MATCH"], '"');
                    if ($if_none_match === $etag) {
                        status_header(304);
                        exit();
                    }
                }
            }
        }
    }

    /** 從封鎖清單中移除主流搜索引擎 UA(輔助) */
    public function allow_major_bots(array $blocked): array
    {
        if (!get_option("moelog_aiqna_geo_mode")) {
            return $blocked;
        }
        $allow = [
            "googlebot",
            "bingbot",
            "duckduckbot",
            "yandexbot",
            "applebot",
            "slurp",
        ];
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

    // =========================================
    // AI Sitemap (索引 + 分頁)
    // =========================================
    // 1) 路由註冊
    public function register_sitemap()
    {
        if (!get_option("moelog_aiqna_geo_mode")) {
            return;
        }

        // ✅ 改成 .php 結尾,避免被 XML Sitemap Generator 攔截
        add_rewrite_rule('^ai-qa-sitemap\.php$', "index.php?moe_aiqna_sitemap=1", "top");
        add_rewrite_tag("%moe_aiqna_sitemap%", "1");
        add_rewrite_rule('^ai-qa-sitemap-([0-9]+)\.php$', 'index.php?moe_aiqna_sitemap_part=$matches[1]', "top");
        add_rewrite_tag("%moe_aiqna_sitemap_part%", "([0-9]+)");
    }

    // 2) 輸出 sitemap
    public function render_sitemap()
    {
        if (!get_option("moelog_aiqna_geo_mode")) {
            return;
        }
        $is_index = get_query_var("moe_aiqna_sitemap") === "1";
        $part = intval(get_query_var("moe_aiqna_sitemap_part"));
        if (!$is_index && $part === 0) {
            return;
        }
        if (!headers_sent()) {
            header("Content-Type: application/xml; charset=UTF-8");
            header("X-Robots-Tag: noarchive");
            header("Cache-Control: public, max-age=3600, s-maxage=3600, stale-while-revalidate=86400");
            header("Vary: Accept-Encoding, User-Agent");
        }
        $post_types = apply_filters("moelog_aiqna_sitemap_post_types", ["post", "page"]);
        $chunk_size = $this->get_sitemap_chunk_size();
        $PER_PAGE = 49000;
        $pages = 1;

        if ($is_index) {
            $total = $this->count_total_questions($post_types, $chunk_size);
            $pages = max(1, (int) ceil($total / $PER_PAGE));
        }

        // ------ 索引檔 ------
        if ($is_index) {
            // 即使只有 1 頁，也輸出 sitemapindex，利於擴充（搜尋引擎 OK）
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            echo "\n";
            echo "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
            $base = home_url("ai-qa-sitemap-");
            $now = gmdate("c");
            for ($i = 1; $i <= $pages; $i++) {
                $loc = esc_url($base . $i . ".php"); // ✅ .php
                echo "  <sitemap>\n";
                echo "    <loc>{$loc}</loc>\n";
                echo "    <lastmod>{$now}</lastmod>\n";
                echo "  </sitemap>\n";
            }
            echo "</sitemapindex>";
            $this->log_sitemap_debug(sprintf("Sitemap index generated: %d files", $pages));
            exit();
        }

        // ------ 單頁/分頁內容 ------
        $target_part = max(1, $part);
        $start_index = ($target_part - 1) * $PER_PAGE;
        $emitted = 0;
        $seen = 0;
        $offset = 0;
        $stop = false;
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        while (!$stop) {
            $batch_ids = $this->get_question_post_ids($post_types, $chunk_size, $offset);
            if (empty($batch_ids)) {
                break;
            }

            $this->log_sitemap_debug(
                sprintf(
                    "Sitemap part batch offset=%d ids=%d target_part=%d",
                    $offset,
                    count($batch_ids),
                    $target_part
                )
            );

            foreach ($batch_ids as $pid) {
                $raw = get_post_meta($pid, "_moelog_aiqna_questions", true);
                $qs = moelog_aiqna_parse_questions($raw);

                if (!$qs) {
                    continue;
                }
                $lastmod = get_post_modified_time("c", true, $pid);
                foreach ($qs as $q) {
                    if ($seen++ < $start_index) {
                        continue;
                    }
                    if ($emitted >= $PER_PAGE) {
                        $stop = true;
                        break;
                    }

                    // 由主外掛提供 URL
                    if (method_exists($this->main_plugin, "build_answer_url")) {
                        $url = $this->main_plugin->build_answer_url($pid, $q);
                    } elseif (function_exists('moelog_aiqna_build_url')) {
                        $url = moelog_aiqna_build_url($pid, $q);
                    } else {
                        $this->log_sitemap_debug("Cannot generate URL for question: " . substr((string) $q, 0, 50));
                        continue;
                    }
                    echo "  <url>\n";
                    echo "    <loc>" . esc_url($url) . "</loc>\n";
                    echo "    <lastmod>" . esc_html($lastmod) . "</lastmod>\n";
                    echo "    <changefreq>weekly</changefreq>\n";
                    echo "    <priority>0.6</priority>\n";
                    echo "  </url>\n";
                    $emitted++;
                }

                if ($stop) {
                    break;
                }
            }

            $offset += $chunk_size;
        }
        echo "</urlset>";
        $this->log_sitemap_debug(
            sprintf(
                "Sitemap part %d rendered: %d URLs (scanned %d questions)",
                $target_part,
                $emitted,
                $seen
            )
        );
        exit();
    }

    /** robots.txt 公告 */
    public function robots_announce_sitemap($output, $public)
    {
        if (!$public || !get_option("moelog_aiqna_geo_mode")) {
            return $output;
        }
        $base = home_url("ai-qa-sitemap.php"); // ✅ .php
        $out = rtrim((string) $output) . "\nSitemap: " . esc_url($base) . "\n";
        return $out;
    }

    /**
     * 取得 Sitemap 查詢批次大小
     */
    private function get_sitemap_chunk_size(): int
    {
        $size = (int) apply_filters("moelog_aiqna_sitemap_chunk_size", 1000);
        return $size > 0 ? $size : 1000;
    }

    /**
     * 以 SQL 抓取具問答資料的文章 ID
     *
     * @param array $post_types
     * @param int   $limit
     * @param int   $offset
     * @return array
     */
    private function get_question_post_ids(array $post_types, int $limit, int $offset): array
    {
        global $wpdb;

        if (empty($post_types)) {
            return [];
        }

        $placeholders = implode(",", array_fill(0, count($post_types), "%s"));
        $sql = "
            SELECT p.ID
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pm
                ON pm.post_id = p.ID
                AND pm.meta_key = %s
                AND pm.meta_value <> ''
            WHERE p.post_status = 'publish'
              AND p.post_type IN ($placeholders)
            ORDER BY p.ID ASC
            LIMIT %d OFFSET %d
        ";
        $params = array_merge([MOELOG_AIQNA_META_KEY], $post_types, [$limit, $offset]);
        $prepared = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_col($prepared);

        if (empty($results)) {
            return [];
        }

        return array_map("intval", $results);
    }

    /**
     * 計算總問答數量（分批避免記憶體爆炸）
     *
     * @param array $post_types
     * @param int   $chunk_size
     * @return int
     */
    private function count_total_questions(array $post_types, int $chunk_size): int
    {
        $offset = 0;
        $total = 0;

        while (true) {
            $ids = $this->get_question_post_ids($post_types, $chunk_size, $offset);
            if (empty($ids)) {
                break;
            }

            foreach ($ids as $pid) {
                $raw = get_post_meta($pid, MOELOG_AIQNA_META_KEY, true);
                $qs = moelog_aiqna_parse_questions($raw);
                $total += count($qs);
            }

            $offset += $chunk_size;
            $this->log_sitemap_debug(
                sprintf(
                    "Sitemap count batch offset=%d ids=%d total=%d",
                    $offset,
                    count($ids),
                    $total
                )
            );
        }

        return $total;
    }

    /**
     * 簡易除錯紀錄
     *
     * @param string $message
     */
    private function log_sitemap_debug(string $message): void
    {
        if (defined("WP_DEBUG") && WP_DEBUG) {
            error_log("[Moelog AIQnA STM] " . $message);
        }
    }

}

// =========================================
// 啟動 STM模組
// =========================================
add_action("plugins_loaded", function () {
    if (isset($GLOBALS["moelog_aiqna_instance"])) {
        new Moelog_AIQnA_GEO();
    }
}, 20);