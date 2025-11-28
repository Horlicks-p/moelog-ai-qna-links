# Hooks & Filters 完整參考

本文檔列出 Moelog AI Q&A Links 提供的所有 Hooks (動作鉤子) 和 Filters (過濾器鉤子)。

## 📋 目錄

- [Hooks (動作鉤子)](#hooks-動作鉤子)
- [Filters (過濾器鉤子)](#filters-過濾器鉤子)
- [實用示例](#實用示例)
- [最佳實踐](#最佳實踐)

---

## 🎣 Hooks (動作鉤子)

動作鉤子允許您在特定事件發生時執行自定義代碼。

### 核心生命週期

#### `moelog_aiqna_loaded`

插件完全載入後觸發。

**時機**: 在所有類別和依賴載入完成後  
**參數**: 無

```php
add_action('moelog_aiqna_loaded', function() {
    // 初始化您的自定義功能
});
```

---

#### `moelog_aiqna_activated`

插件啟用時觸發。

**時機**: 在 `register_activation_hook` 後  
**參數**: 無

```php
add_action('moelog_aiqna_activated', function() {
    // 執行一次性設置任務
});
```

---

### AI 生成相關

#### `moelog_aiqna_before_generate`

在調用 AI API 生成答案**之前**觸發。

**參數**:

- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題文字
- `$params` (array) - API 請求參數

```php
add_action('moelog_aiqna_before_generate', function($post_id, $question, $params) {
    error_log("開始生成答案: Post #{$post_id}, Q: {$question}");
}, 10, 3);
```

---

#### `moelog_aiqna_after_generate`

在 AI 答案生成**之後**觸發。

**參數**:

- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題文字
- `$answer` (string) - 生成的答案 (Markdown 格式)
- `$params` (array) - API 請求參數

```php
add_action('moelog_aiqna_after_generate', function($post_id, $question, $answer, $params) {
    // 保存到自定義日誌
    global $wpdb;
    $wpdb->insert('ai_generation_log', [
        'post_id'   => $post_id,
        'question'  => $question,
        'answer'    => $answer,
        'timestamp' => current_time('mysql'),
    ]);
}, 10, 4);
```

---

#### `moelog_aiqna_generate_failed`

AI 生成失敗時觸發。

**參數**:

- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題文字
- `$error` (WP_Error|string) - 錯誤訊息

```php
add_action('moelog_aiqna_generate_failed', function($post_id, $question, $error) {
    wp_mail(
        'admin@example.com',
        'AI 生成失敗',
        "Post #{$post_id}\n問題: {$question}\n錯誤: " .
        (is_wp_error($error) ? $error->get_error_message() : $error)
    );
}, 10, 3);
```

---

### 快取相關

#### `moelog_aiqna_cache_saved`

靜態快取保存時觸發。

**參數**:

- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題文字
- `$file_path` (string) - 快取檔案路徑

```php
add_action('moelog_aiqna_cache_saved', function($post_id, $question, $file_path) {
    error_log("快取已保存: {$file_path}");
}, 10, 3);
```

---

#### `moelog_aiqna_cache_cleared`

快取被清除時觸發。

**參數**:

- `$post_id` (int) - 文章 ID
- `$question` (string|null) - 問題文字，`null` 表示清除所有

```php
add_action('moelog_aiqna_cache_cleared', function($post_id, $question) {
    if ($question === null) {
        error_log("文章 #{$post_id} 的所有快取已清除");
    } else {
        error_log("已清除快取: {$question}");
    }
}, 10, 2);
```

---

#### `moelog_aiqna_all_cache_cleared`

全域快取清除時觸發。

**參數**:

- `$stats` (array) - 清除統計 `['transient' => int, 'static' => int]`

```php
add_action('moelog_aiqna_all_cache_cleared', function($stats) {
    error_log(sprintf(
        "已清除 %d 個 Transient 和 %d 個靜態檔案",
        $stats['transient'],
        $stats['static']
    ));
});
```

---

### 渲染相關

#### `moelog_aiqna_before_render`

渲染答案頁**之前**觸發。

**參數**:

- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題文字

```php
add_action('moelog_aiqna_before_render', function($post_id, $question) {
    // 追蹤瀏覽次數
    $views = (int) get_post_meta($post_id, '_answer_views', true);
    update_post_meta($post_id, '_answer_views', $views + 1);
}, 10, 2);
```

---

#### `moelog_aiqna_answer_head`

在答案頁 `<head>` 區塊中輸出內容。

**參數**:

- `$answer_url` (string) - 答案頁 URL
- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題
- `$answer` (string) - 答案內容

```php
add_action('moelog_aiqna_answer_head', function($answer_url, $post_id, $question, $answer) {
    // 添加 JSON-LD
    ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": {
            "@type": "Question",
            "name": "<?php echo esc_js($question); ?>",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "<?php echo esc_js(wp_strip_all_tags($answer)); ?>"
            }
        }
    }
    </script>
    <?php
}, 10, 4);
```

---

### 管理界面相關

#### `moelog_aiqna_settings_updated`

設定更新時觸發。

**參數**:

- `$old_settings` (array) - 舊設定
- `$new_settings` (array) - 新設定

```php
add_action('moelog_aiqna_settings_updated', function($old, $new) {
    // 檢查 API 提供商是否改變
    if ($old['provider'] !== $new['provider']) {
        // 清除所有快取
        Moelog_AIQnA_Cache::clear_all();
    }
}, 10, 2);
```

---

#### `moelog_aiqna_metabox_saved`

文章的問題列表保存時觸發。

**參數**:

- `$post_id` (int) - 文章 ID
- `$questions` (array) - 問題陣列

```php
add_action('moelog_aiqna_metabox_saved', function($post_id, $questions) {
    error_log(sprintf(
        "文章 #%d 已保存 %d 個問題",
        $post_id,
        count($questions)
    ));
}, 10, 2);
```

---

## 🔍 Filters (過濾器鉤子)

過濾器允許您修改插件的資料和行為。

### AI 請求相關

#### `moelog_aiqna_ai_params`

修改發送給 AI 的請求參數。

**參數**:

- `$params` (array) - API 參數
- `$post_id` (int) - 文章 ID

**默認參數結構**:

```php
[
    'question'    => string,
    'context'     => string,
    'lang'        => string,
    'temperature' => float,
    'model'       => string,
]
```

**示例**:

```php
add_filter('moelog_aiqna_ai_params', function($params, $post_id) {
    // 技術文章使用更精確的溫度
    if (has_category('技術', $post_id)) {
        $params['temperature'] = 0.1;
    }

    // VIP文章使用更好的模型
    if (get_post_meta($post_id, 'is_premium', true)) {
        $params['model'] = 'gpt-4o';
    }

    return $params;
}, 10, 2);
```

---

#### `moelog_aiqna_system_prompt`

修改 AI 的系統提示詞。

**參數**:

- `$prompt` (string) - 系統提示
- `$lang` (string) - 語言代碼

```php
add_filter('moelog_aiqna_system_prompt', function($prompt, $lang) {
    // 添加領域特定知識
    $prompt .= "\n\n你是 WordPress 專家，請特別注重 WordPress 最佳實踐。";

    // 針對不同語言調整語氣
    if ($lang === 'ja') {
        $prompt = "あなたは丁寧な専門家です。\n\n" . $prompt;
    }

    return $prompt;
}, 10, 2);
```

---

#### `moelog_aiqna_user_prompt`

修改用戶提示（問題部分）。

**參數**:

- `$prompt` (string) - 用戶提示
- `$question` (string) - 原始問題
- `$context` (string) - 文章內容 (可能為空)

```php
add_filter('moelog_aiqna_user_prompt', function($prompt, $question, $context) {
    // 添加格式要求
    $prompt .= "\n\n請使用標題、列表和**粗體**來組織答案，讓內容更易讀。";

    return $prompt;
}, 10, 3);
```

---

### 答案處理相關

#### `moelog_aiqna_answer`

修改 AI 生成的答案。

**參數**:

- `$answer` (string) - 答案 (Markdown 格式)
- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題

```php
add_filter('moelog_aiqna_answer', function($answer, $post_id, $question) {
    // 添加免責聲明
    $disclaimer = "\n\n---\n\n*本答案由 AI 自動生成，內容僅供參考。*";
    $answer .= $disclaimer;

    // 添加相關連結
    if (strpos($question, 'WordPress') !== false) {
        $answer .= "\n\n📚 **延伸閱讀**: [WordPress官方文檔](https://wordpress.org/documentation/)";
    }

    // 替換特定詞彙
    $answer = str_replace('AI', '人工智慧 (AI)', $answer);

    return $answer;
}, 10, 3);
```

---

#### `moelog_aiqna_markdown_to_html`

修改 Markdown 轉 HTML 的過程。

**參數**:

- `$html` (string) - 轉換後的 HTML
- `$markdown` (string) - 原始 Markdown

```php
add_filter('moelog_aiqna_markdown_to_html', function($html, $markdown) {
    // 為外部連結添加 target="_blank"
    $html = preg_replace(
        '/<a href="http/i',
        '<a target="_blank" rel="noopener" href="http',
        $html
    );

    // 為代碼區塊添加行號
    $html = preg_replace_callback(
        '/<pre><code class="language-(\w+)">(.*?)<\/code><\/pre>/s',
        function($matches) {
            $lang = $matches[1];
            $code = $matches[2];
            return sprintf(
                '<pre data-lang="%s"><code class="language-%s">%s</code></pre>',
                $lang, $lang, $code
            );
        },
        $html
    );

    return $html;
}, 10, 2);
```

---

### 渲染相關

#### `moelog_aiqna_render_html`

修改最終輸出的 HTML。

**參數**:

- `$html` (string) - 完整的 HTML
- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題

```php
add_filter('moelog_aiqna_render_html', function($html, $post_id, $question) {
    // 注入自定義 CSS
    $custom_css = '
    <style>
    .custom-highlight {
        background: linear-gradient(120deg, #84fab0 0%, #8fd3f4 100%);
        padding: 2px 6px;
        border-radius: 3px;
    }
    </style>
    ';
    $html = str_replace('</head>', $custom_css . '</head>', $html);

    // 添加分享按鈕
    $share_html = sprintf(
        '<div class="share-buttons">
            <a href="https://twitter.com/intent/tweet?text=%s&url=%s" target="_blank">
                分享到 Twitter
            </a>
        </div>',
        urlencode($question),
        urlencode(get_permalink($post_id))
    );
    $html = str_replace('</article>', $share_html . '</article>', $html);

    return $html;
}, 10, 3);
```

---

#### `moelog_aiqna_template_path`

修改答案頁模板路徑。

**參數**:

- `$path` (string) - 模板路徑
- `$template_name` (string) - 模板名稱

```php
add_filter('moelog_aiqna_template_path', function($path, $template_name) {
    // 行動裝置使用不同模板
    if (wp_is_mobile() && $template_name === 'answer-page.php') {
        $mobile_path = get_stylesheet_directory() . '/moelog-ai-qna/answer-page-mobile.php';
        if (file_exists($mobile_path)) {
            return $mobile_path;
        }
    }

    // 根據分類使用不同模板
    $post_id = get_query_var('moe_post_id');
    if (has_category('video', $post_id)) {
        $video_path = get_stylesheet_directory() . '/moelog-ai-qna/answer-page-video.php';
        if (file_exists($video_path)) {
            return $video_path;
        }
    }

    return $path;
}, 10, 2);
```

---

#### `moelog_aiqna_template_vars`

修改傳遞給模板的變數。

**參數**:

- `$vars` (array) - 模板變數陣列

**默認變數**:

```php
[
    'question'     => string,
    'answer'       => string,
    'post_id'      => int,
    'post_title'   => string,
    'post_url'     => string,
    'nonce'        => string,
    'settings'     => array,
]
```

**示例**:

```php
add_filter('moelog_aiqna_template_vars', function($vars) {
    // 添加作者資訊
    $vars['author'] = [
        'name'   => get_the_author_meta('display_name', $vars['post_id']),
        'url'    => get_author_posts_url(get_post_field('post_author', $vars['post_id'])),
        'avatar' => get_avatar_url(get_post_field('post_author', $vars['post_id'])),
    ];

    // 添加相關文章
    $vars['related_posts'] = get_posts([
        'post_type'      => 'post',
        'posts_per_page' => 3,
        'post__not_in'   => [$vars['post_id']],
        'category__in'   => wp_get_post_categories($vars['post_id']),
    ]);

    // 添加閱讀時間
    $content = get_post_field('post_content', $vars['post_id']);
    $word_count = str_word_count(strip_tags($content));
    $vars['reading_time'] = ceil($word_count / 200); // 假設每分鐘200字

    return $vars;
});
```

---

### 快取相關

#### `moelog_aiqna_cache_ttl`

修改快取有效期限（秒）。

**參數**:

- `$ttl` (int) - 默認 TTL（秒）

```php
add_filter('moelog_aiqna_cache_ttl', function($ttl) {
    // 工作日：6小時
    // 周末：24小時
    $day_of_week = date('N');

    if ($day_of_week >= 6) {
        return 24 * HOUR_IN_SECONDS;
    }

    return 6 * HOUR_IN_SECONDS;
});
```

---

#### `moelog_aiqna_cache_key`

修改快取鍵值。

**參數**:

- `$key` (string) - 默認快取鍵
- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題

```php
add_filter('moelog_aiqna_cache_key', function($key, $post_id, $question) {
    // 包含語言資訊
    $lang = get_locale();
    return $key . '_' . $lang;
}, 10, 3);
```

---

### URL 與路由相關

#### `moelog_aiqna_answer_url`

修改生成的答案 URL。

**參數**:

- `$url` (string) - 答案 URL
- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題

```php
add_filter('moelog_aiqna_answer_url', function($url, $post_id, $question) {
    // 添加追蹤參數
    $url = add_query_arg([
        'utm_source'   => 'ai_qna',
        'utm_medium'   => 'plugin',
        'utm_campaign' => 'answer_page',
    ], $url);

    return $url;
}, 10, 3);
```

---

#### `moelog_aiqna_pretty_base`

修改 URL 基礎路徑（默認 `qna`）。

**參數**:

- `$base` (string) - URL 基礎

```php
add_filter('moelog_aiqna_pretty_base', function($base) {
    // 多語言網站
    $lang = get_locale();

    if ($lang === 'ja') {
        return 'ai-qa';
    } elseif ($lang === 'zh_TW') {
        return 'ai-wenda';
    }

    return $base;
});
```

---

### 前端顯示相關

#### `moelog_aiqna_question_list_html`

修改文章底部問題清單的 HTML。

**參數**:

- `$html` (string) - 問題清單 HTML
- `$post_id` (int) - 文章 ID
- `$questions` (array) - 問題陣列

```php
add_filter('moelog_aiqna_question_list_html', function($html, $post_id, $questions) {
    // 添加快取狀態指示
    $new_html = '<div class="moelog-question-list">';

    foreach ($questions as $q) {
        $cached = moelog_aiqna_cache_exists($post_id, $q);
        $icon = $cached ? '⚡' : '🤔';
        $url = moelog_aiqna_build_url($post_id, $q);

        $new_html .= sprintf(
            '<a href="%s" class="question-link %s">%s %s</a>',
            esc_url($url),
            $cached ? 'cached' : 'not-cached',
            $icon,
            esc_html($q)
        );
    }

    $new_html .= '</div>';

    return $new_html;
}, 10, 3);
```

---

#### `moelog_aiqna_list_heading`

修改問題清單標題。

**參數**:

- `$heading` (string) - 標題文字
- `$post_id` (int) - 文章 ID

```php
add_filter('moelog_aiqna_list_heading', function($heading, $post_id) {
    // 根據分類自定義標題
    if (has_category('tutorial', $post_id)) {
        return '💡 教學常見問題';
    } elseif (has_category('review', $post_id)) {
        return '❓ 關於這個評測';
    }

    return $heading;
}, 10, 2);
```

---

## 💡 實用示例

### 示例 1: 完整的分析追蹤系統

```php
// 追蹤答案生成
add_action('moelog_aiqna_after_generate', function($post_id, $question, $answer) {
    global $wpdb;

    $wpdb->insert('ai_analytics', [
        'post_id'      => $post_id,
        'question'     => $question,
        'answer_length'=> strlen($answer),
        'generated_at' => current_time('mysql'),
    ]);
}, 10, 3);

// 追蹤答案瀏覽
add_action('moelog_aiqna_before_render', function($post_id, $question) {
    global $wpdb;

    $wpdb->query($wpdb->prepare(
        "UPDATE ai_analytics
         SET view_count = view_count + 1
         WHERE post_id = %d AND question = %s",
        $post_id, $question
    ));
}, 10, 2);
```

---

### 示例 2: 多供應商智能切換

```php
add_filter('moelog_aiqna_ai_params', function($params) {
    static $failed_count = 0;

    // 如果前一次失敗，嘗試切換供應商
    if ($failed_count > 0) {
        $providers = ['openai', 'gemini', 'claude'];
        $current_index = array_search($params['provider'], $providers);
        $next_index = ($current_index + 1) % count($providers);

        $params['provider'] = $providers[$next_index];
        error_log("切換到 {$providers[$next_index]}");
    }

    return $params;
});

add_action('moelog_aiqna_generate_failed', function() {
    static $failed_count = 0;
    $failed_count++;
});
```

---

### 示例 3: 自動 SEO 優化

```php
add_filter('moelog_aiqna_answer', function($answer, $post_id, $question) {
    // 提取焦點關鍵字
    $focus_keyword = get_post_meta($post_id, 'yoast_wpseo_focuskw', true);

    if (!empty($focus_keyword) && strpos($answer, $focus_keyword) === false) {
        // 在答案中自然插入關鍵字
        $intro = "關於 **{$focus_keyword}**，\n\n";
        $answer = $intro . $answer;
    }

    // 添加內部連結
    $related_posts = get_posts([
        'post_type'      => 'post',
        'posts_per_page' => 3,
        'post__not_in'   => [$post_id],
        's'              => $focus_keyword,
    ]);

    if (!empty($related_posts)) {
        $answer .= "\n\n## 相關文章\n\n";
        foreach ($related_posts as $post) {
            $answer .= sprintf(
                "- [%s](%s)\n",
                get_the_title($post),
                get_permalink($post)
            );
        }
    }

    return $answer;
}, 10, 3);
```

---

## ✅ 最佳實踐

### 1. 優先級設定

```php
// 早期執行（優先級 < 10）
add_filter('moelog_aiqna_ai_params', 'my_early_filter', 5);

// 默認執行（優先級 = 10）
add_filter('moelog_aiqna_answer', 'my_default_filter', 10);

// 晚期執行（優先級 > 10）
add_filter('moelog_aiqna_render_html', 'my_late_filter', 20);
```

### 2. 條件性應用

```php
add_filter('moelog_aiqna_answer', function($answer, $post_id) {
    // 只在生產環境應用
    if (wp_get_environment_type() === 'production') {
        return $answer . "\n\n*AI生成內容*";
    }
    return $answer;
}, 10, 2);
```

### 3. 性能考量

```php
// ❌ 不好: 在 filter 中執行複雜查詢
add_filter('moelog_aiqna_answer', function($answer) {
    $posts = get_posts(['posts_per_page' => 100]); // 慢！
    // ...
    return $answer;
});

// ✅ 好: 使用快取
add_filter('moelog_aiqna_answer', function($answer) {
    $posts = wp_cache_get('my_posts');
    if ($posts === false) {
        $posts = get_posts(['posts_per_page' => 100]);
        wp_cache_set('my_posts', $posts, '', HOUR_IN_SECONDS);
    }
    // ...
    return $answer;
});
```

### 4. 除錯與日誌

```php
add_action('moelog_aiqna_after_generate', function($post_id, $question, $answer) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            "[AI Q&A] 生成成功 - Post: %d, Question: %s, Length: %d",
            $post_id,
            $question,
            strlen($answer)
        ));
    }
}, 10, 3);
```

---

## 🚀 STM 模式 Hooks

以下 hooks 僅在啟用 STM 模式時可用。

### Filters

#### `moelog_aiqna_answer_image`

自訂答案頁的 OG/Twitter 圖片。

```php
add_filter('moelog_aiqna_answer_image', function($image, $post_id, $question) {
    // 根據問題類型返回不同圖片
    if (strpos($question, 'WordPress') !== false) {
        return 'https://example.com/wp-logo.png';
    }
    return $image;
}, 10, 3);
```

**參數**:

- `$image` (string) - 預設圖片 URL（可能為空）
- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題文字

#### `moelog_aiqna_sitemap_post_types`

指定要包含在 AI Sitemap 中的文章類型。

```php
add_filter('moelog_aiqna_sitemap_post_types', function($types) {
    // 預設: ['post', 'page']
    return ['post', 'page', 'product'];
});
```

#### `moelog_aiqna_sitemap_chunk_size`

調整 Sitemap 查詢批次大小。

```php
add_filter('moelog_aiqna_sitemap_chunk_size', function($size) {
    // 預設: 1000
    return 500; // 降低以減少記憶體使用
});
```

#### `moelog_aiqna_blocked_bots`

自訂封鎖的爬蟲名單（STM 會自動移除主流搜尋引擎）。

```php
add_filter('moelog_aiqna_blocked_bots', function($blocked) {
    // 移除特定爬蟲
    return array_diff($blocked, ['somebot']);
});
```

**詳細說明**: 請參閱 [stm-mode.md](stm-mode.md)

---

## 📚 相關文檔

- [API 參考](api-reference.md)
- [架構概覽](architecture.md)
- [數據流詳解](data-flow.md)
- [STM 模式](stm-mode.md)

---

最後更新：2025-11-28
