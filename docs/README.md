# Moelog AI Q&A Links - 技術文檔

WordPress 插件，提供 AI 驅動的問答系統，支援 OpenAI、Google Gemini 和 Anthropic Claude。

**插件版本**: 1.10.2 | **PHP**: 7.4+ (8.3 相容) | **WordPress**: 5.0+

## 📚 文檔結構

### 核心文檔

| 文檔 | 說明 |
|------|------|
| [architecture.md](architecture.md) | 系統架構、模組設計與安全機制 |
| [data-flow.md](data-flow.md) | 請求處理流程、快取策略與數據流 |
| [api-reference.md](api-reference.md) | 公共 API、類別方法與 AJAX 端點 |
| [hooks-filters.md](hooks-filters.md) | 擴展點、動作鉤子與過濾器 |
| [quick-start.md](quick-start.md) | 安裝配置與基本使用 |

### 功能模組

| 文檔 | 說明 |
|------|------|
| [stm-mode.md](stm-mode.md) | STM 結構化資料模式 (SEO/Sitemap) |
| [i18n.md](i18n.md) | 國際化與翻譯開發指南 |

## 🏗️ 系統架構

### 核心模組

```
WordPress Core
    ↓
Plugin Bootstrap (moelog-ai-qna.php)
    ↓
Core Coordinator (Moelog_AIQnA_Core)
    ├─→ Router              - URL 路由與請求處理
    ├─→ AI_Client           - 多供應商 API 整合
    ├─→ Cache               - 雙層快取系統
    │   ├─→ Post_Cache      - 文章物件快取
    │   └─→ Meta_Cache      - Meta 資料快取
    ├─→ Renderer            - HTML 渲染引擎
    │   ├─→ Template        - 模板載入與變數替換
    │   └─→ Security        - CSP 與內容過濾
    ├─→ Admin               - 後台設定介面
    │   ├─→ Settings        - 設定頁面渲染
    │   ├─→ Cache_Manager   - 快取管理介面
    │   └─→ Ajax            - AJAX 處理器
    ├─→ Metabox             - 文章編輯器整合
    ├─→ Pregenerate         - 預生成任務調度
    ├─→ Assets              - 前後台資源載入
    ├─→ Feedback_Controller - 使用者反饋系統（👍/👎）
    └─→ GEO/STM (可選)      - 結構化資料模式 (SEO/Sitemap)
```

### 技術特性

- **三層快取**: 靜態 HTML + WordPress Transient + 物件快取
- **安全機制**: HMAC URL 簽名、API 金鑰 AES-256 加密、CSP、IP 頻率限制
- **多 AI 供應商**: OpenAI GPT-4o、Google Gemini 2.5、Anthropic Claude Opus 4.5
- **URL 路由**: 自定義 rewrite rules (`/qna/{slug}-{hash}-{id}/`)
- **模板系統**: 可覆寫的答案頁模板
- **回饋系統**: 按讚/倒讚、問題回報（含防濫用機制）
- **STM 模式**: 結構化資料 (QAPage/Breadcrumb)、AI Sitemap、SEO 優化
- **國際化**: 繁中/英文/日文翻譯，支援自訂翻譯
- **預生成機制**: 自動預生成常見問題答案
- **PHP 8.x**: 完整相容 PHP 8.0-8.3

## 💻 API 概覽

### 公共函數

```php
// 建立答案 URL
moelog_aiqna_build_url(int $post_id, string $question): string

// 快取操作
moelog_aiqna_cache_exists(int $post_id, string $question): bool
moelog_aiqna_clear_cache(int $post_id, ?string $question = null): bool

// 問題解析
moelog_aiqna_parse_questions(string $questions): array
```

### 核心類別

```php
// 單例核心
Moelog_AIQnA_Core::get_instance()

// 快取管理（靜態方法）
Moelog_AIQnA_Cache::exists(int $post_id, string $question): bool
Moelog_AIQnA_Cache::save(int $post_id, string $question, string $html): bool
Moelog_AIQnA_Cache::delete(int $post_id, ?string $question): bool
Moelog_AIQnA_Cache::clear_all(): int
Moelog_AIQnA_Cache::get_stats(): array

// 快取輔助
Moelog_AIQnA_Post_Cache::get(int $post_id)  // 文章物件快取
Moelog_AIQnA_Meta_Cache::get(int $post_id, string $key)  // Meta 快取

// AI 客戶端
$ai_client->generate_answer(array $params): string
$ai_client->test_connection(string $provider, string $api_key): array

// 路由與渲染
$router->register_routes()
$renderer->render_answer_page()
Moelog_AIQnA_Renderer_Template::load(string $name, array $vars)
Moelog_AIQnA_Renderer_Security::filter_html(string $html): string

// 反饋系統
Moelog_AIQnA_Feedback_Controller::record_feedback(...)
Moelog_AIQnA_Feedback_Controller::get_stats(int $post_id, string $hash)
```

## 🔌 擴展機制

### Hooks (動作)

```php
do_action('moelog_aiqna_before_generate', $post_id, $question, $params);
do_action('moelog_aiqna_after_generate', $post_id, $question, $answer);
do_action('moelog_aiqna_cache_cleared', $post_id, $question);
do_action('moelog_aiqna_before_render', $post_id, $question);
```

### Filters (過濾器)

```php
apply_filters('moelog_aiqna_ai_params', $params, $post_id);
apply_filters('moelog_aiqna_answer', $answer, $post_id, $question);
apply_filters('moelog_aiqna_render_html', $html, $post_id, $question);
apply_filters('moelog_aiqna_cache_ttl', $ttl);
apply_filters('moelog_aiqna_template_path', $path, $template_name);
```

詳細說明請參閱 [hooks-filters.md](hooks-filters.md)。

## 🔐 安全機制

### URL 簽名驗證 (HMAC)

```php
// HMAC-SHA256 簽名，防止 URL 偽造
$secret = get_option(MOELOG_AIQNA_SECRET_KEY);
$data = $post_id . '|' . $question;
$hash = substr(hash_hmac('sha256', $data, $secret), 0, 3);
// URL: /qna/{slug}-{hash}-{base36_id}/
```

### API 金鑰加密

```php
// AES-256-CBC 加密存儲（需 OpenSSL）
// 加密前綴: moe_enc_v1:
$key = hash('sha256', wp_salt('auth'));
$encrypted = openssl_encrypt($api_key, 'AES-256-CBC', $key, 0, $iv);

// 無 OpenSSL 時使用 XOR 混淆（會顯示警告）
// 混淆前綴: moe_obf_v1:
```

### 內容安全策略 (CSP)

```http
Content-Security-Policy:
    default-src 'self';
    script-src 'nonce-{RANDOM}';
    style-src 'nonce-{RANDOM}';
```

### IP 頻率限制

```php
// 問題回報功能防濫用
const REPORT_RATE_LIMIT = 3;      // 每小時 3 次
const REPORT_MAX_LENGTH = 300;    // 最大 300 字
const REPORT_SITE_RATE_LIMIT = 30; // 全站每小時 30 次

// 預設只信 REMOTE_ADDR，再以 site salt 產生匿名識別
$identity = Moelog_AIQnA_Client_IP::anonymized_id();
// bootstrap/view/vote/report 使用各自的 transient 配額
```

### 蜜罐欄位 (Honeypot)

```html
<!-- 前端隱藏欄位，機器人填寫則靜默拒絕 -->
<input type="text" name="website" style="display:none" tabindex="-1">
```

## 📊 數據流

### 答案生成流程

```
用戶請求 → Router 驗證 → 檢查靜態快取
                              ├─ 存在 → 返回 HTML
                              └─ 不存在 → 檢查 Transient
                                         ├─ 存在 → 渲染 HTML → 保存靜態快取
                                         └─ 不存在 → 調用 AI API → 保存雙層快取
```

詳細流程圖與時序圖請參閱 [data-flow.md](data-flow.md)。

## 🗄️ 數據存儲

### 快取系統

| 層級 | 類型      | 位置                                | TTL     |
| ---- | --------- | ----------------------------------- | ------- |
| L1   | 靜態 HTML | `wp-content/ai-answers/{hash}.html` | 30 天   |
| L2   | Transient | `wp_options` 表                     | 24 小時 |

### 數據庫

```php
// Post Meta
_moelog_aiqna_questions                  // 問題列表（JSON）
_moelog_aiqna_questions_lang             // 問題語言（可選）
_moelog_aiqna_content_hash               // 內容雜湊（失效檢測）
_moelog_aiqna_feedback_stats_{hash}      // 反饋統計（👍/👎 計數）

// Options
moelog_aiqna_settings                    // 插件設定
moelog_aiqna_secret                      // HMAC 密鑰
moelog_aiqna_geo_mode                    // GEO 模組設定（可選）
```

## 🔧 擴展範例

### 修改 AI 參數

```php
add_filter('moelog_aiqna_ai_params', function($params, $post_id) {
    if (has_category('technical', $post_id)) {
        $params['temperature'] = 0.1;
        $params['model'] = 'gpt-4o';
    }
    return $params;
}, 10, 2);
```

### 自定義快取策略

```php
add_filter('moelog_aiqna_cache_ttl', function($ttl) {
    return date('H') >= 9 && date('H') <= 17
        ? 6 * HOUR_IN_SECONDS
        : 24 * HOUR_IN_SECONDS;
});
```

### 答案內容處理

```php
add_filter('moelog_aiqna_answer', function($answer, $post_id, $question) {
    $answer .= "\n\n---\n*本答案由 AI 生成，僅供參考*";
    return $answer;
}, 10, 3);
```

## 📞 參考資料

- **安裝配置**: [quick-start.md](quick-start.md)
- **完整 API 文檔**: [api-reference.md](api-reference.md)
- **系統架構**: [architecture.md](architecture.md)
- **擴展開發**: [hooks-filters.md](hooks-filters.md)
- **數據流程**: [data-flow.md](data-flow.md)
- **STM 模式**: [stm-mode.md](stm-mode.md)
- **國際化**: [i18n.md](i18n.md)

## 📄 授權

GPL v2 或更高版本

---

最後更新：2025-11-28
