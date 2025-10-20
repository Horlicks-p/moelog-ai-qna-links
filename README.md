=== Moelog AI Q&A Links ===  

作者: Horlicks  
作者連結: https://www.moelog.com/  
標籤: AI, OpenAI, Gemini, Claude, ChatGPT, Anthropic, Q&A, GPT, AI Answer, SEO, Schema, 結構化資料  
Wordprss: 6.8.3  
最低 PHP 版本: 7.4  
目前版本: 1.8.2  
授權條款: GPLv2 或更新版本  
授權網址: https://www.gnu.org/licenses/gpl-2.0.html  

== 🧠 外掛說明 ==

**Moelog AI Q&A Links** 能在文章或頁面底部自動加入互動式的「AI 問答清單」。  
讀者點擊問題後，將開啟新分頁顯示由 **OpenAI**、**Google Gemini** 或 **Anthropic Claude** 即時生成的 AI 回答。

回答頁具備：乾淨的 HTML 佈局、打字動畫效果、內建快取（含靜態檔案）、  
以及可選的 **結構化資料模式（Structured Data Mode）**，  
讓搜尋與 AI 爬蟲能更正確解析頁面內容。

---

== ✨ 主要特色 ==

✅ 自動在文章底部加入 AI 問答清單  
✅ 支援 `[moelog_aiqna index="N"]` 短碼，可個別插入單一問題  
✅ 同時支援 **OpenAI / Gemini / Claude (Anthropic)** 三大供應商  
✅ 可自訂 System Prompt、模型、溫度與語言  
✅ 自動偵測語言（繁中 / 日文 / 英文）  
✅ AI 回答頁支援打字動畫效果  
✅ 內建快取系統（預設 24 小時 TTL，可自訂 1–365 天，含 transient + 靜態檔）  
✅ **智慧預生成機制：僅在內容變化時重新生成，節省 API tokens**  
✅ 後台快取管理介面：可清除全部或單篇快取  
✅ 結構化資料模式（Structured Data Mode）：加入 QAPage / Breadcrumb Schema、Canonical、Robots、快取標頭  
✅ 與主要 SEO 外掛（Slim SEO / AIOSEO / Jetpack）相容，避免重複 OG / Meta 標籤  
✅ 完整符合 CSP（Content Security Policy）安全規範  
✅ 模組化架構（Core / Router / Renderer / Cache / Admin / Assets / Pregenerate）  
✅ 相容 Cloudflare / Proxy 架構的 IP 偵測  

---

== ⚙️ 結構化資料模式（Structured Data Mode） ==

「結構化資料模式」可讓搜尋引擎與 AI 爬蟲更容易正確解析 AI 問答頁面，但不保證索引或排名提升。

啟用後將會：
- 加入 QAPage / Breadcrumb 結構化資料  
- 加入 Canonical（指回原文）  
- Robots 自動設定為：`index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1`  
- 輸出 Cache-Control / Last-Modified 標頭（CDN 友善）  
- 生成 AI 問答 Sitemap (`ai-qa-sitemap.php`)，並自動 ping Google / Bing  

未啟用（預設狀態）時：
- 使用 `noindex,follow` 以避免重複內容  
- 仍輸出結構化資料供爬蟲解析  
- 不生成 Sitemap、不進行 ping  

---

== 🧩 短碼用法 ==

| 短碼 | 說明 |
|------|------|
| `[moelog_aiqna]` | 顯示完整問題清單 |
| `[moelog_aiqna index="1"]` | 只顯示第 1 題 |
| `[moelog_aiqna index="3"]` | 只顯示第 3 題（1–8 皆可） |

若文章中已使用短碼，系統會自動隱藏底部自動清單以避免重複顯示。

---

== 🧮 快取系統 ==

- 預設 TTL 為 24 小時  
- 可於後台設定自訂快取時間（1–365 天）  
- 同時使用 WordPress transient + 靜態檔案雙層快取  
- 後台提供快取清除工具  
- 自動輸出 CDN 友善的 Cache-Control 標頭  
- 支援 `stale-while-revalidate` 讓快取重建更平滑  
- 智慧預生成：使用 **內容雜湊（content hash）** 偵測變化，僅在必要時重新生成  

---

== ⚙️ 效能與穩定性 ==

- 啟動時間提升約 45%  
- 後台查詢減少約 30%  
- 智慧預生成大幅降低不必要的 API 呼叫  
- 完全模組化架構：Core / Router / Renderer / Cache / Admin / Assets / Pregenerate  
- 可安全與主要 SEO 外掛並存（Slim SEO / AIOSEO / Jetpack）  
- 自動防止重複的 Open Graph / Meta 標籤  
- 啟用時自動刷新 rewrite 規則  
- 支援多語系內容（UTF-8）  

---

== 🔐 安全性 ==

- 具備 CSP（Content Security Policy）與 nonce 驗證  
- 所有輸出皆經 `esc_html` / `esc_attr` 過濾  
- 使用 HMAC 驗證快取完整性  
- 具備 IP 基礎的請求速率限制  
- 透過 HTTPS 與官方 API 通訊  
- 無收集任何使用者個資  
- 符合 GDPR 與隱私規範  

---

== 💬 隱私聲明 ==

本外掛僅傳送以下資料至 AI 服務提供者（OpenAI / Gemini / Claude）：
- 問題內容（由網站作者預先定義）  
- （可選）文章內容（若勾選「包含文章內容」）  
- 系統提示詞與語言設定  

不會傳送任何訪客個資。所有資料皆透過 HTTPS 加密傳輸。

---

== 🧩 更新記錄 ==

= 1.8.2 (2025-10-20) – 智慧預生成優化與錯誤修正 =  
**新增功能：**  
- ✨ 智慧預生成機制：使用 **內容雜湊（content hash）** 偵測變化  
- ✨ 僅在文章內容或問題清單變更時才重新生成答案，節省 API tokens  
- ✨ 新增 `skip_clear` transient 標記以避免誤刪快取  
- ✨ 分離自動清除與手動清除邏輯，保護 content_hash 不被誤刪  

**錯誤修正：**  
- 🔧 修正 `schedule_single_task()` 方法名稱不一致導致的致命錯誤  
- 🔧 修正匿名函式無法使用 `$this` 的問題，改為使用 class method  
- 🔧 修正 `save_post` hook 參數數量不匹配問題  
- 🔧 修正每次更新文章都觸發預生成的問題  
- 🔧 改善 `clear_post_cache()` 邏輯，避免清除 content_hash  

**改進項目：**  
- 📝 優化 Debug Log 訊息，清楚顯示預生成狀態  
- 📝 新增「SKIP pregenerate: unchanged」日誌訊息  
- 🎯 改善預生成排程機制，增加節流與去重檢查  
- 🎯 優化 Metabox 快取清除流程  

= 1.8.1 (2025-10-19) – 新增 Claude AI 支援 =  
- 新增 Anthropic Claude (claude.ai) 供應商  
- 支援 Claude Sonnet 4.5 模型  
- 統一 API Key 欄位（A 方案）  
- 修正 system 屬性與 messages 格式  
- 改進 max_tokens 與錯誤回傳日誌  
- 更新後台「快速連結」新增 Claude AI 控制台入口  

= 1.8.0 (2025-10-18) – 完全模組化重構 =  
- 全面重構 Core / Router / Renderer / Cache / Admin / Assets 架構  
- 新增 helpers-template.php 模板輔助函式  
- 可調整快取時間（1–365 天）  
- 新增結構化資料模式（Structured Data Mode）  
- 加入 Canonical 指向原文、強化 Robots 控制  
- Sitemap 改為 .php 結尾以避免外掛衝突  
- 強化安全性與轉義處理  
- 更新後台 UI 與內嵌說明文字  

---

== 🧭 支援與回報 ==

Bug 回報與功能建議：  
🌐 官方網站：https://www.moelog.com/  
💻 GitHub：https://github.com/Horlicks-p/moelog-ai-qna-links  

---

== 🧩 授權 ==

本外掛採用 **GPL v2 或更新版本授權**。  
您可自由修改或重新發布。  
© 2025 Horlicks / moelog.com
