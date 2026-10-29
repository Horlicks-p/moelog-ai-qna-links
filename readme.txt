=== Moelog AI Q&A Links ===
Contributors: Horlicks
Author URI: https://www.moelog.com/
Tags: AI, OpenAI, Gemini, Claude, ChatGPT, Anthropic, Q&A, GPT, AI Answer, Schema, Structured Data, CSP, Generative Engine Optimization
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Tested PHP: 8.3
Stable tag: 2.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== 🧠 Description ==

**Moelog AI Q&A Links** automatically adds an interactive **AI Q&A list** to the end of your posts or pages.  
When a reader clicks a question, a new tab opens and fetches an AI-generated answer from **OpenAI**, **Google Gemini**, or **Anthropic Claude**.

The answer page features a clean HTML layout, a typing animation effect, a built-in caching system (including static files), and an optional **STM (Structured Data Mode)** to help search engines and AI crawlers better understand the page content.

---

== ✨ Key Features ==

* **Multi-provider support:** Integrates with **OpenAI** (e.g., GPT-6 Luna), **Google Gemini** (e.g., Gemini 3.5 Flash-Lite), and **Anthropic Claude**.
* **Highly customizable:** Configure system prompt, model, and temperature.  
* **Per-question answer control:** Use standard AI, add editor-verified guidance, or display an editor-written answer without calling AI.
* **Smart language detection:** Built-in rules (Traditional Chinese / Japanese / English), no external API needed.  
* **Dual-layer caching:** WordPress Transients + **static HTML files** for faster load times.  
* **Smart pregeneration:** Uses a **content hash** to regenerate answers only when posts or questions change, saving API costs.  
* **Admin interface:**  
  * Metabox on the post editor for the “AI Question List”.  
  * **Drag-and-drop** sorting, add/delete questions, live word count.  
  * Gutenberg compatible.  
  * AJAX **“Regenerate All”** button to manually clear cache and trigger pregeneration.  
* **Routing & templating:**  
  * Pretty URLs using `qna/slug-hash-id/`.  
  * HMAC hashing ensures URL safety and prevents guessing.  
  * Customizable route base (default `qna`) and cache directory name.  
* **Shortcodes:**  
  * `[moelog_aiqna index="N"]` — insert a single question at any position in the post (index range: 1-8).  
  * The full question list is automatically appended at the bottom, excluding questions already displayed via shortcode to avoid duplicates.  
* **Security:**  
  * **API key encryption:** Stores API keys with `AES-256-CBC` (random IV), key material derived from WordPress salts.  
  * **Strict CSP:** Full **Content Security Policy** support; all inline scripts/styles use a `nonce`.  
* **Modular architecture:** Clean and maintainable code (Core / Router / Renderer / Cache / Metabox / AI_Client / Pregenerate).
* **Multi-language support:** Built-in translations for Traditional Chinese (`zh_TW`), English (`en_US`), and Japanese (`ja`).
* **Developer documentation:** Comprehensive technical docs (`docs/`) covering architecture, API reference, Hooks/Filters, STM mode, and i18n guide.

---

== 🚀 STM (Structured Data Mode) ==

STM helps search engines and AI crawlers **parse** your AI answer pages. **It does not guarantee indexing or ranking.**

**When enabled (optional):**  
* **SEO tags:** Sets Robots to `index, follow`.  
* **Canonical:** Adds a `canonical` link **pointing to the original post** (key SEO practice).  
* **Structured data:** Injects JSON-LD for `QAPage` and `BreadcrumbList`.  
* **Cache headers (CDN-friendly):**  
  * Outputs `Cache-Control` (with `s-maxage` and `stale-while-revalidate`).  
  * Outputs `Last-Modified` and `ETag`.  
  * **Supports `304 Not Modified`** to save crawl budget and server resources.  
* **Sitemap:**  
  * Generates an AI Q&A **sitemap index and pages** (`ai-qa-sitemap.php`).  
  * Uses `.php` to **avoid `.xml` route conflicts** with other SEO plugins.  
  * Automatically advertises the sitemap path in `robots.txt`.  
  * Pings Google and Bing on publish.  
* **Crawler access:** Allows popular crawlers like `Googlebot` and `Bingbot`.

**When disabled (default):**  
* **SEO tags:** Uses `noindex, nofollow` to prevent duplicate content and indexing.  
* **No** Schema, sitemap, or ping output.

---

== 🧩 Shortcodes ==

| Shortcode | Description |
|----------|-------------|
| `[moelog_aiqna index="1"]` | Insert question #1 at any position in the post |
| `[moelog_aiqna index="3"]` | Insert question #3 at any position in the post (supports 1–8) |

**Usage Notes:**
* Shortcodes are used to insert a **single question** link at **any position** in the post.
* The full question list is **automatically appended** at the bottom of the post. Questions already displayed via shortcode are automatically excluded to avoid duplicates.
* Example: Using `[moelog_aiqna index="1"]` to display question 1 in the middle of the post, the bottom list will automatically show only questions 2, 3, etc.
* **Note:** `[moelog_aiqna]` (without index parameter) has been removed. Only single-question mode is supported to simplify usage and avoid conflicts with the auto-appended list.

---

== 🧮 Caching System ==

* **TTL:** Configurable in the admin from 1–365 days (default 30 days).  
* **Mechanism:** Combines WordPress transients with static `.html` files in `wp-content/`.  
* **Management:** Global cache clear, or per-post clear from the editor.  
* **Headers:** CDN-friendly `Cache-Control` (with `stale-while-revalidate`).  
* **Smart rebuilds:** Uses a **content hash** to detect post changes and rebuild only when needed.  
* **Cache placeholders:** CSP `nonce` values in static cache are stored as `{{PLACEHOLDER}}` and filled at request time for security and performance.

---

== ⚙️ Performance & Stability ==

* **Modular design:** Separated core logic (Core, Router, Renderer, Cache, Metabox, Pregenerate, AI_Client).  
* **Autoloading:** Uses `spl_autoload_register` for class loading.  
* **Lifecycle:** Robust activate/deactivate/uninstall flows.  
  * **Activate:** Deferred rewrite flush, auto-generate HMAC secret.  
  * **Upgrade:** v1.8.3 migrates and **encrypts legacy plaintext API keys**.  
  * **Uninstall:** Cleans up all `options`, `post_meta`, `transients`, and the static cache directory.  
* **Compatibility:**  
  * Sitemap uses `.php` to avoid conflicts with popular SEO plugins (Slim SEO, AIOSEO, etc.).  
  * Metabox leverages `MutationObserver` for Gutenberg compatibility.  
* **Environment checks:** Validates PHP version at activation; admin checks for `json`, `hash`, `mbstring`, and more.

---

== 🔐 Security ==

* **API key encryption:** `AES-256-CBC` with WordPress salts–derived key material.  
* **CSP & Nonce:** Strict **Content Security Policy**; all inline scripts/styles verified with a `nonce`.  
* **Safe output:** All admin/front-end output is sanitized with `esc_html` / `esc_attr` / `wp_kses`.  
* **URL integrity:** **HMAC** used for answer page URLs to prevent enumeration/tampering.  
* **XSS protection:** HTML in AI answers is strictly filtered; `on...` attributes are removed and **URLs are rendered as harmless `<span>` elements**.  
* **Abuse prevention:** Built-in IP-based **rate limiting**.  
* **IP detection:** Trusts only `REMOTE_ADDR` by default. Forwarded headers are parsed only when trusted proxy/CDN CIDRs are explicitly configured.
* **Privacy-friendly rate limits:** Uses short-lived, site-salted anonymous hashes for abuse controls and feedback deduplication; full IPs are not stored in report emails or sent to AI providers.

Trusted proxies may be configured in `wp-config.php`; include only proxy/CDN ranges that directly connect to WordPress and sanitize forwarded headers:

`define('MOELOG_AIQNA_TRUSTED_PROXIES', ['10.0.0.0/8', '2001:db8:ffff::/48']);`

---

== 💬 Privacy ==

The plugin only sends the following to AI providers (OpenAI / Gemini / Claude):  
* Questions predefined by the **site author** in the admin.  
* (Optional) Original post content if “Include article content” is checked.  
* System prompt and language settings.
* (Optional) Editor-provided answer guidance / verified facts for that question. Editor-written answers are not sent to AI providers.

Visitor IPs, user agents, and other visitor personal data are **not sent to AI providers**. To protect public endpoints, the site temporarily stores rate-limit counters and anonymous identifiers derived from the IP plus the WordPress site salt; these hashes do not directly reveal the original IP. AI-provider traffic uses HTTPS.

---

== 🧩 Changelog ==

= 2.2.1 (2026-10-29) – Cloudflare Rocket Loader Fix =
* **Fix:** On sites behind Cloudflare with Rocket Loader on, answer pages showed "—" instead of view, like and dislike counts, and the feedback buttons did nothing. Rocket Loader re-runs inline scripts without the CSP nonce, so the browser blocked the feedback settings and `answer.js` never started. Answer-page scripts now carry `data-cfasync="false"`, so Rocket Loader leaves them alone.
* **After updating:** Clear the static answer cache (and the Cloudflare cache) so cached pages pick up the change.

= 2.2.0 (2026-10-29) – Model Refresh =
* **OpenAI:** The default model is now `gpt-6-luna`; `gpt-4o-mini` retires on 2027-01-14. GPT-6 Luna and GPT-5.6 Luna requests use `max_completion_tokens` and `reasoning_effort: none`, so answers do not stall on reasoning and stay within the request timeout. Other models are sent the same parameters as before.
* **Google Gemini:** The default model is now `gemini-3.5-flash-lite`; `gemini-2.5-flash` is no longer available to new API keys. Gemini 3 and later are no longer sent `temperature`, and non-Lite models get `thinkingLevel: low`. Gemini 2.5 requests are unchanged.
* **Gemini endpoint:** Requests go to `v1beta` again. Google documents Gemini 3 thinking settings only on `v1beta`; the API key stays in the `x-goog-api-key` header.
* **Gemini answers:** Multi-part replies are joined, and thought parts are skipped, instead of reading only the first part.
* **Anthropic:** The default stays `claude-opus-4-8`. Claude Sonnet 5.5, Haiku 5.5 and Opus 5.5 are listed. Claude 5 models get `effort: low` and at least 1024 output tokens, because their thinking counts toward the limit. Claude 4 requests are unchanged.
* **Model list:** The model picker now lists recommended and older models with notes, for example the GPT-4o mini retirement date.
* **Existing sites:** A saved model is never changed. A site using "Use default" follows the new default, as with earlier default changes; define `MOELOG_AIQNA_DEFAULT_MODEL_*` in `wp-config.php` to pin one. Because the model is part of the answer cache key, those sites generate new answers with the new model as pages are requested, within the daily and monthly generation budgets.
* **Translations:** The Claude model hint (`claude-opus-4-8`) is now translated; it still showed the old `claude-opus-4-7` text.

= 2.1.0 (2026-09-09) – Per-Question Answer Control =
* **Three answer modes:** Each question can use standard AI, AI with editor guidance, or an editor-written answer.
* **Editor guidance:** Adds verified settings and facts to the question prompt as high-priority instructions, improving accuracy for niche topics.
* **Editor-written answers:** Display complete Markdown answers without calling an AI provider, while retaining the existing HTML safety filters.
* **Cache consistency:** Answer settings follow drag-and-drop sorting; changing guidance or a manual answer invalidates and regenerates that question's cache.
* **Compact UI:** The icon-based answer-mode selector sits beside the language selector and only expands an input panel when needed.
* **Translations:** Updated Traditional Chinese, English, and Japanese translations plus feature and privacy documentation.

= 2.0.9 (2026-07-30) – Feedback Stats Fix =
* **Fix:** View, like, and dislike counts no longer reset to zero. Clearing or expiring the static HTML cache — including the automatic clear that runs on every post save — deleted the accumulated stats along with the files.
* **Orphan cleanup:** Stale stats are now detected by comparing each post’s current question list instead of checking whether a cached file exists, so only genuinely removed questions are cleaned up.
* **View dedupe:** The permanent browser-side `localStorage` flag is gone; deduplication is handled solely by the 24-hour server-side window, so an admin reset takes effect immediately for existing visitors.
* **Reset reliability:** Clearing all feedback stats now invalidates dedupe keys through a generation counter, which also works with a persistent object cache (Redis/Memcached).
* **Display:** Static pages no longer bake in a stale counter that was briefly shown before being corrected.

= 2.0.8 (2026-07-17) – Milestone B Completion =
* **Proxy trust:** `CF-Connecting-IP` now requires a separate Cloudflare/CDN CIDR allowlist; ordinary trusted proxies ignore spoofed Cloudflare headers and use the trusted XFF chain.
* **Feedback limits:** Sliding transient counters are replaced by atomic database increments in fixed windows with an observable status hook; rejected requests return HTTP 429 and `Retry-After`.
* **Cache maintenance:** Upgrade migration no longer encrypts `index.html` and repairs previously encrypted control files; uninstall safely supports custom cache directories containing case, underscores, or dots.
* **Metadata:** Corrected the lifecycle class `@since` version.

= 2.0.7 (2026-07-17) – Hotfix =
* **Fix:** Fatal error `Call to undefined method Parsedown::setSafeMode()` when another plugin or theme loads a legacy Parsedown (pre-1.7) first. The bundled parser is now plugin-prefixed (`Moelog_AIQnA_Parsedown`) and never collides with foreign copies.

= 2.0.6 (2026-07-17) – Correctness & Stability =
* **Updater:** GitHub updates require an exactly named release ZIP asset and never fall back to source archives.
* **Settings:** Custom URL prefix (`pretty_base`) and cache directory (`static_dir`) now take effect correctly; legacy wp-config constants remain supported as overrides.
* **Static cache:** Page payloads are encrypted with AES-256-GCM, so direct file access on Nginx/Caddy/IIS cannot leak HTML; tampered or legacy plaintext files are never served.
* **Cache fingerprints:** Answer and page caches are versioned separately — display or template changes re-render pages without new paid AI calls.
* **Cost controls:** Site-wide daily/monthly AI generation budgets (default 100/2000, 0 disables), a single-flight generation lock, and a configurable max output tokens setting (default 2048).
* **Availability:** Temporary capacity and provider errors return HTTP 503 with Retry-After instead of 500.
* **Providers:** Structured provider results — error text can no longer be cached as an answer, and translation changes no longer affect success detection.
* **Anthropic:** Default model for new installs is now `claude-opus-4-8`; saved model choices are never rewritten.
* **Lifecycle:** Deactivation clears all scheduled events; uninstall removes options, post meta, transients, budget counters, locks, and tracked cache directories with allowlist-only deletion.
* **CI:** GitHub Actions quality matrix (PHP 7.4–8.5), wp-env WordPress integration tests, and version-consistency gating with draft releases.

= 2.0.5 (2026-07-16) – Auto Update =
* **New:** Automatic updates from GitHub Releases via Plugin Update Checker 5.6 (bundled, MIT). Release ZIP assets are preferred over source archives.
* **New:** `moelog_aiqna_questions_block_html` filter lets themes customize the question list container markup without patching the plugin, so customizations survive automatic updates.

= 2.0.4 (2026-07-16) – Security Hardening =
* **Access policy:** Draft, private, password-protected, missing posts, and invalid answer tokens now return a consistent 404 before question metadata, cache, or AI access.
* **Static cache:** Apache cache directories deny direct HTTP access and existing `.htaccess` files are upgraded safely; normal answer URLs continue through PHP.
* **Trusted proxies:** Only `REMOTE_ADDR` is trusted by default; Cloudflare/X-Forwarded-For headers require explicitly configured proxy CIDRs.
* **Feedback endpoints:** Public post/question validation, server-recomputed hashes, basic rate limits, and duplicate view/vote protection.
* **Gemini:** API keys are sent in the `x-goog-api-key` header instead of request URLs.
* **Anthropic:** Opus 4.7/4.8, Sonnet 5, and unknown models use a conservative sampling request surface without model-name regex guessing.
* **Anthropic aliases:** Floating `*-latest` aliases are treated as unknown and omit temperature unless explicitly allowlisted after site contract testing.
* **Compatibility:** Existing public answer URLs and saved model IDs are not rewritten.

= 2.0.3 (2026-05-08) – Structured Data Fix =
- 🐛 **Fixed:** QAPage Schema was missing recommended fields flagged by Google Search Console: added `upvoteCount` and `url` to `acceptedAnswer`, added `author` and `datePublished` to `mainEntity` (Question), and added `url` to `acceptedAnswer.author`.

= 2.0.2 (2026-04-15) – Bug Fixes & Maintenance =
- 🐛 **Fixed:** Anthropic default model ID had wrong date suffix; upgraded to latest `claude-opus-4-7`.
- 🐛 **Fixed:** `is_error_message()` known error strings did not match actual Gemini/Anthropic error messages, allowing error responses to be written into the static cache.
- 🐛 **Fixed:** Static cache files called `touch()` on every read, resetting mtime and preventing TTL expiry. Removed `touch()` so cache expiry now works correctly.
- 🐛 **Fixed:** `moelog_aiqna_check_upgrade()` ran migrations in reverse order (1.8.3 before 1.8.0), causing the 1.8.0 TTL initialization to be skipped on fresh installs.
- 🐛 **Fixed:** `moelog_aiqna_clear_route_cache()` did not actually reset PHP static variable caches. Refactored to use a `$reset` parameter so `pretty_base` and `static_dir` caches are properly cleared on settings save.
- ✨ **New:** Added "Show Q&A Block" toggle in Display Settings (the `block_enabled` option previously had no UI and could only be controlled via code).
- 🔧 **Updated:** Gemini API endpoint upgraded from experimental `v1beta` to stable `v1`.
- 🔧 **Fixed:** `.htaccess` protection file no longer contains leading whitespace when created.

= 2.0.1 (2026-02-19) – Layout Refinement & Custom Banner =
- 🎨 **UI:** Replaced background images with pure CSS for precise padding, spacing, and border-radius control.
- 🎨 **Typography:** Standardized font sizes to px across body text, lists, and headings; responsive styles updated.
- 🖼️ **Custom Banner:** New admin upload option to set a custom banner image via the WordPress media library (recommended 880 × 240 px).
- 🐛 **Fixed:** Re-added missing `answer.js` script tag lost during refactoring.
- 🌍 **i18n:** Added English (`en_US`) and Japanese (`ja`) translations for custom banner strings.

= 2.0.0 (2026-02-18) – Major UI Overhaul & CSS Refinement =
- 📝 **Markdown:** Integrated Parsedown library to properly render Markdown in AI answers.
- 🎨 **Styles:** Overhauled answer page CSS to complement Markdown output.
- 🎨 **Admin UI:** Redesigned admin interface with a clean, minimal aesthetic.

= 1.10.2 (2025-11-28) – Bug Fixes & Improvements =
- 🐛 **Fixed:** Cache statistics now update immediately after deleting cache files.
- 🐛 **Fixed:** STM (Structured Data Mode) settings no longer reset when saving other tabs.
- 🔒 **Security:** Added anti-abuse measures to issue reporting (IP rate limit, honeypot, message length limit).
- ✨ **New:** Added "Feedback Feature" toggle in Display Settings to enable/disable interactive feedback.
- ✨ **New:** Added "Clear All Feedback Stats" button to delete all views/likes/dislikes data.
- 🎨 **UI:** Removed duplicate cache statistics from System Info page.
- ⚡ **Performance:** Cache management page now always shows real-time statistics.
- 🌍 **i18n:** Added full language pack support (Traditional Chinese `zh_TW`, English `en_US`, Japanese `ja`).
- 📚 **Docs:** Added technical documentation in `docs/` directory with 8 comprehensive technical documents.

= 1.10.1 (2025-11-26) – PHP 8.x Compatibility & Code Quality =
- 🐘 **PHP 8.x Compatibility:** Full compatibility with PHP 8.0, 8.1, 8.2, and 8.3.
- 🔧 **Fixed:** `preg_replace()`, `json_decode()`, `trim()`, `parse_url()` null handling.
- 🎨 **UI Enhancement:** Added consistent emoji icons to all admin section headers.
- 🎨 **UI Enhancement:** Removed redundant `<hr>` dividers for cleaner layout.
- 🔒 **Security:** Enhanced singleton pattern implementation for main plugin instance.
- ⚡ **Performance:** Added transient fallback for rate limiting without persistent object cache.

= 1.10.0 (2025-11-25) – Interactive Answer Page & Model Registry =
- ✨ Answer page overhaul with typing animation, interactive feedback card.
- 🤖 Model Registry + dropdown/custom inputs in admin, dynamic defaults per provider.
- 🧭 Settings screen split into five tabs (General, Display, Cache, Cache Tools, System Info).
- 🗺️ Sitemap rendering now chunks post IDs via `$wpdb`, preventing memory spikes.
- ⏱️ API timeout increased to 45s for GPT-4 / Claude long-form answers.

= 1.9.0 (2025-11-23) – Admin UI Improvements & Bug Fixes =
- ✨ Delete single static HTML file feature with question dropdown selection.
- 🔧 Improved AJAX error handling and nonce verification.
- ⚡ Optimized cache operations with batch processing.

---

== 🧭 Support ==

Bug reports & feature requests:  
Official site: https://www.moelog.com/  
GitHub: https://github.com/Horlicks-p/moelog-ai-qna-links  

---

== 🧩 License ==

This plugin is licensed under **GPL v2 or later**.  
You are free to modify and redistribute it.  
© 2025 Horlicks / moelog.com



