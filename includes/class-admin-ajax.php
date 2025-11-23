<?php
/**
 * Moelog AI Q&A Admin AJAX Handler
 *
 * 負責處理 AJAX 請求
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
  exit();
}

class Moelog_AIQnA_Admin_Ajax
{
  /**
   * 處理 API 測試 AJAX 請求
   */
  public function ajax_test_api()
  {
    check_ajax_referer("moelog_aiqna_test_api", "nonce");

    if (!current_user_can("manage_options")) {
      wp_send_json_error(["message" => __("權限不足", "moelog-ai-qna")]);
      return;
    }

    $provider = sanitize_text_field($_POST["provider"] ?? "openai");
    $api_key = sanitize_text_field($_POST["api_key"] ?? "");
    $model = sanitize_text_field($_POST["model"] ?? "");

    // ✅ 處理常數定義的情況
    if ($api_key === "from_constant") {
      // 從常數讀取 API Key
      if (defined("MOELOG_AIQNA_API_KEY") && constant("MOELOG_AIQNA_API_KEY")) {
        $api_key = constant("MOELOG_AIQNA_API_KEY");
      } else {
        wp_send_json_error(["message" => __("常數 MOELOG_AIQNA_API_KEY 未定義", "moelog-ai-qna")]);
        return;
      }
    }

    if (empty($api_key)) {
      wp_send_json_error(["message" => __("請提供 API Key", "moelog-ai-qna")]);
      return;
    }

    // 使用 AI Client 測試連線
    $ai_client = new Moelog_AIQnA_AI_Client();
    $result = $ai_client->test_connection($provider, $api_key, $model);

    if ($result["success"]) {
      wp_send_json_success(["message" => $result["message"]]);
    } else {
      wp_send_json_error(["message" => $result["message"]]);
    }
  }

  /**
   * 獲取文章的問題列表（AJAX）
   * ✅ 修正版本:使用標準 WordPress AJAX nonce 驗證
   */
  public function ajax_get_post_questions()
  {
    // 先檢查權限（只允許能開設定頁的人）
    if (!current_user_can("manage_options")) {
      wp_send_json_error([
        "message" => __("權限不足", "moelog-ai-qna"),
      ]);
    }

    // 標準 WordPress AJAX nonce 驗證
    // 前端用的是欄位名 nonce，action 字串為 'moelog_aiqna_get_questions'
    check_ajax_referer("moelog_aiqna_get_questions", "nonce");

    $post_id = isset($_POST["post_id"]) ? intval($_POST["post_id"]) : 0;

    if (!$post_id) {
      wp_send_json_error([
        "message" => __("請提供有效的文章 ID", "moelog-ai-qna"),
      ]);
    }

    // 驗證文章存在
    $post = get_post($post_id);
    if (!$post) {
      wp_send_json_error([
        "message" => __("找不到指定的文章", "moelog-ai-qna"),
      ]);
    }

    // 獲取問題列表
    $raw_questions = get_post_meta($post_id, MOELOG_AIQNA_META_KEY, true);

    if (function_exists("moelog_aiqna_parse_questions")) {
      $questions = moelog_aiqna_parse_questions($raw_questions);
    } else {
      // 後備方案：兼容舊格式
      if (is_array($raw_questions)) {
        $questions = array_filter(array_map("trim", $raw_questions));
      } elseif (is_string($raw_questions)) {
        $questions = array_filter(array_map("trim", explode("\n", $raw_questions)));
      } else {
        $questions = [];
      }
    }

    if (empty($questions)) {
      wp_send_json_error([
        "message" => __("此文章尚未設定任何問題", "moelog-ai-qna"),
      ]);
    }

    wp_send_json_success([
      "questions" => array_values($questions),
      "post_title" => get_the_title($post_id),
      "count" => count($questions),
    ]);
  }
}

// 註冊 AJAX 處理
add_action("wp_ajax_moelog_aiqna_test_api", function () {
  $ajax_handler = new Moelog_AIQnA_Admin_Ajax();
  $ajax_handler->ajax_test_api();
});

add_action("wp_ajax_moelog_aiqna_get_questions", function () {
  $ajax_handler = new Moelog_AIQnA_Admin_Ajax();
  $ajax_handler->ajax_get_post_questions();
});