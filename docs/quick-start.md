# 安裝與配置

Moelog AI Q&A Links 的安裝、配置與基本使用說明。

**插件版本**: 1.10.2

## 系統需求

| 項目 | 最低需求 | 建議 |
|------|----------|------|
| PHP | 7.4+ | 8.0+ (已完整相容 PHP 8.3) |
| WordPress | 5.0+ | 6.0+ |
| 擴展 | cURL | cURL + mbstring + OpenSSL |
| 其他 | - | 至少一個 AI 供應商的 API 金鑰 |

### PHP 8.x 相容性

自 v1.10.1 起，插件已完整支援 PHP 8.x：

- ✅ 處理 `null` 參數傳入字串函數（`strlen()`, `substr()`, `preg_replace()` 等）
- ✅ `json_decode()` 返回值檢查
- ✅ `parse_url()` 返回值處理
- ✅ 避免 Deprecation warnings

### 建議擴展

| 擴展 | 用途 | 必要性 |
|------|------|--------|
| `cURL` | API 請求 | ⚠️ 必要 |
| `mbstring` | 多語言文字處理 | 建議 |
| `OpenSSL` | API Key 加密 (AES-256) | 建議 |

> 若無 OpenSSL，插件會使用較弱的 XOR 混淆方式存儲 API Key，並顯示安全警告。

## 安裝方式

### 從 GitHub 安裝

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/Horlicks-p/moelog-ai-qna-links.git
cd moelog-ai-qna-links
```

### 手動安裝

1. 下載：[GitHub Releases](https://github.com/Horlicks-p/moelog-ai-qna-links/releases)
2. 解壓至 `wp-content/plugins/moelog-ai-qna-links/`
3. 啟用：WordPress 後台 → 外掛 → 已安裝外掛 → 啟用

## 基本配置

### AI 供應商設定

路徑：**設定 → Moelog AI Q&A**

| 設定項目 | 說明 | 預設值 |
|----------|------|--------|
| AI 供應商 | OpenAI / Google Gemini / Anthropic Claude | - |
| API 金鑰 | 供應商 API 金鑰（AES-256 加密存儲） | - |
| 模型 | 使用的 AI 模型 | 依供應商 |
| Temperature | 回答創意度 (0.0-1.0) | 0.7 |

**API 金鑰取得**：

- OpenAI: https://platform.openai.com/api-keys
- Google Gemini: https://makersuite.google.com/app/apikey
- Anthropic Claude: https://console.anthropic.com

### 推薦模型

| 供應商 | 模型 | 特性 | 預設 |
|--------|------|------|------|
| OpenAI | `gpt-4o-mini` | 快速、經濟 | ✅ |
| OpenAI | `gpt-4o` | 高品質 | |
| Google | `gemini-2.5-flash` | 快速、免費額度高 | ✅ |
| Google | `gemini-2.0-flash-exp` | 實驗版、極快 | |
| Google | `gemini-2.5-pro` | 高品質、長上下文 | |
| Anthropic | `claude-opus-4-7` | 最新版本 | ✅ |
| Anthropic | `claude-3-5-sonnet-20241022` | 高品質 | |

### 內容設定

| 設定項目 | 說明 | 預設值 |
|----------|------|--------|
| 包含文章內容 | 將文章內容作為上下文提供給 AI | 開啟 |
| 內容截斷長度 | 最大上下文字符數 | 6000 |
| System Prompt | 自定義 AI 系統提示詞 | 預設提示 |

### 顯示設定

| 設定項目 | 說明 | 預設值 |
|----------|------|--------|
| 問題清單標題 | 文章底部問題區塊標題 | "💡 關於本文的 AI 問答" |
| 免責聲明 | 答案頁免責聲明文字 | "本答案由 AI 自動生成，僅供參考" |
| 回饋功能 | 顯示按讚/倒讚、問題回報 | 開啟 |
| STM 模式 | 結構化資料與 SEO 優化 | 關閉 |

> **STM 模式** 詳細說明請參閱 [stm-mode.md](stm-mode.md)。

### 快取設定

| 設定項目 | 說明 | 預設值 |
|----------|------|--------|
| 快取有效期 | 靜態 HTML 快取 TTL | 30 天 |
| Transient TTL | WordPress Transient 快取 | 24 小時 |

## wp-config.php 常數

可在 `wp-config.php` 中定義以下常數覆蓋預設值：

```php
// API 金鑰（優先於後台設定，不存入資料庫）
define('MOELOG_AIQNA_API_KEY', 'sk-your-api-key');

// URL 路徑前綴（預設: qna）
define('MOELOG_AIQNA_PRETTY_BASE', 'ai-answer');

// 靜態快取目錄（預設: ai-answers）
define('MOELOG_AIQNA_STATIC_DIR', 'my-ai-cache');
```

### 使用常數的優點

| 優點 | 說明 |
|------|------|
| 🔒 更安全 | API Key 不存入資料庫 |
| 🚀 效能 | 跳過資料庫查詢 |
| 🔧 環境分離 | 不同環境使用不同設定 |
| 📦 版本控制 | 可加入 `.env` 檔案管理 |

## 問題管理

### 文章編輯器整合

在文章編輯頁面右側欄位「AI 問題清單」區塊：

- **新增問題**：點擊「新增問題」按鈕
- **排序**：拖曳問題進行排序
- **預覽**：點擊「預覽」生成並查看答案
- **刪除**：點擊「刪除」移除問題
- **重新生成**：點擊「重新生成全部」刷新所有答案快取
- **清除快取**：清除該文章的所有答案快取

### 問題儲存格式

```php
// Post Meta Key
_moelog_aiqna_questions

// 格式（JSON 陣列）
["問題1", "問題2", "問題3"]

// 解析函數
$questions = get_post_meta($post_id, '_moelog_aiqna_questions', true);
$questions = moelog_aiqna_parse_questions($questions);
```

## URL 結構

### 答案頁 URL 格式

```
https://example.com/qna/{slug}-{hash}-{id}/
                          └─┬─┘ └─┬─┘ └┬┘
                            │     │    └─ 文章 ID (Base36)
                            │     └────── HMAC 簽名 (3字符)
                            └──────────── URL slug (從問題生成)
```

### URL 生成

```php
// 公共函數
$url = moelog_aiqna_build_url($post_id, $question);

// 核心方法
$core = Moelog_AIQnA_Core::get_instance();
$url = $core->build_answer_url($post_id, $question);
```

## 快取系統

### 雙層快取架構

```
L1: 靜態 HTML 檔案
    位置: wp-content/ai-answers/{hash}.html
    速度: 極快
    TTL: 30 天（可設定）

L2: WordPress Transient
    位置: wp_options 表
    速度: 快
    TTL: 24 小時
    支援: Redis/Memcached (若已配置)
```

### 快取操作

```php
// 檢查快取
if (moelog_aiqna_cache_exists($post_id, $question)) {
    // 快取存在
}

// 清除特定快取
moelog_aiqna_clear_cache($post_id, $question);

// 清除文章所有快取
moelog_aiqna_clear_cache($post_id);

// 清除全部快取
Moelog_AIQnA_Cache::clear_all();
```

## API 使用

### 公共 API

```php
// 建立答案 URL
$url = moelog_aiqna_build_url(
    int $post_id,
    string $question
): string

// 檢查快取存在
$exists = moelog_aiqna_cache_exists(
    int $post_id,
    string $question
): bool

// 清除快取
$result = moelog_aiqna_clear_cache(
    int $post_id,
    ?string $question = null
): bool

// 解析問題列表
$questions = moelog_aiqna_parse_questions(
    string $questions
): array
```

### 核心類別

```php
// 取得核心實例
$core = Moelog_AIQnA_Core::get_instance();

// 取得子模組
$router = $core->get_router();
$ai_client = $core->get_ai_client();
$renderer = $core->get_renderer();
```

## 自定義開發

### 使用 Hooks

```php
// 生成前處理
add_action('moelog_aiqna_before_generate', function($post_id, $question, $params) {
    error_log("生成答案: {$question}");
}, 10, 3);

// 生成後處理
add_action('moelog_aiqna_after_generate', function($post_id, $question, $answer) {
    // 自定義邏輯
}, 10, 3);
```

### 使用 Filters

```php
// 修改 AI 參數
add_filter('moelog_aiqna_ai_params', function($params, $post_id) {
    $params['temperature'] = 0.1;
    return $params;
}, 10, 2);

// 修改答案內容
add_filter('moelog_aiqna_answer', function($answer, $post_id, $question) {
    return $answer . "\n\n---\n*免責聲明*";
}, 10, 3);

// 修改快取 TTL
add_filter('moelog_aiqna_cache_ttl', function($ttl) {
    return 7 * DAY_IN_SECONDS;
});
```

### 自定義模板

```php
// 模板優先級
1. {主題目錄}/moelog-ai-qna/answer-page.php
2. {插件目錄}/templates/answer-page.php

// 複製模板到主題
mkdir -p wp-content/themes/your-theme/moelog-ai-qna/
cp wp-content/plugins/moelog-ai-qna-links/templates/answer-page.php \
   wp-content/themes/your-theme/moelog-ai-qna/answer-page.php
```

### 自定義樣式

```php
add_action('wp_enqueue_scripts', function() {
    if (strpos($_SERVER['REQUEST_URI'], '/qna/') !== false) {
        wp_add_inline_style('moelog-aiqna-answer', '
            .moelog-aiqna-answer {
                font-family: "Custom Font", serif;
                line-height: 2.0;
            }
        ');
    }
}, 100);
```

## 測試驗證

### API 連線測試

```php
$core = Moelog_AIQnA_Core::get_instance();
$ai_client = $core->get_ai_client();

$result = $ai_client->test_connection(
    'openai',
    'sk-your-api-key',
    'gpt-4o-mini'
);

if ($result['success']) {
    echo '✓ 連線正常';
} else {
    echo '✗ ' . $result['message'];
}
```

### 除錯模式

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// 查看日誌
tail -f wp-content/debug.log
```

## 故障排除

### 答案頁 404

**原因**：Rewrite rules 未刷新

**解決**：

1. WordPress 後台 → 設定 → 永久連結
2. 點擊「儲存變更」（不需修改設定）

### API 連線失敗

**檢查項目**：

- API 金鑰正確性
- 伺服器防火牆規則
- PHP cURL 擴展：`php -m | grep curl`
- API 配額限制

### 快取未生成

**檢查項目**：

- 目錄權限：`ls -la wp-content/ai-answers/`
- 修復權限：`chmod 755 wp-content/ai-answers/`
- 除錯日誌：`wp-content/debug.log`

## 參考文檔

- [架構概覽](architecture.md) - 系統架構與設計
- [API 參考](api-reference.md) - 完整 API 文檔
- [Hooks & Filters](hooks-filters.md) - 擴展點詳解
- [數據流程](data-flow.md) - 流程圖與時序圖
- [STM 模式](stm-mode.md) - 結構化資料與 SEO 優化

---

最後更新：2025-11-28
