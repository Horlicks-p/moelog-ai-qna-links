<?php
/**
 * Moelog AI Q&A GEO Module
 * Generative Engine Optimization Enhancement
 *
 * 讓 AI 問答內容更容易被 Google SGE、Bing Copilot 等生成式搜尋引擎引用
 *
 * @package Moelog_AIQnA
 * @since   1.6.3
 * @author  Horlicks
 */

if (!defined('ABSPATH')) exit;

class Moelog_AIQnA_GEO
{
    /** @var Moelog_AIQnA|null 主插件實例 */
    private $main_plugin = null;

    public function __construct()
    {
        // 取得主插件實例
        $this->main_plugin = $GLOBALS['moelog_aiqna_instance'] ?? null;
        if (!$this->main_plugin) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>Moelog AI Q&A GEO 模組需要主插件支援。</p></div>';
            });
            return;
        }

        // 設定頁
        add_action('admin_init', [$this, 'register_settings']);

        // 回答頁 <head> 由主外掛 render_answer_html() 觸發
        add_action('moelog_aiqna_answer_head', [$this, 'output_head'], 10, 4);

        // 回答頁 HTTP headers(Robots / Cache)
        add_action('send_headers', [$this, 'answer_headers'], 20);

        // 主流搜尋引擎 UA 保證 200
        add_action('template_redirect', [$this, 'force_200_for_major_bots'], 1);

        // 從主外掛封鎖清單中移除主流爬蟲
        add_filter('moelog_aiqna_blocked_bots', [$this, 'allow_major_bots']);

        // AI Sitemap(index + 分頁)
        add_action('init',              [$this, 'register_sitemap']);
        add_action('template_redirect', [$this, 'render_sitemap']);
        add_filter('robots_txt',        [$this, 'robots_announce_sitemap'], 10, 2);

        // 發文後自動 ping 搜尋引擎
        add_action('save_post', [$this, 'maybe_ping_on_publish'], 999, 2);
        add_action('moelog_aiqna_ping_search_engines', [$this, 'ping_search_engines']);

        // 後台提醒
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    /* ---------------------------------------
     * 工具:回答頁判斷旗標
     * ------------------------------------- */
    private function is_answer_page(): bool
    {
        if (!get_option('moelog_aiqna_geo_mode')) return false;
        if (!empty($GLOBALS['moe_aiqna_is_answer_page'])) return true;

        // 後備判斷(避免某些佈景/流程中 head 尚未設旗標)
        $req_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (isset($_GET['moe_ai'])) return true;
        if (strpos($req_uri, '/qna/') !== false) return true;
        if (strpos($req_uri, '/ai-answer/') !== false) return true;

        return false;
    }

    /* =========================================
     * 設定頁
     * =======================================*/
    public function register_settings()
    {
        register_setting('moelog_aiqna_settings', 'moelog_aiqna_geo_mode', [
            'type'              => 'boolean',
            'default'           => false,
            'sanitize_callback' => [$this, 'sanitize_geo_mode'],
        ]);

        add_settings_section(
            'moelog_aiqna_geo_section',
            '🚀 GEO(Generative Engine Optimization)',
            [$this, 'geo_section_callback'],
            'moelog_aiqna_settings'
        );

        add_settings_field(
            'moelog_aiqna_geo_mode',
            '啟用 GEO 模式',
            [$this, 'geo_mode_field_callback'],
            'moelog_aiqna_settings',
            'moelog_aiqna_geo_section'
        );
    }

    public function geo_section_callback()
    {
        echo '<p style="color:#666;line-height:1.6">';
        echo '讓 AI 問答內容更容易被 Google SGE、Bing Copilot 等生成式搜尋引擎引用與展示。<br>';
        echo '啟用後將加入結構化資料標記、統一 Robots、友善快取策略,並提供 AI 問答專用 Sitemap。';
        echo '</p>';
    }

    public function geo_mode_field_callback()
    {
        $enabled     = (bool) get_option('moelog_aiqna_geo_mode', false);
        $sitemap_url = home_url('/ai-qa-sitemap.php'); // ✅ 使用 .php 避免被 Auctollo 攔截
        ?>
        <label style="display:block;margin-bottom:12px;">
            <input type="checkbox" name="moelog_aiqna_geo_mode" value="1" <?php checked($enabled, true); ?>>
            <strong>啟用結構化資料、SEO 優化與 AI Sitemap</strong>
        </label>
        <div style="background:#f9f9f9;border-left:4px solid #2271b1;padding:12px 15px;margin-top:8px;">
            <ul style="margin:0;padding-left:20px;line-height:1.8;">
                <li>✓ QAPage / Breadcrumb 結構化資料</li>
                <li>✓ Open Graph / Canonical / Robots(HTML+HTTP 雙保險)</li>
                <li>✓ 友善 CDN 的 Cache-Control(含 Last-Modified)</li>
                <li>✓ AI 問答專用 Sitemap(index+分頁),自動 ping Google/Bing</li>
            </ul>
            <?php if ($enabled): ?>
                <p style="margin:10px 0 0;">
                    <strong>📍 AI 問答 Sitemap:</strong><br>
                    <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank" style="word-break:break-all;"><?php echo esc_html($sitemap_url); ?></a>
                </p>
            <?php endif; ?>
        </div>
        <p class="description" style="margin-top:10px;color:#d63638;">
            ⚠️ 啟用/停用後,請到
            <a href="<?php echo admin_url('options-permalink.php'); ?>">設定 → 永久連結</a>
            點「儲存變更」刷新規則。
        </p>
        <?php
    }

    public function sanitize_geo_mode($input)
    {
        $old = (bool) get_option('moelog_aiqna_geo_mode', false);
        $new = !empty($input);
        if ($old !== $new) {
            flush_rewrite_rules(false);
            if ($new) wp_schedule_single_event(time() + 30, 'moelog_aiqna_ping_search_engines');
        }
        return $new ? 1 : 0;
    }

    /* =========================================
     * 回答頁 <head>(Schema / OG / Canonical / Robots)
     * =======================================*/
    public function output_head($answer_url, $post_id, $question, $answer)
    {
        if (!get_option('moelog_aiqna_geo_mode')) return;

        // 標記:目前為回答頁,供 headers/快取/放行使用
        $GLOBALS['moe_aiqna_is_answer_page'] = true;

        echo $this->meta_tags($answer_url, $post_id, $question, $answer);
        echo $this->schema_qa($answer_url, $post_id, $question, $answer);
        echo $this->schema_breadcrumb($post_id, $answer_url);
    }

    /** Meta 標籤(SEO 與 Open Graph) */
    private function meta_tags($answer_url, $post_id, $question, $answer)
    {
        $desc  = esc_attr(wp_trim_words(wp_strip_all_tags($answer), 30));
        $title = esc_attr($question);
        $site  = esc_attr(get_bloginfo('name'));
        $lang  = esc_attr(get_bloginfo('language')); // zh-TW / ja-JP 等

        // 圖片:filter → 精選圖 → 站台 icon
        $image = apply_filters('moelog_aiqna_answer_image', '', $post_id, $question);
        if (!$image && has_post_thumbnail($post_id)) {
            $src = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'full');
            $image = $src ? $src[0] : '';
        }
        if (!$image) {
            $site_icon = get_site_icon_url(512);
            if ($site_icon) $image = $site_icon;
        }

        $now_iso = esc_attr(current_time('c'));

        ob_start(); ?>
<!-- Moelog AI Q&A GEO: Meta Tags -->
<meta name="description" content="<?php echo $desc; ?>">
<meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">

<link rel="canonical" href="<?php echo esc_url($answer_url); ?>">

<meta property="og:type" content="article">
<meta property="og:title" content="<?php echo $title; ?>">
<meta property="og:description" content="<?php echo $desc; ?>">
<meta property="og:url" content="<?php echo esc_url($answer_url); ?>">
<meta property="og:site_name" content="<?php echo $site; ?>">
<meta property="og:locale" content="<?php echo $lang; ?>">
<?php if ($image): ?><meta property="og:image" content="<?php echo esc_url($image); ?>"><?php endif; ?>
<meta property="article:published_time" content="<?php echo $now_iso; ?>">
<meta property="article:modified_time" content="<?php echo $now_iso; ?>">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo $title; ?>">
<meta name="twitter:description" content="<?php echo $desc; ?>">
<?php if ($image): ?><meta name="twitter:image" content="<?php echo esc_url($image); ?>"><?php endif; ?>
<?php
        return ob_get_clean();
    }

    /** Schema.org QAPage 結構化資料 */
    private function schema_qa($answer_url, $post_id, $question, $answer)
    {
        $lang = get_bloginfo('language'); // e.g. zh-TW
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'QAPage',
            'url'      => $answer_url,
            'name'     => wp_strip_all_tags($question),
            'inLanguage' => $lang,
            'mainEntity' => [
                '@type'        => 'Question',
                'name'         => wp_strip_all_tags($question),
                'text'         => wp_strip_all_tags($question),
                'answerCount'  => 1,
                'dateCreated'  => current_time('c'),
                'inLanguage'   => $lang,
                'author'       => [
                    '@type' => 'Organization',
                    'name'  => get_bloginfo('name'),
                    'url'   => home_url('/'),
                ],
                'acceptedAnswer' => [
                    '@type'       => 'Answer',
                    'text'        => wp_strip_all_tags($answer),
                    'dateCreated' => current_time('c'),
                    'inLanguage'  => $lang,
                    'author'      => [
                        '@type' => 'Organization',
                        'name'  => get_bloginfo('name'),
                        'url'   => home_url('/'),
                    ],
                    'url' => $answer_url,
                ],
            ],
            'about' => [
                '@type'         => 'Article',
                'headline'      => get_the_title($post_id),
                'url'           => get_permalink($post_id),
                'datePublished' => get_the_date('c', $post_id),
                'dateModified'  => get_the_modified_date('c', $post_id),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => get_bloginfo('name'),
                'url'   => home_url('/'),
            ],
        ];

        return "\n<!-- Moelog AI Q&A GEO: QAPage Schema -->\n" .
            '<script type="application/ld+json">' .
            wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) .
            "</script>\n";
    }

    /** Schema.org BreadcrumbList 結構化資料 */
    private function schema_breadcrumb($post_id, $answer_url = '')
    {
        $list = [
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => get_bloginfo('name'),
                    'item'     => home_url('/'),
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => get_the_title($post_id),
                    'item'     => get_permalink($post_id),
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 3,
                    'name'     => 'AI 解答',
                    'item'     => $answer_url,
                ],
            ],
        ];

        return "\n<!-- Moelog AI Q&A GEO: Breadcrumb Schema -->\n" .
            '<script type="application/ld+json">' .
            wp_json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) .
            "</script>\n";
    }

    /* =========================================
     * 回答頁 HTTP Headers(Robots / Cache)
     * =======================================*/
    public function answer_headers()
    {
        if (headers_sent()) return;
        if (!get_option('moelog_aiqna_geo_mode')) return;
        if (!$this->is_answer_page()) return;

        // 先移除既有 Robots,再設我們的(HTTP 等級更硬)
        header_remove('X-Robots-Tag');
        header('X-Robots-Tag: index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1');

        // 友善 CDN 的快取策略
        header_remove('Cache-Control');
        header('Cache-Control: public, max-age=3600, s-maxage=86400, stale-while-revalidate=604800');
        header('Vary: Accept-Encoding, User-Agent');

        // 提供條件式 GET 線索(擇一使用,這裡使用 Last-Modified)
        $ts = $GLOBALS['moe_aiqna_answer_cache_ts'] ?? time();
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int)$ts) . ' GMT');
    }

    /** 主流搜尋引擎 UA 一律 200(避免任何 403 誤殺) */
    public function force_200_for_major_bots()
    {
        if (!$this->is_answer_page()) return;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/Googlebot|bingbot|DuckDuckBot|Applebot|YandexBot|Slurp/i', $ua)) {
            status_header(200);
        }
    }

    /** 從封鎖清單中移除主流搜索引擎 UA(輔助) */
    public function allow_major_bots(array $blocked): array
    {
        if (!get_option('moelog_aiqna_geo_mode')) return $blocked;
        $allow = ['googlebot', 'bingbot', 'duckduckbot', 'yandexbot', 'applebot', 'slurp'];
        $out = [];
        foreach ($blocked as $pattern) {
            $keep = true;
            foreach ($allow as $ua) {
                if (stripos($pattern, $ua) !== false) { $keep = false; break; }
            }
            if ($keep) $out[] = $pattern;
        }
        return $out;
    }

    /* =========================================
     * 問題清單解析：同時支援陣列與純文字（一行一題）
     * =======================================*/
    private function parse_questions($raw): array
    {
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $row) {
                if (is_array($row) && !empty($row['q'])) {
                    $q = trim((string)$row['q']);
                    if ($q !== '') $out[] = $q;
                } elseif (is_string($row)) {
                    $q = trim($row);
                    if ($q !== '') $out[] = $q;
                }
            }
            return array_values(array_filter($out));
        }
        if (is_string($raw)) {
            return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw))));
        }
        return [];
    }

    /* =========================================
     * AI Sitemap (索引 + 分頁)
     * =======================================*/

    // 1) 路由註冊
    public function register_sitemap()
    {
        if (!get_option('moelog_aiqna_geo_mode')) return;

        // ✅ 改成 .php 結尾,避免被 XML Sitemap Generator 攔截
        add_rewrite_rule('^ai-qa-sitemap\.php$', 'index.php?moe_aiqna_sitemap=1', 'top');
        add_rewrite_tag('%moe_aiqna_sitemap%', '1');

        add_rewrite_rule('^ai-qa-sitemap-([0-9]+)\.php$', 'index.php?moe_aiqna_sitemap_part=$matches[1]', 'top');
        add_rewrite_tag('%moe_aiqna_sitemap_part%', '([0-9]+)');
    }

    // 2) 輸出 sitemap
    public function render_sitemap()
    {
        if (!get_option('moelog_aiqna_geo_mode')) return;

        $is_index = (get_query_var('moe_aiqna_sitemap') === '1');
        $part     = intval(get_query_var('moe_aiqna_sitemap_part'));
        if (!$is_index && $part === 0) return;

        if (!headers_sent()) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('X-Robots-Tag: noarchive');
            header('Cache-Control: public, max-age=3600, s-maxage=3600, stale-while-revalidate=86400');
            header('Vary: Accept-Encoding, User-Agent');
        }

        $post_types = apply_filters('moelog_aiqna_sitemap_post_types', ['post', 'page']);
        $ids = get_posts([
            'post_type'     => $post_types,
            'post_status'   => 'publish',
            'fields'        => 'ids',
            'numberposts'   => -1,
            'no_found_rows' => true,
        ]);

        // 計算所有問答 URL 總數
        $total = 0;
        foreach ($ids as $pid) {
            $raw = get_post_meta($pid, '_moelog_aiqna_questions', true);
            $qs  = $this->parse_questions($raw);
            if ($qs) $total += count($qs);
        }

        $PER_PAGE = 49000;
        $pages    = max(1, (int) ceil($total / $PER_PAGE));

        // ------ 索引檔 ------
        if ($is_index) {
            // 即使只有 1 頁，也輸出 sitemapindex，利於擴充（搜尋引擎 OK）
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            echo "<!-- Moelog AI Q&A Sitemap Index | Total: {$total} URLs in {$pages} files -->\n";
            echo "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
            $base = home_url('ai-qa-sitemap-');
            $now  = gmdate('c');
            for ($i = 1; $i <= $pages; $i++) {
                $loc = esc_url($base . $i . '.php'); // ✅ .php
                echo "  <sitemap>\n";
                echo "    <loc>{$loc}</loc>\n";
                echo "    <lastmod>{$now}</lastmod>\n";
                echo "  </sitemap>\n";
            }
            echo "</sitemapindex>";
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[Moelog AIQnA GEO] Sitemap index generated: {$pages} files, {$total} total URLs");
            }
            exit;
        }

        // ------ 單頁/分頁內容 ------
        $target_part = max(1, $part);
        $start_index = ($target_part - 1) * $PER_PAGE;
        $emitted     = 0;
        $seen        = 0;

        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<!-- Moelog AI Q&A Sitemap | Part: {$target_part}/{$pages} | Generated: " . gmdate('Y-m-d H:i:s') . " UTC -->\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        $should_stop = false;
        foreach ($ids as $pid) {
            if ($should_stop) break;

            $raw = get_post_meta($pid, '_moelog_aiqna_questions', true);
            $qs  = $this->parse_questions($raw);
            if (!$qs) continue;

            $lastmod = get_post_modified_time('c', true, $pid);

            foreach ($qs as $q) {
                if ($seen++ < $start_index) continue;
                if ($emitted >= $PER_PAGE) { $should_stop = true; break; }

                // 由主外掛提供 URL
                if (method_exists($this->main_plugin, 'build_answer_url')) {
                    $url = $this->main_plugin->build_answer_url($pid, $q);
                } elseif (method_exists($this->main_plugin, 'slugify_question_public')) {
                    $slug = $this->main_plugin->slugify_question_public($q, $pid);
                    $base = class_exists('Moelog_AIQnA') ? Moelog_AIQnA::PRETTY_BASE : 'qna';
                    $url  = user_trailingslashit(home_url($base . '/' . $slug));
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("[Moelog AIQnA GEO] Cannot generate URL for question: " . substr($q, 0, 50));
                    }
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
        }

        echo "</urlset>";

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                "[Moelog AIQnA GEO] Sitemap part %d/%d rendered: %d URLs (scanned %d total)",
                $target_part, $pages, $emitted, $seen
            ));
        }

        exit;
    }

    /** robots.txt 公告 */
    public function robots_announce_sitemap($output, $public)
    {
        if (!$public || !get_option('moelog_aiqna_geo_mode')) return $output;
        $base = home_url('ai-qa-sitemap.php'); // ✅ .php
        $out  = rtrim((string)$output) . "\nSitemap: " . esc_url($base) . "\n";
        return $out;
    }

    /* =========================================
     * 自動 Ping 搜尋引擎
     * =======================================*/
    public function maybe_ping_on_publish($post_id, $post)
    {
        if (!get_option('moelog_aiqna_geo_mode')) return;
        if ($post->post_status !== 'publish') return;
        if (wp_is_post_revision($post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        $questions = get_post_meta($post_id, '_moelog_aiqna_questions', true);
        $qs        = $this->parse_questions($questions);
        if (empty($qs)) return;

        wp_schedule_single_event(time() + 60, 'moelog_aiqna_ping_search_engines');
    }

    public function ping_search_engines()
    {
        if (!get_option('moelog_aiqna_geo_mode')) return;

        $sitemap_url = home_url('/ai-qa-sitemap.php'); // ✅ .php
        $google_ping = 'https://www.google.com/ping?sitemap=' . rawurlencode($sitemap_url);
        $bing_ping   = 'https://www.bing.com/ping?sitemap='   . rawurlencode($sitemap_url);

        $args = [
            'timeout'   => 5,
            'sslverify' => true,
            'headers'   => ['User-Agent' => 'Moelog-AIQnA/1.6.3 (+'.home_url('/').')'],
        ];

        $g = wp_remote_get($google_ping, $args);
        $b = wp_remote_get($bing_ping,   $args);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Moelog AIQnA GEO] Ping Google: ' . (is_wp_error($g) ? $g->get_error_message() : wp_remote_retrieve_response_code($g)));
            error_log('[Moelog AIQnA GEO] Ping Bing: '   . (is_wp_error($b) ? $b->get_error_message() : wp_remote_retrieve_response_code($b)));
        }
    }

    /* =========================================
     * 後台提醒
     * =======================================*/
    public function admin_notices()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'settings_page_moelog_aiqna') return;

        if (get_option('moelog_aiqna_geo_mode')) {
            $rules = get_option('rewrite_rules');
            // ✅ 同時檢查 index 與分頁規則是否存在
            $ok1 = is_array($rules) && isset($rules['^ai-qa-sitemap\.php$']);

            $ok2 = false;
            if (is_array($rules)) {
                foreach ($rules as $pattern => $dest) {
                    // 由於 rewrite_rules 的 key 常見是 regex 字串，這裡用 strpos 檢視
                    if (strpos($pattern, '^ai-qa-sitemap-([0-9]+)\.php$') !== false) {
                        $ok2 = true;
                        break;
                    }
                }
            }

            if (!$ok1 || !$ok2) { ?>
                <div class="notice notice-warning">
                    <p>
                        <strong>Moelog AI Q&A GEO:</strong>
                        偵測到路由規則可能未正確設定。請至
                        <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>">設定 → 永久連結</a>
                        點擊「儲存變更」以重新整理規則。
                    </p>
                </div>
            <?php }
        }
    }
}

/* =========================================
 * 啟動 GEO 模組
 * =======================================*/
add_action('plugins_loaded', function () {
    if (isset($GLOBALS['moelog_aiqna_instance'])) {
        new Moelog_AIQnA_GEO();
    }
}, 20);
