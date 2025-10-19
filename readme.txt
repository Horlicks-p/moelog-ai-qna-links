=== Moelog AI Q&A Links ===
Contributors: horlicks  
Author URI: https://www.moelog.com/  
Tags: AI, OpenAI, Gemini, Claude, ChatGPT, Anthropic, Q&A, GPT, AI Answer, SEO, Schema, WordPress Plugin  
Requires at least: 5.0  
Tested up to: 6.7  
Requires PHP: 7.4  
Stable tag: 1.8.1  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

== Description ==

**Moelog AI Q&A Links** automatically adds an interactive **AI Q&A section** to the bottom of your WordPress posts or pages.  
When a reader clicks a question, a new tab opens showing an AI-generated answer in real time from **OpenAI**, **Google Gemini**, or **Anthropic Claude**.

The answer page features a clean HTML layout, typewriter animation, built-in caching (with static files),  
and an optional **Structured Data Mode** for improved parsing by search and AI crawlers.

---

### ✨ Key Features

✅ Automatically append an AI-powered Q&A block below posts or pages  
✅ Shortcode `[moelog_aiqna index="N"]` for inserting individual questions  
✅ Supports **OpenAI**, **Google Gemini**, and **Anthropic Claude**  
✅ Configurable system prompt, model, temperature, and language  
✅ Automatic language detection (Traditional Chinese / Japanese / English)  
✅ Typewriter animation for AI answer pages  
✅ Built-in caching (default 24-hour TTL, configurable 1–365 days, transient + static file)  
✅ Admin cache management: clear all or single-post cache  
✅ **Structured Data Mode** — adds QAPage / Breadcrumb schema, Canonical, Robots, and cache headers  
✅ Compatible with major SEO plugins (Slim SEO / AIOSEO / Jetpack) to prevent duplicate meta tags  
✅ Full **CSP (Content Security Policy)** compliance  
✅ Modular architecture (Core / Router / Renderer / Cache / Admin / Assets / Pregenerate)  
✅ Cloudflare / proxy-friendly IP detection  

---

### ⚙️ Structured Data Mode

The **Structured Data Mode** helps search engines and AI crawlers better interpret Q&A content —  
though it does **not guarantee indexing or ranking improvements**.

When enabled:
- Adds **QAPage** and **Breadcrumb structured data**  
- Adds **Canonical** tag (pointing back to the original article)  
- Robots tag set to `index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1`  
- Outputs **Cache-Control** and **Last-Modified** headers (CDN-friendly)  
- Generates an AI Q&A Sitemap (`ai-qa-sitemap.php`) and automatically pings Google/Bing  

When disabled (default):
- Uses `noindex,follow` to prevent duplicate content  
- Still outputs structured data for AI crawlers  
- Does not generate Sitemap or ping search engines  

---

### 🧰 Installation

1. Upload the plugin folder to `/wp-content/plugins/`  
2. Activate **Moelog AI Q&A Links** from the Plugins menu  
3. Go to **Settings → Moelog AI Q&A** and enter your API key and model  
4. *(Optional)* Enable **Structured Data Mode**  
5. Edit a post and enter one question per line in the **AI Question List** meta box  
6. Save — your Q&A block will appear automatically below the post  

---

### 🧩 Shortcodes

| Shortcode | Description |
|------------|-------------|
| `[moelog_aiqna]` | Display the full question list |
| `[moelog_aiqna index="1"]` | Display only question #1 |
| `[moelog_aiqna index="3"]` | Display only question #3 (1–8 available) |

If shortcodes are present in the post, the automatic block below the content will be hidden to prevent duplication.

---

### 🧮 Cache System

- Default TTL: 24 hours  
- Customizable cache time (1–365 days)  
- Dual-layer caching: WordPress transient + static HTML files  
- Cache management tools in admin page  
- Outputs CDN-friendly **Cache-Control** headers  
- Supports **stale-while-revalidate** for smooth cache regeneration  

---

### ⚙️ Performance & Stability

- ~45% faster initialization  
- ~30% fewer database queries on admin pages  
- Fully modular architecture: Core / Router / Renderer / Cache / Admin / Assets / Pregenerate  
- Seamlessly compatible with major SEO plugins (Slim SEO / AIOSEO / Jetpack)  
- Prevents duplicate Open Graph / Meta tags  
- Auto-refresh rewrite rules on activation  
- UTF-8 multilingual support  

---

### 🤖 New in 1.8.1 — Anthropic Claude AI Support

- Added new provider: **Anthropic Claude (claude.ai)**  
- Choose “Anthropic (Claude)” in Settings → Provider  
- Default model: `claude-sonnet-4-5-20250929` (Claude Sonnet 4.5)  
- Uses official **Messages API** with top-level `system` field  
- Enhanced debug logging for API errors and HTTP codes  
- Improved model auto-correction and `max_tokens` safety (1–8192, default 1024)  
- Refined admin “Quick Links” section with direct Claude API console link  

---

### 🔐 Security

- Full **CSP (Content Security Policy)** compliance with nonce validation  
- All outputs escaped using `esc_html` / `esc_attr`  
- HMAC verification for cache integrity  
- Basic IP-based rate limiting  
- All API communication via HTTPS  
- No user data collection — GDPR compliant  

---

### 💬 Privacy Notice

This plugin only sends the following data to AI providers (OpenAI / Gemini / Claude):  
- The predefined question text  
- *(Optional)* Article content (if enabled in settings)  
- System prompt and language preference  

No user information is transmitted.  
All communication is encrypted via HTTPS.  

---

### 🧩 Changelog

= 1.8.1 (2025-10-19) – Anthropic Claude Support =  
- Added **Anthropic Claude (claude.ai)** provider  
- Supports Claude Sonnet 4.5 model  
- Unified API key field (A-scheme, shared by all providers)  
- Corrected system and messages schema per Anthropic API  
- Added debug logs and improved error reporting  
- Adjusted `max_tokens` (default 1024, range 1–8192)  
- Updated admin sidebar “Quick Links” to include Claude Console  

= 1.8.0 (2025-10-18) – Complete Modular Rebuild =  
- Fully modular architecture (Core / Router / Renderer / Cache / Admin / Assets)  
- Added helper files (`helpers-template.php`)  
- Configurable cache TTL (1–365 days)  
- Introduced **Structured Data Mode** (replacing old GEO system)  
- Added Canonical and improved Robots control  
- Converted Sitemap to `.php` extension for better compatibility  
- Strengthened escaping and security validation  
- Updated admin UI with contextual help  

---

### 🧩 License

This plugin is licensed under the GPL v2 or later.  
You may freely modify or redistribute it under the same terms.  
© 2025 Horlicks / moelog.com  

---

### 🧭 Support & Feedback

Bug reports and feature requests:  
- Official site: https://www.moelog.com/  
- GitHub: https://github.com/Horlicks-p/moelog-ai-qna-links




