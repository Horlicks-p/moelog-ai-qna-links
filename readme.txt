=== Moelog AI Q&A Links ===
Contributors: horlicks  
Author URI: https://www.moelog.com/  
Tags: AI, OpenAI, Gemini, ChatGPT, Q&A, GPT, AI Answer, SEO, Schema, GEO, WordPress Plugin  
Requires at least: 5.0  
Tested up to: 6.7  
Requires PHP: 7.4  
Stable tag: 1.8.0  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

== Description ==

**Moelog AI Q&A Links** adds an interactive, AI-powered Q&A section to your WordPress posts or pages.  
Each predefined question automatically opens a new tab where **OpenAI** or **Google Gemini** generates a dynamic answer in real time.

Version **1.8.0** is a **complete modular rebuild** — fully re-architected for performance, maintainability, and WordPress best practices.  
It continues to include the optional **GEO (Generative Engine Optimization)** module, helping your AI answers get indexed and cited by Google SGE, Bing Copilot, and other generative search engines.

---

### ✨ Key Features

✅ Append an AI Q&A block to any post or page  
✅ Flexible shortcodes — insert full list or individual questions anywhere  
✅ Supports **OpenAI** and **Google Gemini** models  
✅ Configurable system prompt, temperature, and language  
✅ Multilingual support (auto / zh / ja / en)  
✅ Animated typing effect for AI answers  
✅ Built-in caching (24-hour TTL, transient + static file)  
✅ One-click cache management in admin panel  
✅ GEO mode for structured data and AI-optimized SEO  
✅ Full **CSP (Content Security Policy)** compliance  
✅ Cloudflare / proxy-aware IP detection  
✅ Modular architecture — clean, extendable, and developer-friendly  

---

== 🚀 New in 1.8.0 – Complete Modular Rebuild ==

**Major architectural overhaul:**
- Core plugin split into 10 self-contained modules under `/includes/`
- New `Moelog_AIQnA_Core` orchestrator managing all subsystems  
- Cleaner shortcode logic with duplicate-prevention detection  
- Utility and template helpers (`helpers-utils.php`, `helpers-template.php`)  
- All JS/CSS loaded externally — no inline scripts, full CSP compliance  
- New `typing.js` (autonomous typing animation, zero inline code)  

**Performance & stability:**
- ~45% faster initialization time  
- ~30% fewer database queries on admin pages  
- Seamless integration with Slim SEO, All in One SEO, and Jetpack  
- Automatically refreshes rewrite rules when permalinks change  

**GEO module improvements:**
- More stable QAPage structured data generator  
- Automatic pings to Google/Bing when publishing  
- Smarter AI Sitemap cache and 404 fallbacks  
- Updated bot allowlist (Googlebot, Bingbot, Perplexity, ChatGPTBot, etc.)  

**Developer enhancements:**
- Public getters (`get_router()`, `get_ai_client()`, etc.) for better extensibility  
- Action hooks: `moelog_aiqna_answer_head`, `moelog_aiqna_render_output`  
- CLI- and cron-friendly cache/pregeneration methods  

---

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/moelog-ai-qna/`  
2. Activate **Moelog AI Q&A Links** from the Plugins menu  
3. Go to **Settings → Moelog AI Q&A** and enter your API key and model  
4. *(Optional)* Enable **GEO mode** for structured data and AI Sitemap  
5. Edit a post and enter your question list (one per line) in the **AI Question List** meta box  
6. Save the post — your Q&A block will appear automatically below the content  

---

== Shortcodes ==

| Shortcode | Description |
|------------|-------------|
| `[moelog_aiqna]` | Display the full question list |
| `[moelog_aiqna index="1"]` | Display only question #1 |
| `[moelog_aiqna index="3"]` | Display only question #3 |
| `[moelog_aiqna index="8"]` | Display only question #8 |

When shortcodes are present, the automatic list below the post is hidden to prevent duplication.

---

== GEO Mode (Generative Engine Optimization) ==

**Make your AI answers discoverable by Google SGE, Bing Copilot, and Perplexity.**

GEO mode adds:
- **QAPage structured data** (JSON-LD)  
- **Open Graph & Twitter Card** metadata  
- **Breadcrumb markup** for better indexing  
- **Dedicated AI Q&A Sitemap** (`/ai-qa-sitemap.php`)  
- Automatic pinging of major search engines  
- Public cache with `s-maxage` and `stale-while-revalidate` headers  

**To enable GEO mode:**
1. Go to **Settings → Moelog AI Q&A → GEO Module**  
2. Check **Enable structured data & AI Sitemap**  
3. Visit **Settings → Permalinks** and click **Save Changes**  
4. Submit `/ai-qa-sitemap.php` to Google Search Console and Bing Webmaster Tools  

---

== Cache System ==

- Cached answers persist for **24 hours**  
- Manual clearing available under **Settings → Moelog AI Q&A → Cache Management**  
- Cache key format: `moe_aiqna_{hash(post_id|question|model|lang)}`  
- Options:  
  - Clear all cached answers  
  - Clear cache for a specific post  

---

== Screenshots ==

1. Admin settings page (API and model selection)  
2. Post editor meta box with question list and shortcode tips  
3. GEO module configuration panel  
4. Front-end Q&A block example  
5. AI answer page with typing animation  
6. Structured data output and AI Sitemap example  

---

== Changelog ==

= 1.8.0 (2025-10-18) =  
**Complete Modular Rebuild**

- Fully modularized architecture (Core, Router, Renderer, AI Client, Cache, Admin, Metabox, Assets, Pregenerate)  
- Added Helpers and Typing.js for cleaner front-end rendering  
- Removed inline scripts, full CSP compliance  
- Improved startup speed (+45%) and admin load time (-30%)  
- Enhanced GEO module with auto-ping and stronger sitemap caching  
- New responsive design with modernized typography and layout  
- Compatible with WordPress 6.7 and PHP 8.2  

= 1.6.3 (2025-10-15) =  
Maintenance & refinement update  
- Unified sitemap format  
- Improved GEO module initialization  
- Fixed SEO plugin meta duplication issue  

= 1.6.2 (2025-10-14) =  
Enhanced shortcode system  
- Individual question shortcodes with smart duplication prevention  
- Improved prefetch script injection  

= 1.6.1 (2025-10-13) =  
Cache management + typing animation  
- New cache management UI  
- Fixed URL encoding display issues  
- Refined prompt citation rules  

= 1.6.0 (2025-10-12) =  
Major GEO module introduction  
- Added QAPage structured data, OG/Twitter meta, and AI Sitemap  

---

== Upgrade Notice ==

= 1.8.0 =
**Major update — fully modular architecture.**  
After upgrading, visit **Settings → Permalinks** and click **Save Changes** to refresh rewrite rules.  
All existing question data (`_moelog_aiqna_questions`) remain compatible — no migration needed.

---

== License ==

This plugin is licensed under the GPL v2 or later.  
You may redistribute or modify it under the same license terms.  

© 2025 Horlicks / moelog.com
