=== Moelog AI Q&A Links ===  

Contributors: Horlicks  
Author URI: https://www.moelog.com/  
Tags: AI, OpenAI, Gemini, Claude, ChatGPT, Anthropic, Q&A, GPT, AI Answer, SEO, Schema, Structured Data  
Requires at least: 5.0  
Tested up to: 6.7  
Requires PHP: 7.4  
Stable tag: 1.8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

== 🧠 Description ==

**Moelog AI Q&A Links** automatically appends an interactive, AI-powered Q&A block to your WordPress posts or pages.  
Each predefined question opens in a new tab and displays an AI-generated answer from **OpenAI**, **Google Gemini**, or **Anthropic Claude**.

Answers are rendered with a clean HTML layout, smooth typing animation, and a built-in caching system (transient + static file).  
An optional **Structured Data Mode** adds QAPage / Breadcrumb Schema and SEO-friendly meta headers for search engines and AI crawlers.

---

== ✨ Key Features ==

✅ Automatically append an AI Q&A list below posts  
✅ Shortcode support — `[moelog_aiqna index="N"]` to embed single questions  
✅ Supports **OpenAI**, **Gemini**, and **Claude (Anthropic)**  
✅ Customizable system prompt, model, temperature, and language  
✅ Automatic language detection (ZH-TW / JA / EN)  
✅ Typing animation for AI answers  
✅ Dual-layer cache (transient + static file)  
✅ Configurable TTL (1–365 days)  
✅ Smart pregeneration — only regenerates when content changes (via content hash)  
✅ Built-in cache management (clear all or per post)  
✅ Structured Data Mode — adds QAPage / Breadcrumb Schema, Canonical, Robots, and caching headers  
✅ Compatible with major SEO plugins (Slim SEO / AIOSEO / Jetpack)  
✅ Fully CSP-compliant (Content Security Policy)  
✅ Modular architecture (Core / Router / Renderer / Cache / Admin / Assets / Pregenerate)  
✅ Cloudflare / reverse-proxy IP aware  

---

== ⚙️ Structured Data Mode ==

When enabled, Structured Data Mode helps search and AI crawlers correctly interpret AI Q&A pages.

It adds:
- QAPage + Breadcrumb JSON-LD  
- Canonical link (points back to original article)  
- Robots: `index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1`  
- Cache-Control & Last-Modified headers (CDN-friendly)  
- AI Q&A Sitemap (`ai-qa-sitemap.php`) with automatic Google/Bing ping  

When disabled (default):
- Uses `noindex,follow` to avoid duplicate content  
- Still outputs schema for parsing  
- Skips sitemap and ping functions  

---

== 🧩 Shortcodes ==

| Shortcode | Description |
|------------|-------------|
| `[moelog_aiqna]` | Display full question list |
| `[moelog_aiqna index="1"]` | Display only question #1 |
| `[moelog_aiqna index="3"]` | Display only question #3 |

If shortcodes are used within the post, the automatic list will be hidden to prevent duplication.

---

== 🧮 Cache System ==

- Default TTL: 24 hours (configurable 1–365 days)  
- Dual cache layers: transient + static file  
- Admin tools for clearing all or specific post cache  
- CDN-friendly headers (`Cache-Control`, `stale-while-revalidate`)  
- Smart pregeneration detects changes using **content hash**  
- Only regenerates when article content or question list changes  

---

== ⚙️ Performance & Stability ==

- Startup time improved by ~45%  
- Admin queries reduced by ~30%  
- Smart pregeneration minimizes API calls  
- Fully modular structure for maintainability  
- Safe coexistence with SEO plugins (no duplicate OG/meta tags)  
- Rewrite rules auto-refresh on activation  
- Full UTF-8 and multilingual compatibility  

---

== 🔐 Security ==

- CSP & nonce validation  
- All outputs escaped with `esc_html` / `esc_attr`  
- HMAC verification for cache integrity  
- Rate limiting by IP  
- Secure HTTPS API requests  
- No user data collected (GDPR-compliant)  

---

== 💬 Privacy Notice ==

This plugin only sends the following data to AI providers (OpenAI / Gemini / Claude):

- Predefined question text  
- (Optional) Related article content (if enabled)  
- System prompt and language settings  

No visitor data is transmitted. All communication is encrypted via HTTPS.

---

== 🧩 Changelog ==

= 1.8.2 (2025-10-20) – Smart Pregeneration Optimization & Bug Fixes =  
**New Features:**  
- ✨ Smart pregeneration: detects changes via **content hash**  
- ✨ Only regenerates answers when article or question list changes  
- ✨ Added `skip_clear` transient to prevent accidental cache deletion  
- ✨ Separated automatic vs manual cache clearing logic  

**Fixes:**  
- 🔧 Fixed `schedule_single_task()` naming mismatch causing fatal error  
- 🔧 Fixed anonymous closure `$this` context issue  
- 🔧 Fixed `save_post` argument mismatch  
- 🔧 Fixed unnecessary pregeneration trigger on every update  
- 🔧 Fixed cache clearing deleting `content_hash`  

**Improvements:**  
- 📝 Improved debug log clarity for pregeneration  
- 📝 Added “SKIP pregenerate: unchanged” log entry  
- 🎯 Enhanced throttling and deduplication for scheduled tasks  
- 🎯 Optimized Metabox cache management flow  

= 1.8.1 (2025-10-19) – Added Claude AI Support =  
- Added Anthropic Claude (claude.ai) integration  
- Added Claude Sonnet 4.5 model  
- Unified API Key handling (Option A)  
- Fixed `system` and `messages` structure for Claude API  
- Improved `max_tokens` and error logging  
- Updated admin sidebar with Claude API link  

= 1.8.0 (2025-10-18) – Modular Architecture Rebuild =  
- Fully modularized Core / Router / Renderer / Cache / Admin / Assets  
- Added `helpers-template.php` utilities  
- Adjustable cache TTL (1–365 days)  
- Introduced Structured Data Mode (QAPage / Breadcrumb / Canonical / Robots)  
- Sitemap switched to `.php` for compatibility  
- Enhanced security and escaping  
- Updated admin UI and inline help  

---

== 🧭 Support ==

Bug reports & feature requests:  
🌐 Official site: https://www.moelog.com/  
💻 GitHub: https://github.com/Horlicks-p/moelog-ai-qna-links  

---

== 🧩 License ==

This plugin is licensed under GPLv2 or later.  
You are free to modify and redistribute it under the same terms.  
© 2025 Horlicks / moelog.com
