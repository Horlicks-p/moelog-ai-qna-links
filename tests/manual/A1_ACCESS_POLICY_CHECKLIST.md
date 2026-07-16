# PR A1：文章公開政策手動驗收

## 前置準備

1. 在乾淨的 WordPress 測試站啟用外掛，建立具有相同問題的文章狀態樣本：`publish`、`draft`、`pending`、`private`、`trash`。
2. 另建立一篇已發布但有文章密碼的樣本，以及一篇正常公開文章。
3. 為每篇樣本記錄以目前短 token 格式產生的答案 URL；A1 不修改 URL 格式。
4. 使用 `pre_http_request` 或測試 provider 記錄 AI 呼叫次數，避免真的產生付費請求。

## 未登入公開請求矩陣

| 狀態 | 預期 HTTP | 可讀問題／答案 | 讀取靜態快取 | AI 呼叫 |
|---|---:|---|---|---:|
| publish、有效 token | 200 | 是 | 可 | 僅 cache miss 時一次 |
| publish、錯誤 token | 404 | 否 | 否 | 0 |
| draft | 404 | 否 | 否 | 0 |
| pending | 404 | 否 | 否 | 0 |
| private | 404 | 否 | 否 | 0 |
| trash | 404 | 否 | 否 | 0 |
| password protected | 404 | 否 | 否 | 0 |
| 不存在文章 ID | 404 | 否 | 否 | 0 |

確認所有 404 使用相同頁面標題、訊息與 cache headers，不洩漏文章是否存在或其狀態。

## 已登入使用者

1. 以管理員、編輯者及作者重試 draft／private URL，仍應得到一致 404；A1 尚未提供明確且隔離的答案預覽路由。
2. 正常 WordPress 文章預覽不應受到影響，但預覽文章不會觸發公開答案 cache 或 AI 生成。

## Router 執行順序

1. 對 draft／private／不存在文章請求加上 `get_post_meta()` 監測，確認問題 meta 未被讀取。
2. 確認只有文章通過 `Moelog_AIQnA_Access_Policy` 後才執行 `resolve_request()`。
3. 確認無效 token 不會進入 `Moelog_AIQnA_Cache::load()` 或 `generate_answer()`。

## 預生成競態

1. 對已發布文章安排預生成，執行 cron 前把文章改為 draft 或 private。
2. 執行排程，確認不呼叫 provider、不建立新靜態快取。
3. 把文章重新發布後再次排程，確認正常生成。

## 回歸檢查

1. 公開文章既有答案 URL 保持不變並可正常顯示。
2. 公開文章 cache hit 與 cache miss 均正常。
3. `?pf=1` 只有在公開文章及 token 驗證通過後回傳 204；非公開文章、無效 token 及不存在文章仍回傳一致 404，且不讀取未授權文章 meta、cache 或呼叫 AI。
4. Sitemap 包含具有問題的正常公開文章，但不包含密碼保護文章的答案 URL。
5. 執行 `php tests/unit/access-policy-test.php`。
6. 執行 `php tests/unit/access-policy-fallback-test.php`，確認 WordPress 5.0～5.6 fallback 接受內建 Page。
7. 執行 `php tests/unit/router-access-order-test.php`。
8. 在專用本機 WordPress 測試站設定 `MOELOG_RUN_WP_SMOKE=1`，執行 `php tests/integration/a1-wordpress-smoke.php`；腳本會建立並刪除暫存文章。
9. 對全部 PHP 檔案執行 `php -l`。

## 2026-07-16 本機執行記錄

- PHP 8.2.12：`access-policy-test.php` 通過 10 assertions。
- PHP 8.2.12：`access-policy-fallback-test.php` 通過 7 assertions，包含內建 Page。
- PHP 8.2.12：`router-access-order-test.php` 通過 3 assertions。
- WordPress 7.0／Apache：HTTP smoke test 通過 8 cases，另通過公開／密碼保護文章 sitemap 2 assertions；公開有效 URL 命中預先建立的測試快取，未請求公開 cache miss，因此未呼叫付費 provider。
- 31 個 PHP 檔案全數通過 `php -l`。
- HTTP smoke test 的暫存文章、測試 cache 與相關清理排程均由腳本清除。

## 發布說明備註

- 無效或缺少路由參數原本回傳 400，A1 起刻意與不存在／非公開文章／無效 token 統一回傳 404，以降低文章狀態探測。
- 預生成排程建立階段仍只檢查 `publish`；執行階段及 renderer 會重驗密碼與公開政策，因此至多產生一次無效排程，不會呼叫 provider 或產生答案快取。可在後續正確性整理時消除此排程浪費。
