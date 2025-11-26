<?php
/**
 * Moelog AI Q&A Renderer Template Class
 *
 * 負責渲染器的模板處理功能:
 * - HTML 生成
 * - 模板變數準備
 * - 答案內容處理
 * - 模板渲染
 * - 內聯樣式和腳本
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
  exit();
}

class Moelog_AIQnA_Renderer_Template
{
  /**
   * 路由器實例
   * @var Moelog_AIQnA_Router
   */
  private $router;

  /**
   * AI 客戶端實例（用於錯誤檢查）
   * @var Moelog_AIQnA_AI_Client
   */
  private $ai_client;

  /**
   * CSP Nonce
   * @var string
   */
  private $csp_nonce;

  /**
   * Nonce placeholder 常數
   */
  const NONCE_PLACEHOLDER = "{{MOELOG_CSP_NONCE}}";

  /**
   * 建構函數
   *
   * @param Moelog_AIQnA_Router    $router    路由器
   * @param Moelog_AIQnA_AI_Client $ai_client AI 客戶端
   */
  public function __construct($router, $ai_client)
  {
    $this->router = $router;
    $this->ai_client = $ai_client;
    $this->csp_nonce = "";
  }

  /**
   * 設定 CSP Nonce
   *
   * @param string $nonce
   */
  public function set_csp_nonce($nonce)
  {
    $this->csp_nonce = $nonce;
  }

  /**
   * 建立完整的答案頁 HTML
   *
   * @param array   $params 請求參數
   * @param WP_Post $post   文章物件
   * @param string  $answer AI 回答
   * @return string
   */
  public function build_html($params, $post, $answer)
  {
    // 標記為回答頁(供 GEO 模組使用)
    $GLOBALS["moe_aiqna_is_answer_page"] = true;

    // 準備模板變數
    $template_vars = $this->prepare_template_vars(
      $params,
      $post,
      $answer
    );

    // 開始輸出緩衝
    ob_start();

    // 載入模板
    $this->render_template($template_vars);

    return ob_get_clean();
  }

  /**
   * 準備模板變數
   *
   * @param array   $params 請求參數
   * @param WP_Post $post   文章物件
   * @param string  $answer AI 回答
   * @return array
   */
  public function prepare_template_vars(
    $params,
    $post,
    $answer
  ) {
    $question_hash = isset($params["question_hash"])
      ? $params["question_hash"]
      : Moelog_AIQnA_Cache::generate_hash($params["post_id"], $params["question"]);

    // 基本資訊
    $vars = [
      "post_id" => $params["post_id"],
      "question" => $params["question"],
      "question_hash" => $question_hash,
      "answer" => $answer,
      "post_title" => get_the_title($post),
      "post_url" => get_permalink($post),
      "answer_url" => $this->router->build_url(
        $params["post_id"],
        $params["question"],
      ),
      "site_name" => get_bloginfo("name", "display"),
      "site_url" => home_url("/"),
      "charset" => get_bloginfo("charset"),
      "language" => get_bloginfo("language"),
    ];

    // 處理答案內容
    $vars["answer_html"] = $this->process_answer_html($answer);

    // 原文連結區塊
    $vars["original_link_html"] = $this->build_original_link_html($vars);

    // Robots meta
    $default_robots = get_option("moelog_aiqna_geo_mode")
      ? "index, follow"
      : "noindex, follow";
    $vars["robots"] = apply_filters(
      "moelog_aiqna_robots_meta",
      $default_robots,
    );

    // Banner 圖片
    $vars["banner_url"] = "";
    $vars["banner_alt"] = get_the_title($post);

    $thumb_id = get_post_thumbnail_id($post->ID);
    if ($thumb_id) {
      $vars["banner_url"] = wp_get_attachment_image_url($thumb_id, "full");
    }

    // 免責聲明
    $vars["disclaimer"] = $this->get_disclaimer($vars["site_name"]);

    // ✅ 修正: 資源 URL (加回 plugin_url 和版本號)
    $vars["plugin_url"] = MOELOG_AIQNA_URL;
    $vars["style_url"] =
      MOELOG_AIQNA_URL .
      "includes/assets/css/style.css?ver=" .
      MOELOG_AIQNA_VERSION;
    $vars["theme_style_url"] =
      get_stylesheet_uri() . "?ver=" . wp_get_theme()->get("Version");

    // CSP Nonce
    $vars["csp_nonce"] = $this->csp_nonce;

    return apply_filters("moelog_aiqna_template_vars", $vars, $params, $post);
  }

  /**
   * 檢查答案是否為錯誤訊息
   *
   * ✅ 新增: 用於優化項目 5,避免將錯誤寫入快取
   *
   * @param string $answer 答案內容
   * @return bool
   */
  public function is_error_response($answer)
  {
    if (empty($answer)) {
      return true;
    }

    // 使用 AI Client 的錯誤檢查方法
    if (method_exists($this->ai_client, "is_error_message")) {
      return $this->ai_client->is_error_message($answer);
    }

    // 備用檢查邏輯
    $error_keywords = [
      "失敗",
      "錯誤",
      "無法",
      "暫時",
      "異常",
      "fail",
      "error",
      "unable",
      "unavailable",
    ];

    foreach ($error_keywords as $keyword) {
      if (stripos($answer, $keyword) !== false) {
        return true;
      }
    }

    return false;
  }

  /**
   * 處理答案 HTML(安全化與格式化)
   *
   * @param string $answer 原始答案
   * @return string
   */
  public function process_answer_html($answer)
  {
    // 允許的 HTML 標籤
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

    // 清理 HTML
    $safe_html = $answer ? wp_kses(wpautop($answer), $allowed) : "";

    // 移除事件處理器
    $safe_html = preg_replace(
      "/<(\w+)\s+[^>]*on\w+\s*=\s*[^>]*>/i",
      '<$1>',
      $safe_html,
    );

    // 處理 URL(轉換為帶 title 的 span)
    $safe_html = preg_replace_callback(
      '/(https?:\/\/[^\s<>"]+)/i',
      function ($matches) {
        $url = urldecode($matches[1]);
        return '<span class="moe-url" title="' .
          esc_attr($url) .
          '">' .
          esc_html($url) .
          "</span>";
      },
      $safe_html,
    );

    return $safe_html;
  }

  /**
   * 建立原文連結 HTML
   *
   * @param array $vars 模板變數
   * @return string
   */
  public function build_original_link_html($vars)
  {
    $domain = parse_url($vars["site_url"], PHP_URL_HOST);
    $clean_domain = preg_replace("/^www\./", "", $domain);
    $display_url = urldecode($vars["post_url"]);

    return sprintf(
      '<div class="moe-original-section">
  %s<br>
  [%s]
  <a href="%s" target="_blank" rel="noopener noreferrer" class="moe-original-link">%s</a>
</div>',
      esc_html__("原文連結", "moelog-ai-qna"),
      esc_html($clean_domain),
      esc_url($vars["post_url"]),
      esc_html($display_url),
    );
  }

  /**
   * 取得免責聲明文字
   *
   * @param string $site_name 網站名稱
   * @return string
   */
  public function get_disclaimer($site_name)
  {
    $template = Moelog_AIQnA_Settings::get_disclaimer_text();

    $disclaimer = str_replace(["{site}", "%s"], $site_name, $template);

    return apply_filters(
      "moelog_aiqna_disclaimer_text",
      $disclaimer,
      $site_name,
    );
  }

  /**
   * 渲染 HTML 模板
   *
   * @param array $vars 模板變數
   */
  public function render_template($vars)
  {
    // 提取變數到當前作用域
    extract($vars, EXTR_SKIP);

    // 檢查是否有自訂模板
    $custom_template = apply_filters("moelog_aiqna_answer_template", null);

    if ($custom_template && file_exists($custom_template)) {
      include $custom_template;
      return;
    }

    // 使用預設模板
    $default_template = MOELOG_AIQNA_DIR . "templates/answer-page.php";

    if (file_exists($default_template)) {
      include $default_template;
    } else {
      // 如果模板檔案不存在,使用內建 HTML
      $this->render_inline_template($vars);
    }
  }

  /**
   * 渲染內建 HTML 模板(後備方案)
   *
   * @param array $vars 模板變數
   */
  public function render_inline_template($vars)
  {
    extract($vars, EXTR_SKIP); ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php echo esc_attr($charset); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="<?php echo esc_attr($robots); ?>">
<link rel="canonical" href="<?php echo esc_url($answer_url); ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=Noto+Sans+JP&family=Noto+Sans+TC:wght@100..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url($style_url); ?>">
<link rel="stylesheet" href="<?php echo esc_url($theme_style_url); ?>">
<title><?php echo esc_html(
  $post_title,
); ?> - <?php esc_html_e("AI 解答", "moelog-ai-qna"); ?></title>
<?php do_action(
  "moelog_aiqna_answer_head",
  $answer_url,
  $post_id,
  $question,
  $answer,
); ?>
<?php $this->render_inline_styles(); ?>
<?php $this->render_inline_scripts(); ?>
</head>
<body class="moe-aiqna-answer">
<div class="moe-container">
<?php if ($banner_url): ?>
<div class="moe-banner" style="background-image:url('<?php echo esc_url(
  $banner_url,
); ?>')" role="img" aria-label="<?php echo esc_attr($banner_alt); ?>"></div>
<?php else: ?>
<div class="moe-banner" role="img" aria-label="<?php echo esc_attr(
  $banner_alt,
); ?>"></div>
<?php endif; ?>

<div class="moe-answer-wrap">
  <div class="moe-question-echo"><?php echo esc_html($question); ?></div>
  <div id="moe-ans-target"></div>
  <template id="moe-ans-source"><?php echo $answer_html .
    $original_link_html; ?></template>
  <noscript><?php echo $answer_html
    ? $answer_html
    : "<p>" .
      esc_html__("抱歉,目前無法取得 AI 回答,請稍後再試。", "moelog-ai-qna") .
      "</p>"; ?></noscript>
  <?php $this->render_typewriter_script(); ?>
</div>

<div class="moe-close-area">
  <a href="#" id="moe-close-btn" class="moe-close-btn"><?php esc_html_e(
    "← 關閉此頁",
    "moelog-ai-qna",
  ); ?></a>
  <div id="moelog-fallback" class="moe-fallback" style="display:none;">
    <?php esc_html_e(
      "若瀏覽器不允許自動關閉視窗,請點此回到文章:",
      "moelog-ai-qna",
    ); ?>
    <a href="<?php echo esc_url($post_url); ?>" target="_self" rel="noopener"><?php echo esc_html($post_title); ?></a>
  </div>
</div>
</div>
<div class="moe-bottom"></div>

<p class="moe-disclaimer" style="margin-top:0px;text-align:center;font-size:0.85em;color:#666;line-height:1.5em;">
  <?php echo nl2br(esc_html($disclaimer)); ?>
</p>
</body>
</html>
        <?php
  }

  /**
   * 渲染內聯樣式
   */
  public function render_inline_styles()
  {
    ?>
<style nonce="<?php echo esc_attr($this->csp_nonce); ?>">
.moe-typing-cursor{display:inline-block;width:1px;background:#999;margin-left:2px;animation:moe-blink 1s step-end infinite;vertical-align:baseline;}
@keyframes moe-blink{50%{background:transparent;}}
.moe-answer-wrap{word-wrap:break-word;word-break:break-word;overflow-wrap:anywhere;max-width:100%;}
.moe-answer-wrap p,.moe-answer-wrap li{word-wrap:break-word;word-break:break-word;overflow-wrap:anywhere;}
.moe-answer-wrap code,.moe-answer-wrap pre{word-wrap:break-word;word-break:break-all;overflow-wrap:anywhere;white-space:pre-wrap;max-width:100%;}
.moe-original-section{padding:0 45px;font-size:120%;letter-spacing:0.5px;color:#666;}
.moe-original-link{color:#94B800;text-decoration:none;}
.moe-original-link:hover{color:#69644e;text-decoration:underline;}
@media (max-width:768px){.moe-original-section{padding:0 20px;}.moe-original-url{font-size:0.85em;}}
</style>
        <?php
  }

  /**
   * 渲染內聯腳本(關閉按鈕)
   */
  public function render_inline_scripts()
  {
    ?>
<script nonce="<?php echo esc_attr($this->csp_nonce); ?>">
window.MoelogAIQnA=window.MoelogAIQnA||{};
if(typeof window.MoelogAIQnA.typing_ms==='undefined'){window.MoelogAIQnA.typing_ms=10;}  // 改這裡
window.MoelogAIQnA.typing_jitter_ms=window.MoelogAIQnA.typing_jitter_ms??6;
window.MoelogAIQnA.typing_disabled=window.MoelogAIQnA.typing_disabled??false;

function moelogClosePage(){
  try{
    window.close();
    setTimeout(function(){try{window.open('','_self');}catch(e){}try{window.close();}catch(e){}},50);
    setTimeout(function(){
      var fb=document.getElementById('moelog-fallback');
      if(!window.closed){
        if(history.length>1){history.back();}
        else{if(fb)fb.style.display='block';}
      }
    },300);
  }catch(e){
    var fb=document.getElementById('moelog-fallback');
    if(fb)fb.style.display='block';
  }
  return false;
}
document.addEventListener('DOMContentLoaded',function(){
  var btn=document.getElementById('moe-close-btn');
  if(btn){btn.addEventListener('click',function(e){e.preventDefault();moelogClosePage();});}
});
</script>
        <?php
  }

  /**
   * 渲染打字機效果腳本
   */
  public function render_typewriter_script()
  {
    ?>
<script nonce="<?php echo esc_attr($this->csp_nonce); ?>">
(function(){
  const srcTpl=document.getElementById('moe-ans-source');
  const target=document.getElementById('moe-ans-target');
  if(!srcTpl||!target)return;
  const ALLOWED=new Set(['P','UL','OL','LI','STRONG','EM','BR','SPAN','A','DIV']);
  const SPEED=10;
  function cloneShallow(node){
    if(node.nodeType===Node.TEXT_NODE)return document.createTextNode('');
    if(node.nodeType===Node.ELEMENT_NODE){
      const tag=node.tagName.toUpperCase();
      if(!ALLOWED.has(tag))return document.createTextNode(node.textContent||'');
      if(tag==='BR')return document.createElement('br');
      const el=document.createElement(tag.toLowerCase());
      if(node.className)el.className=node.className;
      if(node.title)el.title=node.title;
      if(tag==='A'){
        const href=node.getAttribute('href');if(href)el.setAttribute('href',href);
        const tgt=node.getAttribute('target');if(tgt)el.setAttribute('target',tgt);
        const rel=node.getAttribute('rel');if(rel)el.setAttribute('rel',rel);
      }
      return el;
    }
    return document.createTextNode('');
  }
  function prepareTyping(srcParent,dstParent,queue){
    Array.from(srcParent.childNodes).forEach(src=>{
      if(src.nodeType===Node.TEXT_NODE){
        const t=document.createTextNode('');
        dstParent.appendChild(t);
        const text=src.textContent||'';
        if(text.length)queue.push({node:t,text});
      }else if(src.nodeType===Node.ELEMENT_NODE){
        const cloned=cloneShallow(src);
        dstParent.appendChild(cloned);
        if(cloned.nodeType===Node.TEXT_NODE){
          const text=cloned.textContent||'';
          if(text.length)queue.push({node:cloned,text});
        }else if(cloned.tagName&&cloned.tagName.toUpperCase()==='BR'){
        }else{
          prepareTyping(src,cloned,queue);
        }
      }
    });
  }
  async function typeQueue(queue){
    const cursor=document.createElement('span');
    cursor.className='moe-typing-cursor';
    target.appendChild(cursor);
    for(const item of queue){
      const chars=Array.from(item.text);
      for(let i=0;i<chars.length;i++){
        item.node.textContent+=chars[i];
        await new Promise(r=>setTimeout(r,SPEED));
      }
    }
    cursor.remove();
  }
  const sourceRoot=document.createElement('div');
  sourceRoot.innerHTML=srcTpl.innerHTML;
  const queue=[];
  prepareTyping(sourceRoot,target,queue);
  if(queue.length===0){
    target.innerHTML='<p><?php echo esc_js(
      esc_html__("抱歉,目前無法取得 AI 回答,請稍後再試。", "moelog-ai-qna"),
    ); ?></p>';
  }else{
    typeQueue(queue);
  }
})();
</script>
        <?php
  }

  /**
   * 替換 HTML 中的 nonce 值
   *
   * @param string $html       HTML 內容
   * @param string $new_nonce  新的 nonce 值(或 placeholder)
   * @return string 替換後的 HTML
   */
  public function replace_nonce_in_html($html, $new_nonce)
  {
    // 如果是從快取讀取,HTML 中是 placeholder,要替換成真實 nonce
    // 如果是要儲存,HTML 中是真實 nonce,要替換成 placeholder

    // 替換 nonce 屬性
    $html = preg_replace(
      '/nonce="[^"]*"/',
      'nonce="' . esc_attr($new_nonce) . '"',
      $html,
    );

    return $html;
  }
}

