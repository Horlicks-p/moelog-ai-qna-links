# STM (Structured Data Mode) 模式

SEO 與 AI 爬蟲優化模組，提供結構化資料、Sitemap 與快取策略。

## 📋 目錄

- [概述](#概述)
- [功能列表](#功能列表)
- [啟用與設定](#啟用與設定)
- [結構化資料](#結構化資料)
- [SEO Meta 標籤](#seo-meta-標籤)
- [HTTP 快取策略](#http-快取策略)
- [AI Sitemap](#ai-sitemap)
- [技術實現](#技術實現)
- [Hooks 與 Filters](#hooks-與-filters)

---

## 📖 概述

STM (Structured Data Mode) 是 Moelog AI Q&A Links 的 SEO 增強模組。

**設計目標**:

- 讓搜尋引擎（Google、Bing）正確解析 AI 答案頁
- 讓 AI 爬蟲（GPTBot、ClaudeBot 等）能索引問答內容
- 提供 CDN 友善的快取策略

**注意事項**:

> ⚠️ 此功能**不保證**索引或排名。預設為 `noindex`，僅在啟用 STM 模式時採用 `index,follow`。

---

## ✅ 功能列表

啟用 STM 模式後，模組將執行：

| 功能 | 說明 |
|------|------|
| ✓ `index, follow` | 取代預設的 `noindex`，允許搜尋引擎索引 |
| ✓ QAPage Schema | JSON-LD 結構化資料，符合 Schema.org 規範 |
| ✓ Breadcrumb Schema | 麵包屑導航結構化資料 |
| ✓ Open Graph | Facebook / LINE 分享卡片 |
| ✓ Twitter Card | Twitter/X 分享卡片 |
| ✓ Canonical 標籤 | 指向原始文章，避免重複內容問題 |
| ✓ HTTP 快取標頭 | ETag, 304 Not Modified, Last-Modified |
| ✓ AI Sitemap | 專用 Sitemap (index + 分頁) |
| ✓ 自動 Ping | 通知 Google/Bing 索引 |

---

## ⚙️ 啟用與設定

### 後台設定路徑

**設定 → Moelog AI Q&A → 顯示設定 (顯示/介面) → STM 模式**

### 啟用後必要步驟

```
⚠️ 啟用或停用 STM 模式後：
   → 設定 → 永久連結 → 點擊「儲存變更」刷新規則
```

### 程式化控制

```php
// 檢查 STM 模式是否啟用
// 注意：option 名稱為 moelog_aiqna_geo_mode (歷史原因)
$stm_enabled = (bool) get_option('moelog_aiqna_geo_mode', false);

// 程式化啟用/停用
update_option('moelog_aiqna_geo_mode', 1); // 啟用
update_option('moelog_aiqna_geo_mode', 0); // 停用
flush_rewrite_rules(false); // 刷新路由規則
```

> **技術說明**：Option 名稱 `moelog_aiqna_geo_mode` 源自模組早期命名（GEO），現已更名為 STM (Structured Data Mode)，但 option key 保持不變以維護向後相容性。

---

## 📊 結構化資料

### QAPage Schema

符合 [Schema.org QAPage](https://schema.org/QAPage) 規範：

```json
{
  "@context": "https://schema.org",
  "@type": "QAPage",
  "mainEntity": {
    "@type": "Question",
    "name": "問題文字",
    "text": "問題文字",
    "answerCount": 1,
    "acceptedAnswer": {
      "@type": "Answer",
      "text": "AI 生成的回答內容...",
      "dateCreated": "2025-01-01T12:00:00+08:00",
      "author": {
        "@type": "Organization",
        "name": "網站名稱"
      }
    }
  },
  "url": "https://example.com/qna/xxx/",
  "headline": "問題 - AI 解答 | 網站名稱",
  "description": "回答內容前 155 字...",
  "datePublished": "2025-01-01T10:00:00+08:00",
  "dateModified": "2025-01-01T12:00:00+08:00",
  "author": {
    "@type": "Organization",
    "name": "網站名稱",
    "url": "https://example.com"
  },
  "publisher": {
    "@type": "Organization",
    "name": "網站名稱",
    "url": "https://example.com",
    "logo": {
      "@type": "ImageObject",
      "url": "https://example.com/logo.png"
    }
  },
  "image": "https://example.com/featured-image.jpg"
}
```

### BreadcrumbList Schema

```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "網站名稱",
      "item": "https://example.com/"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "原始文章標題",
      "item": "https://example.com/original-post/"
    },
    {
      "@type": "ListItem",
      "position": 3,
      "name": "AI 解答",
      "item": "https://example.com/qna/xxx/"
    }
  ]
}
```

---

## 🏷️ SEO Meta 標籤

### 輸出的 Meta 標籤

```html
<!-- 基本 SEO -->
<meta name="title" content="問題 - AI 解答 | 網站名稱">
<meta name="description" content="回答內容前 155 字...">
<meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">

<!-- Open Graph (Facebook / LINE) -->
<meta property="og:type" content="article">
<meta property="og:title" content="問題 - AI 解答 | 網站名稱">
<meta property="og:description" content="回答內容前 155 字...">
<meta property="og:url" content="https://example.com/qna/xxx/">
<meta property="og:site_name" content="網站名稱">
<meta property="og:locale" content="zh-TW">
<meta property="og:image" content="https://example.com/image.jpg">
<meta property="article:published_time" content="2025-01-01T10:00:00+08:00">
<meta property="article:modified_time" content="2025-01-01T12:00:00+08:00">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="問題 - AI 解答 | 網站名稱">
<meta name="twitter:description" content="回答內容前 155 字...">
<meta name="twitter:url" content="https://example.com/qna/xxx/">
<meta name="twitter:image" content="https://example.com/image.jpg">

<!-- Canonical (指向原始文章) -->
<link rel="canonical" href="https://example.com/original-post/" />
```

### 圖片選擇優先順序

1. `moelog_aiqna_answer_image` filter 自訂
2. 文章精選圖片 (Featured Image)
3. 網站 Logo (Custom Logo)
4. 網站圖示 (Site Icon)

---

## 🚀 HTTP 快取策略

### HTTP Headers

```http
X-Robots-Tag: index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1
Cache-Control: public, max-age=3600, s-maxage=86400, stale-while-revalidate=604800
Vary: Accept-Encoding, User-Agent
Last-Modified: Sat, 01 Jan 2025 12:00:00 GMT
ETag: "a1b2c3d4e5f6..."
```

### 快取時間

| 層級 | max-age | 說明 |
|------|---------|------|
| 瀏覽器 | 1 小時 | `max-age=3600` |
| CDN | 24 小時 | `s-maxage=86400` |
| Stale | 7 天 | `stale-while-revalidate=604800` |

### 條件式請求 (304 Not Modified)

支援 `If-Modified-Since` 和 `If-None-Match` 標頭：

```php
// 客戶端發送
If-Modified-Since: Sat, 01 Jan 2025 12:00:00 GMT
If-None-Match: "a1b2c3d4e5f6..."

// 如果內容未變更，伺服器回應
HTTP/1.1 304 Not Modified
```

---

## 🗺️ AI Sitemap

### Sitemap URL

```
https://example.com/ai-qa-sitemap.php        # 索引檔
https://example.com/ai-qa-sitemap-1.php      # 第 1 頁
https://example.com/ai-qa-sitemap-2.php      # 第 2 頁 (如有)
```

> 使用 `.php` 副檔名避免與其他 XML Sitemap 插件衝突。

### Sitemap 結構

**索引檔 (Sitemap Index)**:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <sitemap>
    <loc>https://example.com/ai-qa-sitemap-1.php</loc>
    <lastmod>2025-01-01T12:00:00+00:00</lastmod>
  </sitemap>
</sitemapindex>
```

**分頁內容**:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://example.com/qna/question-abc-7b/</loc>
    <lastmod>2025-01-01T12:00:00+08:00</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
</urlset>
```

### 分頁機制

- 每頁最多 49,000 條 URL
- 使用 chunk 讀取避免記憶體溢出
- 自動公告到 `robots.txt`

### robots.txt 整合

啟用後自動添加：

```
Sitemap: https://example.com/ai-qa-sitemap.php
```

---

## 🔧 技術實現

### 類別結構

```php
class Moelog_AIQnA_GEO {
    // 設定頁
    public function register_settings();
    public function geo_section_callback();
    public function geo_mode_field_callback();
    
    // <head> 輸出
    public function output_head($answer_url, $post_id, $question, $answer);
    private function meta_tags(...);
    private function schema_qa(...);
    private function schema_breadcrumb(...);
    
    // HTTP Headers
    public function answer_headers();
    
    // Sitemap
    public function register_sitemap();
    public function render_sitemap();
    public function robots_announce_sitemap($output, $public);
    
    // 爬蟲白名單
    public function allow_major_bots(array $blocked): array;
}
```

### 允許的搜尋引擎爬蟲

啟用 STM 後，以下爬蟲會從封鎖名單中移除：

- `googlebot`
- `bingbot`
- `duckduckbot`
- `yandexbot`
- `applebot`
- `slurp` (Yahoo)

---

## 🎣 Hooks 與 Filters

### Actions

#### `moelog_aiqna_answer_head`

在答案頁 `<head>` 中輸出 SEO 標籤。

```php
add_action('moelog_aiqna_answer_head', function($answer_url, $post_id, $question, $answer) {
    // STM 模組在此注入所有 Meta 和 Schema
}, 10, 4);
```

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

#### `moelog_aiqna_sitemap_post_types`

指定要包含在 Sitemap 中的文章類型。

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

---

## 🔍 除錯

### 驗證結構化資料

1. [Google Rich Results Test](https://search.google.com/test/rich-results)
2. [Schema.org Validator](https://validator.schema.org/)

### 驗證 Sitemap

1. 訪問 `https://your-site.com/ai-qa-sitemap.php`
2. 使用 [XML Sitemap Validator](https://www.xml-sitemaps.com/validate-xml-sitemap.html)

### 查看除錯日誌

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// 日誌位置
wp-content/debug.log

// 日誌格式
[Moelog AIQnA STM] Sitemap index generated: 1 files
[Moelog AIQnA STM] Sitemap part 1 rendered: 150 URLs (scanned 150 questions)
```

---

## 📚 相關文檔

- [架構概覽](architecture.md)
- [API 參考](api-reference.md)
- [Hooks & Filters](hooks-filters.md)

---

最後更新：2025-11-28

