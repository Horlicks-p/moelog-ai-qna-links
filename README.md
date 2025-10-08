=== Moelog AI Q&A Links ===  
Contributors: horlicks  
Author link: https://www.moelog.com/  
Tags: AI, OpenAI, Gemini, ChatGPT, Q&A, GPT, AI Answer  
Requires at least: 5.0  
Tested up to: 6.7  
Requires PHP: 7.4  
Stable tag: 1.5.1  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

== Description ==

**Moelog AI Q&A Links** is a WordPress plugin that enhances your posts and pages by appending a customizable list of questions at the bottom of each article.  
When users click on these questions, a new tab opens with AI-generated answers powered by OpenAI or Google Gemini.

The plugin allows customization of AI models, prompts, and language, making it ideal for multilingual content.  
Each question is securely encoded to prevent tampering, and caching ensures rapid load speed for repeated requests.

> Inspired by modern “Ask AI” sections in digital media — now you can easily add the same interactive experience to your own blog.


== Key Features ==

* 🧠 **AI Q&A List** – Add your own list of questions at the end of each post.  
* 🔗 **Smart URL Encoding** – Automatically generates short, unique URLs (v1.5+ ultra-compact format).  
* ⚡ **One-Click Answer Page** – Each question opens a clean AI answer page with a disclaimer.  
* 🌍 **Multilingual Support** – Auto-detects language or manually select zh / ja / en.  
* 🧩 **Customizable Prompts** – Supports “System Prompt” and “AI Model” for OpenAI & Gemini.  
* 🔒 **Secure Tokens** – HMAC-based URL protection with nonce and hash verification.  
* 🚀 **Caching & Rate-Limit** – Reduces API calls and prevents abuse.  
* 🎨 **Custom Heading & Disclaimer** – Editable via Settings → Moelog AI Q&A.  
* 🪶 **Lightweight & Theme-Friendly** – Minimal CSS, integrates seamlessly with any theme.  

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via WordPress admin.  
2. Activate the plugin through the **Plugins** menu.  
3. Go to **Settings → Moelog AI Q&A** and add your API key (OpenAI or Gemini).  
4. When editing posts, add questions in the “AI 問題清單” metabox (one per line).  
5. The question list will appear automatically below each post — or use shortcode `[moelog_aiqna]`.  

== Frequently Asked Questions ==

= Is it compatible with WordPress 6.7? =  
Yes. Fully tested on WordPress 6.7 and PHP 8.2.

= Can I use both OpenAI and Gemini? =  
You can switch providers anytime from the settings page. Each provider keeps its own model name.

= Are the answers cached? =  
Yes. Each unique question + post combination is cached for 24 hours (default 86400 seconds).

= How do I customize the disclaimer text? =  
In the settings page, modify “回答頁免責聲明”. You can use `{site}` or `%s` as a placeholder for your site name.

= What about the new short URL format? =  
Since v1.5.0, all generated URLs are extremely compact (e.g. `/qna/ic-a7-3239`), and remain backward compatible with older `/ai-answer/` links.

= What does the “Temperature” setting do? = 

The “Temperature” value controls how creative or deterministic the AI’s answers will be.
A lower value (e.g. 0.2–0.3) makes the AI give more focused and consistent answers, suitable for factual or technical topics.
A higher value (e.g. 0.7–1.0) increases creativity and randomness, which may be useful for brainstorming or open-ended questions.
For most blog-related Q&A use cases, a setting around 0.3 is recommended.

== Changelog ==

= 1.5.1 =
* ✅ Added **automatic rewrite flush** on activation (no need to resave permalinks).  
* ✅ CSP header refined for better compatibility (`connect-src` now includes Google Fonts).  
* ✅ Minor code cleanup and consistency improvements.  
* ✅ Maintains full backward compatibility with 1.3.x / 1.4.x links.  

= 1.5.0 =
* 🚀 Introduced ultra-short URL format (–84% shorter).  
* ✨ Improved token & hash mechanism for stable permalink mapping.  
* ⚙️ Added compatibility with existing `/ai-answer/` links.  
* 🧱 Added smart prefetching for smoother user experience.  

= 1.4.3a =
* Improved caching, model selection, and translation.  

== Screenshots ==

1. Settings page (API Key, model, and options).  
2. Post editor meta box for defining question list.  
3. Example of AI Q&A section at the bottom of an article.  
4. AI answer page with disclaimer and “Close Page” button.

== Upgrade Notice ==

= 1.5.1 =
Recommended update.  
Adds safe auto-flush for rewrite rules and improved security headers.  
No database change required.

== Author ==  

*Horlicks*
Website: [https://www.moelog.com]

This plugin is licensed under the GPL v2 or later.  
