# 數據流與流程圖

本文檔使用圖表詳細說明插件中的數據流動和業務流程。

## 📋 目錄

- [完整答案生成流程](#完整答案生成流程)
- [路由處理流程](#路由處理流程)
- [快取策略流程](#快取策略流程)
- [預生成流程](#預生成流程)
- [安全驗證流程](#安全驗證流程)
- [錯誤處理流程](#錯誤處理流程)

---

## 🔄 完整答案生成流程

這是從用戶訪問答案頁到顯示內容的完整流程。

```mermaid
graph TB
    Start([用戶訪問 /qna/xxx]) --> WP[WordPress 路由解析]
    WP --> Router{Router 驗證}

    Router -->|URL 格式錯誤| Error404[返回 404]
    Router -->|HMAC 驗證失敗| Error403[返回 403]
    Router -->|驗證成功| ParseURL[解析 URL 參數]

    ParseURL --> ExtractData[提取 Post ID 和 Question]
    ExtractData --> CheckStatic{檢查靜態 HTML 快取}

    CheckStatic -->|快取存在且未過期| LoadStatic[載入靜態檔案]
    LoadStatic --> ReplaceNonce[替換動態內容 CSP Nonce]
    ReplaceNonce --> OutputHTML[輸出 HTML]

    CheckStatic -->|快取不存在或已過期| CheckTransient{檢查 Transient 快取}

    CheckTransient -->|Transient 存在| GetAnswer[取得 AI 答案]
    CheckTransient -->|Transient 不存在| CallAI[調用 AI API]

    CallAI --> PreparePrompt[準備 System Prompt]
    PreparePrompt --> DetectLang[偵測語言]
    DetectLang --> GetContent{包含文章內容?}

    GetContent -->|是| LoadPost[載入文章內容]
    GetContent -->|否| BuildPrompt[建立用戶 Prompt]
    LoadPost --> TruncateContent[截斷內容]
    TruncateContent --> BuildPrompt

    BuildPrompt --> SendAPI[發送 API 請求]
    SendAPI --> WaitResponse{等待 API 響應}

    WaitResponse -->|成功| ParseResponse[解析響應]
    WaitResponse -->|失敗| Retry{重試次數 < 3?}

    Retry -->|是| ExponentialBackoff[指數退避]
    ExponentialBackoff --> SendAPI
    Retry -->|否| ErrorResponse[返回錯誤訊息]

    ParseResponse --> SaveTransient[保存 Transient 快取]
    SaveTransient --> GetAnswer

    GetAnswer --> ConvertMD[Markdown 轉 HTML]
    ConvertMD --> ApplySecurity[應用安全過濾]
    ApplySecurity --> LoadTemplate[載入答案頁模板]
    LoadTemplate --> RenderHTML[渲染 HTML]
    RenderHTML --> SaveStatic[保存靜態快取]
    SaveStatic --> InjectNonce[注入 CSP Nonce]
    InjectNonce --> OutputHTML

    OutputHTML --> End([顯示答案頁])
    Error404 --> End
    Error403 --> End
    ErrorResponse --> End
```

---

## 🛤️ 路由處理流程

WordPress 如何將 URL 路由到插件處理器。

```mermaid
sequenceDiagram
    participant U as 用戶
    participant WP as WordPress
    participant RW as Rewrite Engine
    participant R as Router
    participant V as Validator
    participant RN as Renderer

    U->>WP: GET /qna/example-abc-7b/
    WP->>RW: 解析 URL
    RW->>WP: 匹配 rewrite rule

    Note over WP: query_vars:<br/>moe_ai=1<br/>moe_slug=example-abc-7b

    WP->>R: template_redirect hook
    R->>R: 檢查 moe_ai

    alt moe_ai 不存在
        R-->>WP: 繼續 WordPress 流程
    else moe_ai = 1
        R->>R: parse_slug(moe_slug)

        Note over R: 解析結果:<br/>post_id=123<br/>hash=abc<br/>slug=example

        R->>V: verify_signature(123, question, abc)

        alt 簽名無效
            V-->>R: false
            R->>U: HTTP 403 Forbidden
        else 簽名有效
            V-->>R: true
            R->>R: 設置全域變數
            R->>RN: render_answer_page(123, question)
            RN->>RN: 生成 HTML
            RN->>U: 輸出答案頁
        end
    end
```

---

## 💾 快取策略流程

雙層快取系統如何運作。

```mermaid
graph TB
    Start([請求答案]) --> L1{第一層: 靜態 HTML}

    L1 -->|存在| CheckExpire1{已過期?}
    CheckExpire1 -->|否| Return1[返回 HTML]
    CheckExpire1 -->|是| L2

    L1 -->|不存在| L2{第二層: Transient}

    L2 -->|存在| CheckExpire2{已過期?}
    CheckExpire2 -->|否| Render[渲染 HTML]
    CheckExpire2 -->|是| Generate

    L2 -->|不存在| Generate[調用 AI 生成]

    Generate --> SaveL2[保存 Transient]
    SaveL2 --> Render

    Render --> SaveL1[保存靜態 HTML]
    SaveL1 --> Return2[返回 HTML]

    Return1 --> End([完成])
    Return2 --> End

    style L1 fill:#e1f5ff
    style L2 fill:#fff3e0
    style Generate fill:#ffebee
```

**快取層級說明**:

| 層級 | 類型                | 速度        | TTL            | 適用場景                 |
| ---- | ------------------- | ----------- | -------------- | ------------------------ |
| L1   | 靜態 HTML 檔案      | ⚡⚡⚡ 極快 | 30 天 (可設定) | 完整答案頁               |
| L2   | WordPress Transient | ⚡⚡ 快     | 24 小時        | AI 生成的答案 (Markdown) |
| L0   | 對象快取 (可選)     | ⚡⚡⚡ 極快 | 視伺服器設定   | Redis/Memcached          |

---

## 🔄 預生成流程

文章發布或更新時自動預生成答案。

```mermaid
sequenceDiagram
    participant U as 用戶
    participant WP as WordPress
    participant C as Core
    participant P as Pregenerate
    participant AI as AI_Client
    participant Cache as Cache

    U->>WP: 點擊"發布"或"更新"
    WP->>WP: save_post hook
    WP->>C: handle_save_post_pregenerate()

    C->>C: 檢查是否需要預生成

    alt 自動預生成已關閉
        C-->>WP: 跳過
    else 自動預生成已開啟
        C->>C: 取得問題列表

        alt 沒有問題
            C-->>WP: 跳過
        else 有問題
            C->>WP: wp_schedule_single_event()

            Note over WP: 60秒後執行<br/>moelog_aiqna_pregenerate_event

            WP->>P: 執行排程任務
            P->>P: 取得問題列表

            loop 每個問題
                P->>Cache: 檢查快取是否存在

                alt 快取已存在
                    Cache-->>P: 跳過此問題
                else 快取不存在
                    P->>AI: generate_answer()
                    AI->>AI: 調用 API
                    AI-->>P: 返回答案
                    P->>Cache: 保存快取
                end
            end

            P->>P: 記錄統計
            P-->>WP: 預生成完成
        end
    end
```

**預生成觸發條件**:

```php
// 1. 文章發布
add_action('publish_post', 'trigger_pregenerate');

// 2. 文章更新 (內容有變化)
add_action('post_updated', 'trigger_pregenerate_on_content_change');

// 3. 問題列表變更
add_action('moelog_aiqna_metabox_saved', 'trigger_pregenerate');

// 4. 手動觸發 (後台按鈕)
add_action('wp_ajax_moelog_aiqna_pregenerate', 'manual_pregenerate');
```

---

## 🔒 安全驗證流程

URL 簽名驗證和內容安全處理。

### HMAC URL 簽名驗證

```mermaid
graph LR
    A[生成 URL] --> B[計算 HMAC]
    B --> C[取得 Secret Key]
    C --> D[組合數據:<br/>post_id | question]
    D --> E[HMAC-SHA256]
    E --> F[取前 3 個字符]
    F --> G[附加到 URL]

    H[用戶訪問] --> I[解析 URL]
    I --> J[提取參數]
    J --> K[重新計算 HMAC]
    K --> L{HMAC 匹配?}
    L -->|是| M[允許訪問]
    L -->|否| N[返回 403]

    style A fill:#e8f5e9
    style H fill:#fff3e0
    style N fill:#ffebee
```

**程式碼實現**:

```php
// 生成簽名
function generate_signature($post_id, $question) {
    $secret = get_option(MOELOG_AIQNA_SECRET_KEY);
    $data = $post_id . '|' . $question;
    $hash = hash_hmac('sha256', $data, $secret);
    return substr($hash, 0, 3);
}

// 驗證簽名
function verify_signature($post_id, $question, $provided_hash) {
    $expected_hash = generate_signature($post_id, $question);
    return hash_equals($expected_hash, $provided_hash);
}
```

### 內容安全策略 (CSP)

```mermaid
graph TB
    Start[AI 生成答案] --> Parse[解析 Markdown]
    Parse --> Filter[安全過濾]

    Filter --> RemoveJS[移除 on* 事件]
    RemoveJS --> SanitizeURL[清理 URL]
    SanitizeURL --> RemoveScript[移除 script 標籤]
    RemoveScript --> WhitelistTags[只允許安全標籤]

    WhitelistTags --> Template[載入模板]
    Template --> GenerateNonce[生成 CSP Nonce]
    GenerateNonce --> InjectNonce[注入 Nonce]

    InjectNonce --> SetHeader[設置 CSP Header]
    SetHeader --> Output[輸出安全的 HTML]

    style Filter fill:#ffe0b2
    style SetHeader fill:#e1f5fe
```

**允許的 HTML 標籤**:

```php
$allowed_tags = [
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'p', 'br', 'strong', 'em', 'u', 'del',
    'ul', 'ol', 'li',
    'blockquote', 'pre', 'code',
    'a' => ['href', 'title'],
    'img' => ['src', 'alt', 'title'],
    'table', 'thead', 'tbody', 'tr', 'th', 'td',
];
```

---

## ❌ 錯誤處理流程

API 調用失敗時的重試機制。

```mermaid
graph TB
    Start[發送 API 請求] --> SendRequest[HTTP POST]
    SendRequest --> Wait{等待響應}

    Wait -->|連線超時| TimeoutCheck{重試次數 < 3?}
    Wait -->|HTTP 錯誤| HTTPCheck{重試次數 < 3?}
    Wait -->|成功| ParseJSON[解析 JSON 響應]

    TimeoutCheck -->|是| Backoff1[延遲 2^n 秒]
    HTTPCheck -->|是| Backoff1

    Backoff1 --> IncAttempt1[嘗試次數 +1]
    IncAttempt1 --> SendRequest

    TimeoutCheck -->|否| LogError[記錄錯誤]
    HTTPCheck -->|否| LogError

    ParseJSON --> ValidateJSON{JSON 格式正確?}

    ValidateJSON -->|否| LogError
    ValidateJSON -->|是| CheckError{有錯誤訊息?}

    CheckError -->|是| ClassifyError{錯誤類型}
    CheckError -->|否| ExtractAnswer[提取答案]

    ClassifyError -->|配額超限| QuotaRetry{有備用 API?}
    ClassifyError -->|臨時錯誤| TempRetry{重試次數 < 3?}
    ClassifyError -->|永久錯誤| LogError

    QuotaRetry -->|是| SwitchAPI[切換 API 提供商]
    QuotaRetry -->|否| LogError

    TempRetry -->|是| Backoff2[延遲 2^n 秒]
    TempRetry -->|否| LogError

    SwitchAPI --> SendRequest
    Backoff2 --> IncAttempt2[嘗試次數 +1]
    IncAttempt2 --> SendRequest

    ExtractAnswer --> ValidateAnswer{答案有效?}

    ValidateAnswer -->|否| FallbackAnswer[使用後備答案]
    ValidateAnswer -->|是| Success[返回答案]

    LogError --> SendNotification{發送通知?}
    SendNotification -->|是| EmailAdmin[郵件通知管理員]
    SendNotification -->|否| FallbackAnswer

    EmailAdmin --> FallbackAnswer

    FallbackAnswer --> End([返回結果])
    Success --> End

    style SendRequest fill:#e3f2fd
    style LogError fill:#ffebee
    style Success fill:#e8f5e9
```

**重試策略**:

| 嘗試次數 | 延遲時間 | 說明                  |
| -------- | -------- | --------------------- |
| 1        | 0 秒     | 立即嘗試              |
| 2        | 2 秒     | 2^1 = 2 秒            |
| 3        | 4 秒     | 2^2 = 4 秒            |
| 4        | 8 秒     | 2^3 = 8 秒 (最後一次) |

**錯誤分類**:

```php
// 1. 臨時錯誤 (可重試)
$temporary_errors = [
    'rate_limit_exceeded',  // 速率限制
    'timeout',              // 超時
    'server_error',         // 伺服器錯誤 (5xx)
];

// 2. 永久錯誤 (不可重試)
$permanent_errors = [
    'invalid_api_key',      // API 金鑰無效
    'model_not_found',      // 模型不存在
    'invalid_request',      // 請求格式錯誤
];

// 3. 配額錯誤 (可切換提供商)
$quota_errors = [
    'quota_exceeded',       // 配額超限
    'insufficient_quota',   // 配額不足
];
```

---

## 📊 用戶互動流程

從用戶點擊問題到查看答案的完整體驗。

```mermaid
journey
    title 用戶體驗旅程
    section 發現問題
      閱讀文章: 5: 用戶
      滾動到底部: 4: 用戶
      看到問題清單: 5: 用戶
    section 選擇問題
      選擇感興趣的問題: 5: 用戶
      點擊問題連結: 5: 用戶
      新分頁開啟: 4: 系統
    section 等待答案
      載入頁面: 3: 系統
      顯示載入動畫: 3: 系統
      快取命中(快): 5: 系統
      快取未命中(慢): 2: 系統
    section 閱讀答案
      打字機動畫顯示: 5: 用戶, 系統
      閱讀答案內容: 5: 用戶
      點擊反饋按鈕: 4: 用戶
    section 返回或分享
      返回原文: 4: 用戶
      分享答案: 3: 用戶
```

---

## 🔄 快取失效與更新流程

當文章內容變更時如何處理快取。

```mermaid
stateDiagram-v2
    [*] --> 文章已發布

    文章已發布 --> 快取已保存: 生成答案
    快取已保存 --> 檢查變更: 文章更新

    檢查變更 --> 內容有變: 比較內容雜湊
    檢查變更 --> 內容未變: 比較內容雜湊

    內容未變 --> 快取已保存: 保持快取

    內容有變 --> 清除快取: 偵測到變更
    清除快取 --> 標記過期: 刪除靜態檔案
    標記過期 --> 排程預生成: 清除 Transient

    排程預生成 --> 生成新答案: 60秒後執行
    生成新答案 --> 快取已保存: 保存新快取

    快取已保存 --> [*]: 使用者訪問時<br/>直接返回快取
```

**內容雜湊計算**:

```php
function calculate_content_hash($post_id) {
    $post = get_post($post_id);
    $questions = get_post_meta($post_id, '_moelog_aiqna_questions', true);

    $data = implode('|', [
        $post->post_content,
        $post->post_title,
        $post->post_modified,
        serialize($questions),
    ]);

    return hash('sha256', $data);
}
```

---

## 📈 性能優化決策樹

根據不同場景選擇最佳策略。

```mermaid
graph TD
    Start{訪問類型?} --> FirstVisit[首次訪問]
    Start --> Returning[回訪用戶]

    FirstVisit --> CheckPregen{已預生成?}

    CheckPregen -->|是| FastPath[快速路徑:<br/>靜態快取]
    CheckPregen -->|否| SlowPath[慢速路徑:<br/>即時生成]

    SlowPath --> AsyncGen{啟用非同步?}
    AsyncGen -->|是| ShowPlaceholder[顯示佔位內容]
    AsyncGen -->|否| WaitGen[等待生成]

    ShowPlaceholder --> BackgroundGen[背景生成]
    BackgroundGen --> NotifyUser[通知用戶]

    WaitGen --> GenerateNow[立即生成]
    GenerateNow --> SaveCache[保存快取]

    Returning --> CDNCache{有 CDN?}

    CDNCache -->|是| CDNHit{CDN 命中?}
    CDNCache -->|否| ServerCache

    CDNHit -->|是| UltraFast[超快:<br/>CDN 邊緣]
    CDNHit -->|否| ServerCache[伺服器快取]

    ServerCache --> FastPath

    FastPath --> Render[渲染答案]
    SaveCache --> Render
    NotifyUser --> Render
    UltraFast --> Render

    Render --> End[顯示給用戶]

    style UltraFast fill:#c8e6c9
    style FastPath fill:#fff9c4
    style SlowPath fill:#ffccbc
```

---

## 🔍 除錯流程

開發者如何追蹤和除錯問題。

```mermaid
graph TB
    Issue[發現問題] --> EnableDebug[啟用 WP_DEBUG]
    EnableDebug --> CheckLogs{查看日誌}

    CheckLogs --> FoundLogs[找到錯誤日誌]
    CheckLogs --> NoLogs[沒有日誌]

    FoundLogs --> AnalyzeError{錯誤類型?}

    AnalyzeError --> APIError[API 錯誤]
    AnalyzeError --> CacheError[快取錯誤]
    AnalyzeError --> RenderError[渲染錯誤]

    APIError --> TestAPI[測試 API 連線]
    TestAPI --> FixAPI[修復 API 設定]

    CacheError --> CheckPerms[檢查檔案權限]
    CheckPerms --> FixPerms[修復權限]

    RenderError --> CheckTemplate[檢查模板]
    CheckTemplate --> FixTemplate[修復模板]

    NoLogs --> AddLogging[添加除錯日誌]
    AddLogging --> ReprodBug[重現問題]
    ReprodBug --> CheckLogs

    FixAPI --> Test[測試修復]
    FixPerms --> Test
    FixTemplate --> Test

    Test --> Works{問題解決?}
    Works -->|是| CleanUp[清理除錯代碼]
    Works -->|否| DeepDive[深入分析]

    DeepDive --> AddMoreLogs[添加更多日誌]
    AddMoreLogs --> ReprodBug

    CleanUp --> Done[完成]

    style Issue fill:#ffebee
    style Done fill:#e8f5e9
```

**除錯檢查清單**:

```php
// 1. 啟用除錯模式
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// 2. 檢查錯誤日誌
tail -f wp-content/debug.log

// 3. 測試 API 連線
$ai_client = Moelog_AIQnA_Core::get_instance()->get_ai_client();
$result = $ai_client->test_connection('openai', 'your-api-key');

// 4. 檢查快取權限
ls -la wp-content/ai-answers/

// 5. 驗證 URL 簽名
$url = moelog_aiqna_build_url(123, '測試問題');
// 訪問 URL 並檢查是否正常
```

---

## 📚 相關文檔

- [架構概覽](architecture.md) - 系統整體架構
- [API 參考](api-reference.md) - 詳細 API 文檔
- [Hooks & Filters](hooks-filters.md) - 擴展點詳解

---

最後更新：2025-11-28
