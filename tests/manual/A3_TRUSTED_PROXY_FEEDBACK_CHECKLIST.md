# PR A3：可信代理與 Feedback 基本防護驗收

## 可信代理設定

預設不信任任何轉送標頭，只使用 `REMOTE_ADDR`。若站點確實位於受控 proxy/CDN 後方，可在 `wp-config.php` 定義 IP／CIDR：

```php
define('MOELOG_AIQNA_TRUSTED_PROXIES', [
    '10.0.0.0/8',
    '2001:db8:ffff::/48',
]);
```

也可使用 `moelog_aiqna_trusted_proxies` filter。只應填入實際直連 WordPress 主機的可信 proxy 網段，不應填訪客網段或 `0.0.0.0/0`。

## 自動驗證

1. `php tests/unit/client-ip-test.php`。
2. `php tests/integration/a3-feedback-smoke.php`，需在專用本機 WordPress 設定 `MOELOG_RUN_WP_SMOKE=1`。
3. 對全部 PHP 檔案執行 `php -l`。

## IP 政策矩陣

1. 未設定 trusted proxies 時，偽造 `CF-Connecting-IP`／`X-Forwarded-For` 不改變解析結果。
2. `REMOTE_ADDR` 命中可信 IPv4／IPv6 CIDR 時，Cloudflare 單值標頭及多層 XFF 可解析正確訪客。
3. XFF 從最靠近主機的一端反向跳過可信 proxy，取第一個不可信的有效 IP。
4. 無效 direct peer 回 `0.0.0.0`，不信任其 forwarded headers。
5. Renderer AI 限流與 Feedback 全部共用 `Moelog_AIQnA_Client_IP`。

## Feedback 關聯與濫用防護

1. bootstrap、view、vote、report 只接受公開、無密碼文章目前存在的前 8 個問題。
2. 伺服器只接受嚴格 16 位十六進位 hash，再逐題重算 `Moelog_AIQnA_Cache::generate_hash()`；任意／畸形 hash、錯誤 question 或非公開文章不能取得 nonce、讀寫統計或寄信。
3. 任意 hash 不得建立 `_moelog_aiqna_feedback_stats_{hash}` meta。
4. 同一匿名識別／問題 24 小時內重送 view 不重複累加。
5. vote 不讀取前端 `previous_vote`：相同票重送不累加，like↔dislike 由伺服器 transient 狀態切換。
6. bootstrap、view、vote 分別有每小時基本限流；report 每匿名識別每小時 3 次，另有全站每小時 30 次保險絲。
7. report 郵件與 Renderer 限流 debug log 不保存完整 IP，只包含帶站點 salt 的匿名識別前 16 字元。

## 2026-07-16 本機執行記錄

- PHP 8.2.12：`client-ip-test.php` 通過 10 assertions，涵蓋直連 spoof、IPv4／IPv6 CIDR、Cloudflare-style header 與多層 XFF。
- WordPress 7.0／Apache：feedback smoke test 通過 14 endpoint cases 與 1 limiter assertion；任意 hash 未建立 post meta，view/vote 重送具基本冪等性。
- 測試不送 report 郵件、不呼叫 AI；暫存文章、feedback meta、transients 與清理排程由腳本移除。

## A3 明確限制

- 統計仍使用 post meta read-modify-write，高併發原子計數與專用資料表留待後續。
- 匿名 vote state 保存 30 天；到期後同一訪客可再次計票。
- transient 限流是基本近似防護，不是跨節點強一致的 WAF。
- Cloudflare／其他 CDN 網段不硬編碼；管理員必須維護實際可信來源 CIDR。
