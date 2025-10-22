<?php
/**
 * Moelog AI Q&A Answer Page Template
 * 完整修復版 - 符合原始 render_answer_html 邏輯
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
    exit();
}

// 設定全域標記
$GLOBALS["moe_aiqna_is_answer_page"] = true;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo("charset"); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php
// GEO 模式預設允許索引
$default_robots = get_option("moelog_aiqna_geo_mode")
    ? "index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1"
    : "noindex,nofollow";
$robots = apply_filters("moelog_aiqna_answer_robots", $default_robots);
?>
<meta name="robots" content="<?php echo esc_attr($robots); ?>">
<link rel="canonical" href="<?php echo esc_url($answer_url); ?>" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=Noto+Sans+JP&family=Noto+Sans+TC:wght@100..900&family=Press+Start+2P&display=swap" rel="stylesheet">
	
<link rel="stylesheet" href="<?php echo esc_url(
    MOELOG_AIQNA_URL . "includes/assets/css/style.css"
); ?>?ver=<?php echo esc_attr(MOELOG_AIQNA_VERSION); ?>">
<link rel="stylesheet" href="<?php echo esc_url(
    get_stylesheet_uri()
); ?>?ver=<?php echo esc_attr(wp_get_theme()->get("Version")); ?>">
<?php
$post_title = get_the_title($post_id);
$site_name = get_bloginfo("name");
$ai_label = __("AI 解答", "moelog-ai-qna");

// 產生 SEO 友善的完整標題：〈文章標題〉 - AI 解答 | 〈站名〉
$title = sprintf(
    __('%1$s - %2$s | %3$s', "moelog-ai-qna"),
    $post_title,
    $ai_label,
    $site_name
);
?>
<title><?php echo esc_html($title); ?></title>
<?php do_action(
    "moelog_aiqna_answer_head",
    $answer_url,
    $post_id,
    $question,
    $answer
); ?>

<style nonce="<?php echo esc_attr($csp_nonce); ?>">
/* 打字游標 - 如果不想顯示,可以設定 display: none */
.moe-typing-cursor{display:none;width:1px;background:#999;margin-left:2px;animation:moe-blink 1s step-end infinite;vertical-align:baseline;}
@keyframes moe-blink{50%{background:transparent;}}
.moe-answer-wrap{word-wrap:break-word;word-break:break-word;overflow-wrap:anywhere;max-width:100%;}
.moe-answer-wrap p,.moe-answer-wrap li{word-wrap:break-word;word-break:break-word;overflow-wrap:anywhere;}
.moe-answer-wrap code,.moe-answer-wrap pre{word-wrap:break-word;word-break:break-all;overflow-wrap:anywhere;white-space:pre-wrap;max-width:100%;}
.moe-original-section {padding: 0 45px 0 45px;font-size: 120%;letter-spacing: 0.5px;color: #666;}
.moe-original-link {color: #94B800;text-decoration: none;}
.moe-original-link:hover {color: #69644e; text-decoration: underline;}
@media (max-width: 768px) {.moe-original-section {padding: 0 20px;}.moe-original-url { font-size: 0.85em;}}
</style>
<script nonce="<?php echo esc_attr($csp_nonce); ?>">
// 可在任何頁面先寫入全域預設;之後也能在後台設定頁動態輸出
window.MoelogAIQnA = window.MoelogAIQnA || {};
// 站點預設(毫秒/字),不填就走程式內建 12ms
if (typeof window.MoelogAIQnA.typing_ms === 'undefined') {
  window.MoelogAIQnA.typing_ms = <?php echo intval(
      apply_filters("moelog_aiqna_typing_speed", 10)
  ); ?>;
}
// 可選:全域隨機抖動(±毫秒),讓節奏更像真人
if (typeof window.MoelogAIQnA.typing_jitter_ms === 'undefined') {
  window.MoelogAIQnA.typing_jitter_ms = <?php echo intval(
      apply_filters("moelog_aiqna_typing_jitter", 6)
  ); ?>;
}
// 可選:設為 true 直接關掉打字動畫(用於 A/B 測試或法規頁)
if (typeof window.MoelogAIQnA.typing_disabled === 'undefined') {
  window.MoelogAIQnA.typing_disabled = <?php echo apply_filters(
      "moelog_aiqna_typing_disabled",
      false
  )
      ? "true"
      : "false"; ?>;
}
</script>
<!-- 打字效果外部腳本 -->
<script 
  src="<?php echo esc_url(
      plugins_url("includes/assets/js/typing.js", dirname(__FILE__))
  ); ?>?ver=<?php echo esc_attr(MOELOG_AIQNA_VERSION); ?>" 
  defer>
</script>
</head>
<body class="moe-aiqna-answer">
<div class="moe-container">
<?php
$banner_url = apply_filters("moelog_aiqna_banner_url", "");
$banner_alt = apply_filters("moelog_aiqna_banner_alt", get_bloginfo("name"));
?>
<div class="moe-banner" <?php if (
    $banner_url
): ?>style="background-image:url('<?php echo esc_url(
    $banner_url
); ?>')"<?php endif; ?> role="img" aria-label="<?php echo esc_attr(
     $banner_alt
 ); ?>"></div>

<!-- QUESTION bar（顯示 Q: 問題文字） -->
<div class="moe-answer-wrap">
  <div class="moe-question-echo"><?php echo esc_html($question); ?></div>
<?php
// 清理與安全化答案 HTML
$allowed = [
    "p" => [],
    "ul" => [],
    "ol" => [],
    "li" => [],
    "strong" => [],
    "em" => [],
    "br" => [],
    "span" => ["class" => [], "title" => []],
];
$safe_html = $answer ? wp_kses(wpautop($answer), $allowed) : "";

// 移除事件處理器
$safe_html = preg_replace(
    "/<(\w+)\s+[^>]*on\w+\s*=\s*[^>]*>/i",
    '<$1>',
    $safe_html
);

// 處理 URL - 轉換為帶 title 的 span (重要!)
$safe_html = preg_replace_callback(
    '/(https?:\/\/[^\s<>"]+)/i',
    function ($m) {
        $url = urldecode($m[1]);
        return '<span class="moe-url" title="' .
            esc_attr($url) .
            '">' .
            esc_html($url) .
            "</span>";
    },
    $safe_html
);

// 取得乾淨的域名與原文連結
$domain = parse_url(home_url(), PHP_URL_HOST);
$clean_domain = preg_replace("/^www\./", "", $domain);
$post_permalink = get_permalink($post_id);
$display_url = urldecode($post_permalink);

// 將「原文連結」納入打字來源
$original_html =
    '<div class="moe-original-section">
  ' .
    esc_html__("原文連結", "moelog-ai-qna") .
    '<br>
  [' .
    esc_html($clean_domain) .
    ']
  <a href="' .
    esc_url($post_permalink) .
    '"
     target="_blank"
     rel="noopener noreferrer"
     class="moe-original-link">' .
    esc_html($display_url) .
    '</a>
</div>';
?>
  <div id="moe-ans-target"></div>
  <!-- 將回答 + 原文連結一併放進 template，讓打字效果持續到結尾 -->
  <template id="moe-ans-source"><?php echo $safe_html .
      $original_html; ?></template>

  <noscript><?php echo $safe_html
      ? $safe_html
      : "<p>" .
          esc_html__(
              "抱歉,目前無法取得 AI 回答,請稍後再試。",
              "moelog-ai-qna"
          ) .
          "</p>"; ?></noscript>
  <script nonce="<?php echo esc_attr($csp_nonce); ?>">
  (function(){
    const srcTpl = document.getElementById('moe-ans-source');
    const target = document.getElementById('moe-ans-target');
    if(!srcTpl||!target) return;

    // 允許的標籤：加入 A / DIV，確保連結可點與區塊結構
    const ALLOWED = new Set(['P','UL','OL','LI','STRONG','EM','BR','SPAN','A','DIV']);
    const SPEED = window.MoelogAIQnA.typing_ms || 12;

    function cloneShallow(node){
      if(node.nodeType===Node.TEXT_NODE) return document.createTextNode('');
      if(node.nodeType===Node.ELEMENT_NODE){
        const tag=node.tagName.toUpperCase();
        if(!ALLOWED.has(tag)) return document.createTextNode(node.textContent||'');
        if(tag==='BR') return document.createElement('br');

        const el=document.createElement(tag.toLowerCase());

        // 通用屬性
        if(node.className) el.className=node.className;
        if(node.title)     el.title=node.title;

        // 超連結屬性
        if(tag==='A'){
          const href=node.getAttribute('href');   if(href) el.setAttribute('href', href);
          const tgt =node.getAttribute('target'); if(tgt)  el.setAttribute('target', tgt);
          const rel =node.getAttribute('rel');    if(rel)  el.setAttribute('rel', rel);
        }
        return el;
      }
      return document.createTextNode('');
    }

    function prepareTyping(srcParent, dstParent, queue){
      Array.from(srcParent.childNodes).forEach(src=>{
        if(src.nodeType===Node.TEXT_NODE){
          const t=document.createTextNode('');
          dstParent.appendChild(t);
          const text=src.textContent||'';
          if(text.length) queue.push({node:t, text});
        }else if(src.nodeType===Node.ELEMENT_NODE){
          const cloned=cloneShallow(src);
          dstParent.appendChild(cloned);
          if(cloned.nodeType===Node.TEXT_NODE){
            const text=cloned.textContent||'';
            if(text.length) queue.push({node:cloned, text});
          }else if(cloned.tagName && cloned.tagName.toUpperCase()==='BR'){
            // no-op
          }else{
            prepareTyping(src, cloned, queue);
          }
        }
      });
    }

    async function typeQueue(queue){
      if(window.MoelogAIQnA.typing_disabled){
        // 禁用打字效果,直接顯示全部內容
        for(const item of queue){
          item.node.textContent = item.text;
        }
        return;
      }

      const cursor=document.createElement('span');
      cursor.className='moe-typing-cursor';
      target.appendChild(cursor);
      
      for(const item of queue){
        const chars=Array.from(item.text);
        for(let i=0;i<chars.length;i++){
          item.node.textContent+=chars[i];
          await new Promise(r=>setTimeout(r, SPEED));
        }
      }
      
      // 確保移除游標
      if(cursor && cursor.parentNode){
        cursor.parentNode.removeChild(cursor);
      }
    }

    const sourceRoot=document.createElement('div');
    sourceRoot.innerHTML=srcTpl.innerHTML;
    const queue=[]; 
    prepareTyping(sourceRoot, target, queue);

    if(queue.length===0){
      target.innerHTML='<p><?php echo esc_js(
          esc_html__("抱歉,目前無法取得 AI 回答,請稍後再試。", "moelog-ai-qna")
      ); ?></p>';
    }else{
      typeQueue(queue);
    }
  })();
  </script>
</div>

<div class="moe-close-area">
  <a href="#" id="moe-close-btn" class="moe-close-btn"><?php esc_html_e(
      "← 關閉此頁",
      "moelog-ai-qna"
  ); ?></a>
  <div id="moelog-fallback" class="moe-fallback" style="display:none;">
    <?php esc_html_e(
        "若瀏覽器不允許自動關閉視窗,請點此回到文章:",
        "moelog-ai-qna"
    ); ?>
    <a href="<?php echo esc_url(
        get_permalink($post_id)
    ); ?>" target="_self" rel="noopener"><?php echo esc_html(
    get_the_title($post_id)
); ?></a>
  </div>
</div>
</div><!-- /.moe-container -->
<div class="moe-bottom"></div>

<?php
// 免責聲明
$site_name = get_bloginfo("name", "display");
if (empty($site_name)) {
    $host = parse_url(home_url(), PHP_URL_HOST);
    $site_name = $host ?: "本網站";
}

$options = get_option(MOELOG_AIQNA_OPT_KEY, []);
$default_tpl =
    "本頁面由AI生成,可能會發生錯誤,請查核重要資訊。\n使用本AI生成內容服務即表示您同意此內容僅供個人參考,且您了解輸出內容可能不準確。\n所有爭議內容{site}保有最終解釋權。";
$tpl =
    isset($options["disclaimer_text"]) && $options["disclaimer_text"] !== ""
        ? $options["disclaimer_text"]
        : $default_tpl;
$disclaimer = str_replace(["{site}", "%s"], $site_name, $tpl);
$disclaimer = apply_filters(
    "moelog_aiqna_disclaimer_text",
    $disclaimer,
    $site_name
);
?>
<p class="moe-disclaimer" style="margin-top:0px;text-align:center;font-size:0.85em; color:#666; line-height:1.5em;">
  <?php echo nl2br(esc_html($disclaimer)); ?>
</p>
</body>
</html>
