=== Moelog AI Q&A Links ===  
Contributors: Horlicks  
Author URI: https://www.moelog.com/  
Tags: AI, OpenAI, Gemini, Claude, ChatGPT, Anthropic, Q&A, GPT, AI Answer,  Schema  
Requires at least: 5.0  
Tested up to: 6.8.3  
Requires PHP: 7.4  
Stable tag: 1.8.3  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

== 🧠 Description ==

**Moelog AI Q&A Links** automatically adds an interactive **AI Q&A list** to the end of your posts or pages.  
When readers click a question, a new tab opens to display an **AI-generated answer** from **OpenAI**, **Google Gemini**, or **Anthropic Claude** in real time.

The answer page features a clean HTML layout, typing animation effects, a built-in caching system (including static files),  
and an optional **Structured Data Mode**, which helps search engines and AI crawlers better understand the page content.

---

== ✨ Key Features ==

✅ Automatically inserts an AI Q&A section at the bottom of posts  
✅ Supports `[moelog_aiqna index="N"]` shortcode for embedding individual questions  
✅ Works with **OpenAI / Gemini / Claude (Anthropic)**  
✅ Customizable system prompt, model, temperature, and language  
✅ Automatic language detection (Traditional Chinese / Japanese / English)  
✅ Built-in caching system (default 24h TTL, configurable from 1–365 days with transient + static files)  
✅ Smart pregeneration: only regenerates when content changes, saving API tokens  
✅ Admin cache manager: clear all or per-post cache  
✅ Structured Data Mode: adds QAPage / Breadcrumb Schema, Canonical, Robots, and cache headers  
✅ Fully compliant with **CSP (Content Security Policy)**  
✅ Modular architecture (Core / Router / Renderer / Cache / Admin / Assets / Pregenerate)  
✅ Compatible with Cloudflare and proxy IP environments  

---

== ⚙️ Structured Data Mode ==

Structured Data Mode helps search engines and AI crawlers better parse your AI answer pages, though it doesn’t guarantee indexing or ranking improvements.

When enabled:
- Adds **QAPage** and **Breadcrumb** structured data  
- Adds a canonical link pointing to the original article  
- Automatically sets Robots to:  
  `index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1`  
- Outputs **Cache-Control** and **Last-Modified** headers (CDN-friendly)  
- Generates an **AI Q&A Sitemap** (`ai-qa-sitemap.php`) and pings Google/Bing  

When disabled (default):
- Uses `noindex,follow` to prevent duplicate content  
- Still outputs structured data for parsing  
- Does not generate a sitemap or send pings  

---

== 🧩 Shortcodes ==

| Shortcode | Description |
|------------|-------------|
| `[moelog_aiqna]` | Displays the full question list |
| `[moelog_aiqna index="1"]` | Displays only question #1 |
| `[moelog_aiqna index="3"]` | Displays only question #3 (1–8 supported) |

If a shortcode is used in the post, the automatic bottom Q&A list will be hidden to avoid duplication.

---

== 🧮 Caching System ==

- Default TTL: 24 hours  
- Custom TTL setting available in admin (1–365 days)  
- Dual caching system: WordPress transient + static file  
- Built-in cache clear tools (global or per-post)  
- Outputs CDN-friendly Cache-Control headers  
- Supports `stale-while-revalidate` for smoother cache refresh  
- Smart pregeneration using **content hash** to detect changes and rebuild only when needed  

---

== ⚙️ Performance & Stability ==

- Startup time improved by ~45%  
- Backend queries reduced by ~30%  
- Smart pregeneration significantly cuts unnecessary API calls  
- Fully modular architecture: Core / Router / Renderer / Cache / Admin / Assets / Pregenerate  
- Prevents duplicate Open Graph / Meta tags  
- Automatically refreshes rewrite rules on activation  
- Fully UTF-8 and multilingual compatible  

---

== 🔐 Security ==

- Implements CSP (Content Security Policy) and nonce validation  
- All outputs escaped via `esc_html` / `esc_attr`  
- Uses HMAC for cache integrity verification  
- IP-based rate limiting to prevent abuse  
- HTTPS communication with official APIs  
- No user data collection  
- Fully GDPR-compliant  

---

== 💬 Privacy ==

This plugin only sends the following data to AI service providers (OpenAI / Gemini / Claude):  
- Predefined question content (set by the site author)  
- (Optional) Post content if “Include article content” is checked  
- System prompt and language setting  

No visitor data is ever sent. All communications are securely encrypted via HTTPS.

---

== 🧩 Changelog ==

= 1.8.3 (2025-10-21) – Encrypted API Key Storage =
- 🔒 Added API key encryption for enhanced data security  


= 1.8.2 (2025-10-20) – Smart Pregeneration Optimization & Bug Fixes =
**New Features:**  
- ✨ Added smart pregeneration using **content hash** detection  
- ✨ Only regenerates answers when post content or Q&A list changes  
- ✨ Added `skip_clear` transient flag to prevent accidental cache deletion  
- ✨ Separated automatic vs. manual cache clearing to protect `content_hash`  

**Bug Fixes:**  
- 🔧 Fixed fatal error from mismatched method name `schedule_single_task()`  
- 🔧 Fixed `$this` scope issue inside anonymous functions  
- 🔧 Fixed incorrect argument count in `save_post` hook  
- 🔧 Prevented unwanted pregeneration on every post update  
- 🔧 Improved `clear_post_cache()` logic to preserve `content_hash`  

**Improvements:**  
- 📝 Clearer debug logs showing pregeneration status  
- 📝 Added “SKIP pregenerate: unchanged” log message  
- 🎯 Optimized scheduling logic to reduce redundant tasks  
- 🎯 Improved metabox cache clearing workflow  

= 1.8.1 (2025-10-19) – Added Claude AI Support =
- Added Anthropic Claude provider (claude.ai)  
- Supports Claude Sonnet 4.5 model  
- Unified API key field  
- Fixed system/message property format  
- Improved max_tokens handling and error logging  
- Added Claude console shortcut in admin  

= 1.8.0 (2025-10-18) – Full Modular Refactor =
- Rebuilt Core / Router / Renderer / Cache / Admin / Assets structure  
- Added `helpers-template.php` utility functions  
- Customizable cache TTL (1–365 days)  
- Added Structured Data Mode  
- Added Canonical and enhanced Robots controls  
- Changed sitemap to `.php` extension for compatibility  
- Strengthened security and output sanitization  
- Updated admin UI and inline documentation  

---

== 🧭 Support ==

Bug reports & feature suggestions:  
🌐 Official site: https://www.moelog.com/  
💻 GitHub: https://github.com/Horlicks-p/moelog-ai-qna-links  

---

== 🧩 License ==

This plugin is licensed under **GPL v2 or later**.  
You are free to modify and redistribute it.  
© 2025
