=== Moelog AI Q&A Links ===
Contributors: Horlicks
Author URI: https://www.moelog.com/
Tags: AI, OpenAI, Gemini, Claude, ChatGPT, Anthropic, Q&A, GPT, AI Answer, Schema, Structured Data, CSP, Generative Engine Optimization
Requires at least: 5.0
Tested up to: 6.8.3
Requires PHP: 7.4
Tested PHP: 8.3
Stable tag: 1.10.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== 🧠 Description ==

**Moelog AI Q&A Links** automatically adds an interactive **AI Q&A list** to the end of your posts or pages.  
When a reader clicks a question, a new tab opens and fetches an AI-generated answer from **OpenAI**, **Google Gemini**, or **Anthropic Claude**.

The answer page features a clean HTML layout, a typing animation effect, a built-in caching system (including static files), and an optional **STM (Structured Data Mode)** to help search engines and AI crawlers better understand the page content.

---

== ✨ Key Features ==

* **Multi-provider support:** Integrates with **OpenAI** (e.g., GPT-4o-mini), **Google Gemini** (e.g., Gemini 2.5 Flash), and **Anthropic Claude**.  
* **Highly customizable:** Configure system prompt, model, and temperature.  
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
* **IP detection:** Correctly identifies real client IPs behind Cloudflare and reverse proxies.  
* **GDPR:** No collection or transmission of visitor personal data.

---

== 💬 Privacy ==

The plugin only sends the following to AI providers (OpenAI / Gemini / Claude):  
* Questions predefined by the **site author** in the admin.  
* (Optional) Original post content if “Include article content” is checked.  
* System prompt and language settings.

**No** visitor IPs, user agents, or personal data are sent. All communications are encrypted via HTTPS.

---

== 🧩 Changelog ==

= 1.10.2 (2025-11-27) – Bug Fixes & Improvements =
- 🐛 **Fixed:** Cache statistics now update immediately after deleting cache files.
- 🐛 **Fixed:** STM (Structured Data Mode) settings no longer reset when saving other tabs.
- 🎨 **UI:** Removed duplicate cache statistics from System Info page.
- ⚡ **Performance:** Cache management page now always shows real-time statistics.

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



