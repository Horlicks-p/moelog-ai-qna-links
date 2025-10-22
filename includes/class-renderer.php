<?php
/**
 * Moelog AI Q&A Renderer Class
 *
 * 負責渲染 AI 回答頁面:
 * - 解析請求參數
 * - 檢查快取
 * - 呼叫 AI 生成答案
 * - 渲染 HTML 模板
 * - 輸出 HTTP Headers
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
  exit();
}

class Moelog_AIQnA_Renderer
{
  /**
   * 路由器實例
   * @var Moelog_AIQnA_Router
   */
  private $router;

  /**
   * AI 客戶端實例
   * @var Moelog_AIQnA_AI_Client
   */
  private $ai_client;

  /**
   * HMAC 密鑰
   * @var string
   */
  private $secret;

  /**
   * CSP Nonce (用於內聯腳本)
   * @var string
   */
  private $csp_nonce;

  /**
   * 頻率限制 TTL (秒)
   */
  const RATE_TTL = 60;

  /**
   * 每小時每 IP 最大請求數
   */
  const MAX_REQUESTS_PER_HOUR = 10;

  /**
   * Nonce placeholder 常數
   */
  const NONCE_PLACEHOLDER = "{{MOELOG_CSP_NONCE}}";

  /**
   * 建構函數
   *
   * @param Moelog_AIQnA_Router    $router    路由器
   * @param Moelog_AIQnA_AI_Client $ai_client AI 客戶端
   * @param string                 $secret    HMAC 密鑰
   */
  public function __construct($router, $ai_client, $secret)
  {
    $this->router = $router;
    $this->ai_client = $ai_client;
    $this->secret = $secret;

    // 生成 CSP nonce
    $this->generate_csp_nonce();
  }

  // =========================================
  // 主要渲染流程
  // =========================================

  /**
   * 渲染回答頁面(主入口)
   */
  public function render_answer_page()
  {
    // 檢查是否為回答頁請求
    if (!$this->router->is_answer_request()) {
      return;
    }

    // 設定安全 Headers
    $this->set_security_headers();

    // 執行爬蟲封鎖
    $this->block_unwanted_bots();

    // 處理預抓取請求
    if ($this->is_prefetch_request()) {
      $this->handle_prefetch();
      return;
    }

    // 解析請求參數
    $params = $this->router->parse_request();
    if (!$params) {
      $this->render_error(400, __("參數錯誤或連結已失效。", "moelog-ai-qna"));
      return;
    }

    // 驗證文章存在
    $post = get_post($params["post_id"]);
    if (!$post) {
      $this->render_error(404, __("找不到文章。", "moelog-ai-qna"));
      return;
    }

      // 檢查靜態快取
      if (Moelog_AIQnA_Cache::exists($params["post_id"], $params["question"])) {
      // ✅ 正確!
      $html = Moelog_AIQnA_Cache::load($params["post_id"], $params["question"]);
      if ($html) {
        // ⭐ 關鍵:讀取快取時,替換 placeholder 為新的 nonce
        $html = $this->replace_nonce_in_html($html, $this->csp_nonce);

        //$this->set_security_headers();
        $this->set_cache_headers(true);
        $this->output_html($html);
        exit();
      }
    }

    // 執行頻率限制
    if (!$this->check_rate_limit($params["post_id"], $params["question"])) {
      $this->render_error(
        429,
        __("請求過於頻繁,請稍後再試。", "moelog-ai-qna"),
      );
      return;
    }

    // 生成新答案
    $this->generate_and_render($params, $post);
  }

  /**
   * 從快取提供答案
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   */
  private function serve_from_cache($post_id, $question)
  {
    $html = Moelog_AIQnA_Cache::load($post_id, $question);

    if ($html === false) {
      // 快取讀取失敗,重新生成
      $params = [
        "post_id" => $post_id,
        "question" => $question,
        "lang" => "auto",
      ];
      $post = get_post($post_id);
      if ($post) {
        $this->generate_and_render($params, $post);
      }
      return;
    }

    // ⭐ 關鍵:讀取快取時,替換 placeholder 為新的 nonce
    $html = $this->replace_nonce_in_html($html, $this->csp_nonce);

    // 設定 Headers
    //$this->set_security_headers();
    $this->set_cache_headers(true);

    // 記錄靜態檔案路徑(供 GEO 模組使用)
    $GLOBALS["moe_aiqna_static_file"] = Moelog_AIQnA_Cache::get_static_path(
      $post_id,
      $question,
    );

    // 輸出 HTML
    $this->output_html($html);
    exit();
  }
  // =========================================
  // 新增輔助方法:替換 HTML 中的 nonce
  // =========================================

  /**
   * 替換 HTML 中的 nonce 值
   *
   * @param string $html       HTML 內容
   * @param string $new_nonce  新的 nonce 值(或 placeholder)
   * @return string 替換後的 HTML
   */
  private function replace_nonce_in_html($html, $new_nonce)
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

  /**
   * 生成新答案並渲染
   *
   * ✅ 優化: 取得設定一次後傳入子方法,避免重複 get_option
   *
   * @param array   $params 請求參數
   * @param WP_Post $post   文章物件
   */
  private function generate_and_render($params, $post)
  {
    // ✅ 優化: 只取得一次設定
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);

    // 準備 AI 呼叫參數
    $ai_params = [
      "post_id" => $params["post_id"],
      "question" => $params["question"],
      "lang" =>
        $params["lang"] !== "auto"
          ? $params["lang"]
          : $this->detect_language($params["question"]),
      "context" => $this->get_post_context($post, $settings),
    ];

    // 呼叫 AI 生成答案
    $answer = $this->ai_client->generate_answer($ai_params);

    // ✅ 優化: 檢查答案是否為錯誤訊息,避免寫入錯誤快取(優化項目 5)
    if ($this->is_error_response($answer)) {
      // 不寫入快取,直接渲染錯誤頁面
      $this->render_error(500, $answer);
      return;
    }

    // 建立 HTML (✅ 傳入 settings)
    $html = $this->build_html($params, $post, $answer, $settings);

    // ⭐ 儲存前:將 nonce 替換成 placeholder
    $html_for_cache = $this->replace_nonce_in_html(
      $html,
      self::NONCE_PLACEHOLDER,
    );
    Moelog_AIQnA_Cache::save(
      $params["post_id"],
      $params["question"],
      $html_for_cache,
    );

    // 設定 Headers
    //$this->set_security_headers();
    $this->set_cache_headers(false);

    // 輸出(使用含真實 nonce 的版本)
    $this->output_html($html);
    exit();
  }

  // =========================================
  // HTML 生成
  // =========================================

  /**
   * 建立完整的答案頁 HTML
   *
   * ✅ 優化: 接收預先取得的 settings 參數
   *
   * @param array      $params   請求參數
   * @param WP_Post    $post     文章物件
   * @param string     $answer   AI 回答
   * @param array|null $settings 插件設定(可選,避免重複查詢)
   * @return string
   */
  private function build_html($params, $post, $answer, $settings = null)
  {
    // 標記為回答頁(供 GEO 模組使用)
    $GLOBALS["moe_aiqna_is_answer_page"] = true;

    // ✅ 優化: 如果沒有傳入 settings,才查詢
    if ($settings === null) {
      $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    }

    // 準備模板變數 (✅ 傳入 settings)
    $template_vars = $this->prepare_template_vars(
      $params,
      $post,
      $answer,
      $settings,
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
   * ✅ 優化: 接收預先取得的 settings 參數,不再重複查詢
   *
   * @param array      $params   請求參數
   * @param WP_Post    $post     文章物件
   * @param string     $answer   AI 回答
   * @param array|null $settings 插件設定(可選,避免重複查詢)
   * @return array
   */
  private function prepare_template_vars(
    $params,
    $post,
    $answer,
    $settings = null,
  ) {
    // ✅ 優化: 如果沒有傳入 settings,才查詢(向下相容)
    if ($settings === null) {
      $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    }

    // 基本資訊
    $vars = [
      "post_id" => $params["post_id"],
      "question" => $params["question"],
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
    $vars["disclaimer"] = $this->get_disclaimer($vars["site_name"], $settings);

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
  private function is_error_response($answer)
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
  private function process_answer_html($answer)
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
  private function build_original_link_html($vars)
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
   * @param array  $settings  設定
   * @return string
   */
  private function get_disclaimer($site_name, $settings)
  {
    $default_tpl =
      "本頁面由AI生成,可能會發生錯誤,請查核重要資訊。\n" .
      "使用本AI生成內容服務即表示您同意此內容僅供個人參考,且您了解輸出內容可能不準確。\n" .
      "所有爭議內容{site}保有最終解釋權。";

    $template =
      isset($settings["disclaimer_text"]) && $settings["disclaimer_text"] !== ""
        ? $settings["disclaimer_text"]
        : $default_tpl;

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
  private function render_template($vars)
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
  private function render_inline_template($vars)
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
    <a href="<?php echo esc_url(
      $post_url,
    ); ?>" target="_self" rel="noopener"><?php echo esc_html($post_title); ?></a>
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
  } /**
   * 渲染內聯樣式
   */
  private function render_inline_styles()
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
  } /**
   * 渲染內聯腳本(關閉按鈕)
   */
  private function render_inline_scripts()
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
  } /**
   * 渲染打字機效果腳本
   */
  private function render_typewriter_script()
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
  } /** // ========================================= // HTTP Headers 處理 // =========================================
   * 設定安全 Headers
   */
  private function set_security_headers()
  {
    if (headers_sent()) {
      return;
    } // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin"); // === CSP(只送一次,並包含 nonce)===
    $nonce = $this->csp_nonce ?: "";
    $csp =
      "default-src 'self'; " .
      "script-src 'self' 'nonce-{$nonce}';" .
      "img-src 'self' data:; " .
      "style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; " .
      "font-src 'self' https://fonts.gstatic.com data:; " .
      "connect-src 'self'; " .
      "frame-ancestors 'none'; " .
      "base-uri 'self'; " .
      "form-action 'self'"; // 可選:若你確定是 Kaspersky 在本機開發造成阻擋,開發期臨時放行(正式環境不建議)
    // $csp .= " https://gc.kis.v2.scr.kaspersky-labs.com ws://gc.kis.v2.scr.kaspersky-labs.com";
    // 若要加 report-uri
    $csp_report_uri = apply_filters("moelog_aiqna_csp_report_uri", "");
    if (!empty($csp_report_uri)) {
      $csp .= "; report-uri {$csp_report_uri}";
    }
    header("Content-Security-Policy: {$csp}");
  } /**
   * 設定快取 Headers
   *
   * @param bool $is_cached 是否來自快取
   */
  private function set_cache_headers($is_cached)
  {
    if (headers_sent()) {
      return;
    }
    header_remove("Cache-Control");
    header("Vary: Accept-Encoding, User-Agent");
    if (get_option("moelog_aiqna_geo_mode")) {
      // GEO 模式:更積極的快取
      header(
        "Cache-Control: public, max-age=3600, s-maxage=86400, stale-while-revalidate=604800",
      );
    } else {
      // 一般模式
      header(
        "Cache-Control: public, max-age=3600, s-maxage=86400, stale-while-revalidate=60",
      );
    }
    if ($is_cached) {
      header("X-Served-By: Static-Cache");
    }
  } /**
   * 輸出 HTML 並結束執行
   *
   * @param string $html HTML 內容
   */
  private function output_html($html)
  {
    if (!headers_sent()) {
      status_header(200);
      header("Content-Type: text/html; charset=UTF-8");
    }
    echo $html;
  } /** // ========================================= // =========================================
   * 取得文章內容作為上下文
   *
   * @param WP_Post $post     文章物件
   * @param array   $settings 設定
   * @return string
   */ // 輔助方法
  private function get_post_context($post, $settings)
  {
    // 檢查是否啟用內容附加
    if (empty($settings["include_content"])) {
      return "";
    }
    $max_chars = isset($settings["max_chars"])
      ? intval($settings["max_chars"])
      : 6000; // 提取純文字
    $raw = $post->post_title . "\n\n" . strip_shortcodes($post->post_content);
    $raw = wp_strip_all_tags($raw);
    $raw = preg_replace("/\s+/u", " ", $raw); // 截斷
    if (function_exists("mb_strlen") && function_exists("mb_strcut")) {
      return mb_strlen($raw, "UTF-8") > $max_chars
        ? mb_strcut($raw, 0, $max_chars * 4, "UTF-8")
        : $raw;
    } else {
      return strlen($raw) > $max_chars ? substr($raw, 0, $max_chars) : $raw;
    }
  } /**
   * 檢測語言
   *
   * @param string $text 文字
   * @return string 語言代碼 (ja|zh|en)
   */
  private function detect_language($text)
  {
    return moelog_aiqna_detect_language($text);
  } /**
   * 生成 CSP Nonce
   */
  private function generate_csp_nonce()
  {
    if (!$this->router->is_answer_request()) {
      return;
    }
    try {
      $this->csp_nonce = rtrim(
        strtr(base64_encode(random_bytes(16)), "+/", "-_"),
        "=",
      );
    } catch (Exception $e) {
      $this->csp_nonce = base64_encode(hash("sha256", microtime(true), true));
    }
  } /**
   * 封鎖不需要的爬蟲
   */
  private function block_unwanted_bots()
  {
    $ua = $_SERVER["HTTP_USER_AGENT"] ?? ""; // 根據 GEO 模式決定封鎖策略
    if (get_option("moelog_aiqna_geo_mode")) {
      // GEO 模式:只封鎖惡意爬蟲
      $default_blocked = [
        "scrape",
        "curl",
        "wget",
        "Baiduspider",
        "SemrushBot",
        "AhrefsBot",
        "MJ12bot",
        "DotBot",
      ];
    } else {
      // 一般模式:封鎖所有爬蟲
      $default_blocked = [
        "bot",
        "crawl",
        "spider",
        "scrape",
        "curl",
        "wget",
        "Googlebot",
        "Bingbot",
        "Baiduspider",
        "facebookexternalhit",
      ];
    }
    $bot_patterns = apply_filters(
      "moelog_aiqna_blocked_bots",
      $default_blocked,
    );
    foreach ($bot_patterns as $pattern) {
      if (stripos($ua, $pattern) !== false) {
        status_header(403);
        exit("Bots are not allowed");
      }
    }
  } /**
   * 檢查是否為預抓取請求
   *
   * @return bool
   */
  private function is_prefetch_request()
  {
    return isset($_GET["pf"]) && $_GET["pf"] === "1";
  } /**
   * 處理預抓取請求
   */
  private function handle_prefetch()
  {
    status_header(204);
    header("Cache-Control: private, max-age=300");
    exit();
  }
  /**
   * 檢查頻率限制
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   * @return bool 是否允許繼續
   */ private function check_rate_limit($post_id, $question)
  {
    $ip = $this->get_client_ip(); // 單一問題頻率限制(60秒內不能重複請求)
    $freq_key = "moe_aiqna_freq_" . md5($ip . "|" . $post_id . "|" . $question);
    if (get_transient($freq_key)) {
      return false;
    } // IP 總請求數限制(每小時最多 10 次)
    $ip_key = "moe_aiqna_ip_" . md5($ip);
    $ip_count = (int) get_transient($ip_key);
    if ($ip_count >= self::MAX_REQUESTS_PER_HOUR) {
      if (defined("WP_DEBUG") && WP_DEBUG) {
        error_log(
          sprintf(
            "[Moelog AIQnA] Rate limit hit: IP %s, Post %d, Question: %s",
            $ip,
            $post_id,
            substr($question, 0, 50),
          ),
        );
      }
      return false;
    } // 設定頻率限制標記
    set_transient(
      $freq_key,
      1,

      self::RATE_TTL,
    );
    set_transient($ip_key, $ip_count + 1, HOUR_IN_SECONDS);
    return true;
  }
  /**
   * 取得客戶端 IP
   *
   * @return string
   */ private function get_client_ip()
  {
    // Cloudflare
    if (!empty($_SERVER["HTTP_CF_CONNECTING_IP"])) {
      return $_SERVER["HTTP_CF_CONNECTING_IP"];
    } // 代理伺服器
    if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
      $ips = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);
      return trim($ips[0]);
    }
    // 直接連線
    return $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";
  } /**
   * 渲染錯誤頁面
   *
   * @param int    $code    HTTP 狀態碼
   * @param string $message 錯誤訊息
   */
  private function render_error($code, $message)
  {
    status_header($code);
    if (!headers_sent()) {
      header("Content-Type: text/html; charset=UTF-8");
      header("Cache-Control: no-cache, no-store, must-revalidate");
    }
    $title = $this->get_error_title($code);
    $site_name = get_bloginfo("name", "display");
    ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo("charset"); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html(
  $title,
); ?> - <?php echo esc_html($site_name); ?></title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;margin:0;padding:40px 20px;background:#f5f5f5;color:#333;}
.error-container{max-width:600px;margin:0 auto;background:#fff;padding:40px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
h1{margin:0 0 20px;font-size:24px;color:#d63638;}
p{margin:0 0 20px;line-height:1.6;}
.back-link{display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;}
.back-link:hover{background:#135e96;}
</style>
</head>
<body>
<div class="error-container">
  <h1><?php echo esc_html($title); ?></h1>
  <p><?php echo esc_html($message); ?></p>
  <a href="<?php echo esc_url(
    home_url("/"),
  ); ?>" class="back-link"><?php esc_html_e("返回首頁", "moelog-ai-qna"); ?></a>
</div>
</body>
</html>
        <?php exit();
  }
  /**
   * 取得錯誤標題
   *
   * @param int $code HTTP 狀態碼
   * @return string
   */ private function get_error_title($code)
  {
    $titles = [
      400 => __("請求錯誤", "moelog-ai-qna"),
      403 => __("禁止存取", "moelog-ai-qna"),
      404 => __("頁面不存在", "moelog-ai-qna"),
      410 => __("連結已失效", "moelog-ai-qna"),
      429 => __("請求過於頻繁", "moelog-ai-qna"),
      500 => __("伺服器錯誤", "moelog-ai-qna"),
    ];
    return $titles[$code] ?? __("發生錯誤", "moelog-ai-qna");
  } // =========================================
  // 公開 API
  /**
   * 取得 CSP Nonce
   *
   * @return string
   */
  // =========================================
  public function get_csp_nonce()
  {
    return $this->csp_nonce;
  } /**
   * 手動渲染特定問題的答案(用於預生成)
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   * @param string $lang     語言代碼
   * @return string|false HTML 內容,失敗返回 false
   */
  public function render_for_pregeneration($post_id, $question, $lang = "auto")
  {
    $post = get_post($post_id);
    if (!$post) {
      return false;
    }
    $params = [
      "post_id" => $post_id,
      "question" => $question,
      "lang" => $lang !== "auto" ? $lang : $this->detect_language($question),
    ];
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []); // 準備 AI 呼叫參數
    $ai_params = [
      "post_id" => $params["post_id"],
      "question" => $params["question"],
      "lang" => $params["lang"],
      "context" => $this->get_post_context($post, $settings),
    ]; // 呼叫 AI
    $answer = $this->ai_client->generate_answer($ai_params);
    if (!$answer) {
      return false;
    }
    // 建立 HTML
    $html = $this->build_html($params, $post, $answer); // ⭐ 預生成時直接使用 placeholder
    return $this->replace_nonce_in_html($html, self::NONCE_PLACEHOLDER);
  } // ==== // Debug 輔助(僅在 WP_DEBUG 時使用) // ====
  /**
   * 取得當前渲染狀態(除錯用)
   *
   * @return array
   */
  public function debug_render_state()
  {
    if (!defined("WP_DEBUG") || !WP_DEBUG) {
      return [];
    }
    return [
      "is_answer_request" => $this->router->is_answer_request(),
      "is_prefetch" => $this->is_prefetch_request(),
      "csp_nonce" => $this->csp_nonce,
      "client_ip" => $this->get_client_ip(),
      "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? "",
      "request_method" => $_SERVER["REQUEST_METHOD"] ?? "",
      "geo_mode_enabled" => (bool) get_option("moelog_aiqna_geo_mode"),
    ];
  } /**
   * 測試模板渲染(除錯用)
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   * @return void
   */
  public function debug_test_render($post_id, $question)
  {
    if (!defined("WP_DEBUG") || !WP_DEBUG) {
      return;
    }
    $post = get_post($post_id);
    if (!$post) {
      echo "Error: Post {$post_id} not found\n";
      return;
    }
    $params = [
      "post_id" => $post_id,
      "question" => $question,
      "lang" => "auto",
    ];
    $test_answer =
      "這是測試答案。\n\n這是第二段。\n\n參考資料\n- [Example](https://example.com)";
    $vars = $this->prepare_template_vars($params, $post, $test_answer);
    echo "<pre>";
    print_r($vars);
    echo "</pre>";
    echo "\n\n<hr>\n\n";
    $this->render_template($vars);
  }
}
