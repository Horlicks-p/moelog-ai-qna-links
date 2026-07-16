# PR A2：Apache 靜態快取旁路封鎖驗收

## 前置稽核結論

動工前已全文搜尋 `.html`、cache path、URL、rewrite、sitemap、canonical 與 prefetch：

- 正常答案 cache hit 由 `Moelog_AIQnA_Renderer::render_answer_page()` 呼叫 `Moelog_AIQnA_Cache::load()`，再由 PHP 輸出。
- 前台問題連結、prefetch 與答案 sitemap 都使用 `Moelog_AIQnA_Router::build_url()` 產生 `/qna/...` URL。
- Canonical 指向原始文章 permalink。
- 沒有任何正式前台連結、rewrite、redirect、sitemap 或 canonical 輸出 `wp-content/{static_dir}/*.html` URL。
- 修補前對既有實體 cache URL 的直接 HTTP 請求實測為 200，旁路可重現。

因此封鎖 cache 目錄 HTTP 存取不會中斷正常答案 URL，也不影響 PHP filesystem cache hit。

## 自動驗證

1. 執行 `php tests/unit/cache-protection-test.php`。
2. 在專用本機 WordPress／Apache 測試站設定 `MOELOG_RUN_WP_SMOKE=1`，執行 `php tests/integration/a2-cache-protection-smoke.php`。
3. 對全部 PHP 檔案執行 `php -l`。

## 升級驗收

1. 在既有 cache 目錄放置舊規則 `Require all granted`，並清除 `moelog_aiqna_cache_protection_version` option。
2. 載入外掛後確認 `.htaccess` 被重寫，舊 allow 規則消失。
3. 確認 option 只在保護檔成功寫入後更新；再次載入不重寫相同檔案。
4. 將 cache 目錄設為不可寫時，確認 upgrade flag 不會被寫成完成，且 `Cache::save()` 不會新增完整 HTML。

## HTTP 與功能回歸

1. 直接請求任一 `wp-content/{static_dir}/*.html`，Apache 應回 403 或 404，response body 不得包含 cache 內容。
2. 使用正常 `/qna/...` URL 命中相同 cache，仍應由 PHP 回 200 並顯示答案。
3. 確認 sitemap、canonical、文章底部問題連結與 hover prefetch 均未輸出實體 `.html` URL。
4. Apache 2.4 使用 `Require all denied`；舊版相容路徑使用 `Order Allow,Deny`／`Deny from all`。

## 2026-07-16 本機執行記錄

- PHP 8.2.12：`cache-protection-test.php` 通過 7 assertions。
- WordPress 7.0／Apache：cache protection smoke test 通過 6 assertions；正常 `/qna/...` cache hit 回 200，實體 `.html` URL 被拒絕且未洩漏 marker。
- 測試只使用預先寫入的 cache，不產生 cache miss 或付費 provider 呼叫。
- 暫存文章、HTML cache 與相關清理排程由腳本移除。

## A2 明確不涵蓋

- Nginx、Caddy 與不讀 `.htaccess` 的主機。
- 將 cache 搬離 web root。
- 動態 `static_dir` 設定失效修正。
- 一般答案 HTML 的原子寫入；A2 只保證 protection file 優先使用同目錄暫存檔與 rename。
