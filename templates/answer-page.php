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
<!-- 移除這行,讓 STM 模組統一處理 -->
<!-- <link rel="canonical" href="<?php echo esc_url($answer_url); ?>" /> -->
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=Noto+Sans+JP&family=Noto+Sans+TC:wght@100..900&family=Press+Start+2P&display=swap" rel="stylesheet">	
<?php
$moelog_aiqna_style_path = MOELOG_AIQNA_DIR . "includes/assets/css/style.css";
$moelog_aiqna_style_ver = file_exists($moelog_aiqna_style_path)
    ? filemtime($moelog_aiqna_style_path)
    : MOELOG_AIQNA_VERSION;
?>
<link rel="stylesheet" href="<?php echo esc_url(
    MOELOG_AIQNA_URL . "includes/assets/css/style.css"
); ?>?ver=<?php echo esc_attr($moelog_aiqna_style_ver); ?>">
<link rel="stylesheet" href="<?php echo esc_url(
    get_stylesheet_uri()
); ?>?ver=<?php echo esc_attr(wp_get_theme()->get("Version")); ?>">

<?php
// $post_title = get_the_title($post_id); // <-- 不再需要
$site_name = get_bloginfo("name");
$ai_label = __("AI 解答", "moelog-ai-qna");

// ✅ SEO 優化: 產生以「問題」為核心的標題
// 格式: 〈問題〉 - AI 解答 | 〈站名〉
$title = sprintf(
    __('%1$s - %2$s | %3$s', "moelog-ai-qna"),
    $question,  // <-- 關鍵修改: 使用 $question 取代 $post_title
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
<?php
$feedback_stats = class_exists("Moelog_AIQnA_Feedback_Controller")
    ? Moelog_AIQnA_Feedback_Controller::get_stats($post_id, $question_hash ?? null)
    : [
        "views" => 0,
        "likes" => 0,
        "dislikes" => 0,
    ];
$feedback_config = [
    "ajaxUrl" => admin_url("admin-ajax.php"),
    "nonce" => null,
    "postId" => $post_id,
    "question" => $question,
    "questionHash" => $question_hash ?? "",
    "stats" => $feedback_stats,
    "i18n" => [
        "unexpected" => __(
            "發生未預期錯誤,請稍後再試。",
            "moelog-ai-qna"
        ),
        "alreadyVoted" => __(
            "您已經回覆過囉！",
            "moelog-ai-qna"
        ),
        "submitting" => __(
            "送出中…",
            "moelog-ai-qna"
        ),
        "thanks" => __("感謝您的回饋！", "moelog-ai-qna"),
        "failed" => __("送出失敗,請稍後再試。", "moelog-ai-qna"),
        "needMore" => __(
            "請提供至少 3 個字的描述。",
            "moelog-ai-qna"
        ),
        "reportThanks" => __(
            "已送出,感謝您的回饋！",
            "moelog-ai-qna"
        ),
        "reportSeen" => __(
            "已收到您稍早的回饋，感謝！",
            "moelog-ai-qna"
        ),
    ],
];
?>

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
@media (max-width: 768px) {
  .moe-original-section {padding: 0 20px;}
  .moe-original-url { font-size: 0.85em;}
}
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
if (typeof window.MoelogAIQnA.typing_fallback === 'undefined') {
  window.MoelogAIQnA.typing_fallback = '<?php echo esc_js(
      esc_html__("抱歉,目前無法取得 AI 回答,請稍後再試。", "moelog-ai-qna")
  ); ?>';
}
window.MoelogAIQnA.feedback = <?php echo wp_json_encode($feedback_config); ?>;
</script>
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
$banner_alt = apply_filters("moelog_aiqna_banner_alt", $post_title);
$banner_style = $banner_url
    ? sprintf("background-image:url('%s')", esc_url($banner_url))
    : "";
?>
<div class="moe-banner"<?php if ($banner_style): ?> style="<?php echo esc_attr($banner_style); ?>"<?php endif; ?> role="img" aria-label="<?php echo esc_attr($banner_alt); ?>"></div>

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
  <section class="moe-feedback-card" id="moe-feedback-card">
    <h3 class="moe-feedback-title"><?php esc_html_e(
        "你覺得AI回答的內容正確嗎？",
        "moelog-ai-qna"
    ); ?></h3>
    <div class="moe-feedback-actions" role="group" aria-label="<?php esc_attr_e(
        "文章回饋操作",
        "moelog-ai-qna"
    ); ?>">
      <button type="button" class="moe-feedback-btn" data-action="like">
        <img src="<?php echo esc_url(plugins_url("includes/assets/images/good.png", dirname(__FILE__))); ?>" alt="<?php esc_attr_e("正確", "moelog-ai-qna"); ?>" width="28" height="28" aria-hidden="true">
        <span><?php esc_html_e("正確", "moelog-ai-qna"); ?></span>
      </button>
      <button type="button" class="moe-feedback-btn" data-action="dislike">
        <img src="<?php echo esc_url(plugins_url("includes/assets/images/bad.png", dirname(__FILE__))); ?>" alt="<?php esc_attr_e("錯誤", "moelog-ai-qna"); ?>" width="28" height="28" aria-hidden="true">
        <span><?php esc_html_e("錯誤", "moelog-ai-qna"); ?></span>
      </button>
      <button type="button" class="moe-feedback-btn" data-action="report">
        <img src="<?php echo esc_url(plugins_url("includes/assets/images/report.png", dirname(__FILE__))); ?>" alt="<?php esc_attr_e("回報問題", "moelog-ai-qna"); ?>" width="28" height="28" aria-hidden="true">
        <span><?php esc_html_e("回報問題", "moelog-ai-qna"); ?></span>
      </button>
    </div>

    <div class="moe-feedback-report" id="moe-feedback-report" hidden>
      <textarea name="moe-feedback-text" placeholder="<?php esc_attr_e(
          "請簡述您遇到的問題或建議",
          "moelog-ai-qna"
      ); ?>"></textarea>
      <div class="moe-feedback-report-actions">
        <button type="button" class="moe-feedback-secondary" data-feedback-cancel><?php esc_html_e(
            "取消",
            "moelog-ai-qna"
        ); ?></button>
        <button type="button" class="moe-feedback-primary" data-feedback-submit><?php esc_html_e(
            "送出",
            "moelog-ai-qna"
        ); ?></button>
      </div>
    </div>
    <p class="moe-feedback-message" id="moe-feedback-message"></p>
  </section>
  <div class="moe-feedback-stats">
    <div class="moe-feedback-stat">
      <img src="<?php echo esc_url(plugins_url("includes/assets/images/viewer.png", dirname(__FILE__))); ?>" alt="<?php esc_attr_e("瀏覽次數", "moelog-ai-qna"); ?>" width="28" height="28" aria-hidden="true">
      <span data-stat="views"><?php echo esc_html(
          $feedback_stats["views"]
      ); ?></span>
    </div>
    <div class="moe-feedback-stat">
      <img src="<?php echo esc_url(plugins_url("includes/assets/images/good.png", dirname(__FILE__))); ?>" alt="<?php esc_attr_e("好評", "moelog-ai-qna"); ?>" width="28" height="28" aria-hidden="true">
      <span data-stat="likes"><?php echo esc_html(
          $feedback_stats["likes"]
      ); ?></span>
    </div>
    <div class="moe-feedback-stat">
      <img src="<?php echo esc_url(plugins_url("includes/assets/images/bad.png", dirname(__FILE__))); ?>" alt="<?php esc_attr_e("差評", "moelog-ai-qna"); ?>" width="28" height="28" aria-hidden="true">
      <span data-stat="dislikes"><?php echo esc_html(
          $feedback_stats["dislikes"]
      ); ?></span>
    </div>
  </div>
<?php
$close_label = esc_html__("← 關閉此頁", "moelog-ai-qna");
$fallback_label = esc_html__(
    "若瀏覽器不允許自動關閉視窗,請點此回到文章:",
    "moelog-ai-qna"
);
$post_link = get_permalink($post_id);
$post_title = get_the_title($post_id);
?>
<div class="moe-close-area">
  <a href="#" id="moe-close-btn" class="moe-close-btn"><?php echo $close_label; ?></a>
  <div id="moelog-fallback" class="moe-fallback" style="display:none;">
    <?php echo $fallback_label; ?>
    <a href="<?php echo esc_url($post_link); ?>" target="_self" rel="noopener">
      <?php echo esc_html($post_title); ?>
    </a>
  </div>
</div>
</div>
</div>
<div class="moe-bottom"></div>

<script 
  src="<?php echo esc_url(
      plugins_url("includes/assets/js/answer.js", dirname(__FILE__))
  ); ?>?ver=<?php echo esc_attr(MOELOG_AIQNA_VERSION); ?>" 
  defer>
</script>

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