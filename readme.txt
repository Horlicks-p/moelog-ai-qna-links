=== Moelog AI Q&A Links ===
Contributors: horlicks
Author link: https://www.moelog.com/
Tags: AI, OpenAI, Gemini, ChatGPT, Q&A, GPT, AI Answer, SEO, Schema, Structured Data
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

🧠 Description

Moelog AI Q&A Links enhances your WordPress posts by adding an interactive list of AI-powered Q&A links.
When a visitor clicks a question, a new tab opens showing an AI-generated answer powered by OpenAI or Google Gemini.

Each answer page includes clean layout, typing animation, caching, and optional structured data for better parsing by search/AI crawlers.

✨ Key Features

✅ Append customizable AI Q&A lists to posts or pages
✅ Flexible shortcodes – insert full list or single questions anywhere ([moelog_aiqna index="N"])
✅ Supports OpenAI and Gemini models
✅ Configurable system prompt, temperature, and language
✅ Multilingual support (auto / zh / ja / en)
✅ Typing animation for dynamic answer display
✅ Built-in caching system (default 24-hour TTL, adjustable duration, transient + static file)
✅ Cache management interface in the admin panel
✅ Structured Data Mode (replaces old GEO mode) – adds QAPage & Breadcrumb schema, canonical, robots, and cache headers
✅ Designed for compatibility with major SEO plugins (Slim SEO, All in One SEO, Jetpack) – prevents duplicate OG/meta tags on AI pages
✅ Full Content Security Policy (CSP) compliance
✅ Modular architecture (Core, Router, Renderer, Admin, Cache, Assets, Pregenerate)
✅ Cloudflare/proxy-aware IP detection

⚙️ Structured Data Mode

Structured Data Mode adds schema and meta information for search and AI crawlers to correctly understand AI answer pages.
It does not guarantee indexing or ranking.

When enabled, this mode provides:

QAPage / Breadcrumb schema
Canonical pointing back to the original post
Robots meta: index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1
Cache-Control and Last-Modified headers (CDN-friendly)
AI Q&A Sitemap (ai-qa-sitemap.php) with automatic ping to Google/Bing

When disabled (default):
Pages use noindex,follow for safe non-indexing
Structured data remains active for crawler parsing
Sitemap and pinging are disabled

🧰 Installation

Upload the plugin folder to /wp-content/plugins/
Activate it from Plugins → Installed Plugins
Go to Settings → Moelog AI Q&A and configure your API key and model
Optionally enable Structured Data Mode

Edit a post and enter your Q&A list in the “AI Question List” meta box (one per line)

The question list automatically appears below your post

🧩 Shortcodes

Shortcode	Description
[moelog_aiqna]	Displays full Q&A list
[moelog_aiqna index="1"]	Displays only the first question
[moelog_aiqna index="2"]	Displays question #2, and so on (1–8)

When shortcodes are used, the automatic list is hidden to prevent duplicates.

🧮 Cache System

Default TTL: 24 hours
Adjustable via Settings → Moelog AI Q&A
Cache includes both transient (database) and static file storage
Admin UI lets you clear all or specific cached answers
Cache-Control headers are automatically optimized for CDN use
Stale-while-revalidate enabled for smooth regeneration

🧩 Performance & Stability

~45% faster initialization
~30% fewer database queries on admin pages
Fully modular architecture (Core, Router, Renderer, Cache, Admin, Assets, Pregenerate)
Designed for compatibility with major SEO plugins (Slim SEO, AIOSEO, Jetpack)
Automatically prevents duplicate OG/meta tags on AI pages
Rewrite rules auto-refresh on activation
Full UTF-8 support (Japanese/Chinese content safe)

📦 Technical Overview
Main files

moelog-ai-qna/
├─ moelog-ai-qna.php              → Main plugin loader
├─ moelog-ai-geo.php              → Structured Data module (optional)
├─ includes/
│  ├─ class-core.php              → Core controller
│  ├─ class-router.php            → URL routing & rewrite rules
│  ├─ class-renderer.php          → HTML rendering
│  ├─ class-ai-client.php         → AI API client
│  ├─ class-cache.php             → Caching system
│  ├─ class-admin.php             → Settings page
│  ├─ class-metabox.php           → Post editor meta box
│  ├─ class-assets.php            → Enqueue CSS/JS
│  ├─ class-pregenerate.php       → Background pregeneration tasks
│  ├─ helpers-utils.php           → Utility functions
│  └─ helpers-template.php        → Template helpers (optional)
├─ templates/answer-page.php      → Frontend answer layout
└─ assets/
   ├─ css/style.css
   └─ js/typing.js

🔐 Security & Compliance

CSP (nonce-based script execution)
HMAC-signed cache integrity
Escaped HTML output (XSS-safe)
IP-based rate limiting
HTTPS enforced for API calls
No user data collection
GDPR-compliant: only AI query text and optional post excerpt are sent to APIs

💬 Privacy Notice

This plugin sends the following to the AI provider (OpenAI/Gemini):
The pre-defined question text
Optional post content (if “Include post context” is enabled)
System prompt and language setting
No personal or user-submitted data is transmitted.
All API requests use HTTPS encryption.

🧩 Changelog

= 1.8.0 (2025-10-18) – Complete Modular Rebuild =
Fully refactored architecture (Core / Router / Renderer / Cache / Admin / Assets)

Added helpers-template.php for reusable template components
Added adjustable cache TTL (default 24 h, customizable 1–365 days)
Renamed “GEO Mode” → Structured Data Mode
Added canonical tag pointing to original article
Improved Robots handling (noindex,follow by default)
Revised Sitemap generation (.php format, safer routing)
Enhanced security and escaping throughout
Updated admin UI and inline documentation

🧩 License

This plugin is licensed under the GPL v2 or later.
You may redistribute or modify it under the same license terms.

© 2025 Horlicks / moelog.com

🧭 Support

For bug reports or feature requests:
Website: https://www.moelog.com/
GitHub: https://github.com/Horlicks-p/moelog-ai-qna-links
