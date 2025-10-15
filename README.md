=== Moelog AI Q&A Links ===

Contributors: horlicks  
Author link: https://www.moelog.com/  
Tags: AI, OpenAI, Gemini, ChatGPT, Q&A, GPT, AI Answer, SEO, Schema, GEO  
Requires at least: 5.0  
Tested up to: 6.7  
Requires PHP: 7.4  
Stable tag: 1.6.3  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

== Description ==

**Moelog AI Q&A Links** appends a customizable list of AI Q&A links to each post or page.  
When a user clicks a question, a new tab opens with an AI-generated answer powered by **OpenAI** or **Gemini**.  
You can customize the model, prompt, and language, making it flexible for multilingual sites.

🆕 **NEW in 1.6.2:** Enhanced shortcode functionality! Supports `[moelog_aiqna index="N"]` to insert individual questions anywhere in your content, with smart duplicate prevention.

🧠 **NEW in 1.6.0:** Built-in **GEO (Generative Engine Optimization)** module helps your AI-generated content get discovered and cited by Google SGE, Bing Copilot, Perplexity, and other AI-powered search engines.

== Key Features ==

✅ Append interactive Q&A list to posts/pages  
✅ Flexible shortcodes - Insert entire list or individual questions  
✅ Supports **OpenAI & Google Gemini** models  
✅ Customizable system prompt & model settings  
✅ Multilingual question support (auto / zh / ja / en)  
✅ Built-in rate limit & content cache  
✅ Optional context (include post content in AI query)  
✅ Customizable disclaimer text on answer pages  
✅ Full **CSP (Content Security Policy)** for answer pages  
✅ Cloudflare / proxy compatible IP detection  
✅ **GEO Module** - Optimize AI answers for generative search engines  
✅ Cache Management - Clear AI answer cache from admin panel  

---

== 🚀 GEO Module Features (v1.6.0+) ==

**What is GEO?**  
Generative Engine Optimization (GEO) makes your AI-generated Q&A content more discoverable by next-generation AI search engines like Google SGE, Bing Copilot, and Perplexity.

When enabled, the GEO module adds:

- Schema.org **QAPage** structured data for each answer  
- **Open Graph & Twitter Card** meta tags  
- Breadcrumb navigation structured data  
- Removes `noindex` restriction (allow search indexing)  
- Optimized caching (24-hour public cache + stale-while-revalidate)  
- **AI Q&A Sitemap** – dedicated XML sitemap for all answers  
- Auto-ping Google & Bing when new content is published  
- Search bot allowlist (Google, Bing, Perplexity, etc.)

**How to enable GEO Mode:**

1. Go to **Settings → Moelog AI Q&A**  
2. Scroll to **GEO (Generative Engine Optimization)** section  
3. Check **“Enable structured data, SEO optimization & AI Sitemap”**  
4. Go to **Settings → Permalinks**, click **Save Changes**  
5. Submit `/ai-qa-sitemap.php` to **Google Search Console** & **Bing Webmaster Tools**

---

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`  
2. Activate it from **Plugins → Installed Plugins**  
3. Go to **Settings → Moelog AI Q&A** and configure your API key and model  
4. *(Optional)* Enable GEO mode for SEO enhancements  
5. Edit a post and add your Q&A list in **AI question list** meta box (one per line)  
6. The question list appears automatically below the post  

Shortcodes:  
- `[moelog_aiqna]` — Display full list  
- `[moelog_aiqna index="1"]` — Display only question #1  
- `[moelog_aiqna index="3"]` — Display only question #3  

---

== Frequently Asked Questions ==

= How do I use the shortcode features? =
- `[moelog_aiqna]` → Entire list  
- `[moelog_aiqna index="1"]` → Individual question  

When shortcodes are used, the automatic list is hidden to prevent duplicates.

= What’s the difference between automatic display and shortcodes? =
- **Automatic:** Always shows Q&A list below post.  
- **Shortcode:** Lets you position individual questions anywhere.

= What is GEO mode? =
GEO (Generative Engine Optimization) improves visibility to AI search engines (Google SGE, Bing Copilot, etc.).  
Enable if you want AI-generated answers indexed and cited.

= Where is the AI Q&A Sitemap? =
`https://yoursite.com/ai-qa-sitemap.php`

Submit to:
- Google Search Console → *Sitemaps*  
- Bing Webmaster Tools → *Sitemaps*

= How do I clear cached answers? =
**Settings → Moelog AI Q&A → Cache Management**

Options:
- Clear all cached answers  
- Clear cache for specific post/question  

= Can I change the look of the Q&A list? =
Yes. Override `/assets/style.css` from your theme’s CSS.

= What does the “Temperature” setting do? =
- **0.2–0.3:** Factual / stable  
- **0.7–1.0:** Creative / varied  
Default: 0.3

= Can I include post content for better context? =
Yes, check **“Include post content in AI context.”**

= Does GEO mode affect performance? =
No. Structured data is lightweight and cached for 24h.

---

== Screenshots ==

1. Admin settings page for API/model selection  
2. GEO settings section with sitemap options  
3. Cache management interface  
4. Post edit screen meta box with shortcode instructions  
5. Example Q&A list under a post  
6. Individual question shortcode  
7. AI answer page with schema markup  

---

== Changelog ==

= 1.6.3 (2025-10-15) =  
**Maintenance & Refinement Update**

- Unified sitemap file to `.php` for compatibility with XML Sitemap Generator plugins  
- Enhanced GEO module initialization and admin notice handling  
- Improved integration between main plugin and GEO module  
- Fixed potential head/meta duplication issue with SEO plugins  
- Minor code clean-up and inline documentation updates  


= 1.6.2 (2025-10-14) =
**Major Update: Enhanced Shortcode Functionality**

**New Features:**
* **Flexible shortcode system** - Insert questions anywhere in your content
  - `[moelog_aiqna]` - Display entire question list
  - `[moelog_aiqna index="1"]` - Display only question #1 (range: 1-8)
  - `[moelog_aiqna index="3"]` - Display only question #3
* **Smart duplicate prevention** - Automatic list at post bottom is hidden when shortcodes are used
* **Enhanced prefetch script** - Single-question links now support hover prefetch
* **Improved admin UI** - Metabox now shows shortcode usage examples

**Improvements:**
* Optimized prefetch script injection - prevents duplicate loading
* Better error handling for invalid shortcode parameters
* HTML comment hints when questions don't exist
* Improved code modularity with separate shortcode handler

**Bug Fixes:**
* Fixed issue where shortcodes and automatic list would both appear
* Fixed prefetch not working with single-question links
* Fixed undefined variable in shortcode processing

**Documentation:**
* Added comprehensive shortcode examples in admin
* Updated metabox with blue-highlighted tips
* Improved settings page with clear usage instructions

= 1.6.1 (2025-10-13) =
**Improvements & Bug Fixes:**

**New Features:**
* Added cache management UI in admin settings
  - Clear all cached AI answers at once
  - Clear cache for specific questions by post ID
  - Real-time feedback on cache clearing operations
* Added typing animation support for SPAN tags in answer pages
* Improved URL display: Chinese/Japanese URLs now display decoded

**Bug Fixes:**
* Fixed URL encoding display issue - non-ASCII characters in URLs now render correctly
* Fixed typing animation not working with URL spans
* Fixed long URLs breaking page layout (added automatic line wrapping)
* Removed Markdown link syntax from AI responses

**AI Prompt Improvements:**
* Enhanced citation rules to prevent AI from incorrectly citing the source article
* Added explicit instructions for AI to use domain names only in citations
* Improved multi-language prompts with better citation guidelines

= 1.6.0 (2025-10-12) =
**Major Update: GEO Module**

**New Features:**
* Added GEO (Generative Engine Optimization) module for AI search engines
* Schema.org QAPage structured data for each AI answer
* Open Graph & Twitter Card meta tags
* Breadcrumb navigation structured data
* Dedicated AI Q&A Sitemap (`/ai-qa-sitemap.xml`)
* Auto-ping Google & Bing when new content is published
* Allowlist for major search engine bots
* Optimized HTTP headers for public caching (24h CDN cache)

**Improvements:**
* Modular architecture: GEO is a separate file (`moelog-ai-geo.php`)
* Public API for external modules
* Enhanced documentation and inline comments

= 1.5.1 =
* Improved security and compatibility
* Added delayed rewrite flush on activation
* Upgraded slug hash to 3 characters
* Enhanced CSP with connect-src for Google Fonts
* Better handling of fonts and head/footer order

= 1.5.0 =
* URL shortened by 84% (from 226 chars → 36 chars)
* Path simplified: `/ai-answer/` → `/qna/`
* Added intelligent abbreviation
* Full backward compatibility with previous versions

== Upgrade Notice ==

= 1.6.2 =
**Recommended update with flexible shortcode system!**
This version adds powerful new shortcode features for inserting individual questions
anywhere in your content, with smart duplicate prevention.

**After upgrading:**
- Visit any post and check the "AI question list" metabox for new shortcode instructions
- Try inserting `[moelog_aiqna index="1"]` to place individual questions in your content
- No configuration needed - shortcodes work immediately!

No breaking changes. Fully compatible with 1.6.1.

= 1.6.1 =
**Recommended update with cache management & URL display fixes!**
This version adds a cache management UI and fixes several display issues with non-ASCII URLs.

**After upgrading:**
- Check Settings → Moelog AI Q&A → Cache Management to test the new features
- If you have cached answers with display issues, use "Clear all cache" to regenerate them

No breaking changes. Fully compatible with 1.6.0.

= 1.6.0 =
**Major update with GEO module!**
This version adds Generative Engine Optimization features to help your AI answers
get discovered by Google SGE, Bing Copilot, and other AI search engines.

**After upgrading:**
1. Go to Settings → Permalinks and click "Save Changes"
2. (Optional) Enable GEO mode in Settings → Moelog AI Q&A
3. Submit `/ai-qa-sitemap.xml` to search engines

Fully backward compatible with 1.5.x URLs.

== Technical Details ==

**Shortcode System (v1.6.2+):**
- Core function: `shortcode_questions_block($atts)`
- Supported parameters: `index` (integer, 1-8)
- Automatic duplicate prevention via `has_shortcode()` check
- Single-question prefetch optimization with `ensure_prefetch_script_once()`

**URL Structure:**
- New format (v1.5+): `/qna/{abbr}-{hash}-{post_id}/`
- Example: `/qna/dns-a7f-12345/`
- Old format (v1.3-1.4): `/ai-answer/{slug}-{post_id}/`
- Both formats remain functional (backward compatibility)

**Cache Management (v1.6.1+):**
- Cache TTL: 24 hours (86400 seconds)
- Admin UI for clearing cache (all or specific questions)
- Cache key format: `moe_aiqna_{hash(post_id|question|model|context|lang)}`
- Automatic expiration handling

**GEO Architecture:**
- Main plugin: `moelog-ai-qna.php` (core functionality)
- GEO module: `moelog-ai-geo.php` (optional SEO features)
- Clean separation: GEO can be disabled without affecting core features
- Hook-based integration: `moelog_aiqna_answer_head` action, filters

**Security:**
- Content Security Policy (CSP) with nonce-based script execution
- Rate limiting: Per-IP and per-question
- HMAC-based URL signing for cache integrity
- XSS protection: All output is escaped
- HTML sanitization: Only allows safe tags

**Performance:**
- Answer caching: 24 hours (86400 seconds)
- CDN-friendly: `s-maxage` header for edge caching
- Stale-while-revalidate: 60 seconds
- Smart prefetch: Hover-triggered resource hints (100ms delay)
- Typing animation: 18ms per character for smooth UX

== Privacy & Data ==

This plugin sends the following data to OpenAI/Gemini APIs:
- User's question (from your pre-defined list)
- (Optional) Post content excerpt (if "Include post content" is enabled)
- System prompt (configured by site admin)

**No user personal data is sent.**
All communications with AI APIs are encrypted via HTTPS.

IP addresses are used only for rate limiting and are not stored permanently.
Cached answers are stored in WordPress transients (database) for 24 hours.

**v1.6.1+:** The cache management feature allows administrators to manually delete cached answers.

== Support ==

For bug reports, feature requests, or questions:
- Visit: https://www.moelog.com/
- GitHub: [https://github.com/Horlicks/moelog-ai-qna-links](https://github.com/Horlicks-p/moelog-ai-qna-links)

== License ==

This plugin is licensed under the GPL v2 or later.
You may redistribute or modify it under the same license terms.

© 2025 Horlicks / moelog.com
