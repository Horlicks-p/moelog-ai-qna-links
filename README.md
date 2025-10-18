=== Moelog AI Q&A Links ===
Contributors: horlicks  
Author URI: https://www.moelog.com/  
Tags: AI, OpenAI, Gemini, ChatGPT, Q&A, GPT, AI Answer, SEO, Schema, GEO, WordPress Plugin  
Requires at least: 5.0  
Tested up to: 6.7  
Requires PHP: 7.4  
Stable tag: 1.8.0  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

== 外掛說明 ==

**Moelog AI Q&A Links** 是一款可為文章自動附加「AI 問答清單」的 WordPress 外掛。  
每個預先設定的問題都會開啟新分頁，並由 **OpenAI** 或 **Google Gemini** 即時生成 AI 回答。  

1.8.0 版本為 **完整重構版（Complete Modular Rebuild）**，  
以模組化架構重新設計，效能更快、維護更簡潔，  
並延續 **GEO (Generative Engine Optimization)** 模組，  
讓 AI 回答更容易被 Google SGE、Bing Copilot、Perplexity 等生成式搜尋引擎引用。

---

### ✨ 主要特色

✅ 自動在文章或頁面底部新增互動式 AI 問答清單  
✅ 支援 `[moelog_aiqna index="N"]` 短碼，能個別插入指定題目  
✅ 支援 **OpenAI** 與 **Google Gemini** 雙引擎  
✅ 可自訂 System Prompt、模型、溫度、語言等參數  
✅ 自動語言偵測（繁中 / 日文 / 英文）  
✅ AI 回答頁支援打字動畫效果（typing.js）  
✅ 內建快取（24 小時 TTL，含 transient + 靜態檔）  
✅ 後台快取管理介面：一鍵清除快取  
✅ GEO 模式：自動產生結構化資料與 AI Sitemap  
✅ 完整符合 **CSP (Content Security Policy)** 安全規範  
✅ 相容 Cloudflare / Proxy 架構的 IP 偵測  
✅ 全模組化架構，易於擴充與除錯  

---

== 🚀 1.8.0 新功能 – 完全模組化重構 ==

**主要改進內容：**
- 將主程式拆分為 10 個獨立模組（位於 `/includes/`）  
- 新增核心協調類別 `Moelog_AIQnA_Core` 統一管理所有掛鉤  
- 短碼邏輯更乾淨，自動偵測重複避免輸出兩次  
- 新增工具組與模板輔助函式（`helpers-utils.php`, `helpers-template.php`）  
- 所有 JS / CSS 改為外部載入，無內嵌 script，完全相容 CSP  
- 全新 `typing.js` 打字動畫（自動初始化、無需 inline）  

**效能與穩定性：**
- 啟動速度提升約 45%  
- 後台載入查詢減少 30%  
- 相容 Slim SEO、All in One SEO、Jetpack  
- 自動偵測永久連結變更，重建 rewrite rules  

**GEO 模組升級：**
- QAPage 結構化資料更穩定  
- 自動通知 Google / Bing 當新內容發布  
- 強化 AI Sitemap 快取與 404 回退  
- 更新搜尋引擎允許清單 (Googlebot, Bingbot, Perplexity, ChatGPTBot 等)  

**開發者友善：**
- 提供公用 getter（`get_router()`、`get_ai_client()` 等）  
- 新增掛鉤：`moelog_aiqna_answer_head`, `moelog_aiqna_render_output`  
- 快取與預生成模組可獨立執行（支援 CLI / Cron）  

---

== 安裝方式 ==

1. 將外掛資料夾上傳至 `/wp-content/plugins/moelog-ai-qna/`  
2. 啟用「Moelog AI Q&A Links」外掛  
3. 前往 **設定 → Moelog AI Q&A** 輸入 API Key、選擇模型與溫度  
4. （可選）啟用 **GEO 模式** 以產生結構化資料與 AI Sitemap  
5. 編輯文章，在「AI 問題清單」欄位中輸入問題（每行一題）  
6. 儲存文章後，問答清單會自動顯示在內文下方  

---

== 短碼說明 ==

| 短碼 | 功能 |
|------|------|
| `[moelog_aiqna]` | 顯示完整問題清單 |
| `[moelog_aiqna index="1"]` | 只顯示第 1 題 |
| `[moelog_aiqna index="3"]` | 只顯示第 3 題 |
| `[moelog_aiqna index="8"]` | 只顯示第 8 題 |

若文章中已使用短碼，系統會自動隱藏底部清單以避免重複顯示。

---

== GEO 模式（Generative Engine Optimization） ==

**讓你的 AI 回答能被 Google SGE / Bing Copilot / Perplexity 等搜尋引擎收錄。**

啟用 GEO 模式後，外掛將自動產生：
- **QAPage 結構化資料 (JSON-LD)**  
- **Open Graph / Twitter Card** Meta 標籤  
- **Breadcrumb 結構化導覽路徑**  
- **專屬 AI Q&A Sitemap**（`/ai-qa-sitemap.php`）  
- 自動 Ping Google / Bing 等主要搜尋引擎  
- 公開快取（支援 `s-maxage` / `stale-while-revalidate`）  

**啟用步驟：**
1. 前往 **設定 → Moelog AI Q&A → GEO 模組**  
2. 勾選「啟用結構化資料與 AI Sitemap」  
3. 前往 **設定 → 永久連結** 並點擊「儲存變更」  
4. 將 `/ai-qa-sitemap.php` 提交至 Google 與 Bing 搜尋主控台  

---

== 快取系統 ==

- AI 回答快取時間：**24 小時**  
- 可於 **設定 → Moelog AI Q&A → Cache Management** 清除快取  
- 快取鍵格式：`moe_aiqna_{hash(post_id|question|model|lang)}`  
- 可選操作：  
  - 清除所有快取  
  - 清除指定文章的快取  

---

== 螢幕截圖 ==

1. 管理頁面（API 與模型設定）  
2. 文章編輯頁的問題清單與短碼提示  
3. GEO 模組設定介面  
4. 前端問答清單顯示範例  
5. AI 回答頁（含打字動畫效果）  
6. 結構化資料與 AI Sitemap 範例  

---

== 版本更新紀錄 ==

= 1.8.0 (2025-10-18) =  
**完全模組化重構版**

- 全新模組架構（Core / Router / Renderer / AI Client / Cache / Admin / Metabox / Assets / Pregenerate）  
- 新增 Helpers 與 Typing.js 打字動畫  
- 移除所有 inline script，完全支援 CSP  
- 啟動效能 +45%，後台載入時間 -30%  
- GEO 模組優化、自動 Ping 搜尋引擎  
- 全新樣式與響應式排版  
- 相容 WordPress 6.7 / PHP 8.2  

= 1.6.3 (2025-10-15) =  
維護更新  
- 統一 Sitemap 檔案格式  
- 改進 GEO 模組初始化邏輯  
- 修正 SEO 外掛重複 meta 問題  

= 1.6.2 (2025-10-14) =  
短碼系統強化  
- 支援單題短碼與重複防護  
- 改善預抓取 script 注入機制  

= 1.6.1 (2025-10-13) =  
快取管理 + 打字動畫  
- 新增快取清除介面  
- 改善 URL 顯示與字元編碼  
- 更新 AI Prompt 引用規範  

= 1.6.0 (2025-10-12) =  
GEO 模組正式推出  
- 新增 QAPage 結構化資料與 OG / Twitter meta  
- 專屬 AI Sitemap 與自動 Ping  

---

== 升級提示 ==

= 1.8.0 =
**重大更新 – 全面模組化重構！**  
升級後請前往 **設定 → 永久連結** 並點擊「儲存變更」以重建 rewrite rules。  
舊版的問答資料（`_moelog_aiqna_questions`）將自動相容，無需手動轉換。

---

== 授權條款 ==

本外掛採用 GPL v2 或後續版本授權。  
可依相同條款自由修改與散布。  

© 2025 Horlicks / moelog.com
