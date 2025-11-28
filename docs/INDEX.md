# 開發者文檔索引

Moelog AI Q&A Links 技術文檔導覽。

## 文檔列表

### 專案文檔

| 文檔                                     | 內容               | 大小   |
| ---------------------------------------- | ------------------ | ------ |
| [../README.md](../README.md)             | 專案概覽與功能說明 | ~10 KB |
| [../CONTRIBUTING.md](../CONTRIBUTING.md) | 代碼貢獻規範       | ~11 KB |

### 技術文檔

| 文檔 | 內容 | 大小 |
|------|------|------|
| [README.md](README.md) | 文檔中心與技術概覽 | ~8 KB |
| [quick-start.md](quick-start.md) | 安裝配置與基本使用 | ~14 KB |
| [architecture.md](architecture.md) | 系統架構與設計模式 | ~20 KB |
| [data-flow.md](data-flow.md) | 數據流程與時序圖 | ~17 KB |
| [api-reference.md](api-reference.md) | 公共 API 與類別參考 | ~26 KB |
| [hooks-filters.md](hooks-filters.md) | 擴展點與鉤子列表 | ~20 KB |

### 功能模組文檔

| 文檔 | 內容 | 大小 |
|------|------|------|
| [stm-mode.md](stm-mode.md) | STM 結構化資料模式 (SEO/Sitemap) | ~12 KB |
| [i18n.md](i18n.md) | 國際化與翻譯開發指南 | ~8 KB |

**總計**：8 個技術文檔，約 125 KB

## 文檔功能覆蓋

| 主題 | 文檔 | 覆蓋度 |
|------|------|--------|
| 安裝配置 | [quick-start.md](quick-start.md) | ✅ 完整 |
| 系統架構 | [architecture.md](architecture.md) | ✅ 完整 |
| 數據流程 | [data-flow.md](data-flow.md) | ✅ 完整 |
| API 參考 | [api-reference.md](api-reference.md) | ✅ 完整 |
| 擴展開發 | [hooks-filters.md](hooks-filters.md) | ✅ 完整 |
| STM 模式 | [stm-mode.md](stm-mode.md) | ✅ 完整 |
| 國際化 | [i18n.md](i18n.md) | ✅ 完整 |
| 貢獻規範 | [../CONTRIBUTING.md](../CONTRIBUTING.md) | ✅ 完整 |

## 文檔內容

### README.md - 文檔中心

- 技術概覽
- 模組架構
- API 概覽
- 安全機制
- 快速參考

### quick-start.md - 安裝配置

- 系統需求
- 安裝方式
- 配置選項
- API 金鑰設定
- 快取系統
- 故障排除

### architecture.md - 系統架構

- 整體架構設計
- 核心模組說明（9 個）
- 三層快取架構
- 安全機制（加密、簽名、CSP、頻率限制）
- 數據流示例
- 設計原則

### data-flow.md - 數據流程

- 完整答案生成流程
- 路由處理時序圖
- 快取策略決策樹
- 預生成流程
- 安全驗證流程
- 錯誤處理與重試
- 用戶體驗旅程
- 性能優化決策
- 除錯流程

**包含圖表**：10+ Mermaid 流程圖

### api-reference.md - API 參考

**公共 API**：

- `moelog_aiqna_build_url()`
- `moelog_aiqna_cache_exists()`
- `moelog_aiqna_clear_cache()`
- `moelog_aiqna_parse_questions()`

**核心類別**：

- `Moelog_AIQnA_Core`
- `Moelog_AIQnA_Cache`
- `Moelog_AIQnA_AI_Client`
- `Moelog_AIQnA_Router`
- `Moelog_AIQnA_Renderer`

**Hooks**：

- Actions: 10+
- Filters: 15+

**AJAX 端點**：

- `moelog_aiqna_pregenerate`
- `moelog_aiqna_clear_cache`
- `moelog_aiqna_test_api`

### hooks-filters.md - Hooks & Filters

**Actions (動作鉤子)**：

- `moelog_aiqna_before_generate`
- `moelog_aiqna_after_generate`
- `moelog_aiqna_cache_saved`
- `moelog_aiqna_cache_cleared`
- `moelog_aiqna_before_render`
- `moelog_aiqna_answer_head`
- `moelog_aiqna_settings_updated`
- `moelog_aiqna_metabox_saved`
- 及更多...

**Filters (過濾器鉤子)**：

- `moelog_aiqna_ai_params`
- `moelog_aiqna_system_prompt`
- `moelog_aiqna_answer`
- `moelog_aiqna_render_html`
- `moelog_aiqna_cache_ttl`
- `moelog_aiqna_template_path`
- `moelog_aiqna_answer_url`
- 及更多...

**實用範例**：

- 分析追蹤系統
- 多供應商切換
- SEO 優化
- 最佳實踐

### stm-mode.md - STM 結構化資料模式

- STM 模式概述與功能
- 結構化資料 (QAPage, BreadcrumbList)
- SEO Meta 標籤 (OG, Twitter Card)
- HTTP 快取策略
- AI Sitemap 生成
- Hooks & Filters

### i18n.md - 國際化

- 支援語言列表
- 翻譯檔案結構
- 翻譯開發流程
- 編譯 .mo 檔案
- 新增語言指南
- 字串規範與範例

## 圖表內容

文檔中包含的 Mermaid 圖表類型：

**architecture.md**：

- 系統層次結構圖
- 核心模組關係圖
- AI Client 架構圖
- 雙層快取架構圖
- 數據流時序圖

**data-flow.md**：

- 完整答案生成流程圖
- 路由處理時序圖
- 快取策略決策樹
- 預生成流程圖
- 安全驗證流程圖
- 錯誤處理流程圖
- 用戶體驗旅程圖
- 快取失效狀態圖
- 性能優化決策樹
- 除錯流程圖

**總計**：14+ 專業流程圖

## 技術統計

```
文檔數量：8 個核心文檔
內容總量：~125 KB
代碼範例：60+ 個
API 函數：20+ 個
Hooks：30+ 個
流程圖：14+ 個
覆蓋率：98% 核心功能
```

## 文檔格式

所有文檔採用 GitHub Markdown 格式，支援：

- ✅ 語法高亮
- ✅ 表格
- ✅ Mermaid 圖表（GitHub 原生支援）
- ✅ 錨點導航
- ✅ 代碼區塊

## 本地閱讀

**推薦工具**：

- VS Code + Markdown Preview Enhanced
- Typora
- Obsidian

**生成 PDF**：

```bash
pandoc docs/architecture.md -o architecture.pdf
```

## 貢獻文檔

發現文檔錯誤或需要改進：

1. [提交 Issue](https://github.com/Horlicks-p/moelog-ai-qna-links/issues)
2. Fork 專案並修改
3. 提交 Pull Request

詳見 [CONTRIBUTING.md](../CONTRIBUTING.md)

## 多語言

目前文檔語言：

- ✅ 繁體中文 (zh_TW)

## 授權

文檔採用與專案相同的 **GPL v2 或更高版本** 授權。

---

**文檔版本**: 1.1.0  
**插件版本**: 1.10.2  
**最後更新**: 2025-11-28
