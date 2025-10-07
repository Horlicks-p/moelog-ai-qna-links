=== Moelog AI Q&A Links ===  
Contributors: Horlicks  
Author link: https://www.moelog.com/  
Tags: AI, OpenAI, Gemini, ChatGPT, Q&A, GPT, AI Answer  
Requires at least: 5.0  
Tested up to: 6.7  
Requires PHP: 7.4  
Stable tag: 1.4.3a  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

== Description ==  

Moelog AI Q&A Links is a WordPress plugin that enhances your posts and pages by appending a customizable list of questions at the bottom of each article.  
When users click on these questions, a new tab opens with AI-generated answers powered by **OpenAI** or **Google Gemini**.  
The plugin allows customization of AI models, prompts, and languages, making it versatile for multilingual content.  

== Key Features ==  

- **Dynamic Question Lists:** Add a list of questions to posts or pages via a metabox in the WordPress editor.  
  Each question is displayed at the article's bottom, with customizable headings.  

- **AI-Powered Answers:** Supports OpenAI (default: `gpt-4o-mini`) and Google Gemini (default: `gemini-2.5-flash`) for generating answers.  
  Users can specify models and adjust parameters like temperature.  

- **Multilingual Support:** Questions can be set to auto-detect language or manually specified (English, Chinese, or Japanese),  
  ensuring accurate AI responses in the desired language.  

- **Customizable Prompts:** Define system prompts to tailor AI behavior, ensuring concise and professional answers.  

- **Content Context:** Optionally include post content (up to a configurable character limit) as context for more relevant AI answers.  

- **Shortcode Support:** Use `[moelog_aiqna]` to manually insert question lists anywhere in your content.  

- **Pretty URLs:** Generates clean, readable URLs for answer pages (e.g., `/ai-answer/question-slug-postid/`).  

- **Caching:** Answers are cached for **12 hours** to reduce API calls and improve performance.  

- **Customizable Disclaimer:** Display a customizable disclaimer on answer pages, supporting placeholders like `{site}` for the site name.  

== Installation ==  

1. Upload the `moelog-ai-qna` folder to the `/wp-content/plugins/` directory.  
2. Activate the plugin through the **Plugins** menu in WordPress.  
3. Go to **Settings → Moelog AI Q&A** to configure the API key, AI provider, model, and other options.  
4. In the post or page editor, use the **AI Questions** metabox to add questions (one per line, up to 8 questions, 200 characters each).  
5. Optionally, insert the `[moelog_aiqna]` shortcode in your content to display the question list manually.  

== Configuration ==  

- **API Key:** Enter your OpenAI or Google Gemini API key on the settings page,  
  or define `MOELOG_AIQNA_API_KEY` in `wp-config.php` for enhanced security.  
- **AI Provider:** Choose between OpenAI or Google Gemini.  
- **Model:** Specify the AI model (e.g., `gpt-4o-mini` or `gemini-2.5-flash`).  
- **Temperature:** Adjust the creativity of AI responses (0–2, default: 0.3).  
- **Include Post Content:** Send post content as context (default: 6000 characters, configurable).  
- **System Prompt:** Customize the AI’s tone or role (e.g., “You are a professional editor providing concise and accurate answers.”).  
- **List Heading:** Customize the title displayed above the question list.  
- **Disclaimer Text:** Customize the disclaimer shown on answer pages, supporting `{site}` or `%s` placeholders.  

== Usage ==  

1. **Add Questions:** In the post/page editor, use the **AI Questions** metabox to input questions and select their language (auto, zh, ja, en).  
2. **View Questions:** Questions appear at the bottom of posts/pages or where the `[moelog_aiqna]` shortcode is used.  
3. **Access Answers:** Clicking a question opens a new tab with the AI-generated answer, styled with a typing animation and disclaimer.  
4. **Close Answer Page:** Users can close the answer page using a “← Close this page” button,  
   with fallback navigation to the original post if the browser blocks window.close().  

== Security Features ==  

Moelog AI Q&A Links prioritizes security to protect your site and users:  

- **HMAC-Based Token Validation:** Uses HMAC-SHA256 with a per-site secret key to secure question URLs.  
- **Nonce Protection:** Implements CSP nonces to mitigate XSS risks.  
- **Input Sanitization:** Sanitizes all user inputs using WordPress functions (`sanitize_text_field`, `wp_kses_post`, etc.).  
- **Rate Limiting:** Restricts API requests per IP (10/hour) and per question (1 every 60 seconds).  
- **Bot Blocking:** Blocks known crawlers (e.g., Googlebot, Bingbot) from loading answer pages.  
- **Referrer Policy:** Sets `strict-origin-when-cross-origin` to prevent sensitive data leakage.  
- **Safe HTML Output:** Filters AI answers through `wp_kses` with a strict whitelist.  
- **Secure API Key Storage:** Supports defining API keys in `wp-config.php`; masks saved keys in the admin panel.  
- **Pretty URL Compatibility:** Backward compatible with older 6-character salted slugs.  
- **Cache Security:** Uses transient-based caching with hashed keys to prevent cache poisoning.  
- **Uninstallation Cleanup:** Removes all plugin data, post meta, and transients on uninstall.  

== Frequently Asked Questions ==  

**Q: How do I get an API key?**  
A: Obtain a key from [OpenAI](https://platform.openai.com) or [Google Gemini](https://cloud.google.com/gemini).  
Enter it on the settings page or define it in `wp-config.php`.  

**Q: Can I use both OpenAI and Gemini?**  
A: Yes, but only one provider can be active at a time. You can switch anytime in the settings.  

**Q: Why are my questions not showing?**  
A: Ensure you’ve added questions in the metabox and that the post is published.  
If using the shortcode, verify it’s correctly placed in the content.  

**Q: How secure is this plugin?**  
A: The plugin uses HMAC validation, CSP nonces, sanitization, and rate limiting.  
For maximum security, define your API key in `wp-config.php`.  

== Changelog ==  

= 1.4.3a – 2025-10-07 =  
* Fixed Pretty URL token validation for stateless question restoration.  
* Enhanced slug compatibility for 6- and 8-character salts.  
* Improved close-page behavior under strict CSP policies.  
* Added `window.open()` handling for better browser compatibility.  
* Optimized caching logic (12-hour default) for faster response and reduced API usage.  
* Improved overall input sanitization and fallback navigation.  

= 1.4.3 – 2025-10-06 =  
* Introduced AI answer caching (24 hours, now reduced to 12 hours).  
* Added CSP Nonce and Referrer Policy.  
* Updated slug salt from 6 to 8 characters for improved uniqueness.  

= 1.3.2 – 2025-10-05 =  
* Added Gemini provider support alongside OpenAI.  
* Introduced customizable list headings and disclaimer text.  
* Enhanced settings UI and multilingual support.  

== Author ==  

**Horlicks**  
Website: [https://www.moelog.com]

This plugin is licensed under the GPL v2 or later.  
