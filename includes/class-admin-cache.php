<?php
/**
 * Moelog AI Q&A Admin Cache Manager
 *
 * 負責快取管理相關功能
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
  exit();
}

class Moelog_AIQnA_Admin_Cache
{
  /**
   * 渲染快取管理區塊
   */
  public function render_cache_management()
  {
    // ✅ 強制刷新統計，確保顯示最新數據
    $stats = Moelog_AIQnA_Cache::get_stats(true);
    $ttl_days = moelog_aiqna_get_cache_ttl_days();
    ?>
    <div class="moelog-settings-card">
        <h2><?php esc_html_e("🗑️ 快取管理", "moelog-ai-qna"); ?></h2>

        <div style="background:#f9f9f9;border-left:4px solid #2271b1;padding:15px;margin-bottom:20px;">
            <p style="margin:0;">
                <strong><?php esc_html_e("說明:", "moelog-ai-qna"); ?></strong>
                <?php printf(
                  esc_html__(
                    "AI 回答會快取 %d 天並生成靜態 HTML 檔案。如果發現回答有誤或需要重新生成，可以清除快取。",
                    "moelog-ai-qna",
                  ),
                  $ttl_days,
                ); ?>
            </p>
        </div>

        <!-- 快取統計 -->
        <h3><?php esc_html_e("📊 快取統計", "moelog-ai-qna"); ?></h3>
        <table class="widefat" style="max-width:600px;">
            <tr>
                <th style="width:40%;"><?php esc_html_e(
                  "靜態檔案數量",
                  "moelog-ai-qna",
                ); ?></th>
                <td><strong><?php echo esc_html(
                  moelog_aiqna_format_number($stats["static_count"]),
                ); ?></strong> 個</td>
            </tr>
            <tr>
                <th><?php esc_html_e("佔用空間", "moelog-ai-qna"); ?></th>
                <td><strong><?php echo esc_html(
                  moelog_aiqna_format_bytes($stats["static_size"]),
                ); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e("Transient 數量", "moelog-ai-qna"); ?></th>
                <td><strong><?php echo esc_html(
                  moelog_aiqna_format_number($stats["transient_count"]),
                ); ?></strong> 筆</td>
            </tr>
            <tr>
                <th><?php esc_html_e("快取目錄", "moelog-ai-qna"); ?></th>
                <td>
                    <code><?php echo esc_html($stats["directory"]); ?></code>
                    <?php if ($stats["directory_writable"]): ?>
                        <span style="color:green;">✓ <?php esc_html_e(
                          "可寫",
                          "moelog-ai-qna",
                        ); ?></span>
                    <?php else: ?>
                        <span style="color:red;">✗ <?php esc_html_e(
                          "不可寫",
                          "moelog-ai-qna",
                        ); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- 清除所有快取 -->
        <h3><?php esc_html_e("🗑️ 清除所有快取", "moelog-ai-qna"); ?></h3>
        <form method="post" action="" style="margin-bottom:30px;">
            <?php wp_nonce_field(
              "moelog_aiqna_clear_cache",
              "moelog_aiqna_clear_cache_nonce",
            ); ?>
            <p><?php esc_html_e(
              "這會清除所有文章所有問題的 AI 回答快取與靜態檔案。",
              "moelog-ai-qna",
            ); ?></p>
            <button type="submit"
                    name="moelog_aiqna_clear_cache"
                    class="button button-secondary"
                    onclick="return confirm('<?php echo esc_js(
                      __(
                        "確定要清除所有 AI 回答快取與靜態檔案嗎？此操作無法復原。",
                        "moelog-ai-qna",
                      ),
                    ); ?>');">
                🗑️ <?php esc_html_e("清除所有快取", "moelog-ai-qna"); ?>
            </button>
        </form>

        <!-- 清理孤兒統計數據 -->
        <h3 style="margin-top:30px;"><?php esc_html_e("🧹 清理孤兒統計數據", "moelog-ai-qna"); ?></h3>
        <form method="post" action="" style="margin-bottom:30px;">
            <?php wp_nonce_field(
              "moelog_aiqna_cleanup_orphaned",
              "moelog_aiqna_cleanup_orphaned_nonce",
            ); ?>
            <p><?php esc_html_e(
              "如果手動刪除了靜態 HTML 檔案（例如透過 FTP），對應的統計數據可能會殘留在資料庫中。此功能會掃描所有統計數據，並刪除對應檔案已不存在的孤兒數據。",
              "moelog-ai-qna",
            ); ?></p>
            <button type="submit"
                    name="moelog_aiqna_cleanup_orphaned"
                    class="button button-secondary"
                    onclick="return confirm('<?php echo esc_js(
                      __(
                        "確定要清理孤兒統計數據嗎？此操作會掃描資料庫並刪除對應檔案已不存在的統計數據。",
                        "moelog-ai-qna",
                      ),
                    ); ?>');">
                🧹 <?php esc_html_e("清理孤兒數據", "moelog-ai-qna"); ?>
            </button>
        </form>

        <!-- 清除單一靜態 HTML -->
        <h3 style="margin-top:30px;"><?php esc_html_e("🔍 刪除單一問題的靜態 HTML", "moelog-ai-qna"); ?></h3>
        <form method="post" action="" id="moelog-clear-single-form">
            <?php wp_nonce_field(
              "moelog_aiqna_clear_single",
              "moelog_aiqna_clear_single_nonce",
            ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="clear_post_id"><?php esc_html_e(
                          "文章 ID",
                          "moelog-ai-qna",
                        ); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="clear_post_id"
                               name="post_id"
                               required
                               style="width:150px;"
                               min="1">
                        <button type="button" 
                                id="load-questions-btn" 
                                class="button"
                                style="margin-left:10px;">
                            <?php esc_html_e("載入問題列表", "moelog-ai-qna"); ?>
                        </button>
                        <span id="post-title-display" style="margin-left:10px;color:#666;font-style:italic;"></span>
                        <p class="description">
                            <?php esc_html_e(
                              "輸入文章 ID 後點擊「載入問題列表」，系統會自動載入該文章的所有問題",
                              "moelog-ai-qna",
                            ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="clear_question"><?php esc_html_e(
                          "問題選擇",
                          "moelog-ai-qna",
                        ); ?></label>
                    </th>
                    <td>
                        <select id="clear_question_select"
                                class="regular-text"
                                style="display:none;margin-bottom:10px;">
                            <option value=""><?php esc_html_e("請先載入問題列表", "moelog-ai-qna"); ?></option>
                        </select>
                        <input type="text"
                               id="clear_question"
                               name="question"
                               class="regular-text"
                               placeholder="<?php esc_attr_e("或手動輸入完整問題文字", "moelog-ai-qna"); ?>"
                               style="display:block;">
                        <input type="hidden"
                               id="clear_question_hash"
                               name="question_hash"
                               value="">
                        <p class="description">
                            <?php esc_html_e(
                              "從下拉選單選擇問題，或手動輸入完整的問題文字",
                              "moelog-ai-qna",
                            ); ?>
                        </p>
                        <div id="questions-loading" style="display:none;color:#666;margin-top:5px;">
                            <span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>
                            <?php esc_html_e("載入中...", "moelog-ai-qna"); ?>
                        </div>
                        <div id="questions-error" style="display:none;color:#d63638;margin-top:5px;"></div>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="moelog_aiqna_clear_single" class="button button-secondary">
                    🗑️ <?php esc_html_e("刪除此問題的靜態 HTML", "moelog-ai-qna"); ?>
                </button>
            </p>
        </form>
    </div>

        <script>
        (function($) {
            $(document).ready(function() {
                // 確保 jQuery 已載入
                if (typeof jQuery === 'undefined') {
                    console.error('jQuery is not loaded');
                    return;
                }
                var $postId = $('#clear_post_id');
                var $loadBtn = $('#load-questions-btn');
                var $questionSelect = $('#clear_question_select');
                var $questionInput = $('#clear_question');
                var $questionHash = $('#clear_question_hash');
                var $loading = $('#questions-loading');
                var $error = $('#questions-error');
                var $postTitle = $('#post-title-display');

                // 載入問題列表
                $loadBtn.on('click', function() {
                    var postId = parseInt($postId.val());
                    
                    if (!postId || postId < 1) {
                        alert('<?php echo esc_js(__("請輸入有效的文章 ID", "moelog-ai-qna")); ?>');
                        return;
                    }

                    // 顯示載入狀態
                    $loading.show();
                    $error.hide();
                    $questionSelect.hide().empty();
                    $questionInput.val('');
                    $questionHash.val('');
                    $postTitle.text('');

                    $.ajax({
                        url: '<?php echo esc_js(admin_url("admin-ajax.php")); ?>',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'moelog_aiqna_get_questions',
                            post_id: postId,
                            nonce: '<?php echo wp_create_nonce("moelog_aiqna_get_questions"); ?>'
                        },
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        },
                        success: function(response) {
                            $loading.hide();
                            
                            if (response.success && response.data.questions) {
                                var questions = response.data.questions;
                                var questionTargets = response.data.question_targets || [];
                                var postTitle = response.data.post_title || '';
                                
                                // 顯示文章標題
                                if (postTitle) {
                                    $postTitle.text('《' + postTitle + '》');
                                }

                                // 填充下拉選單
                                $questionSelect.empty();
                                $questionSelect.append(
                                    $('<option></option>')
                                        .attr('value', '')
                                        .text('<?php echo esc_js(__("請選擇問題", "moelog-ai-qna")); ?>')
                                );

                                questions.forEach(function(question, index) {
                                    var target = questionTargets[index] || {};
                                    var questionHash = target.hash || '';
                                    var canonicalQuestion = target.question || question;
                                    var displayText = question.length > 60
                                        ? question.substring(0, 60) + '...'
                                        : question;
                                    $questionSelect.append(
                                        $('<option></option>')
                                            .attr('value', questionHash)
                                            .attr('data-question', canonicalQuestion)
                                            .text((index + 1) + '. ' + displayText)
                                            .attr('title', canonicalQuestion)
                                    );
                                });

                                $questionSelect.show();
                                $questionInput.prop('required', false);
                                
                                // 當選擇問題時，自動填入輸入框並隱藏輸入框（顯示選擇的問題）
                                $questionSelect.off('change').on('change', function() {
                                    var selectedHash = $(this).val();
                                    var selectedQuestion = $(this)
                                        .find('option:selected')
                                        .attr('data-question') || '';
                                    if (selectedHash) {
                                        $questionHash.val(selectedHash);
                                        $questionInput.val(selectedQuestion);
                                        // 可選：隱藏輸入框，只顯示選擇的問題
                                        // $questionInput.hide();
                                    } else {
                                        $questionHash.val('');
                                        $questionInput.val('');
                                        // $questionInput.show();
                                    }
                                });
                            } else {
                                $error.text(response.data.message || '<?php echo esc_js(__("載入失敗", "moelog-ai-qna")); ?>').show();
                            }
                        },
                        error: function(xhr, status, error) {
                            $loading.hide();
                            var errorMsg = '<?php echo esc_js(__("AJAX 請求失敗，請稍後再試", "moelog-ai-qna")); ?>';
                            
                            // WordPress 返回 -1 或 0 表示 nonce 驗證失敗（可能是 400 或 403）
                            if ((xhr.status === 400 || xhr.status === 403) && 
                                (xhr.responseText === '-1' || xhr.responseText === '0')) {
                                errorMsg = '<?php echo esc_js(__("安全驗證失敗，請重新整理頁面後再試", "moelog-ai-qna")); ?>';
                            } else {
                                // 嘗試從響應中獲取錯誤訊息
                                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                                    errorMsg = xhr.responseJSON.data.message;
                                } else if (xhr.responseText && xhr.responseText !== '-1' && xhr.responseText !== '0') {
                                    try {
                                        var response = JSON.parse(xhr.responseText);
                                        if (response.data && response.data.message) {
                                            errorMsg = response.data.message;
                                        }
                                    } catch(e) {
                                        // 忽略解析錯誤
                                    }
                                }
                            }
                            
                            $error.text(errorMsg).show();
                            
                            // 只在開發模式下輸出詳細錯誤
                            if (typeof console !== 'undefined' && console.error) {
                                console.error('AJAX Error:', {
                                    status: xhr.status,
                                    statusText: xhr.statusText,
                                    responseText: xhr.responseText,
                                    error: error
                                });
                            }
                        }
                    });
                });

                // 當用戶在輸入框中輸入時，清空下拉選單選擇（避免混淆）
                $questionInput.on('input', function() {
                    $questionSelect.val('');
                    $questionHash.val('');
                });

                // 表單提交前驗證
                $('#moelog-clear-single-form').on('submit', function(e) {
                    var selectedHash = $questionSelect.val();
                    var selectedQuestion = $questionSelect
                        .find('option:selected')
                        .attr('data-question') || '';
                    var inputQuestion = $questionInput.val().trim();
                    
                    // 優先使用下拉選單選擇的問題
                    var finalQuestion = selectedQuestion || inputQuestion;
                    
                    if (!finalQuestion) {
                        e.preventDefault();
                        alert('<?php echo esc_js(__("請選擇或輸入問題", "moelog-ai-qna")); ?>');
                        return false;
                    }
                    
                    // 確保提交時使用正確的問題文字
                    $questionInput.val(finalQuestion);
                    $questionHash.val(selectedHash || '');
                    
                    // 確保 nonce 欄位存在
                    if (!$('#moelog-clear-single-form input[name="moelog_aiqna_clear_single_nonce"]').length) {
                        console.error('Nonce field is missing!');
                        e.preventDefault();
                        alert('<?php echo esc_js(__("安全驗證欄位缺失，請重新整理頁面", "moelog-ai-qna")); ?>');
                        return false;
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
  }

  /**
   * 處理快取清除動作
   */
  public function handle_cache_actions()
  {
    // 清理孤兒統計數據
    if (
      isset($_POST["moelog_aiqna_cleanup_orphaned"]) &&
      check_admin_referer(
        "moelog_aiqna_cleanup_orphaned",
        "moelog_aiqna_cleanup_orphaned_nonce",
      )
    ) {
      $result = Moelog_AIQnA_Feedback_Controller::cleanup_orphaned_stats();

      if ($result["deleted"] > 0) {
        add_settings_error(
          "moelog_aiqna_messages",
          "orphaned_cleaned",
          sprintf(
            __("✅ 清理完成！掃描了 %d 筆統計數據，刪除了 %d 筆孤兒數據。", "moelog-ai-qna"),
            $result["scanned"],
            $result["deleted"],
          ),
          "success",
        );
      } else {
        add_settings_error(
          "moelog_aiqna_messages",
          "no_orphaned",
          sprintf(
            __("ℹ️ 掃描了 %d 筆統計數據，未發現孤兒數據。", "moelog-ai-qna"),
            $result["scanned"],
          ),
          "info",
        );
      }
    }

    // 清除所有快取
    if (
      isset($_POST["moelog_aiqna_clear_cache"]) &&
      check_admin_referer(
        "moelog_aiqna_clear_cache",
        "moelog_aiqna_clear_cache_nonce",
      )
    ) {
      $result = moelog_aiqna_instance()->clear_all_cache();

      add_settings_error(
        "moelog_aiqna_messages",
        "cache_cleared",
        sprintf(
          __("✅ 成功清除 %d 筆快取記錄與 %d 個靜態檔案!", "moelog-ai-qna"),
          $result["transient"],
          $result["static"],
        ),
        "success",
      );
    }

    // 清除單一快取
    if (isset($_POST["moelog_aiqna_clear_single"])) {
      // 檢查 nonce
      if (
        !isset($_POST["moelog_aiqna_clear_single_nonce"]) ||
        !wp_verify_nonce(
          $_POST["moelog_aiqna_clear_single_nonce"],
          "moelog_aiqna_clear_single",
        )
      ) {
        add_settings_error(
          "moelog_aiqna_messages",
          "nonce_failed",
          __("❌ 安全驗證失敗，請重新整理頁面後再試。", "moelog-ai-qna"),
          "error",
        );
        return;
      }
      $post_id = absint($_POST["post_id"] ?? 0);
      $submitted_hash = isset($_POST["question_hash"]) && is_string($_POST["question_hash"])
        ? strtolower(sanitize_text_field(wp_unslash($_POST["question_hash"])))
        : "";
      $submitted_question = isset($_POST["question"]) && is_string($_POST["question"])
        ? trim(wp_unslash($_POST["question"]))
        : "";
      $question = $this->resolve_cache_question(
        $post_id,
        $submitted_hash,
        $submitted_question,
      );

      if ($post_id && $question) {
        // 只刪除靜態 HTML 檔案（不刪除 transient 快取）
        $static_deleted = Moelog_AIQnA_Cache::delete($post_id, $question);

        if ($static_deleted) {
          add_settings_error(
            "moelog_aiqna_messages",
            "single_cache_cleared",
            sprintf(
              __("✅ 成功刪除靜態 HTML 檔案!（文章 ID: %d）", "moelog-ai-qna"),
              $post_id,
            ) .
              "<br>" .
              sprintf(
                __("問題: %s", "moelog-ai-qna"),
                "<code>" . esc_html($question) . "</code>",
              ),
            "success",
          );
        } else {
          add_settings_error(
            "moelog_aiqna_messages",
            "no_cache_found",
            sprintf(
              __(
                "⚠️ 未找到相關靜態 HTML 檔案。可能原因：檔案不存在、問題文字不符、或該問題從未被訪問過。",
                "moelog-ai-qna",
              ),
            ),
            "warning",
          );
        }
      } else {
        add_settings_error(
          "moelog_aiqna_messages",
          "invalid_input",
          __("❌ 請填寫完整的文章 ID 和問題文字。", "moelog-ai-qna"),
          "error",
        );
      }
    }
  }

  /**
   * Resolve a submitted cache target to the canonical question stored on the post.
   *
   * Dropdown selections use the server-generated hash. Manual input remains
   * supported, but must exactly match one of the post's current questions after
   * WordPress request slashing is removed.
   *
   * @param int    $post_id
   * @param string $submitted_hash
   * @param string $submitted_question
   * @return string|false
   */
  private function resolve_cache_question($post_id, $submitted_hash, $submitted_question)
  {
    if (!$post_id) {
      return false;
    }

    $questions = moelog_aiqna_parse_questions(
      get_post_meta($post_id, MOELOG_AIQNA_META_KEY, true),
    );
    if (!$questions) {
      return false;
    }

    if ($submitted_hash !== "") {
      if (preg_match('/^[a-f0-9]{16}$/', $submitted_hash) !== 1) {
        return false;
      }

      foreach ($questions as $question) {
        $expected_hash = Moelog_AIQnA_Cache::generate_hash($post_id, $question);
        if (hash_equals($expected_hash, $submitted_hash)) {
          return $question;
        }
      }

      return false;
    }

    foreach ($questions as $question) {
      if (hash_equals($question, $submitted_question)) {
        return $question;
      }
    }

    return false;
  }
}

