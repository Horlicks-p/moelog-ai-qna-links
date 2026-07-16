# PR A4：Provider 安全相容與 2.0.4 發布驗收

## Provider contract

1. 執行 `php tests/unit/provider-request-contract-test.php`。
2. 在專用本機 WordPress 設定 `MOELOG_RUN_WP_SMOKE=1`，執行 `php tests/integration/a4-provider-smoke.php`；所有 provider HTTP 由 `pre_http_request` 攔截，不產生外部流量或費用。
3. Gemini request URL 不得包含 API key 或 `?key=`；`x-goog-api-key` header 必須存在。
4. Anthropic Opus 4.7、4.8、Sonnet 5 與未知自訂模型不得帶 `temperature`、`top_p`、`top_k`。
5. 只有 exact-ID legacy allowlist 或 `moelog_aiqna_anthropic_temperature_models` filter 明確加入的模型可帶 temperature；不得用名稱 regex 推測。
6. A4 不更新 Anthropic 預設模型，不新增任何 upgrade migration，也不改寫已保存 model ID。

### 已知相容行為

- `claude-3-5-sonnet-latest`、`claude-3-5-haiku-latest` 等浮動 alias 不在內建 temperature allowlist。升級後這些 alias 會採未知模型的保守參數集合，不傳非預設 temperature，因此輸出隨機度可能與舊版不同，但不會因外掛附加不支援參數而回傳 400。
- 不將浮動 alias 內建加入 allowlist，因供應商可在不改 alias 字串的情況下更換其指向與能力。站點若已針對目前 alias 做 contract test，可透過 `moelog_aiqna_anthropic_temperature_models` filter 加入該 exact string，並自行承擔日後 alias 漂移的相容性檢查。

## 版本與發布

1. 執行 `php tests/unit/version-consistency-test.php`。
2. Plugin header、`MOELOG_AIQNA_VERSION`、主檔 footer、README/readme stable tag 與 2.0.4 changelog 必須一致。
3. `Tested up to: 7.0` 採 WordPress.org major/minor 標記；本機實際版本為 WordPress 7.0.1，但仍未宣稱完整多版本 CI。
4. PO/POT `Project-Id-Version` 為 2.0.4，三個 MO 由 `gettext-po-mo`／gettext-parser 重新編譯。
5. 對全部 PHP 執行 `php -l`，對 `answer.js` 執行 `node --check`，並重跑 A1～A3 standalone regression tests。

## Smoke test

1. 在乾淨或專用本機 WordPress 執行：啟用、設定保存、公開答案 cache hit、無效／非公開答案 404、Feedback bootstrap/view/vote、停用、重新啟用。
2. 不以真實 API key 執行自動測試；若人工測 provider，使用可撤銷的測試 key 並確認 proxy/access log 無 Gemini key。
3. 確認升級前已保存的 model ID 原樣保留；2.0.4 新預設仍不覆寫既有設定。

## A4 明確不涵蓋

- Anthropic 預設從 4.7 升級到 4.8；需另做成本、輸出與 prompt smoke test。
- 完整 capability registry、結構化 provider result、`Retry-After` 與輸出 token 設定。
- URL token 格式／SEO migration、完整 Composer/PHPUnit/CI 與 deterministic ZIP automation。
