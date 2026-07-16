# Moelog AI Q&A Links 程式碼檢視與現代化計畫

## 1. 文件目的

本文件記錄 2026-07-16 對 Moelog AI Q&A Links 外掛的靜態檢視結果，並將安全性、功能正確性、架構、相容性、測試及未來發展建議整理成可分階段執行的工作計畫。

本次檢視未修改外掛程式碼，也未在完整 WordPress 執行環境中進行動態或滲透測試。公司檢視環境先前回報所有 PHP 檔案已在 PHP 8.5 通過語法檢查；2026-07-16 另於目前工作區使用 PHP 8.2.12 重跑 26 個 PHP 檔案，亦全數通過 `php -l`。開始 Milestone A 前，repository 沒有 `tests/`、`composer.json`、PHPUnit／WordPress test suite 或自動化品質檢查流程，因此 PHP 8.5 結果仍應由 CI 或可重現的 PHP 8.5 環境再次驗證。

## 2. 整體評估

外掛已有 Core、Router、Renderer、Cache、AI Client、Admin、Metabox、Pregenerate 等功能分層，輸出大多有適當 escaping，AI 回覆也使用 Parsedown safe mode 及 `wp_kses()` 做二次過濾。因此不建議立即全面重寫，而應先處理會造成未公開內容暴露、API 額度濫用、設定失效及快取不一致的問題，再逐步重構。

建議執行順序如下：

1. 封鎖未公開文章的答案路由。
2. 建立可信代理政策並強化公開回饋端點；URL token 改造另案評估，不阻塞緊急安全版。
3. 修正動態設定、版本與快取失效邏輯。
4. 補齊生命週期清理及測試、CI、發布流程。
5. 重構 provider、模板及資料儲存架構。
6. 再評估 WordPress 原生 AI Client／Connectors API 等未來整合。

目前只承諾執行 Milestone A 與 B。Milestone C、D 是候選方向，必須在 A、B 完成後依實際使用量、維護成本及功能價值重新決定，不預先承諾 service container、專用資料表、WP-CLI 或企業級完整 CI matrix。

### 2.1 第二次檢視（Claude 建議）核對結果

本節記錄後續收到的 Claude 程式碼檢視建議，並以目前程式碼及供應商官方文件重新核對。其大部分觀察成立，且已整合到本計畫；以下列出需特別保留或校正的判斷。

確認成立：

- `pretty_base`／`static_dir` 因常數過早定義而實際失效。
- 修復動態目錄後，uninstall、debug admin bar、feedback orphan cleanup 等所有使用 `STATIC_DIR` 常數的位置都必須同步調整，不能只修 Router 與 Cache。
- activation 在環境需求檢查前便寫入 option 及 secret，執行順序應反轉。
- Renderer 與 Feedback Controller 無條件信任 proxy headers，AI 費用限流與郵件限流都能被繞過。
- vote／view 缺乏伺服器端資料關聯驗證、去重及限流，nonce 本身不能防止灌票。
- Anthropic 以 `/-4[-.]/` 判斷是否省略 temperature 不具前瞻性。官方目前說明 Claude Opus 4.7 之後（包含 Opus 4.8）及 Claude Sonnet 5 對非預設 `temperature`、`top_p`、`top_k` 會回傳 400；應由 model capability metadata 決定參數，而不是用名稱 regex 猜測。
- Anthropic `max_tokens` 預設 1024，且目前設定流程沒有真正傳入可覆寫值；長回答可能被截斷。
- `is_error_message()` 依翻譯後字串及關鍵字推測成功／失敗，可能把未知錯誤快取成答案，也可能把正常短回答誤判為錯誤。
- `moelog_aiqna_html_tag()` 的 `$content` 不轉義且目前沒有呼叫點，屬於容易被未來誤用的危險 helper。
- robots index policy 與 UA bot blocking 現在由同一個 STM／GEO mode 間接綁在一起，應拆成獨立政策。

需要校正或補充：

- 原計畫中的「非公開文章可能透過答案路由暴露」仍是最高優先級；第二次檢視沒有涵蓋這條完整攻擊路徑，不能因其他建議而降級。
- API key 解密不會因「更換佈景」失效。真正會造成既有 AES-CBC 與 XOR 格式無法解密的是 `AUTH_KEY`、`SECURE_AUTH_KEY`、`LOGGED_IN_KEY`、`NONCE_KEY` 或相關 salts 改變。計畫應提供 key rotation／重新輸入流程，而不是警告使用者不要更換佈景。
- `gpt-4o-mini` 與 `gemini-2.5-flash` 目前仍是可用的正式模型；「不是最新世代」不等於應直接替換。Google 目前同時把 `gemini-2.5-flash` 列為穩定且適合高量、低延遲工作，也已有更新的 `gemini-3.5-flash`。預設模型調整必須比較成本、延遲、輸出及 API 相容性。
- `claude-opus-4-7` 已非 Anthropic 最新建議模型，但不能只把字串改成 `claude-opus-4-8` 就視為完成。應一併測試 sampling 參數、錯誤回應、成本、輸出長度，且不得默默改寫既有使用者已保存的 model ID。
- 若提高最低 PHP 版本，不建議在 2026 年提高到已 EOL 的 PHP 8.0 或 8.1。應依實際使用者分布選擇繼續支援 7.4，或在下一個 major 明確提高到仍受安全維護的版本（例如 8.2／8.3），並以 CI 資料決策。PHP 8.2 的安全支援也只到 2026-12-31，若改版時間接近該日期，應優先考慮 8.3。
- 「純邏輯不需要 WordPress 就能測」只在先把邏輯從 WordPress functions、constants 及靜態全域狀態抽離後成立；現況仍需測試 stubs、Brain Monkey 或 WordPress integration test suite。

官方核對資料：

- Anthropic model deprecations：<https://platform.claude.com/docs/en/docs/about-claude/model-deprecations>
- Anthropic current models：<https://platform.claude.com/docs/en/about-claude/models/overview>
- Anthropic Opus 4.7→4.8 migration：<https://platform.claude.com/docs/en/about-claude/models/migration-guide>
- Gemini 2.5 Flash：<https://ai.google.dev/gemini-api/docs/models/gemini-2.5-flash>
- Gemini current models：<https://ai.google.dev/gemini-api/docs/models>
- OpenAI API model／endpoint support：<https://platform.openai.com/docs/models>
- PHP supported versions：<https://www.php.net/supported-versions.php>

### 2.2 第三次現況核對與執行決策

2026-07-16 再次以目前分支逐項抽查後，下列關鍵指控均可由實際程式碼重現，不是推測性建議：

- `moelog-ai-qna.php` 的 top-level fallback 早於 `plugins_loaded` 執行，Router 與 Cache 又將值固化為 class constant，因此 `pretty_base`／`static_dir` 設定實際失效。
- Router 的公開 URL HMAC 只取 3 個十六進位字元，共 4096 種組合，約 12 位元。
- Post Cache 使用未檢查文章狀態的 `get_post()`；Router 又在 access policy 前讀取問題 meta，Renderer 也只檢查文章是否存在，形成未公開內容進入 cache／AI 流程的完整路徑。
- Renderer Security 與 Feedback Controller 都會在未驗證直連來源的情況下優先採用 `CF-Connecting-IP`／`X-Forwarded-For`。
- Gemini API key 位於 request URL query string。
- Anthropic 以 `preg_match('/-4[-.]/', $model)` 推測 sampling 能力。
- plugin header 2.0.2 與程式常數／readme 2.0.3 不一致。

因此 Milestone A 可以執行，但六個安全修補項目不合併成單一大型變更，而是依第 9 節拆成四個可獨立審查、驗收及回滾的 PR。版本統一為 2.0.4 是發布收尾工作，不另算第七個安全功能。

Milestone A 的測試策略採務實分層：每個 PR 必須附手動驗收清單，並盡量加入不需要完整 WordPress test suite 的純邏輯／contract 測試；從零建立 Composer、PHPUnit 與 WordPress integration test suite 的完整基礎設施留在 Milestone B，不阻塞緊急修補發布。

## 3. Phase 0：建立安全基線

優先級：Critical / High

### 3.1 阻止草稿、私人及非公開文章被答案路由讀取

現況：

- 路由會依文章 ID 直接讀取問題 meta。
- `Moelog_AIQnA_Post_Cache::get()` 使用 `get_post()`，沒有驗證文章狀態。
- Renderer 取得文章後，可能把標題與正文送往外部 AI provider。
- 公開 URL 的 HMAC 僅保留 3 個十六進位字元，只有約 12 位元強度。

建議作法：

- Router 第一階段只正規化路由輸入及識別文章，不先讀取問題 meta；取得文章並通過統一可見性政策後，才允許讀取問題 meta、解析 token、讀取靜態快取或呼叫 AI。
- 一般公開請求必須通過 `is_post_publicly_viewable()`。
- 草稿預覽只允許已登入且具有該文章 `read_post` 或 `edit_post` 權限的使用者。
- 404、403 與無效 token 應採一致回應，避免成為文章 ID 或狀態探測器。
- Milestone A 先落實 access policy 與一致的 404 行為，不把 URL 改造綁進緊急安全版；只要未公開文章在 token 解析、快取及 AI 呼叫前被拒絕，短 HMAC 就不再是文章存取控制的唯一防線。
- URL token 強度與格式另案評估。若決定加長，建議至少保留 128 位元，例如 32 個十六進位字元。
- 公開 permalink token 只綁定穩定識別資料，例如文章 ID 與問題的穩定 ID／文字；不要加入內容版本或到期時間，以免每次編輯或時間到期都改變 SEO URL。
- 內容版本應放在 answer cache fingerprint，不放在公開 URL。
- 任何 URL 格式變更都必須先完成長期舊格式解析與逐頁 301 redirect；canonical、sitemap、內部連結及已收錄頁面遷移需一併驗證。
- URL token 改造與答案頁是否 index 是兩個獨立決策，不因 SEO 設定而降低 access policy。

驗收條件：

- 未登入使用者無法透過答案路由取得 draft、pending、private、trash 或不存在文章的任何內容。
- 有權限的編輯者仍可使用明確的預覽流程。
- 自動測試涵蓋所有文章狀態、錯誤 token 與 token 竄改；若後續改 URL，再加入舊 token 及 301 migration 測試。
- 未公開文章不會觸發外部 API 呼叫，也不會讀取或產生公開靜態快取。

### 3.2 建立可信代理 IP 政策

現況：

- Renderer 與 Feedback Controller 優先信任 `CF-Connecting-IP` 及 `X-Forwarded-For`。
- 未確認直接連線來源是否為可信代理，攻擊者可能偽造標頭繞過 AI 或郵件限流。

建議作法：

- 預設只使用 `REMOTE_ADDR`。
- 只有當 `REMOTE_ADDR` 位於管理員設定或常數指定的可信代理／CDN 網段時，才解析轉送標頭。
- 將 IP 解析集中到單一服務，避免 Renderer 與 Feedback Controller 各自實作。
- 設計 IPv4、IPv6、Cloudflare、多層 proxy 及無效標頭測試。
- 日誌中避免長期保存完整 IP；若只為限流，可保存帶站點 salt 的雜湊。
- 增加不依賴 IP 的站點級每日／每月 AI 呼叫上限及管理員可設定的費用保險絲。
- UA 黑名單只保留為禮貌性流量控制，不視為安全或成本防線。

驗收條件：

- 直接對站點偽造 `X-Forwarded-For` 不會改變限流識別結果。
- 受信任代理環境仍可正確取得訪客 IP。
- 所有公開限流功能共用同一套解析邏輯。

### 3.3 強化公開回饋與瀏覽統計端點

現況：

- 公開 bootstrap 端點可取得 nonce，但 nonce 只能防 CSRF，不能提供授權或資料關聯驗證。
- `post_id` 與 `question_hash` 未驗證是否屬於公開文章及其實際問題。
- 投票及瀏覽統計可重複提交，且可能用任意 hash 建立大量 post meta。
- 計數採 read-modify-write，並行請求可能互相覆蓋。

建議作法：

- bootstrap、view、vote、report 全部驗證文章可公開瀏覽。
- 由伺服器重新計算 question hash，禁止信任前端任意提供的 hash。
- 驗證該問題仍存在於文章 meta，並限制每篇文章可接受的問題數。
- 對 view、vote、report 分別設計合理的伺服器端限流。
- 投票應具冪等性；同一匿名識別在同一問題上只能保有一個目前票態。
- 若統計需長期可靠保存，使用具有唯一索引及原子更新的專用資料表。
- 若只需近似統計，可使用 object cache／transient 聚合後批次寫入。
- 報告郵件加入站點級總量限制，避免大量不同 IP 造成郵件轟炸。

驗收條件：

- 任意 `post_id` 或 `question_hash` 不會新增不受控的 post meta。
- 同一訪客重送相同 vote 不會重複累加。
- 高併發測試不會出現明顯遺失更新。
- 草稿、私人文章及不存在問題的回饋請求會被拒絕。

### 3.4 改善靜態 HTML 快取安全模型

執行屬性：安全 easy win；可先做低風險封鎖，再進行跨伺服器完整整理。

現況：

- 完整 HTML 快取存放於可公開瀏覽的 `wp-content/{static_dir}`。
- 產生的 `.htaccess` 允許直接存取 `.html`。
- 直接存取會繞過 WordPress 路由、文章可見性、bot 管制及動態安全標頭。
- `.htaccess` 對 Nginx、Caddy 或部分代管平台不生效。

建議作法：

- 目前正常快取命中本來就是 PHP 在 `template_redirect` 內以 `Cache::load()` 讀檔後輸出，程式與文件沒有把 `.html` 當成正式公開 URL；因此移到 web root 外通常沒有額外的應用層效能代價。
- 立即修補：Apache `.htaccess` 移除對 `.html` 的 `Require all granted`，改成拒絕直接存取；既有 `.htaccess` 也要有 upgrade routine 重寫，不能只影響新目錄。
- 完整方案：優先把快取移到經驗證、可寫且位於 web root 外的路徑，經過授權與路由檢查後由 PHP 輸出，這同時涵蓋 Nginx、Caddy 與不讀 `.htaccess` 的代管環境；不能假設廉價主機或受管 WordPress 一定提供這類路徑。
- 若部署限制無法安全使用 web root 外路徑，必須採明確的安全退路：提供 Nginx／Caddy deny 設定與啟用時健康檢查，或改存不可獨立交付的資料／fragment。健康檢查無法確認直接存取已封鎖時，不應產生完整公開 HTML 快取。
- 不依賴單一伺服器的 `.htaccess` 作為安全邊界。
- 檔案寫入使用暫存檔加原子 rename，避免讀取到半寫入內容。
- 檔案刪除的 realpath 驗證應比對目錄邊界，而不是只做字串 prefix。
- 靜態快取須綁定文章公開狀態與統一 cache fingerprint。

驗收條件：

- 猜測快取檔名不能繞過 WordPress 的可見性與安全標頭。
- Apache、Nginx 與無 `.htaccess` 環境行為一致。
- 並行產生與讀取不會輸出不完整 HTML。

## 4. Phase 1：修正功能正確性與快取一致性

優先級：High / Medium

### 4.1 統一外掛版本

現況：plugin header 為 2.0.2，程式常數及 readme stable tag 為 2.0.3。

建議作法：

- 使用單一版本來源產生 plugin header、常數、readme、資產版本及發布標籤。
- 在 CI 加入版本一致性檢查。

驗收條件：

- 所有發布中繼資料、常數、readme、壓縮包名稱及 Git tag 版本一致。

### 4.2 修正 pretty base 與 static directory 設定失效

現況：

- 程式預定在 `plugins_loaded` 後從 option 定義常數。
- 但在 hook 執行前已把常數定義為 `qna` 與 `ai-answers`，後續設定值無法覆蓋。
- Router 與 Cache 又將常數保存為 class constant，進一步固化執行期設定。

建議作法：

- 移除過早定義的 fallback 常數。
- Router、Cache 改以建構參數、設定物件或 runtime getter 取得值。
- 設定保存後清除 request/object cache，並只在路由真的改變時安排一次 rewrite flush。
- static directory 變更時定義舊目錄遷移或清理政策。

驗收條件：

- 修改 pretty base 後新網址確實採用新路徑。
- 修改 static directory 後新快取確實寫入新目錄。
- 同一 request 不會因設定物件快取而讀到舊值。
- rewrite rules 只在必要時刷新一次。

### 4.3 建立完整 cache fingerprint 與失效策略

現況：

- AI transient key 未完整包含 provider、temperature、system prompt、提示版本等輸入。
- 完整 HTML 靜態快取只依文章 ID 與問題建立檔名。
- 改變模型、顯示設定、STM、模板或外掛版本後可能繼續顯示舊結果。

Answer fingerprint（失效會產生付費重生成）至少包含：

- 文章 ID、文章內容 hash、問題與語言。
- provider、model、實際送出的生成參數及 max chars。
- system prompt、citation 規則及 prompt schema 版本。
- 是否包含文章正文。
- 任何會改變 AI 輸入或答案文字的 filters 所需版本；若無法自動判定，提供開發者自訂 answer salt filter。

Page fingerprint（失效只重新渲染，不呼叫 AI）至少包含：

- answer cache key／answer fingerprint。
- Renderer／模板／前端資產版本。
- 外掛顯示層版本、STM 狀態、robots／Schema／banner／disclaimer 等頁面設定。
- 任何只改變 HTML 表現的 filters 所需版本，並提供自訂 page salt filter。

建議作法：

- 將 AI answer cache 與 rendered page cache 分成兩層，各自使用明確 fingerprint。
- 模板、Renderer、前端資產、STM、robots、Schema 或純顯示設定變更時，只失效 page cache，從既有 answer transient 免費重新渲染。
- provider、model、prompt、citation 規則、是否包含正文或文章內容 hash 變更時，才失效 answer cache並允許付費重生成。
- 不把一般外掛版本號直接放進 answer fingerprint；升級外掛不得無條件觸發全站 AI 重生成。只有實際改變 AI 輸入語意時才提升獨立的 prompt schema／answer schema 版本。
- 若 answer transient 已過期但舊 page cache 因其他原因被重建，應套用費用保險絲與 generation lock，不能在一次升級 request 中批量重生成。
- 保存 cache metadata，讓管理員能知道內容由何種模型與設定生成。
- 使用 generation lock 或 single-flight，避免同一未命中請求同時呼叫多次付費 API。

驗收條件：

- provider、model、生成參數、prompt 或正文改變後不會命中不相容 answer cache。
- 模板、外掛顯示版本或 STM 改變時可重建頁面，但不會因此呼叫付費 provider。
- 多個同時請求同一答案時最多產生一次外部 API 呼叫。
- 管理畫面可解釋快取命中、版本及失效原因。

### 4.4 避免 Gemini API key 出現在 URL

現況：Gemini key 被附加於 endpoint query string，可能進入代理、伺服器或除錯日誌。

建議作法：

- 改用 Google 支援的 `x-goog-api-key` request header。
- provider request log 必須統一遮蔽 authorization、API key、cookie 及其他 secrets。
- 對錯誤 response 設定大小上限，避免完整第三方回應無限制寫入日誌。

驗收條件：

- request URL、WordPress debug log、反向代理 access log 均不包含 API key。

### 4.5 改善 API key 本地保存

現況：

- AES-256-CBC 沒有額外完整性驗證。
- OpenSSL 不可用或加密失敗時會退化為 XOR 混淆甚至明文保存。

建議作法：

- 優先支援 `wp-config.php` 常數或環境 secrets。
- 資料庫保存改用 Sodium `secretbox` 或 AES-GCM 等 authenticated encryption。
- ciphertext 加入版本與演算法識別，支援日後金鑰輪替及遷移。
- 加密不可用或失敗時拒絕保存，清楚通知管理員，不再靜默降級為明文。
- 不要在設定頁把加密 ciphertext 當成使用者輸入重新送回前端。
- 提供 WordPress authentication keys／salts 變更後的明確復原流程，例如要求管理員重新輸入 API key；更換佈景不影響解密，不應顯示錯誤警告。
- 完成舊格式遷移後，逐步淘汰以 base64 格式特徵猜測 legacy ciphertext 的啟發式偵測。

驗收條件：

- ciphertext 遭竄改時解密必定失敗。
- 加密失敗不會保存明文。
- 舊版 ciphertext 有明確、可回復的 migration test。

### 4.6 改用 capability 驅動的 AI 請求參數

現況：Anthropic 以模型 ID 是否符合 `/-4[-.]/` 決定要不要傳送 temperature。這只涵蓋部分 Claude 4 命名，無法涵蓋 Sonnet 5 等新模型；官方已明確說明 Opus 4.7／4.8 與 Sonnet 5 不接受非預設 sampling 參數。

建議作法：

- 短期安全修補可對 Anthropic 預設省略 `temperature`、`top_p`、`top_k`，只對明確確認支援的舊模型送出。
- 長期由 Model Registry 保存 `supports_temperature`、`max_output_tokens`、`supports_system`、API 版本等 capability metadata。
- 不使用模型名稱 regex 推測能力。
- 對管理員自訂的未知 model ID 採保守參數集合，並在連線測試中清楚顯示 provider 回應。
- 各 provider 的參數建構加入 contract tests。

驗收條件：

- Claude Opus 4.7、4.8、Sonnet 5 及未知新模型不會因外掛自行附加 temperature 而收到 400。
- 已知支援 temperature 的 provider／model 仍能依設定傳值。

### 4.7 使用結構化 Provider Result

現況：provider 成功與錯誤都回傳字串，再由 `is_error_message()` 比對翻譯字串、長度與關鍵字。未知或較長的錯誤可能被當成答案快取；正常短回答也可能誤判。

建議作法：

- provider 統一回傳 value object 或明確陣列，例如 `ok`、`text`、`error_code`、`http_status`、`retryable`、`usage`、`provider_request_id`。
- cache 層只接受 `ok === true` 的結果。
- UI 層將穩定 error code 映射成翻譯訊息，不再以翻譯字串反推程式狀態。
- `is_error_message()` 僅在相容舊 filter／舊 provider adapter 時作最後防線，並安排 deprecated 時程。

驗收條件：

- 所有 provider error paths 都不會寫入 answer 或 static cache。
- 翻譯檔變更不會改變成功／失敗判定。

### 4.8 分離 SEO 索引政策與 bot blocking

現況：STM／GEO mode 同時影響 robots meta、sitemap、Schema、cache headers 及 bot allow/block 行為，站長無法獨立決定答案頁是否收錄。

建議作法：

- 新增獨立的答案頁索引政策，至少提供 `noindex, follow` 與 `index, follow`。
- sitemap 只收錄允許 index 且公開的答案頁。
- Schema／Open Graph、HTTP cache、bot blocking 不應暗中改變 robots policy。
- 以 robots meta／HTTP `X-Robots-Tag` 作為索引政策；UA 403 不能替代 SEO 控制。
- 預設保持 `noindex, follow`，升級時不默默改變既有站點索引行為。

驗收條件：

- 索引、Schema、sitemap、cache 與 bot policy 可以分別設定且組合行為有測試。
- 關閉索引時 sitemap 不會列出答案頁。

### 4.9 清理未使用且容易誤用的 HTML helper

`moelog_aiqna_html_tag()` 目前沒有呼叫點，且 `$content` 直接拼接。建議優先移除；若保留，函式名稱／PHPDoc 必須明示內容為 trusted HTML，或預設 `esc_html()` 並另設明確的 trusted HTML API。標籤名稱也應採 allowlist，而不只使用 `esc_attr()`。

## 5. Phase 2：WordPress 生命週期與資料管理

優先級：Medium

### 5.1 啟用、停用及卸載流程

建議作法：

- activation/deactivation hooks 保持在主檔 top-level。
- 啟用時先驗證最低環境需求，再寫入 rewrite flag、secret、option 或建立目錄，避免失敗後留下半成品。
- 停用時以 `wp_clear_scheduled_hook()` 清除 pregeneration、async cache、ping 等所有自有 hooks。
- 卸載移至 `uninstall.php`，先檢查 `WP_UNINSTALL_PLUGIN`。
- 卸載涵蓋 settings、secret、DB/schema version、rewrite flag、內容 hash、previous status、feedback meta、所有相關 transients、cron 及快取檔。
- 動態 static directory 修復後，uninstall、debug admin bar、feedback orphan cleanup、cache statistics 等所有目錄消費者必須使用同一個經驗證的 path service；卸載時讀取保存設定，並在設定遺失時安全處理歷史目錄，不能回退成任意路徑刪除。
- 提供「卸載時保留資料」選項，避免使用者誤刪大量已生成答案或統計。
- multisite 環境明確決定逐站點清除或 network-wide 行為。

驗收條件：

- 啟用、停用、重新啟用及卸載均無 fatal、notice 或殘留排程。
- 保留資料選項的兩種路徑都有整合測試。
- multisite network activation／uninstall 行為有明確測試。

### 5.2 正式使用 Settings API

建議作法：

- 使用 `register_setting()` 的明確 `type`、`default`、`sanitize_callback` 及必要 schema。
- STM 使用獨立 setting 註冊，不在另一 setting 的 sanitize callback 中直接讀取 `$_POST` 和 `update_option()`。
- 所有輸入採明確 key、`wp_unslash()`、驗證與 sanitization。
- capability checks 與 nonce 各司其職；nonce 不視為授權。
- sanitize callback 避免執行昂貴、非冪等或會再次保存 option 的副作用。

驗收條件：

- 各分頁只更新自己負責的欄位，不會因缺少 checkbox 或欄位而重設其他設定。
- 非 `manage_options` 使用者無法保存或執行快取管理操作。

### 5.3 資料 migration 與 schema version

建議作法：

- 定義單一 DB/schema version 常數。
- migrations 必須可重複執行、逐版本前進，且失敗時不提前寫入完成版本。
- 大型 post meta 遷移採批次、WP-CLI 或 Action Scheduler，避免 admin request timeout。
- migration 前後記錄數量及結果，但不得記錄 secrets 或文章正文。

## 6. Phase 3：架構重構

優先級：Medium / Long-term

### 6.1 Provider adapter

將目前大型 `Moelog_AIQnA_AI_Client` 拆成：

- `ProviderInterface`
- `OpenAIProvider`
- `GeminiProvider`
- `AnthropicProvider`
- `ProviderRegistry`
- 共用的 HTTP retry、error mapping、redaction 及 observability 層

provider metadata 應描述：

- model ID、生命週期與建議用途。
- 是否支援 temperature、system instruction、最大輸出及其他參數。
- endpoint/API 版本。
- 預設 timeout、可重試狀態碼與 `Retry-After` 支援。
- 成本或能力分類，例如 economy、balanced、quality，而非只提供單一硬編碼模型。
- 建議輸出 token 上限及 provider 的最大允許值。

避免以模型名稱 regex 推測能力。模型清單應可更新，並允許使用者輸入完整 model ID；預設值變更不應覆蓋既有設定。

目前 Anthropic 的 1024 `max_tokens` 對回答加參考資料可能偏低。短期可將預設調整為經實測的 2048，並提供有上下限的管理設定；長期應依 model capability 與站點成本政策決定。提高上限前需測試回答品質、延遲、截斷率及費用，不能只改常數。

### 6.2 Router、Renderer 與模板責任切分

建議作法：

- Router 只負責匹配、token 驗證及輸入正規化。
- Access Policy 負責文章可見性與預覽權限。
- Answer Service 負責 cache、生成鎖及 provider 呼叫。
- Renderer 負責建立 view model。
- Template 只負責輸出，避免在模板內做資料存取與商業邏輯。
- 合併 `templates/answer-page.php` 與 inline template 的重複頁面結構。
- 改用 `wp_enqueue_script()`／`wp_enqueue_style()` 與外部資產，逐步移除大型 inline JS/CSS。
- 現有 CSP 的 `style-src 'unsafe-inline'` 在目前大量 inline style 架構下可視為相容性取捨，不列為立即漏洞；待資產整理後以 nonce/hash 或外部 stylesheet 逐步收緊，先用 Report-Only 驗證避免破壞主題相容性。

### 6.3 統計資料儲存

若 feedback 是核心功能，建議建立專用資料表，欄位可包含：

- post ID
- question ID 或穩定 UUID
- views、likes、dislikes
- created/updated timestamp
- 唯一索引 `(post_id, question_id)`

匿名投票狀態可另以短期、隱私友善的雜湊識別保存。資料表 migration、retention、export／erase 及 multisite 行為需同步設計。

### 6.4 降低全域副作用

- 保留單一 bootstrap。
- 避免在檔案載入階段註冊散落的匿名 hooks。
- 使用 loader 或 service container 集中註冊 hooks。
- admin-only 類別只在 admin hooks 中初始化。
- 減少 `$GLOBALS` 與全域 helper；保留相容 wrapper 並標註 deprecated 週期。
- 目前 `Moelog_AIQnA_*` 前綴符合 WordPress 外掛慣例，namespace／PSR-4 不列為安全修補前置條件；若在 major version 導入，需提供相容 facade 並避免不必要的大規模 churn。

## 7. Phase 4：測試、品質與發布流程

優先級：High；Milestone A 僅同步建立最小驗收與可行的純邏輯／contract tests，完整測試基礎自 Milestone B 開始

### 7.1 從零建立測試基礎

現況：

- Milestone A 開始前 repository 沒有 `tests/` 目錄，也沒有可沿用的測試草稿。
- PR A1 已開始加入可直接執行的 standalone 純邏輯測試、受環境變數保護的本機 WordPress HTTP smoke test 與手動驗收清單。
- 仍缺少 `composer.json`、PHPUnit 設定、通用測試 bootstrap 及 WordPress test suite，現有 A1 測試不能視為完整 unit／integration regression suite。

建議作法：

- Milestone A 先為每個 PR 維護可重現的手動驗收清單，並只加入可在不啟動完整 WordPress test suite 下可靠執行的純邏輯／provider contract 測試；不要為了緊急版一次導入完整企業級測試矩陣。
- 加入 Composer dev dependencies、PHPUnit 及 WordPress test suite。
- unit tests 聚焦純函式、hash、token、設定正規化及 provider mapping。
- integration tests 驗證 hooks、Settings API、文章狀態、AJAX、cron、cache、activation 及 uninstall。
- HTTP API 使用 `pre_http_request` mock，不在測試中真的呼叫付費 provider。
- 完整 Composer／PHPUnit／WordPress integration test 基礎列為 Milestone B 的交付項目；Milestone A 不得以不存在的自動化測試冒充驗證完成。

### 7.2 必要安全回歸測試

- 所有文章狀態與權限矩陣。
- URL token 竄改與暴力枚舉風險；只有決定改 URL 格式時才啟用舊 URL 解析、逐頁 301、canonical 及 sitemap migration 測試。
- 偽造 proxy headers。
- 任意 feedback hash、重複投票及 meta flooding。
- AI 回覆 Markdown XSS payload。
- custom template filter 的安全邊界。
- cache path traversal、symlink 與直接檔案存取。
- API key ciphertext 竄改及日誌 redaction。

### 7.3 CI matrix

建議至少涵蓋：

- PHP 7.4、8.1、8.2、8.3、8.4、8.5；其中 EOL 版本用於驗證既有宣告相容性，不能被描述成建議的新部署版本。
- WordPress 6.8、6.9、7.0，以及允許失敗的最新 nightly。
- 單站與 multisite。
- Apache-like 與不依賴 `.htaccess` 的快取測試。

執行項目：

- PHPUnit unit/integration tests。
- PHP syntax check。
- WordPress Coding Standards。
- PHPCompatibilityWP。
- WordPress Plugin Check。
- JavaScript lint／測試。
- 版本一致性、翻譯檔、release package allowlist 及 ZIP smoke test。

### 7.4 發布流程

- 由單一版本檔或發布 script 更新所有版本資訊。
- 建立 deterministic ZIP，只包含執行所需檔案。
- 排除 `.git`、測試、開發設定、暫存與本機 agent 資料。
- 在乾淨 WordPress 環境進行安裝、啟用、設定保存、生成、停用與卸載 smoke test。
- 維護 changelog、upgrade notice、安全修補說明及資料 migration 注意事項。

## 8. Phase 5：相容性與未來展望

### 8.1 WordPress 與 PHP 支援政策

截至檢視日期，WordPress 最新穩定版為 7.0.1。建議：

- 保留 PHP 7.4 最低需求時，CI 必須持續覆蓋 PHP 7.4。
- 建議正式運行環境採 PHP 8.3 以上。
- `Tested up to` 應在實際測試後更新，不只修改 readme 數字。
- 評估 WordPress 7 的 AI Client／Connectors API，以 feature detection 提供 adapter，同時保留舊版 WordPress 的直接 provider 實作。

### 8.2 AI provider 生命週期

- 模型更新速度遠高於外掛發布速度，不宜永久硬編碼單一預設模型。
- 建立 model registry 更新機制、管理員健康檢查及 deprecation notice。
- 提供成本優先、平衡、品質優先等建議選項。
- 保留已保存的 model ID；新版預設只套用到新安裝或尚未選擇模型的站點。
- Claude 官方確認 Opus 4.7→4.8 對已能執行 4.7 的 request surface 沒有額外 breaking API changes；因此可在 Milestone B 經基本 contract／prompt smoke test 後，將「新安裝或未保存 model ID」的 Anthropic 預設改為 `claude-opus-4-8`，不必延後到 Milestone D。
- Opus 4.8 仍有非 breaking 的行為差異，應重跑輸出、提示詞、延遲及成本 smoke test；既有站點已保存的 `claude-opus-4-7` 不自動改寫。
- Sonnet 5、Gemini 3.5 Flash 等其他新模型仍先做 API contract、成本及輸出回歸測試，再決定是否列為選項或預設。不要只因世代較新便自動遷移。
- provider 錯誤應區分認證、配額、限流、模型退休、安全拒絕及暫時服務錯誤。
- 遵循 `Retry-After`，只重試冪等且適合重試的錯誤。
- 記錄 token usage、延遲及估計成本，但不得記錄 API key 或完整私人文章內容。

### 8.3 隱私與內容治理

- 在設定頁清楚告知文章內容可能傳送至第三方 AI provider。
- 提供只傳標題／摘要、排除特定 post type、分類或文章的控制。
- 明確說明快取、回饋 IP 雜湊及報告郵件的保存期限。
- 提供資料匯出、清除與隱私政策建議文字。
- 對醫療、法律、財務等內容提供可設定的免責聲明或停用策略。

### 8.4 可觀測性與成本控制

- 為每次 generation 建立不含敏感資訊的 request ID。
- 記錄 provider、model、cache hit、耗時、重試、錯誤類型及 token usage。
- 設定站點每日／每月生成上限及管理員告警。
- 提供 WP-CLI 指令：cache status、cache clear、pregenerate、queue list、health check。
- 背景工作需可重入、可取消、可觀察，並避免依賴前台流量才能及時執行。

## 9. 建議里程碑

### Milestone A：2.0.4 短期安全修補版

目標是小範圍、可快速審查與發布，不等待完整架構重構。六個安全修補項目拆成下列四個 PR；PR 可依相依關係依序合併，但每個 PR 必須可獨立驗收及回滾。

#### PR A1：文章公開政策與一致 404

範圍：

- 建立單一 access policy；一般公開請求只允許 `is_post_publicly_viewable()` 為真的文章。
- 調整流程，使 Router 在 access policy 前不讀取問題 meta；通過政策後才解析問題／token、讀取 cache 或準備 AI context。
- draft、pending、private、trash、不存在文章及無效 token 採一致 404，避免文章 ID／狀態探測。
- 密碼保護文章不得進入公開答案路由、AI／cache 流程或答案 sitemap；WordPress 5.0～5.6 fallback 必須沿用 Core 的 post-type viewability 語意，不能誤殺內建 Page。
- 保留既有公開 URL 格式，不在本 PR 加長 token 或導入 301 migration。

驗收邊界：

- 未登入請求無法取得任何非公開文章的問題、答案或靜態快取，也不會觸發外部 AI 呼叫。
- 內建 Post／Page 在支援的 WordPress 版本維持正常；密碼保護文章的答案 URL 不出現在 sitemap。
- 已發布文章的既有 URL 仍正常工作。
- 附文章狀態／權限矩陣的手動驗收記錄；可抽離的 policy／token 邏輯加入最小測試。

#### PR A2：Apache 靜態快取旁路封鎖

範圍：

- cache 目錄 `.htaccess` 改為拒絕直接讀取 `.html`。
- 加入 upgrade routine，安全重寫既有 cache 目錄的保護檔，而非只保護新目錄。
- 驗證正常答案 URL 仍由 WordPress／PHP 輸出既有快取。
- 若同時碰觸寫檔流程，採暫存檔加原子 rename；不在本 PR 搬遷整個快取架構。

驗收邊界：

- Apache 環境直接猜測 `.html` 路徑會被拒絕，正常答案路由仍能命中快取。
- upgrade 前已存在的 cache 目錄也會更新保護檔。
- Nginx／Caddy、web root 外搬遷與受管主機安全退路明確保留給 Milestone B，不宣稱本 PR 已解決所有 web server。

#### PR A3：可信代理與 Feedback 基本防護

範圍：

- 集中 client IP 解析；預設只採 `REMOTE_ADDR`，僅在直連來源符合明確設定的可信代理／CDN 網段時解析轉送標頭。
- bootstrap、view、vote、report 全部驗證文章公開狀態、實際問題關聯，並由伺服器重算／比對 question hash。
- 為 view、vote、report 加入基本伺服器端限流，限制可建立的 question meta；report 另加站點級總量防護。
- 不再把前端 `previous_vote` 視為可信狀態；若無法在不擴張資料模型下完成可靠冪等性，先採拒絕重複／保守計數並記錄限制。

驗收邊界：

- 偽造 `CF-Connecting-IP`／`X-Forwarded-For` 不會改變直連訪客的限流識別。
- 任意 `post_id`／`question_hash` 不會建立不受控 post meta；非公開文章與不存在問題一律拒絕。
- 專用資料表、跨程序原子計數與完整版匿名投票冪等性不屬於本 PR。

#### PR A4：Provider 安全相容與 2.0.4 發布收尾

範圍：

- Gemini API key 從 query string 移到 `x-goog-api-key` header，確認 request URL 與除錯輸出不含 key。
- Anthropic 對 Opus 4.7／4.8、Sonnet 5 及未知新模型採保守 request surface，省略非預設 sampling 參數；不以新的名稱 regex 擴大猜測。
- 保留既有使用者已保存的 model ID，不在安全版默默遷移模型。
- 統一 plugin header、程式常數及 readme stable tag 為 2.0.4，完成發布前手動 smoke test。

驗收邊界：

- Gemini request URL 不含 API key，header 正確帶入 key。
- 已知新世代與未知 Anthropic model request 不會由外掛附加非預設 sampling 參數。
- 版本中繼資料一致；安裝、啟用、既有公開答案、三個 provider request mock／contract 及停用流程完成手動驗收。

Milestone A 的測試交付為四份可重現的手動驗收清單，加上能在現有工作區可靠執行的最小純邏輯／contract tests。完整 Composer、PHPUnit、WordPress integration suite 與 CI matrix 不阻塞 2.0.4，但必須在驗收記錄中明示哪些路徑仍是手動測試。

下列項目雖重要，但不得阻塞 2.0.4：URL token／301 SEO 遷移、站點級 AI 費用保險絲、feedback 專用資料表與完整原子計數、完整 provider 重構、動態 model registry 及整套 CI。完成 2.0.4 後排入 Milestone B 重新排序。

### Milestone B：正確性與穩定版

- 版本一致性自動檢查與單一發布版本來源。
- pretty base／static directory 正常生效。
- 完成 Nginx／Caddy 等非 Apache 環境的直接存取封鎖，並規劃跨伺服器移出 web root；不重複 Milestone A 已完成的 Apache 修補。
- 明確分層的 answer/page cache fingerprint、設定失效及 generation lock。
- 站點級每日／每月 AI 呼叫與費用保險絲。
- 結構化 provider result，禁止錯誤內容寫入答案快取。
- 可設定且有成本護欄的輸出 token 上限。
- 將新安裝／未設定站點的 Anthropic 預設更新為 `claude-opus-4-8`，保留既有站點已保存模型。
- URL token 強度、舊 URL 長期解析及逐頁 301 SEO 遷移另案評估；在方案與回歸測試完整前不改公開 permalink。
- cron、停用與卸載清理。
- 可執行的 PHPUnit／WordPress integration tests。

### Milestone C：架構整理版

Milestone C 是 A、B 完成後才重新估算的選配工作，不是目前發布承諾。只有實際維護痛點或使用量足以支持成本時才啟動：

- provider adapters。
- Access Policy／Answer Service 分層。
- 合併模板。
- Settings API 正規化。
- feedback 專用資料表（若決定保留此功能）。

### Milestone D：現代化版

Milestone D 是長期候選方向，不因 WordPress 或模型世代更新便自動啟動：

- WordPress 7 AI Client／Connectors adapter。
- 動態 model registry 與 deprecation health check。
- 成本、使用量與健康監控。
- WP-CLI 與完整 CI／release automation。

## 10. 完成定義

### 10.1 Milestone A 完成

- PR A1～A4 的個別驗收邊界全部通過，且沒有把未完成項目描述成已由自動化測試涵蓋。
- 未公開文章不會進入問題 meta、公開 cache 或 AI 呼叫流程。
- Apache 既有及新 cache 目錄均拒絕直接讀取完整 HTML。
- 公開 feedback 端點具有文章／問題關聯驗證、可信 IP 基本限流與 meta flooding 防護。
- Gemini key 不出現在 URL；Anthropic 新世代／未知模型使用保守 sampling request surface。
- 2.0.4 所有版本中繼資料一致，且發布前 smoke test 完成。

### 10.2 Milestone B 完成

- 動態設定、answer/page cache fingerprint、generation lock、生命週期清理及費用保險絲均依第 9 節驗收。
- Composer／PHPUnit／WordPress integration test 基礎可在文件化環境重現執行。
- 非 Apache 快取安全退路已實作及驗證，不能只提供未驗證的設定範例。

### 10.3 長期完成目標

若後續決定執行 Milestone C、D，完整長期目標如下；這些條件不是 2.0.4 或 Milestone B 的發布阻塞條件：

- 未公開文章及其衍生答案不會透過任何公開路徑、快取或回饋 API 暴露。
- 公開端點具備資料關聯驗證、可信限流及可接受的濫用防護。
- 所有會改變答案或頁面的設定都能正確使不相容快取失效。
- API credentials 不出現在 URL、HTML 或日誌，且資料庫密文具有完整性保護。
- 啟用、停用、升級、卸載及 multisite 流程均有測試。
- 支援版本的 WordPress／PHP CI 全數通過。
- 發布包版本一致、可重現，且能在乾淨站點完成 smoke test。

## 11. 本次檢視的重要程式位置

- 主入口與生命週期：`moelog-ai-qna.php`
- 路由與短 HMAC：`includes/class-router.php`
- 文章讀取：`includes/class-post-cache.php`
- AI 生成與文章 context：`includes/class-renderer.php`
- 公開回饋 AJAX：`includes/class-feedback-controller.php`
- IP 與安全標頭：`includes/class-renderer-security.php`
- 靜態快取：`includes/class-cache.php`
- provider HTTP 請求：`includes/class-ai-client.php`
- API key 保存：`includes/helpers-encryption.php`
- Settings API：`includes/class-admin-settings.php`
- STM、Schema 與 sitemap：`moelog-ai-geo.php`
- 現有測試草稿：`tests/`
