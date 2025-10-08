=== Moelog AI Q&A Links ===  
Contributors: Horlicks  
Author link: https://www.moelog.com/  
Tags: AI, OpenAI, Gemini, ChatGPT, Q&A, GPT, AI Answer  
Requires at least: 5.0  
Tested up to: 6.7  
Requires PHP: 7.4  
Stable tag: 1.5.1  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

== Description ==

This plugin appends a customizable list of AI Q&A links to each post or page.  
When a user clicks a question, a new tab opens with an AI-generated answer powered by OpenAI or Gemini.  
You can customize the model, prompt, and language, making it flexible for multilingual sites.  

=== Key Features ===  

- ✅ Append interactive Q&A list to posts/pages  
- ✅ Supports OpenAI & Google Gemini models  
- ✅ Customizable system prompt & model settings  
- ✅ Multilingual question support (auto / zh / ja / en)  
- ✅ Built-in rate limit & content cache  
- ✅ Optional context (include post content in AI query)  
- ✅ Customizable disclaimer text on answer pages  
- ✅ Full CSP (Content Security Policy) for answer pages  
- ✅ Cloudflare / proxy compatible IP detection  

== Installation ==  

1. Upload the plugin folder to `/wp-content/plugins/`  
2. Activate it from **Plugins → Installed Plugins**  
3. Go to **Settings → Moelog AI Q&A** and configure your API key and model  
4. Edit a post and add your Q&A list under “AI 問題清單” meta box (one question per line)  
5. The question list will automatically appear at the bottom of each post  
6. (Optional) Use the `[moelog_aiqna]` shortcode to display it manually  

== Changelog ==  

= 1.5.1 =  
* Improved security and compatibility  
* Added delayed rewrite flush on activation  
* Upgraded slug hash to 3 characters (shorter & safer URLs)  
* Enhanced CSP with `connect-src` for Google Fonts  
* Improved IP detection for Cloudflare / reverse proxy  
* Removed duplicate CSS load in answer page  
* Better handling of fonts and head/footer order  

= 1.5.0 =  
* URL shortened by 84% (from 226 chars → 36 chars)  
* Path simplified: `/ai-answer/` → `/qna/`  
* Added intelligent abbreviation (ICANN → ic, DNS → dns)  
* Improved cache & hash structure  
* Full backward compatibility with 1.3.2 & 1.4.3  

= 1.4.3 =  
* Stable Gemini integration and unified cache key system  
* Rate limit and transient caching optimized  
* Compatibility patch for WordPress 6.6  

= 1.3.2 =  
* Added Gemini support (Google API)  
* Enhanced prompt customization and localization  
* Added disclaimer text field and heading customization  

== Frequently Asked Questions ==  

### Q: How do I enable AI answers?
Go to **Settings → Moelog AI Q&A**, fill in your API key (OpenAI or Gemini),  
and select your preferred model. Then, edit a post and add your questions.

### Q: Can I change the look of the question list?
Yes, the plugin loads its own lightweight CSS file (`assets/style.css`).  
You can override styles from your theme’s stylesheet.

### Q: What does the “Temperature” setting do?

**A:**  
The “Temperature” value controls how *creative* or *deterministic* the AI’s answers will be.  
A **lower value** (e.g. `0.2`–`0.3`) makes the AI give more focused and consistent answers, suitable for factual or technical topics.  
A **higher value** (e.g. `0.7`–`1.0`) increases creativity and randomness, which may be useful for brainstorming or open-ended questions.  
For most blog-related Q&A use cases, a setting around **0.3** is recommended.

### Q: Can I include the post content for better context?
Yes. Check “Include post content in AI context” in the settings page.  
It sends part of your post to the AI for more relevant answers.

### Q: What happens if I change my permalink or theme?
No problem. The plugin uses dynamic WordPress functions (`home_url`, `user_trailingslashit`)  
and automatically keeps old `/ai-answer/` links backward compatible.  

== Screenshots ==  

1. Admin settings page for API and model selection  
2. Post edit screen meta box (“AI question list”)  
3. Example of generated Q&A list under a post  
4. AI answer page with disclaimer and close button  

== Upgrade Notice ==  

= 1.5.1 =  
This version improves security headers (CSP), short URL generation,  
and Cloudflare compatibility.  
Recommended for all users upgrading from 1.5.0 or earlier.  

== License ==  

This plugin is licensed under the GPL v2 or later.  
You may redistribute or modify it under the same license terms.  

© 2025 Horlicks / moelog.com  
