=== Moelog AI Q&A Links ===  
Contributors: Horlicks  
Author URI: [https://www.moelog.com/](https://www.moelog.com/)  
Tags: AI, OpenAI, Gemini, Claude, ChatGPT, Anthropic, Q&A, GPT, AI Answer, Schema, Structured Data, CSP, Generative Engine Optimization  
Requires at least: 5.0  
Tested up to: 6.8.3  
Requires PHP: 7.4  
Tested PHP: 8.3  
Stable tag: 2.0.1  
License: GPLv2 or later  
License URI: [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

== 🧠 Description ==

**Moelog AI Q&A Links** 會自動在你的文章或頁面結尾，加入一個互動式的 **AI Q&A 問題清單**。
當讀者點擊問題時，會開啟一個新分頁，從 **OpenAI**、**Google Gemini** 或 **Anthropic Claude** 獲取 AI 生成的答案。

這個回答頁面具備乾淨的 HTML 佈局、打字機動畫效果、內建的快取系統（包含靜態檔案），以及一個可選的 **STM (結構化資料模式)**，旨在幫助搜尋引擎和 AI 爬蟲更好地理解頁面內容。

**展示頁面：**
範例問題：GitHub是怎樣的一個網站?它的用途是什麼?  
演示頁面：[https://www.moelog.com/qna/qba-4cb-3257/](https://www.moelog.com/qna/qba-4cb-3257/)

---

== ✨ Key Features ==

- **多供應商支援:** 可串接 **OpenAI** (GPT-4o-mini 等)、**Google Gemini** (Gemini 2.5 Flash 等) 和 **Anthropic Claude**。
- **高度客製化:** 可自訂系統提示 (System Prompt)、模型、溫度 (Temperature)。
- **智慧語言偵測:** 內建規則自動偵測 (繁中/日文/英文)，無需 API。
- **雙層快取系統:** 使用 WordPress Transients 和**靜態 HTML 檔案**雙層快取，大幅提升載入速度。
- **智慧預生成:** 使用**內容雜湊 (Content Hash)** 偵測，僅在文章或問題變更時才重新生成答案，節省 API 成本。
- **後台管理介面:**
  - 在文章編輯頁提供「AI 問題清單」Metabox。
  - 支援**拖曳排序**、新增/刪除問題、即時字數統計。
  - Gutenberg (區塊編輯器) 相容。
  - AJAX **「重新生成全部」** 按鈕，可手動清除快取並觸發預生成。
- **路由與模板:**
  - 使用 `qna/slug-hash-id/` 的**漂亮固定網址 (Pretty URL)** 格式。
  - 使用 HMAC 雜湊確保 URL 安全，防止猜測。
  - 可自訂路由基底 (預設 `qna`) 與快取目錄名稱。
- **Shortcode 支援:**
  - `[moelog_aiqna index="N"]` - 在文章任意位置插入指定問題（index 範圍：1-8）。
  - 完整問題清單會自動附加在文章底部，已通過短碼顯示的問題會自動排除，避免重複。
- **安全性:**
  - **API 金鑰加密:** 使用 `AES-256-CBC` 強加密演算法 (搭配隨機 IV) 儲存 API Key，金鑰源自 WordPress Salts。
  - **嚴格 CSP:** 完整支援**內容安全策略 (CSP)**，所有內聯腳本/樣式均使用 `nonce` 驗證。
- **模組化架構:** 程式碼清晰，易於維護 (Core / Router / Renderer / Cache / Metabox / AI_Client / Pregenerate)。
- **多語言支援:** 內建繁體中文 (`zh_TW`)、英文 (`en_US`)、日文 (`ja`) 完整翻譯。
- **開發者文檔:** 完整技術文檔 (`docs/`)，包含架構、API 參考、Hooks/Filters、STM 模式、國際化指南。

---

== 🚀 STM (Structured Data Mode) ==

STM 模式可協助搜尋引擎和 AI 爬蟲「解析」你的 AI 答案頁，**此功能不保證索引或排名**。

**啟用時 (可選):**

- **SEO 標籤:** 將 Robots 標籤設為 `index, follow`。
- **Canonical:** 加入 `canonical` 標籤，並**指向原始文章** (關鍵 SEO 策略)。
- **結構化資料:** 注入 `QAPage` (問答頁) 和 `BreadcrumbList` (麵包屑) 的 JSON-LD Schema。
- **快取標頭 (CDN 友善):**
  - 輸出 `Cache-Control` (含 `s-maxage`, `stale-while-revalidate`)。
  - 輸出 `Last-Modified` 和 `ETag` 標頭。
  - **完整支援 `304 Not Modified`**，大幅節省爬蟲預算與伺服器資源。
- **Sitemap:**
  - 產生 AI 問答專用的 **Sitemap 索引與分頁** (`ai-qa-sitemap.php`)。
  - 使用 `.php` 結尾以**避免與其他 SEO 外掛的 `.xml` 路由衝突**。
  - 自動在 `robots.txt` 中宣告 Sitemap 位置。
  - 在發布文章時自動 `ping` Google 和 Bing。
- **爬蟲放行:** 自動允許 `Googlebot`、`Bingbot` 等主流爬蟲存取。

**停用時 (預設):**

- **SEO 標籤:** 使用 `noindex, nofollow` 防止重複內容與爬蟲索引。
- **不** 輸出 Schema、Sitemap 或 Ping。

---

== 🧩 Shortcodes ==

| Shortcode                  | Description                          |
| -------------------------- | ------------------------------------ |
| `[moelog_aiqna index="1"]` | 在文章任意位置插入問題 #1            |
| `[moelog_aiqna index="3"]` | 在文章任意位置插入問題 #3 (支援 1–8) |

**使用說明：**

- 短碼用於在文章**任意位置**插入**單一問題**連結。
- 文章底部會**自動附加完整的問題清單**，已通過短碼顯示的問題會自動排除，避免重複顯示。
- 例如：使用 `[moelog_aiqna index="1"]` 在文章中間顯示問題 1，底部清單會自動只顯示問題 2、3 等。
- **注意：** `[moelog_aiqna]`（無 index 參數）已移除，改為只支援單一問題模式，以簡化使用並避免與自動附加清單衝突。

---

== 🧮 Caching System ==

- **TTL:** 可在後台設定 1–365 天 (預設 30 天)。
- **機制:** 結合 WordPress transient 和 `wp-content/` 中的靜態 `.html` 檔案。
- **管理:** 內建全域清除、或在文章編輯頁清除單篇快取。
- **標頭:** 輸出 CDN 友善的 `Cache-Control` 標頭 (含 `stale-while-revalidate`)。
- **智慧生成:** 使用**內容雜湊**偵測文章變更，僅在需要時重建快取。
- **快取佔位符:** 靜態快取中的 CSP `nonce` 會被替換為 `{{PLACEHOLDER}}`，在讀取時才動態填入當次請求的 `nonce`，兼顧安全與效能。

---

== ⚙️ Performance & Stability ==

- **模組化架構:** 核心邏輯分離 (Core, Router, Renderer, Cache, Metabox, Pregenerate, AI_Client)。
- **依賴管理:** 透過 `spl_autoload_register` 自動載入類別。
- **生命週期:** 嚴謹的啟用/停用/卸載流程。
  - **啟用:** 智慧刷新固定網址 (Deferred Flush)，自動產生 HMAC 密鑰。
  - **升級:** v1.8.3 自動遷移並**加密舊的明文 API Key**。
  - **卸載:** 徹底清除所有 `options`, `post_meta`, `transients` 和靜態快取目錄。
- **相容性:**
  - Sitemap 使用 `.php` 結尾，避免與主流 SEO 外掛 (Slim SEO, AIOSEO 等) 衝突。
  - Metabox 使用 `MutationObserver` 相容 Gutenberg 編輯器。
- **環境檢查:** 啟用時檢查 PHP 版本，後台檢查 `json`, `hash`, `mbstring` 等擴充。

---

== 🔐 Security ==

- **API Key 加密:** 使用 `AES-256-CBC` 和 WordPress Salts 衍生金鑰，安全儲存 API Key。
- **CSP & Nonce:** 嚴格的**內容安全策略 (CSP)**，所有內聯腳本/樣式均使用 `nonce` 驗證。
- **安全輸出:** 所有後台與前端輸出均經過 `esc_html` / `esc_attr` / `wp_kses` 嚴格過濾。
- **URL 安全:** 使用 **HMAC 雜湊**生成回答頁 URL，防止惡意枚舉或竄改。
- **XSS 防護:** AI 回答內容中的 HTML 被嚴格過濾，`on...` 事件被移除，**連結 (URL) 會被轉換為無害的 `<span>` 標籤**。
- **濫用防護:** 內建 IP 基礎的**頻率限制 (Rate Limiting)**。
- **IP 偵測:** 可正確識別 Cloudflare 和反向代理 (Proxy) 後方的真實訪客 IP。
- **GDPR:** 不會收集或傳輸任何訪客個人資料。

---

== 💬 Privacy ==

本外掛只會將以下資料傳送給 AI 服務供應商 (OpenAI / Gemini / Claude)：

- 由**網站作者**在後台預設的問題。
- (可選) 如果勾選「包含文章內容」，則會傳送原文內容作為上下文。
- 系統提示 (System Prompt) 和語言設定。

**不會傳送**任何訪客的 IP、User-Agent 或個人資料。所有通訊均透過 HTTPS 安全加密。

---

== 🧩 Changelog ==

= 2.0.1 (2026-02-19) – Layout Refinement & Custom Banner =

- 🎨 **版面優化:** 以純 CSS 取代背景圖片，實現精確的白邊、間距與圓角控制。
- 🎨 **字體統一:** 內文、列表、標題全面改用 px 固定字體大小，手機/平板響應式同步更新。
- 🖼️ **自訂 Banner:** 新增後台上傳功能，可透過 WordPress 媒體庫選擇自訂 Banner（建議 880 × 240 px）。
- 🐛 **修正:** 補回因重構遺漏的 `answer.js` script 標籤。
- 🌍 **國際化:** 補充自訂 Banner 相關字串的英文 (`en_US`) 與日文 (`ja`) 翻譯。

= 2.0.0 (2026-02-18) – Major UI Overhaul & CSS Refinement =

- 📝 **Markdown 支援:** 引入 Parsedown，在答案渲染前正確轉換 Markdown。
- 🎨 **樣式修正:** 修正回答頁的CSS，配合 Markdown 的內容。
- 🎨 **UI 重新設計:** 後台管理介面採用素雅風格設計。

= 1.10.2 (2025-11-28) – 錯誤修正 & 改進 =

- 🐛 **修正:** 刪除快取檔案後，快取統計立即更新（不再顯示舊數據）。
- 🐛 **修正:** STM (結構化資料模式) 設定不再因儲存其他分頁而被重置。
- 🔒 **安全:** 問題回報功能新增防濫用機制（IP 頻率限制、蜜罐欄位、字數限制）。
- ✨ **新功能:** 新增「回饋功能」開關，可在後台顯示設定中啟用/停用互動回饋。
- ✨ **新功能:** 新增「清除所有回饋統計」按鈕，可一鍵刪除所有瀏覽/好評/差評數據。
- 🎨 **UI:** 移除系統資訊頁面中重複的快取統計區塊。
- ⚡ **效能:** 快取管理頁面現在始終顯示即時統計。
- 🌍 **國際化:** 新增完整語言包支援（繁體中文 `zh_TW`、英文 `en_US`、日文 `ja`）。
- 📚 **文檔:** 新增技術文檔 `docs/` 目錄，包含架構、API、Hooks、STM 模式、國際化等 8 份技術文件。

= 1.10.1 (2025-11-26) – PHP 8.x 相容性 & 程式碼品質 =

- 🐘 **PHP 8.x 相容性:** 完整支援 PHP 8.0, 8.1, 8.2, 8.3。
- 🔧 **修正:** `preg_replace()`, `json_decode()`, `trim()`, `parse_url()` null 處理。
- 🎨 **UI 優化:** 為所有後台區段標題添加統一的 Emoji 圖示。
- 🎨 **UI 優化:** 移除多餘的 `<hr>` 分隔線，版面更整潔。
- 🔒 **安全性:** 增強主插件實例的單例模式實作。
- ⚡ **效能:** 為沒有持久物件快取的環境添加 transient 頻率限制備援。

---

== 🧭 Support ==

Bug reports & feature suggestions:  
🌐 Official site: [https://www.moelog.com/](https://www.moelog.com/)  
💻 GitHub: [https://github.com/Horlicks-p/moelog-ai-qna-links](https://github.com/Horlicks-p/moelog-ai-qna-links)  

---

== 🧩 License ==

This plugin is licensed under **GPL v2 or later**.
You are free to modify and redistribute it.
© 2025
