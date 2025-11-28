# API 參考文檔

本文檔詳細說明 Moelog AI Q&A Links 插件提供的所有公共 API、Hooks 和 Filters。

## 📚 目錄

- [Feedback 系統 API](#feedback-系統-api)
- [公共函數](#公共函數)
- [核心類別 API](#核心類別-api)
- [Hooks (動作鉤子)](#hooks-動作鉤子)
- [Filters (過濾器鉤子)](#filters-過濾器鉤子)
- [AJAX 端點](#ajax-端點)
- [使用示例](#使用示例)

---

## 🔐 Feedback 系統 API

### Moelog_AIQnA_Feedback_Controller

使用者回饋互動控制器，處理按讚/倒讚、問題回報與瀏覽統計。

#### 常數

```php
// 問題回報防濫用設定
const REPORT_RATE_LIMIT = 3;       // 每小時最大回報次數
const REPORT_RATE_WINDOW = 3600;   // 頻率限制時間窗口 (秒)
const REPORT_MAX_LENGTH = 300;     // 訊息最大字數

// Meta Key
const META_KEY = '_moelog_aiqna_feedback_stats';
const META_KEY_PREFIX = '_moelog_aiqna_feedback_stats_';
```

#### 方法

##### `get_stats()`

取得特定問題的統計資料。

```php
public static function get_stats(int $post_id, ?string $question_hash = null): array
```

**返回值**:

```php
[
    'views'    => 150,  // 瀏覽次數
    'likes'    => 10,   // 按讚數
    'dislikes' => 2,    // 倒讚數
]
```

**示例**:

```php
$stats = Moelog_AIQnA_Feedback_Controller::get_stats(123, 'a1b2c3d4');
echo "瀏覽: {$stats['views']}, 👍 {$stats['likes']}, 👎 {$stats['dislikes']}";
```

##### `cleanup_orphaned_stats()`

清理孤兒統計數據（對應的靜態檔案已不存在）。

```php
public static function cleanup_orphaned_stats(): array
```

**返回值**:

```php
[
    'scanned' => 100,   // 掃描的 meta 數量
    'deleted' => 5,     // 刪除的孤兒數據數量
    'details' => [...], // 詳細資訊
]
```

##### `ajax_clear_all_stats()`

清除所有回饋統計（需管理員權限）。

```php
// AJAX Action: wp_ajax_moelog_aiqna_clear_feedback_stats
// Nonce: moelog_aiqna_clear_feedback

// JavaScript 呼叫
jQuery.ajax({
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'moelog_aiqna_clear_feedback_stats',
        nonce: moelog_vars.clear_feedback_nonce,
    },
    success: function(response) {
        console.log(response.data.message);
        // 輸出: "已清除所有回饋統計（共 25 筆記錄）"
    }
});
```

#### 防濫用機制

問題回報功能內建多層防護：

| 機制 | 說明 |
|------|------|
| 🍯 Honeypot | 隱藏欄位 `website`，機器人填寫則靜默拒絕 |
| ⏱️ IP 頻率限制 | 每 IP 每小時最多 3 次回報 |
| 📏 長度限制 | 訊息 5-300 字元 |
| 🔒 Nonce 驗證 | WordPress CSRF 保護 |

```php
// 頻率限制實現 (使用 Transient)
$client_ip = self::get_client_ip();
$rate_key = 'moe_aiqna_report_' . md5($client_ip);
$report_count = (int) get_transient($rate_key);

if ($report_count >= self::REPORT_RATE_LIMIT) {
    // 返回錯誤: "回報次數過多,請稍後再試"
}

// 成功後更新計數
set_transient($rate_key, $report_count + 1, self::REPORT_RATE_WINDOW);
```

#### AJAX 端點

| Action | 說明 | 權限 |
|--------|------|------|
| `moelog_aiqna_record_view` | 記錄瀏覽次數 | 公開 |
| `moelog_aiqna_vote` | 按讚/倒讚 | 公開 |
| `moelog_aiqna_report_issue` | 問題回報 | 公開 (有頻率限制) |
| `moelog_aiqna_feedback_bootstrap` | 取得即時 nonce 與統計 | 公開 |
| `moelog_aiqna_clear_feedback_stats` | 清除所有統計 | 僅管理員 |

---

## 🔧 公共函數

### moelog_aiqna_build_url()

建立問題的回答 URL。

**語法**:

```php
moelog_aiqna_build_url( int $post_id, string $question ): string
```

**參數**:

- `$post_id` (int) **必需** - 文章 ID
- `$question` (string) **必需** - 問題文字

**返回值**:

- (string) 完整的答案頁 URL

**示例**:

```php
// 基本用法
$url = moelog_aiqna_build_url(123, '什麼是 WordPress?');
// 返回: https://example.com/qna/what-is-wordpress-abc-7b/

// 在模板中使用
$questions = ['問題1', '問題2', '問題3'];
foreach ($questions as $q) {
    $url = moelog_aiqna_build_url(get_the_ID(), $q);
    echo '<a href="' . esc_url($url) . '">' . esc_html($q) . '</a>';
}
```

---

### moelog_aiqna_cache_exists()

檢查特定問題的靜態快取是否存在。

**語法**:

```php
moelog_aiqna_cache_exists( int $post_id, string $question ): bool
```

**參數**:

- `$post_id` (int) **必需** - 文章 ID
- `$question` (string) **必需** - 問題文字

**返回值**:

- (bool) 快取存在返回 `true`，否則返回 `false`

**示例**:

```php
// 檢查快取狀態
if (moelog_aiqna_cache_exists(123, '什麼是 WordPress?')) {
    echo '答案已快取，可快速載入';
} else {
    echo '首次訪問此問題，將即時生成答案';
}

// 批量檢查
$questions = get_post_meta($post_id, '_moelog_aiqna_questions', true);
foreach ($questions as $q) {
    $cached = moelog_aiqna_cache_exists($post_id, $q) ? '✓' : '✗';
    echo "{$q}: {$cached}<br>";
}
```

---

### moelog_aiqna_clear_cache()

清除特定文章或問題的快取。

**語法**:

```php
moelog_aiqna_clear_cache( int $post_id, string|null $question = null ): bool
```

**參數**:

- `$post_id` (int) **必需** - 文章 ID
- `$question` (string|null) **可選** - 問題文字。如果為 `null`，清除該文章的所有快取

**返回值**:

- (bool) 成功返回 `true`，失敗返回 `false`

**示例**:

```php
// 清除特定問題的快取
moelog_aiqna_clear_cache(123, '什麼是 WordPress?');

// 清除文章的所有問題快取
moelog_aiqna_clear_cache(123);

// 在文章更新時自動清除
add_action('save_post', function($post_id) {
    if (wp_is_post_revision($post_id)) {
        return;
    }
    moelog_aiqna_clear_cache($post_id);
});
```

---

### moelog_aiqna_instance()

取得插件核心實例（用於訪問內部 API）。

**語法**:

```php
moelog_aiqna_instance(): Moelog_AIQnA_Core|null
```

**參數**:

- 無

**返回值**:

- (Moelog_AIQnA_Core|null) 核心實例，如果插件未初始化返回 `null`

**示例**:

```php
// 取得核心實例
$instance = moelog_aiqna_instance();

if ($instance) {
    // 訪問 AI Client
    $ai_client = $instance->get_ai_client();

    // 訪問快取管理器
    $cache = Moelog_AIQnA_Cache::class;

    // 取得路由器
    $router = $instance->get_router();
}
```

---

### moelog_aiqna_detect_language()

自動偵測文字語言。

**語法**:

```php
moelog_aiqna_detect_language( string $text ): string
```

**參數**:

- `$text` (string) **必需** - 要偵測的文字

**返回值**:

- (string) 語言代碼 (`zh`, `ja`, `en`)

**示例**:

```php
// 基本用法
$lang = moelog_aiqna_detect_language('這是中文');
// 返回: 'zh'

$lang = moelog_aiqna_detect_language('これは日本語です');
// 返回: 'ja'

$lang = moelog_aiqna_detect_language('This is English');
// 返回: 'en'

// 在生成答案時使用
$question = '什麼是人工智慧?';
$lang = moelog_aiqna_detect_language($question);
// 自動使用繁體中文生成答案
```

---

### moelog_aiqna_parse_questions()

解析問題列表（支援字串或陣列格式）。

**語法**:

```php
moelog_aiqna_parse_questions( mixed $raw ): array
```

**參數**:

- `$raw` (string|array) **必需** - 原始問題資料

**返回值**:

- (array) 問題陣列（最多 8 個）

**示例**:

```php
// 從字串解析（每行一題）
$raw = "問題1\n問題2\n問題3";
$questions = moelog_aiqna_parse_questions($raw);
// 返回: ['問題1', '問題2', '問題3']

// 從陣列解析
$raw = [
    ['q' => '問題1'],
    ['q' => '問題2']
];
$questions = moelog_aiqna_parse_questions($raw);
// 返回: ['問題1', '問題2']

// 處理 post meta
$raw = get_post_meta($post_id, '_moelog_aiqna_questions', true);
$questions = moelog_aiqna_parse_questions($raw);
```

---

## 🎯 核心類別 API

### Moelog_AIQnA_Core

插件的核心協調器類別。

#### 方法列表

##### `get_instance()`

取得單例實例。

```php
public static function get_instance(): Moelog_AIQnA_Core
```

**示例**:

```php
$core = Moelog_AIQnA_Core::get_instance();
```

##### `build_answer_url()`

建立答案 URL（內部使用，推薦使用公共函數）。

```php
public function build_answer_url( int $post_id, string $question ): string
```

##### `get_router()`

取得路由器實例。

```php
public function get_router(): Moelog_AIQnA_Router
```

##### `get_ai_client()`

取得 AI 客戶端實例。

```php
public function get_ai_client(): Moelog_AIQnA_AI_Client
```

##### `get_renderer()`

取得渲染器實例。

```php
public function get_renderer(): Moelog_AIQnA_Renderer
```

---

### Moelog_AIQnA_Cache

快取管理類別（靜態方法）。

#### 方法列表

##### `exists()`

檢查靜態快取是否存在。

```php
public static function exists( int $post_id, string $question ): bool
```

##### `save()`

保存靜態 HTML 快取。

```php
public static function save( int $post_id, string $question, string $html ): bool
```

**示例**:

```php
$html = '<html>...</html>';
Moelog_AIQnA_Cache::save(123, '問題', $html);
```

##### `load()`

載入靜態快取。

```php
public static function load( int $post_id, string $question ): string|false
```

**示例**:

```php
$html = Moelog_AIQnA_Cache::load(123, '問題');
if ($html !== false) {
    echo $html;
}
```

##### `delete()`

刪除快取。

```php
public static function delete( int $post_id, string|null $question = null ): bool
```

##### `clear_all()`

清除所有靜態快取。

```php
public static function clear_all(): int
```

**返回值**: 刪除的檔案數量

**示例**:

```php
$count = Moelog_AIQnA_Cache::clear_all();
echo "已清除 {$count} 個快取檔案";
```

##### `get_stats()`

取得快取統計資訊。

```php
public static function get_stats(): array
```

**返回值**:

```php
[
    'total_files' => 150,      // 快取檔案總數
    'total_size'  => 5242880,  // 總大小（位元組）
    'oldest'      => 1638360000, // 最舊快取時間戳
    'newest'      => 1701432000, // 最新快取時間戳
]
```

---

### Moelog_AIQnA_AI_Client

AI 服務客戶端類別。

#### 方法列表

##### `generate_answer()`

生成 AI 答案。

```php
public function generate_answer( array $params ): string
```

**參數**:

```php
$params = [
    'post_id'  => 123,               // 文章 ID
    'question' => '什麼是AI?',        // 問題
    'lang'     => 'zh',              // 語言 (可選)
    'context'  => '文章內容...',      // 上下文 (可選)
];
```

**示例**:

```php
$core = Moelog_AIQnA_Core::get_instance();
$ai_client = $core->get_ai_client();

$answer = $ai_client->generate_answer([
    'post_id'  => 123,
    'question' => '什麼是 WordPress?',
    'lang'     => 'zh',
]);

echo $answer; // Markdown 格式的答案
```

##### `test_connection()`

測試 API 連線。

```php
public function test_connection( string $provider, string $api_key, string $model = '' ): array
```

**返回值**:

```php
[
    'success' => true,
    'message' => '連線成功',
    'data'    => [...],  // 額外資料
]
```

**示例**:

```php
$result = $ai_client->test_connection('openai', 'sk-...', 'gpt-4o-mini');

if ($result['success']) {
    echo '✓ API 連線正常';
} else {
    echo '✗ ' . $result['message'];
}
```

---

## 🎣 Hooks (動作鉤子)

### moelog_aiqna_before_generate

在生成 AI 答案**之前**觸發。

**參數**:

- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題文字
- `$params` (array) - 請求參數

**示例**:

```php
add_action('moelog_aiqna_before_generate', function($post_id, $question, $params) {
    // 記錄日誌
    error_log("開始生成答案: {$question}");

    // 發送通知
    if ($post_id === 123) {
        wp_mail('admin@example.com', '重要文章更新', "正在為 #{$post_id} 生成答案");
    }
}, 10, 3);
```

---

### moelog_aiqna_after_generate

在生成 AI 答案**之後**觸發。

**參數**:

- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題文字
- `$answer` (string) - 生成的答案
- `$params` (array) - 請求參數

**示例**:

```php
add_action('moelog_aiqna_after_generate', function($post_id, $question, $answer, $params) {
    // 保存到自定義日誌表
    global $wpdb;
    $wpdb->insert('ai_answer_log', [
        'post_id'    => $post_id,
        'question'   => $question,
        'answer'     => $answer,
        'created_at' => current_time('mysql'),
    ]);

    // 計算統計
    $word_count = str_word_count(strip_tags($answer));
    update_post_meta($post_id, '_ai_total_words', $word_count);
}, 10, 4);
```

---

### moelog_aiqna_cache_cleared

當快取被清除時觸發。

**參數**:

- `$post_id` (int) - 文章 ID
- `$question` (string|null) - 問題文字（null 表示清除全部）

**示例**:

```php
add_action('moelog_aiqna_cache_cleared', function($post_id, $question) {
    if ($question === null) {
        error_log("文章 #{$post_id} 的所有快取已清除");
    } else {
        error_log("已清除快取: {$question}");
    }

    // 觸發預生成
    do_action('moelog_aiqna_pregenerate', $post_id);
}, 10, 2);
```

---

### moelog_aiqna_before_render

在渲染答案頁**之前**觸發。

**參數**:

- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題文字

**示例**:

```php
add_action('moelog_aiqna_before_render', function($post_id, $question) {
    // 追蹤瀏覽次數
    $views = (int) get_post_meta($post_id, '_answer_views', true);
    update_post_meta($post_id, '_answer_views', $views + 1);

    // 設置自定義標頭
    header('X-Content-Type: AI-Generated');
}, 10, 2);
```

---

### moelog_aiqna_answer_head

在答案頁 `<head>` 中輸出內容。

**參數**:

- `$answer_url` (string) - 答案頁 URL
- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題
- `$answer` (string) - 答案內容

**示例**:

```php
add_action('moelog_aiqna_answer_head', function($answer_url, $post_id, $question, $answer) {
    // 添加自定義 meta 標籤
    echo '<meta name="ai-generated" content="true">';
    echo '<meta name="ai-question" content="' . esc_attr($question) . '">';

    // 添加 JSON-LD
    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'FAQPage',
        'mainEntity' => [
            '@type' => 'Question',
            'name'  => $question,
        ],
    ];
    echo '<script type="application/ld+json">';
    echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE);
    echo '</script>';
}, 10, 4);
```

---

## 🔍 Filters (過濾器鉤子)

### moelog_aiqna_ai_params

修改發送給 AI API 的參數。

**參數**:

- `$params` (array) - API 參數
- `$post_id` (int) - 文章 ID

**示例**:

```php
add_filter('moelog_aiqna_ai_params', function($params, $post_id) {
    // 針對特定分類調整 temperature
    if (has_category('technical', $post_id)) {
        $params['temperature'] = 0.1; // 更準確
    } elseif (has_category('creative', $post_id)) {
        $params['temperature'] = 0.9; // 更有創意
    }

    // 為 VIP 文章使用更好的模型
    if (get_post_meta($post_id, 'is_vip', true)) {
        $params['model'] = 'gpt-4o';
    }

    return $params;
}, 10, 2);
```

---

### moelog_aiqna_answer

修改 AI 生成的答案內容。

**參數**:

- `$answer` (string) - 原始答案（Markdown 格式）
- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題

**示例**:

```php
add_filter('moelog_aiqna_answer', function($answer, $post_id, $question) {
    // 添加免責聲明
    $disclaimer = "\n\n---\n\n*本答案由 AI 自動生成，僅供參考。*";
    $answer .= $disclaimer;

    // 替換特定詞彙
    $answer = str_replace('WordPress', '**WordPress**', $answer);

    // 添加相關連結
    if (strpos($question, 'SEO') !== false) {
        $answer .= "\n\n參考資料：[SEO 完整指南](https://example.com/seo)";
    }

    return $answer;
}, 10, 3);
```

---

### moelog_aiqna_render_html

修改最終渲染的 HTML。

**參數**:

- `$html` (string) - HTML 內容
- `$post_id` (int) - 文章 ID
- `$question` (string) - 問題

**示例**:

```php
add_filter('moelog_aiqna_render_html', function($html, $post_id, $question) {
    // 注入自定義 CSS
    $custom_css = '<style>.custom-style { color: blue; }</style>';
    $html = str_replace('</head>', $custom_css . '</head>', $html);

    // 添加浮水印
    $watermark = '<div class="watermark">© ' . get_bloginfo('name') . '</div>';
    $html = str_replace('</body>', $watermark . '</body>', $html);

    // 添加分享按鈕
    $share_btn = '<div class="share-buttons">...</div>';
    $html = str_replace('</article>', '</article>' . $share_btn, $html);

    return $html;
}, 10, 3);
```

---

### moelog_aiqna_cache_ttl

修改快取有效期限（秒）。

**參數**:

- `$ttl` (int) - 默認 TTL（秒）

**示例**:

```php
add_filter('moelog_aiqna_cache_ttl', function($ttl) {
    // 根據時段調整快取時間
    $hour = (int) date('H');

    if ($hour >= 9 && $hour <= 17) {
        // 工作時間：較短快取（6小時）
        return 6 * HOUR_IN_SECONDS;
    } else {
        // 非工作時間：較長快取（24小時）
        return 24 * HOUR_IN_SECONDS;
    }
});
```

---

### moelog_aiqna_template_path

自定義模板路徑。

**參數**:

- `$path` (string) - 模板路徑
- `$template_name` (string) - 模板名稱

**示例**:

```php
add_filter('moelog_aiqna_template_path', function($path, $template_name) {
    // 根據裝置類型使用不同模板
    if (wp_is_mobile() && $template_name === 'answer-page.php') {
        $mobile_template = get_stylesheet_directory() . '/moelog-ai-qna/answer-page-mobile.php';
        if (file_exists($mobile_template)) {
            return $mobile_template;
        }
    }

    return $path;
}, 10, 2);
```

---

### moelog_aiqna_system_prompt

修改系統提示詞。

**參數**:

- `$prompt` (string) - 系統提示
- `$lang` (string) - 語言代碼

**示例**:

```php
add_filter('moelog_aiqna_system_prompt', function($prompt, $lang) {
    // 針對不同語言自定義提示
    if ($lang === 'ja') {
        $prompt = "あなたは日本語の専門家です。丁寧に回答してください。\n\n" . $prompt;
    }

    // 添加特定領域知識
    $prompt .= "\n\n請特別注重 WordPress 開發的最佳實踐。";

    return $prompt;
}, 10, 2);
```

---

## 📡 AJAX 端點

### moelog_aiqna_pregenerate

預生成文章的所有問題答案。

**動作**: `wp_ajax_moelog_aiqna_pregenerate`

**參數**:

- `post_id` (int) - 文章 ID
- `nonce` (string) - 安全驗證

**響應**:

```json
{
  "success": true,
  "data": {
    "generated": 5,
    "failed": 0,
    "message": "成功生成 5 個答案"
  }
}
```

**示例**:

```javascript
jQuery.ajax({
  url: ajaxurl,
  method: "POST",
  data: {
    action: "moelog_aiqna_pregenerate",
    post_id: 123,
    nonce: moelog_vars.nonce,
  },
  success: function (response) {
    if (response.success) {
      console.log(response.data.message);
    }
  },
});
```

---

### moelog_aiqna_clear_cache

清除快取（AJAX 版本）。

**動作**: `wp_ajax_moelog_aiqna_clear_cache`

**參數**:

- `post_id` (int) - 文章 ID
- `question` (string, 可選) - 特定問題
- `nonce` (string) - 安全驗證

**響應**:

```json
{
  "success": true,
  "data": {
    "message": "快取已清除"
  }
}
```

---

### moelog_aiqna_test_api

測試 API 連線。

**動作**: `wp_ajax_moelog_aiqna_test_api`

**參數**:

- `provider` (string) - 提供商 (openai/gemini/claude)
- `api_key` (string) - API 金鑰
- `model` (string) - 模型名稱
- `nonce` (string) - 安全驗證

**響應**:

```json
{
  "success": true,
  "data": {
    "message": "✓ 連線成功",
    "model": "gpt-4o-mini",
    "latency": 1250
  }
}
```

**示例**:

```javascript
jQuery("#test-api-btn").on("click", function () {
  jQuery.ajax({
    url: ajaxurl,
    method: "POST",
    data: {
      action: "moelog_aiqna_test_api",
      provider: "openai",
      api_key: jQuery("#api-key").val(),
      model: jQuery("#model").val(),
      nonce: moelog_vars.nonce,
    },
    success: function (response) {
      alert(response.data.message);
    },
  });
});
```

---

## 💡 使用示例

### 示例 1: 自動預生成熱門文章的答案

```php
// 在 functions.php 中
add_action('publish_post', function($post_id) {
    // 取得文章的問題列表
    $questions = get_post_meta($post_id, '_moelog_aiqna_questions', true);
    $questions = moelog_aiqna_parse_questions($questions);

    if (empty($questions)) {
        return;
    }

    // 非同步預生成
    wp_schedule_single_event(time() + 60, 'moelog_custom_pregenerate', [$post_id]);
});

// 註冊自定義事件
add_action('moelog_custom_pregenerate', function($post_id) {
    $core = Moelog_AIQnA_Core::get_instance();
    $ai_client = $core->get_ai_client();

    $questions = get_post_meta($post_id, '_moelog_aiqna_questions', true);
    $questions = moelog_aiqna_parse_questions($questions);

    foreach ($questions as $question) {
        // 生成答案
        try {
            $answer = $ai_client->generate_answer([
                'post_id'  => $post_id,
                'question' => $question,
            ]);

            error_log("✓ 已預生成: {$question}");
        } catch (Exception $e) {
            error_log("✗ 生成失敗: " . $e->getMessage());
        }
    }
});
```

---

### 示例 2: 自定義答案頁樣式

```php
// 在主題的 functions.php 中
add_filter('moelog_aiqna_render_html', function($html, $post_id, $question) {
    // 根據文章分類添加不同樣式
    $categories = get_the_category($post_id);
    $category_slug = !empty($categories) ? $categories[0]->slug : 'default';

    $custom_css = sprintf(
        '<style>body { --theme-color: var(--%s-color); }</style>',
        $category_slug
    );

    $html = str_replace('</head>', $custom_css . '</head>', $html);

    return $html;
}, 10, 3);
```

---

### 示例 3: 添加答案評分系統

```php
// 在答案頁底部添加評分按鈕
add_filter('moelog_aiqna_render_html', function($html) {
    $rating_html = '
    <div class="answer-rating">
        <p>這個答案有幫助嗎？</p>
        <button class="rating-btn" data-rating="helpful">👍 有幫助</button>
        <button class="rating-btn" data-rating="not-helpful">👎 沒幫助</button>
    </div>
    <script nonce="{{NONCE}}">
    document.querySelectorAll(".rating-btn").forEach(btn => {
        btn.addEventListener("click", function() {
            const rating = this.dataset.rating;
            // 發送 AJAX 記錄評分
            fetch(ajaxurl, {
                method: "POST",
                body: new URLSearchParams({
                    action: "save_answer_rating",
                    rating: rating,
                    url: window.location.href
                })
            });
            alert("感謝您的反饋！");
        });
    });
    </script>
    ';

    $html = str_replace('</article>', $rating_html . '</article>', $html);
    return $html;
});

// 處理評分 AJAX
add_action('wp_ajax_nopriv_save_answer_rating', 'save_answer_rating');
add_action('wp_ajax_save_answer_rating', 'save_answer_rating');

function save_answer_rating() {
    $rating = sanitize_text_field($_POST['rating']);
    $url = esc_url_raw($_POST['url']);

    // 保存到數據庫或分析工具
    global $wpdb;
    $wpdb->insert('answer_ratings', [
        'url'        => $url,
        'rating'     => $rating,
        'ip'         => $_SERVER['REMOTE_ADDR'],
        'created_at' => current_time('mysql'),
    ]);

    wp_send_json_success();
}
```

---

### 示例 4: 整合第三方分析

```php
// 追蹤答案頁瀏覽
add_action('moelog_aiqna_before_render', function($post_id, $question) {
    // Google Analytics 4
    if (function_exists('gtag')) {
        gtag('event', 'ai_answer_view', [
            'post_id'  => $post_id,
            'question' => $question,
        ]);
    }

    // Matomo
    if (class_exists('Matomo')) {
        _paq.push(['trackEvent', 'AI Answer', 'View', $question]);
    }
}, 10, 2);
```

---

## 📚 相關文檔

- [架構概覽](architecture.md)
- [Hooks & Filters 完整列表](hooks-filters.md)
- [數據流詳解](data-flow.md)

---

最後更新：2025-11-28
