=== Moelog AI Q&A Links ===

*Contributors: horlicks
*Author link: https://www.moelog.com/
*Tags: openai, ai, chatbot, q&a, gpt, openai api,
*Requires at least: 5.0
*Tested up to: 6.6
*Requires PHP: 7.4
*Stable tag: 1.1.0
*License: GPLv2 or later
*License URI: https://www.gnu.org/licenses/gpl-2.0.html
// 這是 Plugin Header 區塊的結束，簡介文字應該放在這裡，並且可以斷行。

Display your own pre-set AI question list under each post, open answers in a new tab, and let OpenAI generate contextual replies automatically. Simple, secure, and fast.

== Description ==

**Moelog AI Q&A Links** is a lightweight plugin that allows authors to predefine questions for each post.
At the bottom of each article, readers can click a question to open a new page where an AI (powered by OpenAI API) generates a contextual answer.
- 🧠 Supports OpenAI models (e.g., `gpt-4o-mini`, `gpt-4-turbo`, etc.)
- 🗝️ Secure API key management (supports `wp-config.php` constant)
- 🧩 Works with both posts and pages
- 🌏 Multilingual support (auto-detects Chinese / Japanese / English)
- ⚡ Built-in caching and rate limiting
- 🧱 Safe HTML rendering (no XSS risk)
- 🔐 Triple-layer protection (Nonce + Timestamp + HMAC)
- ⌨️ **New in 1.1.0: Typewriter effect on the answer page** (progressively reveals the sanitized AI output)
- 🤖 **New in 1.1.0: User-Agent bot blocking** to avoid unwanted crawler-triggered API calls
- 📜 **New in 1.1.0: Optional disclaimer block** shown below the close button

Built with strict WordPress coding standards and complete security in mind —  
ready for production and even WordPress.org submission.

== Features ==

* Add AI question list per post (metabox editor)
* Auto append or manually insert via `[moelog_aiqna]` shortcode
* Each question opens in a new secure tab for AI-generated answer
* Per-site secret key + nonce + timestamp verification
* Automatic caching to reduce API calls
* IP-based rate limiting and per-question cooldown control
* Strict HTML sanitization for AI output
* **Typewriter display** for the answer page (adjustable speed)
* **Bot blocking** for common crawlers (Googlebot/Bingbot/etc.) to protect API usage
* **Inline legal disclaimer** beneath the “Close this page” button
* Full uninstall cleanup (options, postmeta, transient)
* Compatible with multilingual content

== Installation ==

1. Upload `moelog-ai-qna-links` to your `/wp-content/plugins/` directory, or install directly via the WordPress Plugin Directory.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Go to **Settings → Moelog AI Q&A** and enter your OpenAI API Key and model name.
4. When editing a post, scroll down to **AI 問題清單 (AI Question List)** and add your questions (one per line).
5. Save and view your post — questions will appear below your article.

Optional:
- Add `define('MOELOG_AIQNA_API_KEY', 'sk-xxxxxx');` in `wp-config.php` for higher security.
- Use `[moelog_aiqna]` shortcode to manually insert question list anywhere.

== Frequently Asked Questions ==

= Does it require an OpenAI API key? =
Yes. You can create one at https://platform.openai.com/.  
The plugin allows entering it in Settings, or securely defining it in `wp-config.php`.

= Is it safe to expose AI answers to visitors? =
Yes. All outputs are strictly sanitized using `wp_kses()` with a minimal whitelist to avoid XSS.
Links and potentially risky tags/attributes are removed.

= Can I include the post content as context? =
Yes. There is an option **“Include post content to AI”** in Settings.  
It sends a truncated (default 6000 characters) plain-text version of the article to improve contextual accuracy.

= How does rate limiting work? =
Each visitor’s IP is limited to 10 requests per hour, and each unique question has a 60-second cooldown.  
Cached answers are reused to save your OpenAI API quota.

= Will search engine crawlers trigger API calls? =
The answer page is marked `noindex,nofollow`, and from 1.1.0 we block common bots via User-Agent on the answer route.  
Additionally, requests must pass nonce/timestamp/HMAC checks; invalid/expired links are rejected before any API call.

= Can I change the typewriter speed? =
Yes. In 1.1.0, the speed defaults to 18ms per character. You can adjust the constant in the inline JS (`SPEED = 18`).

= How do I completely remove all data? =
When you uninstall the plugin, all related options, postmeta, and cached transients are automatically deleted.

== Screenshots ==

1. Admin Settings Page (API Key, Model, Temperature)
2. Post Editor Metabox (AI Question List)
3. Front-end Display of Question List
4. AI Answer Page with Typewriter Effect & Close Button
5. Inline Disclaimer under the Close Button

== Changelog ==

= 1.1.0 =
* New: Typewriter (progressive typing) effect on the answer page.
* New: Bot blocking by User-Agent on the answer route to reduce unwanted API triggers.
* New: Inline legal disclaimer below the close button.
* Security kept intact: strict sanitization, nonce + timestamp + HMAC, caching → cooldown → IP limit, uninstall cleanup.

= 1.0.8 =
* Security enhancements:
  - Fixed SHA-256 regex length to `{64}`
  - Replaced `wp_generate_password()` with `random_bytes()` for per-site secret
  - Unified cache key passing between render and API call
  - Added transient cleanup in uninstall()
* Achieved 10/10 security audit score

= 1.0.7 =
* Added per-site secret HMAC signing
* Strengthened Nonce + Timestamp + Signature validation
* Improved cache & rate-limit logic (cache priority)
* Masked API key display in admin
* Removed `<a>`, `<code>`, `<pre>` from AI output whitelist

= 1.0.6 =
* Fixed frequency logic to prioritize cache
* Added IP-based rate limit and per-question cooldown
* Added error handling for expired nonce or invalid parameters

= 1.0.5 =
* Initial public release

== Upgrade Notice ==

= 1.1.0 =
Adds typing effect, bot blocking, and a built-in disclaimer on the answer page while keeping all security protections. Recommended upgrade.

== Credits ==

Developed by **Horlicks (和製ホーリックス)**  
Blog: https://www.moelog.com/

== License ==

This plugin is open-source and distributed under the GPL v2 or later.

Copyright © 2025 Horlicks (moelog.com)

