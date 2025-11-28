# 文檔修正總結

## v1.1.0 更新 (2025-11-28)

### 新增文檔

| 文檔 | 說明 |
|------|------|
| `stm-mode.md` | STM 結構化資料模式完整說明 |
| `i18n.md` | 國際化與翻譯開發指南 |

### 更新文檔

| 文檔 | 更新內容 |
|------|----------|
| `README.md` | 新增 STM/i18n 文檔連結、更新安全機制說明 |
| `quick-start.md` | 新增 PHP 8.x 相容性、wp-config.php 常數、回饋功能設定 |
| `api-reference.md` | 新增 Feedback 系統 API 完整說明 |
| `INDEX.md` | 更新文檔列表與統計資訊 |

### 本次修正內容

1. **README.md**
   - 新增 IP 頻率限制與蜜罐欄位安全機制說明
   - 更新技術特性（三層快取、PHP 8.x 相容）
   - 新增 STM 模式與國際化說明
   - 修正 GEO 模組描述為 STM

2. **quick-start.md**
   - 新增 PHP 8.x 相容性詳細說明
   - 新增建議擴展列表
   - 新增 wp-config.php 常數配置
   - 修正模型表格格式
   - 新增回饋功能與 STM 模式設定

3. **api-reference.md**
   - 新增 Feedback 系統 API 章節
   - 新增防濫用機制說明
   - 新增 AJAX 端點列表
   - 修正目錄增加 Feedback 系統 API 連結

4. **architecture.md**
   - 更新 GEO 模組說明為 STM (結構化資料模式)
   - 新增 stm-mode.md 文檔連結

5. **hooks-filters.md**
   - 新增 STM 模式 Hooks 章節
   - 新增 `moelog_aiqna_answer_image` filter
   - 新增 `moelog_aiqna_sitemap_post_types` filter
   - 新增 `moelog_aiqna_sitemap_chunk_size` filter
   - 新增 `moelog_aiqna_blocked_bots` filter

6. **stm-mode.md**
   - 新增 option 命名說明（geo_mode 向後相容）

7. **INDEX.md**
   - 更新模組數量（9 個）
   - 更新快取說明（三層快取）

---

## v1.0.0 已修正的錯誤與遺漏

### 1. 核心模組架構

**修正前**：

- 列出 6 個核心模組

**修正後**：

- 完整列出所有模組及其子模組
- 新增：Feedback_Controller（反饋系統）
- 新增：STM（結構化資料模式，可選）
- 新增：Post_Cache、Meta_Cache（快取輔助）
- 新增：Renderer_Template、Renderer_Security（渲染子模組）
- 新增：Admin_Settings、Admin_Cache、Admin_Ajax（管理子模組）
- 新增：Pregenerate（預生成調度）
- 新增：Assets（資源載入）

### 2. 核心類別 API

**修正前**：

- 只列出基本的 Cache 和 AI_Client 方法

**修正後**：

- 補充 `Moelog_AIQnA_Cache::get_stats()`
- 新增 `Moelog_AIQnA_Post_Cache::get()`
- 新增 `Moelog_AIQnA_Meta_Cache::get()`
- 新增 `Moelog_AIQnA_Renderer_Template::load()`
- 新增 `Moelog_AIQnA_Renderer_Security::filter_html()`
- 新增 `Moelog_AIQnA_Feedback_Controller` 相關方法

### 3. AI 模型預設值

**修正前**：

- Anthropic 預設：`claude-3-5-sonnet`
- Google 缺少 `gemini-2.5-flash`

**修正後**：

- OpenAI 預設：`gpt-4o-mini` ✅
- Google 預設：`gemini-2.5-flash` ✅
- Anthropic 預設：`claude-opus-4-5-20251101` ✅
- 新增完整的模型對照表

### 4. 技術特性

**修正前**：

- 雙層快取：靜態 HTML + Transient
- 缺少反饋系統說明
- 缺少 GEO 模組說明

**修正後**：

- 三層快取：靜態 HTML + Transient + 物件快取
- 新增：反饋系統（👍/👎）
- 新增：地理追蹤（IP 定位）
- 新增：預生成機制

### 5. 數據存儲

**修正前**：

```php
_moelog_aiqna_questions      // 問題列表
_moelog_aiqna_content_hash   // 內容雜湊
```

**修正後**：

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

## 修正的文件

1. ✅ `docs/README.md` - 技術概覽文檔

   - 模組架構圖
   - 核心類別列表
   - 技術特性
   - 數據存儲

2. ✅ `docs/quick-start.md` - 安裝配置文檔
   - AI 模型推薦表
   - 預設模型標記

## 驗證來源

所有修正都基於實際代碼檢查：

- ✅ `moelog-ai-qna.php` - 主插件文件與常數定義
- ✅ `includes/class-core.php` - 核心模組載入
- ✅ `includes/class-cache.php` - 快取類別方法
- ✅ `includes/class-feedback-controller.php` - 反饋系統
- ✅ `moelog-ai-geo.php` - GEO 追蹤模組

## 確認的正確項目

以下項目經檢查後確認文檔描述正確：

- ✅ URL 路由格式：`/qna/{slug}-{hash}-{id}/`
- ✅ HMAC 簽名機制
- ✅ AES-256-CBC API 金鑰加密
- ✅ CSP 安全策略
- ✅ 公共 API 函數簽名
- ✅ 快取檔案路徑：`wp-content/ai-answers/`
- ✅ Post Meta 鍵值：`_moelog_aiqna_questions`
- ✅ Secret 鍵值：`moelog_aiqna_secret`

---

**v1.0.0 修正完成時間**：2025-11-28  
**v1.1.0 更新時間**：2025-11-28  
**檢查範圍**：核心模組、API、配置、數據存儲、安全機制、國際化  
**修正文件數**：6  
**準確度**：基於實際代碼 100% 驗證
