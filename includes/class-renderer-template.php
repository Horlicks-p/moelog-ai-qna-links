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
   * ✅ 優化: 使用 Parsedown 解析 Markdown → HTML
   *    AI 回傳的答案為 Markdown 格式（標題、表格、粗體、列表等），
   *    需要正確轉換為 HTML 再做安全過濾。
   *
   * @param string $answer 原始答案 (Markdown 格式)
   * @return string
   */
  public function process_answer_html($answer)
  {
    if (empty($answer)) {
      return "";
    }

    // Markdown → HTML
    $html = $this->convert_markdown($answer);

    // 允許的 HTML 標籤（含 Markdown 可能產生的元素）
    $allowed = [
      "p" => [],
      "ul" => [],
      "ol" => ["start" => []],
      "li" => [],
      "strong" => [],
      "em" => [],
      "br" => [],
      "span" => ["class" => [], "title" => []],
      "h1" => ["id" => []],
      "h2" => ["id" => []],
      "h3" => ["id" => []],
      "h4" => ["id" => []],
      "h5" => ["id" => []],
      "h6" => ["id" => []],
      "table" => [],
      "thead" => [],
      "tbody" => [],
      "tr" => [],
      "th" => ["style" => []],
      "td" => ["style" => []],
      "code" => ["class" => []],
      "pre" => [],
      "blockquote" => [],
      "hr" => [],
      "del" => [],
      "a" => ["href" => [], "title" => [], "target" => [], "rel" => []],
    ];

    // 清理 HTML（白名單過濾）
    $safe_html = wp_kses($html, $allowed);

    // 移除事件處理器
    // PHP 8.1+: 確保 preg_replace 不返回 null
    $safe_html = preg_replace(
      "/<(\w+)\s+[^>]*on\w+\s*=\s*[^>]*>/i",
      '<$1>',
      $safe_html,
    ) ?? $safe_html;

    // 處理裸露 URL（不在 <a> 標籤內的）轉換為帶 title 的 span
    // ✅ 修正: 先排除已在 <a> 標籤中的 URL
    $safe_html = preg_replace_callback(
      '/(?<!["\'>])(https?:\/\/[^\s<>"]+)(?![^<]*<\/a>)/i',
      function ($matches) {
        $url = urldecode($matches[1]);
        return '<span class="moe-url" title="' .
          esc_attr($url) .
          '">' .
          esc_html($url) .
          "</span>";
      },
      $safe_html,
    ) ?? $safe_html;

    return $safe_html;
  }

  /**
   * 將 Markdown 轉換為 HTML
   *
   * @param string $text Markdown 文字
   * @return string HTML
   */
  private function convert_markdown($text)
  {
    if (!class_exists('Parsedown')) {
      require_once MOELOG_AIQNA_DIR . 'includes/Parsedown.php';
    }

    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true);      // 防止原始 HTML 注入 (XSS 防護)
    $parsedown->setBreaksEnabled(true);  // 自動換行

    $html = $parsedown->text($text);

    // 提供 filter 讓開發者可自訂 Markdown → HTML 的轉換結果
    return apply_filters('moelog_aiqna_markdown_to_html', $html, $text);
  }

  /**
   * 建立原文連結 HTML
   *
   * @param array $vars 模板變數
   * @return string
   */
  public function build_original_link_html($vars)
  {
    // PHP 8.1+: 確保 parse_url 和 preg_replace 不返回 null
    $domain = parse_url($vars["site_url"], PHP_URL_HOST) ?? "";
    $clean_domain = preg_replace("/^www\./", "", $domain) ?? $domain;
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
    extract($vars, EXTR_SKIP);

    // 🔧 檢查回饋功能是否啟用
    $feedback_enabled_raw = Moelog_AIQnA_Settings::get('feedback_enabled', 1);
    $feedback_enabled = ($feedback_enabled_raw === true || $feedback_enabled_raw === 1 || $feedback_enabled_raw === '1');

    $feedback_stats = class_exists('Moelog_AIQnA_Feedback_Controller')
      ? Moelog_AIQnA_Feedback_Controller::get_stats($post_id, $question_hash ?? null)
      : ['views' => 0, 'likes' => 0, 'dislikes' => 0];

    $feedback_config = [
      'ajaxUrl'      => admin_url('admin-ajax.php'),
      'nonce'        => null,
      'postId'       => $post_id,
      'question'     => $question,
      'questionHash' => $question_hash ?? '',
      'stats'        => $feedback_stats,
      'i18n'         => [
        'unexpected'  => __('發生未預期錯誤,請稍後再試。', 'moelog-ai-qna'),
        'alreadyVoted'=> __('您已經回覆過囉！', 'moelog-ai-qna'),
        'submitting'  => __('送出中…', 'moelog-ai-qna'),
        'thanks'      => __('感謝您的回饋！', 'moelog-ai-qna'),
        'failed'      => __('送出失敗,請稍後再試。', 'moelog-ai-qna'),
        'needMore'    => __('請提供至少 3 個字的描述。', 'moelog-ai-qna'),
        'reportThanks'=> __('已送出,感謝您的回饋！', 'moelog-ai-qna'),
        'reportSeen'  => __('已收到您稍早的回饋，感謝！', 'moelog-ai-qna'),
      ],
    ];

    $moelog_aiqna_style_path = MOELOG_AIQNA_DIR . 'includes/assets/css/style.css';
    $moelog_aiqna_style_ver  = file_exists($moelog_aiqna_style_path)
      ? filemtime($moelog_aiqna_style_path)
      : MOELOG_AIQNA_VERSION;
    ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="<?php echo esc_attr($robots); ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=Noto+Sans+JP&family=Noto+Sans+TC:wght@100..900&family=Press+Start+2P&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url(MOELOG_AIQNA_URL . 'includes/assets/css/style.css'); ?>?ver=<?php echo esc_attr($moelog_aiqna_style_ver); ?>">
<link rel="stylesheet" href="<?php echo esc_url(get_stylesheet_uri()); ?>?ver=<?php echo esc_attr(wp_get_theme()->get('Version')); ?>">
<title><?php echo esc_html($post_title); ?> - <?php esc_html_e('AI 解答', 'moelog-ai-qna'); ?></title>
<?php do_action('moelog_aiqna_answer_head', $answer_url, $post_id, $question, $answer); ?>
<script nonce="<?php echo esc_attr($this->csp_nonce); ?>">
window.MoelogAIQnA = window.MoelogAIQnA || {};
if (typeof window.MoelogAIQnA.typing_ms === 'undefined') { window.MoelogAIQnA.typing_ms = <?php echo intval(apply_filters('moelog_aiqna_typing_speed', 10)); ?>; }
if (typeof window.MoelogAIQnA.typing_jitter_ms === 'undefined') { window.MoelogAIQnA.typing_jitter_ms = <?php echo intval(apply_filters('moelog_aiqna_typing_jitter', 6)); ?>; }
if (typeof window.MoelogAIQnA.typing_disabled === 'undefined') { window.MoelogAIQnA.typing_disabled = <?php echo apply_filters('moelog_aiqna_typing_disabled', false) ? 'true' : 'false'; ?>; }
if (typeof window.MoelogAIQnA.typing_fallback === 'undefined') { window.MoelogAIQnA.typing_fallback = '<?php echo esc_js(esc_html__('抱歉,目前無法取得 AI 回答,請稍後再試。', 'moelog-ai-qna')); ?>'; }
<?php if ($feedback_enabled): ?>
window.MoelogAIQnA.feedback = <?php echo wp_json_encode($feedback_config); ?>;
<?php endif; ?>
</script>
<script src="<?php echo esc_url(MOELOG_AIQNA_URL . 'includes/assets/js/typing.js'); ?>?ver=<?php echo esc_attr(MOELOG_AIQNA_VERSION); ?>" defer></script>
</head>
<body class="moe-aiqna-answer">
<div class="moe-container">
<?php
  $banner_style = $banner_url ? sprintf("background-image:url('%s')", esc_url($banner_url)) : '';
?>
<div class="moe-banner"<?php if ($banner_style): ?> style="<?php echo esc_attr($banner_style); ?>"<?php endif; ?> role="img" aria-label="<?php echo esc_attr($banner_alt); ?>"></div>

<div class="moe-answer-wrap">
  <div class="moe-question-echo"><?php echo esc_html($question); ?></div>
  <div id="moe-ans-target"></div>
  <template id="moe-ans-source"><?php echo $answer_html . $original_link_html; ?></template>
  <noscript><?php echo $answer_html ? $answer_html : '<p>' . esc_html__('抱歉,目前無法取得 AI 回答,請稍後再試。', 'moelog-ai-qna') . '</p>'; ?></noscript>

  <?php if ($feedback_enabled): ?>
  <section class="moe-feedback-card" id="moe-feedback-card">
    <h3 class="moe-feedback-title"><?php esc_html_e('你覺得AI回答的內容正確嗎？', 'moelog-ai-qna'); ?></h3>
    <div class="moe-feedback-actions" role="group" aria-label="<?php esc_attr_e('文章回饋操作', 'moelog-ai-qna'); ?>">
      <button type="button" class="moe-feedback-btn" data-action="like">
        <img src="<?php echo esc_url(MOELOG_AIQNA_URL . 'includes/assets/images/good.png'); ?>" alt="<?php esc_attr_e('正確', 'moelog-ai-qna'); ?>" width="28" height="28" aria-hidden="true">
        <span><?php esc_html_e('正確', 'moelog-ai-qna'); ?></span>
      </button>
      <button type="button" class="moe-feedback-btn" data-action="dislike">
        <img src="<?php echo esc_url(MOELOG_AIQNA_URL . 'includes/assets/images/bad.png'); ?>" alt="<?php esc_attr_e('錯誤', 'moelog-ai-qna'); ?>" width="28" height="28" aria-hidden="true">
        <span><?php esc_html_e('錯誤', 'moelog-ai-qna'); ?></span>
      </button>
      <button type="button" class="moe-feedback-btn" data-action="report">
        <img src="<?php echo esc_url(MOELOG_AIQNA_URL . 'includes/assets/images/report.png'); ?>" alt="<?php esc_attr_e('回報問題', 'moelog-ai-qna'); ?>" width="28" height="28" aria-hidden="true">
        <span><?php esc_html_e('回報問題', 'moelog-ai-qna'); ?></span>
      </button>
    </div>
    <div class="moe-feedback-report" id="moe-feedback-report" hidden>
      <textarea name="moe-feedback-text" placeholder="<?php esc_attr_e('請簡述您遇到的問題或建議 (最多 300 字)', 'moelog-ai-qna'); ?>" maxlength="300"></textarea>
      <!-- 🔒 蜜罐欄位 -->
      <input type="text" name="website" id="moe-hp-field" autocomplete="off" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;height:0;width:0;">
      <div class="moe-feedback-report-actions">
        <button type="button" class="moe-feedback-secondary" data-feedback-cancel><?php esc_html_e('取消', 'moelog-ai-qna'); ?></button>
        <button type="button" class="moe-feedback-primary" data-feedback-submit><?php esc_html_e('送出', 'moelog-ai-qna'); ?></button>
      </div>
    </div>
    <p class="moe-feedback-message" id="moe-feedback-message"></p>
  </section>
  <div class="moe-feedback-stats">
    <div class="moe-feedback-stat">
      <img src="<?php echo esc_url(MOELOG_AIQNA_URL . 'includes/assets/images/viewer.png'); ?>" alt="<?php esc_attr_e('瀏覽次數', 'moelog-ai-qna'); ?>" width="28" height="28" aria-hidden="true">
      <span data-stat="views"><?php echo esc_html($feedback_stats['views']); ?></span>
    </div>
    <div class="moe-feedback-stat">
      <img src="<?php echo esc_url(MOELOG_AIQNA_URL . 'includes/assets/images/good.png'); ?>" alt="<?php esc_attr_e('好評', 'moelog-ai-qna'); ?>" width="28" height="28" aria-hidden="true">
      <span data-stat="likes"><?php echo esc_html($feedback_stats['likes']); ?></span>
    </div>
    <div class="moe-feedback-stat">
      <img src="<?php echo esc_url(MOELOG_AIQNA_URL . 'includes/assets/images/bad.png'); ?>" alt="<?php esc_attr_e('差評', 'moelog-ai-qna'); ?>" width="28" height="28" aria-hidden="true">
      <span data-stat="dislikes"><?php echo esc_html($feedback_stats['dislikes']); ?></span>
    </div>
  </div>
  <?php endif; ?>

  <div class="moe-close-area">
    <a href="#" id="moe-close-btn" class="moe-close-btn"><?php echo esc_html__('← 關閉此頁', 'moelog-ai-qna'); ?></a>
    <div id="moelog-fallback" class="moe-fallback" style="display:none;">
      <?php echo esc_html__('若瀏覽器不允許自動關閉視窗,請點此回到文章:', 'moelog-ai-qna'); ?>
      <a href="<?php echo esc_url($post_url); ?>" target="_self" rel="noopener"><?php echo esc_html($post_title); ?></a>
    </div>
  </div>
</div>
</div>
<div class="moe-bottom"></div>

<?php if ($feedback_enabled): ?>
<script src="<?php echo esc_url(MOELOG_AIQNA_URL . 'includes/assets/js/answer.js'); ?>?ver=<?php echo esc_attr(MOELOG_AIQNA_VERSION); ?>" defer></script>
<?php endif; ?>

<p class="moe-disclaimer" style="margin-top:0px;text-align:center;font-size:0.85em; color:#666; line-height:1.5em;">
  <?php echo nl2br(esc_html($disclaimer)); ?>
</p>
</body>
</html>
    <?php
  }

  /**
   * 渲染內聯樣式（已移至 style.css，此方法保留為空以維持相容性）
   */
  public function render_inline_styles()
  {
    // 所有樣式已整合至 includes/assets/css/style.css
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
  const ALLOWED=new Set(['P','UL','OL','LI','STRONG','EM','BR','SPAN','A','DIV','H1','H2','H3','H4','H5','H6','TABLE','THEAD','TBODY','TR','TH','TD','CODE','PRE','BLOCKQUOTE','HR','DEL']);
  const SPEED=10;
  function cloneShallow(node){
    if(node.nodeType===Node.TEXT_NODE)return document.createTextNode('');
    if(node.nodeType===Node.ELEMENT_NODE){
      const tag=node.tagName.toUpperCase();
      if(!ALLOWED.has(tag))return document.createTextNode(node.textContent||'');
      if(tag==='BR'||tag==='HR')return document.createElement(tag.toLowerCase());
      const el=document.createElement(tag.toLowerCase());
      if(node.className)el.className=node.className;
      if(node.title)el.title=node.title;
      if(tag==='A'){
        const href=node.getAttribute('href');if(href)el.setAttribute('href',href);
        const tgt=node.getAttribute('target');if(tgt)el.setAttribute('target',tgt);
        const rel=node.getAttribute('rel');if(rel)el.setAttribute('rel',rel);
      }
      if(tag==='TH'||tag==='TD'){const s=node.getAttribute('style');if(s)el.setAttribute('style',s);}
      if(tag==='CODE'){const c=node.getAttribute('class');if(c)el.setAttribute('class',c);}
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
    // PHP 8.1+: 確保 preg_replace 不返回 null
    $result = preg_replace(
      '/nonce="[^"]*"/',
      'nonce="' . esc_attr($new_nonce) . '"',
      $html,
    );

    return $result ?? $html;
  }
}

