<?php
/**
 * Moelog AI Q&A Metabox Class
 *
 * @package Moelog_AIQnA
 * @since   1.8.3++
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Metabox
{
    /** 最大問題數量 */
    const MAX_QUESTIONS = 8;

    /** 單一問題最大字元數 */
    const MAX_QUESTION_LENGTH = 200;

    /** 建構子:掛鉤 */
    public function __construct()
    {
        add_action("add_meta_boxes", [$this, "add_metabox"]);
        add_action("save_post", [$this, "save_metabox"], 10, 2);

        // 關鍵:在後台正確掛載 JS/CSS(避免在 metabox 內直出 <script> 不執行)
        add_action("admin_enqueue_scripts", [$this, "enqueue_admin_assets"]);

        // AJAX 處理
        add_action("wp_ajax_moelog_aiqna_regenerate", [
            $this,
            "ajax_regenerate",
        ]);
    }

    /**
     * 後台載入必要資源(jQuery / jQuery UI Sortable / 我們的 JS 與 CSS)
     */
    public function enqueue_admin_assets($hook)
    {
        // 僅在文章/頁面編輯頁載入
        if ($hook !== "post.php" && $hook !== "post-new.php") {
            return;
        }

        // 依站點實際啟用的 post type 決定是否載入(避免多餘載入)
        $screen = get_current_screen();
        if (
            !$screen ||
            !in_array(
                $screen->post_type,
                apply_filters("moelog_aiqna_post_types", ["post", "page"]),
                true
            )
        ) {
            return;
        }

        // 確保 jQuery 與 jQuery UI Sortable
        wp_enqueue_script("jquery");
        wp_enqueue_script("jquery-ui-sortable"); // 依賴會自帶 core/mouse

        // 註冊一個空白 handle,將 JS 內嵌進去,確保載入順序與作用域
        $handle = "moelog-aiqna-metabox";
        wp_register_script($handle, "", [], "1.0.0", true);
        wp_enqueue_script($handle);

        // 將參數導入 JS
        $data = [
            "MAX_QUESTIONS" => self::MAX_QUESTIONS,
            "MAX_LENGTH" => self::MAX_QUESTION_LENGTH,
            "ajaxurl" => admin_url("admin-ajax.php"),
            "i18n" => [
                "clearAllConfirm" => __(
                    "確定要清空所有問題嗎?",
                    "moelog-ai-qna"
                ),
                "removeConfirm" => __("確定要刪除此問題嗎?", "moelog-ai-qna"),
                "maxAlert" => sprintf(
                    __("最多只能設定 %d 個問題", "moelog-ai-qna"),
                    self::MAX_QUESTIONS
                ),
                "sortHint" => __("請直接拖曳問題列來調整順序", "moelog-ai-qna"),
                "setCounter" => __(
                    "已設定 %s 題 / 最多 %d 題",
                    "moelog-ai-qna"
                ),
                "placeholder" => __(
                    "例如: 為何科技新創偏好使用「.io」?",
                    "moelog-ai-qna"
                ),
                "dragTitle" => __("拖曳排序", "moelog-ai-qna"),
                "removeTitle" => __("刪除此問題", "moelog-ai-qna"),
                "regenerateConfirm" => __(
                    "確定要清除快取並重新生成所有答案嗎?",
                    "moelog-ai-qna"
                ),
                "processing" => __("處理中...", "moelog-ai-qna"),
                "regenerateBtn" => __("重新生成全部", "moelog-ai-qna"),
                "regenerateSuccess" => __(
                    "已排程重新生成任務,請等待 2-5 分鐘後重新整理頁面查看進度。",
                    "moelog-ai-qna"
                ),
                "regenerateFailed" => __("操作失敗", "moelog-ai-qna"),
                "requestFailed" => __("請求失敗,請稍後再試。", "moelog-ai-qna"),
            ],
        ];
        wp_add_inline_script(
            $handle,
            "window.MOELOG_AIQNA_META=" .
                wp_json_encode(
                    $data,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) .
                ";",
            "before"
        );

        // 內嵌主要 JS(用 IIFE;確保 sortable 存在;加入 MutationObserver 以支援 Gutenberg 延遲掛載)
        wp_add_inline_script(
            $handle,
            <<<JS
(function($){
'use strict';

var CFG = window.MOELOG_AIQNA_META || {};
var ROOT_SEL = '#moelog-aiqna-metabox';
var ROWS_SEL = '#moelog-aiqna-rows';

function ready(fn){
    if (document.readyState !== 'loading'){ fn(); }
    else document.addEventListener('DOMContentLoaded', fn);
}

function ensureDom(cb){
    var target = document.querySelector(ROWS_SEL);
    if (target){ cb(); return; }
    // Gutenberg 有時候 meta box 會延後出現在 DOM,這裡觀察一次
    var mo = new MutationObserver(function(){
        if (document.querySelector(ROWS_SEL)){
            mo.disconnect();
            cb();
        }
    });
    mo.observe(document.body, {childList:true, subtree:true});
    // 最多 5 秒保險
    setTimeout(function(){ try{mo.disconnect();}catch(e){} cb(); }, 5000);
}

function init(){
    ensureDom(function(){
        if (!document.querySelector(ROWS_SEL)) return;
        bind();
        initSortable();
        initCounters();
        updateCounter();
    });
}

function initSortable(){
    if (!$.fn || !$.fn.sortable){
        console.warn('[Moelog AIQnA] jQuery UI Sortable 未載入,排序功能停用');
        return;
    }
    $(ROWS_SEL).sortable({
        handle: '.moe-drag-handle',
        placeholder: 'moe-sortable-placeholder',
        axis: 'y',
        cursor: 'move',
        opacity: 0.9,
        update: function(){ updateNumbers(); }
    });
}

function bind(){
    // 新增
    $(document).off('click.moeAdd', '#moelog-aiqna-add-btn')
    .on('click.moeAdd', '#moelog-aiqna-add-btn', function(e){
        e.preventDefault();
        addQuestion();
    });

    // 刪除
    $(document).off('click.moeRemove', ROOT_SEL+' .moe-remove-btn')
    .on('click.moeRemove', ROOT_SEL+' .moe-remove-btn', function(e){
        e.preventDefault();
        removeQuestion($(this).closest('.moe-question-row'));
    });

    // 清空
    $(document).off('click.moeClear', '#moelog-aiqna-clear-all')
    .on('click.moeClear', '#moelog-aiqna-clear-all', function(e){
        e.preventDefault();
        if (confirm(CFG.i18n.clearAllConfirm)){ clearAll(); }
    });

    // 排序提示
    $(document).off('click.moeSort', '#moelog-aiqna-sort-btn')
    .on('click.moeSort', '#moelog-aiqna-sort-btn', function(e){
        e.preventDefault();
        alert(CFG.i18n.sortHint);
    });

    // 計數
    $(document).off('input.moeCount', ROOT_SEL+' .moe-question-input')
    .on('input.moeCount', ROOT_SEL+' .moe-question-input', function(){
        updateCharCount($(this));
        checkEmpty($(this).closest('.moe-question-row'));
    });
    
    // 手動重新生成
    $(document).off('click.moeRegen', '#moelog-aiqna-regenerate')
    .on('click.moeRegen', '#moelog-aiqna-regenerate', function(e){
        e.preventDefault();
        if (!confirm(CFG.i18n.regenerateConfirm)) {
            return;
        }
        
        var \$btn = $(this);
        \$btn.prop('disabled', true).text(CFG.i18n.processing);
        
        $.post(CFG.ajaxurl, {
            action: 'moelog_aiqna_regenerate',
            post_id: $('#post_ID').val(),
            nonce: $('#moelog_aiqna_nonce').val()
        }, function(response){
            if (response.success) {
                alert(CFG.i18n.regenerateSuccess);
                location.reload();
            } else {
                alert(CFG.i18n.regenerateFailed + ': ' + (response.data || '未知錯誤'));
                \$btn.prop('disabled', false).text(CFG.i18n.regenerateBtn);
            }
        }).fail(function(){
            alert(CFG.i18n.requestFailed);
            \$btn.prop('disabled', false).text(CFG.i18n.regenerateBtn);
        });
    });
}

function countRows(){ return $(ROWS_SEL+' .moe-question-row').length; }

function addQuestion(){
    var qn = countRows();
    if (qn >= CFG.MAX_QUESTIONS){
        alert(CFG.i18n.maxAlert); return;
    }
    var idx = qn;
    var tpl = [
        '<div class="moe-question-row moe-empty" data-index="'+idx+'">',
        '  <span class="moe-drag-handle" title="'+CFG.i18n.dragTitle+'"><span class="dashicons dashicons-menu"></span></span>',
        '  <span class="moe-question-number">'+(idx+1)+'.</span>',
        '  <textarea name="moelog_aiqna_questions[]" class="moe-question-input" rows="1" maxlength="'+CFG.MAX_LENGTH+'" ',
        '    placeholder="'+CFG.i18n.placeholder+'" data-index="'+idx+'"></textarea>',
        '  <span class="moe-char-count"><span class="current">0</span> / '+CFG.MAX_LENGTH+'</span>',
        '  <select name="moelog_aiqna_langs[]" class="moe-lang-select">',
        '    <option value="auto">🌐 自動偵測</option>',
        '    <option value="zh">🇹🇼 繁體中文</option>',
        '    <option value="ja">🇯🇵 日文</option>',
        '    <option value="en">🇺🇸 英文</option>',
        '  </select>',
        '  <button type="button" class="button moe-remove-btn" title="'+CFG.i18n.removeTitle+'">',
        '    <span class="dashicons dashicons-trash"></span>',
        '  </button>',
        '</div>'
    ].join('');
    $(ROWS_SEL).append(tpl);
    updateCounter();
    var \$new = $(ROWS_SEL+' .moe-question-row:last .moe-question-input');
    \$new.focus();
    if (\$new[0] && \$new[0].scrollIntoView){
        \$new[0].scrollIntoView({behavior:'smooth', block:'nearest'});
    }
}

function removeQuestion(\$row){
    var hasContent = $.trim(\$row.find('.moe-question-input').val()).length > 0;
    if (hasContent && !confirm(CFG.i18n.removeConfirm)){ return; }
    \$row.fadeOut(120, function(){
        $(this).remove();
        updateNumbers();
        updateCounter();
    });
}

function clearAll(){
    $(ROWS_SEL).empty();
    updateCounter();
    addQuestion(); // 留一列空白
}

function updateNumbers(){
    $(ROWS_SEL+' .moe-question-row').each(function(i){
        $(this).attr('data-index', i);
        $(this).find('.moe-question-number').text((i+1)+'.');
        $(this).find('.moe-question-input').attr('data-index', i);
    });
}

function updateCounter(){
    var qn = countRows();
    var \$counter = $('#moelog-aiqna-counter');
    var \$addBtn  = $('#moelog-aiqna-add-btn');
    var html = CFG.i18n.setCounter.replace('%s','<strong>'+qn+'</strong>').replace('%d', CFG.MAX_QUESTIONS);
    \$counter.html(html);
    \$addBtn.prop('disabled', qn >= CFG.MAX_QUESTIONS);
}

function initCounters(){
    $(ROOT_SEL+' .moe-question-input').each(function(){ updateCharCount($(this)); });
}

function updateCharCount(\$textarea){
    var length = \$textarea.val().length;
    var \$c = \$textarea.closest('.moe-question-row').find('.moe-char-count .current');
    \$c.text(length);
    var warn = CFG.MAX_LENGTH*0.9, mid = CFG.MAX_LENGTH*0.7;
    if (length > warn){ \$c.css('color', '#d63638'); }
    else if (length > mid){ \$c.css('color', '#dba617'); }
    else { \$c.css('color', '#666'); }
}

function checkEmpty(\$row){
    var content = $.trim(\$row.find('.moe-question-input').val());
    if (!content){ \$row.addClass('moe-empty'); }
    else { \$row.removeClass('moe-empty'); }
}

ready(init);
})(jQuery);
JS
        );

        // 優化後的內嵌 CSS
        $style_handle = "moelog-aiqna-metabox-style";
        wp_register_style($style_handle, false, [], "1.0.0");
        wp_enqueue_style($style_handle);
        wp_add_inline_style(
            $style_handle,
            <<<CSS
.moelog-aiqna-metabox { margin:-6px -12px -12px; padding:15px; }

/* 問題行容器 */
#moelog-aiqna-rows .moe-question-row{
    display:flex; align-items:flex-start; gap:8px; margin-bottom:12px; padding:10px;
    background:#fff; border:1px solid #ddd; border-radius:4px; transition:all .2s;
}
#moelog-aiqna-rows .moe-question-row:hover{ border-color:#2271b1; box-shadow:0 0 0 1px #2271b1; }
#moelog-aiqna-rows .moe-question-row.moe-empty{ background:#f9f9f9; border-style:dashed; }

/* 把手、編號 */
#moelog-aiqna-rows .moe-drag-handle{ 
    flex-shrink:0; width:20px; color:#999; cursor:move; 
    display:flex; align-items:center; padding-top:4px; 
}
#moelog-aiqna-rows .moe-drag-handle:hover{ color:#2271b1; }
#moelog-aiqna-rows .moe-drag-handle .dashicons{ font-size:18px; width:18px; height:18px; }
#moelog-aiqna-rows .moe-question-number{ 
    flex-shrink:0; width:25px; font-weight:600; color:#2271b1; padding-top:6px; 
}

/* 輸入欄位:關鍵修正 - 讓它真正佔據主要空間 */
#moelog-aiqna-rows .moe-question-input{
    flex: 1 1 auto;           /* 自動增長,佔據剩餘空間 */
    min-width: 300px;         /* 最小寬度 300px */
    min-height: 20px;        /* 最小高度 */
    padding: 8px 12px;
    border: 1px solid #ddd; 
    border-radius: 3px; 
    font-size: 14px; 
    line-height: 1.6; 
    resize: vertical;         /* 只允許垂直調整大小 */
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
}
#moelog-aiqna-rows .moe-question-input:focus{ 
    border-color: #2271b1; 
    box-shadow: 0 0 0 1px #2271b1; 
    outline: none; 
}

/* 計數器 */
#moelog-aiqna-rows .moe-char-count{ 
    flex-shrink: 0; 
    font-size: 12px; 
    color: #666; 
    padding-top: 8px; 
    min-width: 65px; 
    text-align: right; 
    align-self: flex-start;
}
#moelog-aiqna-rows .moe-char-count .current{ font-weight: 600; }

/* 語言選擇 */
#moelog-aiqna-rows .moe-lang-select{ 
    flex-shrink: 0; 
    min-width: 130px; 
    height: 34px;
    align-self: flex-start;
}

/* 刪除按鈕 */
#moelog-aiqna-rows .moe-remove-btn{ 
    flex-shrink: 0; 
    height: 34px; 
    padding: 0 10px; 
    border-color: #d63638; 
    color: #d63638;
    align-self: flex-start;
}
#moelog-aiqna-rows .moe-remove-btn:hover{ 
    background: #d63638; 
    border-color: #d63638; 
    color: #fff; 
}
#moelog-aiqna-rows .moe-remove-btn .dashicons{ 
    font-size: 16px; 
    width: 16px; 
    height: 16px; 
    line-height: 1.3; 
}

/* 排序佔位符 */
#moelog-aiqna-rows .moe-sortable-placeholder{
    height: 100px; 
    background: #f0f6fc; 
    border: 2px dashed #2271b1; 
    border-radius: 4px; 
    margin-bottom: 12px;
}

/* 狀態通知區塊樣式 */
.moelog-aiqna-status-notice {
    margin: 15px 0;
    padding: 12px;
    border-left: 4px solid;
    border-radius: 4px;
    background: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
}
.moelog-aiqna-status-notice.info {
    border-left-color: #2271b1;
    background: #f0f6fc;
}
.moelog-aiqna-status-notice.success {
    border-left-color: #00a32a;
    background: #f0f6f0;
}
.moelog-aiqna-status-notice.warning {
    border-left-color: #dba617;
    background: #fcf9e8;
}
.moelog-aiqna-status-notice .dashicons {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    font-size: 20px;
}
.moelog-aiqna-status-notice p {
    margin: 0;
    flex: 1;
}

/* 響應式設計 - 中等螢幕 */
@media (max-width: 1400px) {
    #moelog-aiqna-rows .moe-question-input{
        min-width: 250px;  /* 縮小最小寬度 */
    }
}

/* 響應式設計 - 小螢幕 */
@media (max-width: 1024px) {
    #moelog-aiqna-rows .moe-question-row{
        flex-wrap: wrap;   /* 允許換行 */
    }
    
    #moelog-aiqna-rows .moe-question-input{
        flex: 1 1 100%;    /* 佔滿整行 */
        min-width: 0;      /* 取消最小寬度限制 */
        width: 100%;
        order: 1;
    }
    
    #moelog-aiqna-rows .moe-drag-handle{ order: 0; }
    #moelog-aiqna-rows .moe-question-number{ order: 0; }
    #moelog-aiqna-rows .moe-char-count{ 
        order: 2; 
        flex-basis: 100%; 
        text-align: left; 
        padding-top: 4px; 
    }
    #moelog-aiqna-rows .moe-lang-select{ 
        order: 3; 
        flex: 1 1 auto;
        min-width: 110px;
    }
    #moelog-aiqna-rows .moe-remove-btn{ 
        order: 4;
    }
}

/* 響應式設計 - 手機 */
@media (max-width: 782px) {
    #moelog-aiqna-rows .moe-question-input{
        min-height: 120px;  /* 手機上更高 */
    }
}
CSS
        );
    }

    /** 新增 Meta Box */
    public function add_metabox()
    {
        $post_types = apply_filters("moelog_aiqna_post_types", [
            "post",
            "page",
        ]);
        foreach ($post_types as $post_type) {
            add_meta_box(
                "moelog_aiqna_box",
                '<span class="dashicons dashicons-admin-comments" style="line-height:1.4;"></span> ' .
                    esc_html__("AI 問題清單", "moelog-ai-qna"),
                [$this, "render_metabox"],
                $post_type,
                "normal",
                "default"
            );
        }
    }

    /** 渲染 Meta Box(僅 HTML,JS/CSS 由 enqueue 掛載) */
    public function render_metabox($post)
    {
        $questions = $this->get_questions($post->ID);
        $langs = $this->get_languages($post->ID);

        wp_nonce_field("moelog_aiqna_save", "moelog_aiqna_nonce");
        ?>
        <div id="moelog-aiqna-metabox" class="moelog-aiqna-metabox">

            <div class="moelog-aiqna-description" style="margin-bottom:15px;padding:10px;background:#f0f6fc;border-left:4px solid #2271b1;">
                <p style="margin:5px 0;"><strong><?php esc_html_e(
                    "使用說明:",
                    "moelog-ai-qna"
                ); ?></strong></p>
                <ul style="margin:5px 0 5px 20px;">
                    <li><?php esc_html_e(
                        "每行輸入一個問題,建議 3-8 題",
                        "moelog-ai-qna"
                    ); ?></li>
                    <li><?php esc_html_e(
                        "每題最多 200 字元",
                        "moelog-ai-qna"
                    ); ?></li>
                    <li><?php esc_html_e(
                        "選擇語言或使用「自動偵測」",
                        "moelog-ai-qna"
                    ); ?></li>
                    <li><?php esc_html_e(
                        "發布後會在文章底部顯示問題清單",
                        "moelog-ai-qna"
                    ); ?></li>
                </ul>
                <p style="margin:5px 0;">
                    <strong style="color:#2271b1;">💡 <?php esc_html_e(
                        '使用 [moelog_aiqna index="1"] 可單獨插入第 1 題',
                        "moelog-ai-qna"
                    ); ?></strong>
                </p>
            </div>

            <div id="moelog-aiqna-rows">
                <?php $this->render_question_rows($questions, $langs); ?>
            </div>

            <?php if (
                get_post_status($post) === "publish" &&
                !empty($questions)
            ): ?>
                <?php
                $pending_tasks = $this->get_pending_count($post->ID);
                $cached_count = $this->get_cached_count($post->ID);
                $total_count = count($questions);
                ?>
                
                <?php if ($pending_tasks > 0): ?>
                    <div class="moelog-aiqna-status-notice info">
                        <span class="dashicons dashicons-clock" style="color:#2271b1;"></span>
                        <p>
                            <strong><?php esc_html_e(
                                "背景預生成進度:",
                                "moelog-ai-qna"
                            ); ?></strong>
                            <?php printf(
                                esc_html__(
                                    "正在處理 %d 個問題 (已完成 %d/%d)",
                                    "moelog-ai-qna"
                                ),
                                $pending_tasks,
                                $cached_count,
                                $total_count
                            ); ?>
                        </p>
                    </div>
                <?php elseif ($cached_count === $total_count): ?>
                    <div class="moelog-aiqna-status-notice success">
                        <span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span>
                        <p>
                            <strong><?php esc_html_e(
                                "所有問題已預生成完成!",
                                "moelog-ai-qna"
                            ); ?></strong>
                            <?php printf(
                                esc_html__(
                                    "共 %d 個答案已快取",
                                    "moelog-ai-qna"
                                ),
                                $cached_count
                            ); ?>
                        </p>
                    </div>
                <?php elseif ($cached_count > 0): ?>
                    <div class="moelog-aiqna-status-notice warning">
                        <span class="dashicons dashicons-info" style="color:#dba617;"></span>
                        <p>
                            <?php printf(
                                esc_html__(
                                    "已快取 %d/%d 個答案",
                                    "moelog-ai-qna"
                                ),
                                $cached_count,
                                $total_count
                            ); ?>
                            <button type="button" class="button button-small" id="moelog-aiqna-regenerate" style="margin-left:10px;">
                                <?php esc_html_e(
                                    "重新生成全部",
                                    "moelog-ai-qna"
                                ); ?>
                            </button>
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div style="margin-top:10px;">
                <button type="button" id="moelog-aiqna-add-btn" class="button" <?php if (
                    count($questions) >= self::MAX_QUESTIONS
                ) {
                    echo "disabled";
                } ?>>
                    <span class="dashicons dashicons-plus-alt" style="line-height:1.3;"></span>
                    <?php esc_html_e("新增問題", "moelog-ai-qna"); ?>
                </button>
                <span id="moelog-aiqna-counter" style="margin-left:10px;color:#666;">
                    <?php printf(
                        esc_html__(
                            "已設定 %d 題 / 最多 %d 題",
                            "moelog-ai-qna"
                        ),
                        count($questions),
                        self::MAX_QUESTIONS
                    ); ?>
                </span>
            </div>

            <div style="margin-top:15px;padding-top:15px;border-top:1px solid #ddd;">
                <button type="button" id="moelog-aiqna-clear-all" class="button">
                    <span class="dashicons dashicons-trash" style="line-height:1.3;"></span>
                    <?php esc_html_e("清空所有問題", "moelog-ai-qna"); ?>
                </button>
                <button type="button" id="moelog-aiqna-sort-btn" class="button" style="margin-left:5px;">
                    <span class="dashicons dashicons-sort" style="line-height:1.3;"></span>
                    <?php esc_html_e("重新排序", "moelog-ai-qna"); ?>
                </button>
                <span style="margin-left:10px;color:#666;font-size:0.9em;"><?php esc_html_e(
                    "提示: 可以拖曳調整順序",
                    "moelog-ai-qna"
                ); ?></span>
            </div>

            <?php if (get_post_status($post) === "publish"): ?>
                <div style="margin-top:15px;padding-top:15px;border-top:1px solid #ddd;">
                    <h4 style="margin:0 0 10px;">
                        <span class="dashicons dashicons-visibility" style="line-height:1.3;"></span>
                        <?php esc_html_e("前台預覽", "moelog-ai-qna"); ?>
                    </h4>
                    <a href="<?php echo esc_url(
                        get_permalink($post)
                    ); ?>" target="_blank" class="button button-secondary">
                        <?php esc_html_e("查看文章", "moelog-ai-qna"); ?>
                    </a>
                    <?php if (!empty($questions)): ?>
                        <span style="margin-left:10px;color:#666;"><?php esc_html_e(
                            "問題清單會顯示在文章底部",
                            "moelog-ai-qna"
                        ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }

    /** 渲染問題行群 */
    private function render_question_rows($questions, $langs)
    {
        if (empty($questions)) {
            $this->render_single_row("", "auto", 0);
            return;
        }
        foreach ($questions as $index => $question) {
            $lang = isset($langs[$index]) ? $langs[$index] : "auto";
            $this->render_single_row($question, $lang, $index);
        }
    }

    /** 單一行 */
    private function render_single_row(
        $question = "",
        $lang = "auto",
        $index = 0
    ) {
        $question = esc_textarea($question);
        $row_class = empty($question)
            ? "moe-question-row moe-empty"
            : "moe-question-row";
        ?>
        <div class="<?php echo esc_attr(
            $row_class
        ); ?>" data-index="<?php echo esc_attr($index); ?>">
            <span class="moe-drag-handle" title="<?php esc_attr_e(
                "拖曳排序",
                "moelog-ai-qna"
            ); ?>">
                <span class="dashicons dashicons-menu"></span>
            </span>

            <span class="moe-question-number"><?php echo esc_html(
                $index + 1
            ); ?>.</span>

            <textarea name="moelog_aiqna_questions[]" class="moe-question-input" rows="1"
                maxlength="<?php echo esc_attr(self::MAX_QUESTION_LENGTH); ?>"
                placeholder="<?php esc_attr_e(
                    "例如: 為何科技新創偏好使用「.io」?",
                    "moelog-ai-qna"
                ); ?>"
                data-index="<?php echo esc_attr(
                    $index
                ); ?>"><?php echo $question; ?></textarea>

            <span class="moe-char-count">
                <span class="current"><?php echo esc_html(
                    mb_strlen($question, "UTF-8")
                ); ?></span>
                / <?php echo esc_html(self::MAX_QUESTION_LENGTH); ?>
            </span>

            <select name="moelog_aiqna_langs[]" class="moe-lang-select">
                <option value="auto" <?php selected(
                    $lang,
                    "auto"
                ); ?>><?php esc_html_e(
    "🌐 自動偵測",
    "moelog-ai-qna"
); ?></option>
                <option value="zh"   <?php selected(
                    $lang,
                    "zh"
                ); ?>><?php esc_html_e(
    "🇹🇼 繁體中文",
    "moelog-ai-qna"
); ?></option>
                <option value="ja"   <?php selected(
                    $lang,
                    "ja"
                ); ?>><?php esc_html_e("🇯🇵 日文", "moelog-ai-qna"); ?></option>
                <option value="en"   <?php selected(
                    $lang,
                    "en"
                ); ?>><?php esc_html_e("🇺🇸 英文", "moelog-ai-qna"); ?></option>
            </select>

            <button type="button" class="button moe-remove-btn" title="<?php esc_attr_e(
                "刪除此問題",
                "moelog-ai-qna"
            ); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
        <?php
    }

    /**
     * 儲存
     * (✅ 1.8.4+ 優化: 僅在問題列表變更時才清除快取並觸發預生成)
     */
    public function save_metabox($post_id, $post)
    {
        // --- 1. 標準安全檢查 ---
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can("edit_post", $post_id)) {
            return;
        }
        if (!isset($_POST["moelog_aiqna_nonce"])) {
            return;
        }
        if (
            !wp_verify_nonce($_POST["moelog_aiqna_nonce"], "moelog_aiqna_save")
        ) {
            return;
        }

        // --- 2. 取得「舊」資料 (用於比對) ---
        $old_questions = $this->get_questions($post_id);
        $old_langs = $this->get_languages($post_id);

        // --- 3. 取得並清理「新」資料 ---
        $new_questions_raw = isset($_POST["moelog_aiqna_questions"])
            ? (array) $_POST["moelog_aiqna_questions"]
            : [];
        $new_langs_raw = isset($_POST["moelog_aiqna_langs"])
            ? (array) $_POST["moelog_aiqna_langs"]
            : [];

        $clean_q = [];
        $clean_l = [];
        foreach ($new_questions_raw as $i => $q) {
            $q = trim(wp_unslash($q));
            if ($q === "") {
                continue;
            }

            if (function_exists("mb_substr")) {
                $q = mb_substr($q, 0, self::MAX_QUESTION_LENGTH, "UTF-8");
            } else {
                $q = substr($q, 0, self::MAX_QUESTION_LENGTH);
            }

            $lg = isset($new_langs_raw[$i]) ? $new_langs_raw[$i] : "auto";
            if (
                function_exists("moelog_aiqna_is_valid_language") &&
                !moelog_aiqna_is_valid_language($lg)
            ) {
                $lg = "auto";
            }

            $clean_q[] = $q;
            $clean_l[] = $lg;
        }

        $clean_q = array_slice($clean_q, 0, self::MAX_QUESTIONS);
        $clean_l = array_slice($clean_l, 0, self::MAX_QUESTIONS);

        // --- 4. 智慧比對 (核心) ---
        $questions_changed = $clean_q !== $old_questions;
        $langs_changed = $clean_l !== $old_langs;

        if (!$questions_changed && !$langs_changed) {
            // ✅ 問題和語言都沒變?
            // 不儲存、不清除快取、不預生成。直接返回。
            return;
        }

        // --- 5. (僅在有變更時) 執行儲存與快取操作 ---
        if (Moelog_AIQnA_Debug::is_enabled()) {
            moelog_aiqna_log(
                sprintf(
                    "Change detected for post %d. Updating questions and clearing cache.",
                    $post_id
                )
            );
        }

        if (!empty($clean_q)) {
            // 儲存 (推薦使用陣列格式, 你的 get_questions 函式可以處理)
            update_post_meta($post_id, MOELOG_AIQNA_META_KEY, $clean_q);
            update_post_meta($post_id, MOELOG_AIQNA_META_LANG_KEY, $clean_l);
            
            // ✅ 清除相關快取
            Moelog_AIQnA_Meta_Cache::clear($post_id, MOELOG_AIQNA_META_KEY);
            Moelog_AIQnA_Meta_Cache::clear($post_id, MOELOG_AIQNA_META_LANG_KEY);
        } else {
            // 清空
            delete_post_meta($post_id, MOELOG_AIQNA_META_KEY);
            delete_post_meta($post_id, MOELOG_AIQNA_META_LANG_KEY);
        }

        // 清除快取
        //if (class_exists("Moelog_AIQnA_Cache")) {
        //     Moelog_AIQnA_Cache::delete($post_id);
        // }

        // 觸發預生成 (與 AJAX 邏輯保持一致)
        global $moelog_aiqna_instance;
        if (
            $moelog_aiqna_instance &&
            isset($moelog_aiqna_instance->pregenerate) &&
            method_exists(
                $moelog_aiqna_instance->pregenerate,
                "batch_pregenerate"
            )
        ) {
            $moelog_aiqna_instance->pregenerate->batch_pregenerate($post_id);
        }
    }

    /** 取得問題列表 */
    private function get_questions($post_id)
    {
        $raw = Moelog_AIQnA_Meta_Cache::get($post_id, MOELOG_AIQNA_META_KEY, true);
        if (function_exists("moelog_aiqna_parse_questions")) {
            return moelog_aiqna_parse_questions($raw);
        }
        if (empty($raw)) {
            return [];
        }
        return array_filter(array_map("trim", explode("\n", $raw)));
    }

    /** 取得語言列表 */
    private function get_languages($post_id)
    {
        $langs = get_post_meta($post_id, MOELOG_AIQNA_META_LANG_KEY, true);
        return is_array($langs) ? $langs : [];
    }

    /** 問題數量 */
    public function get_question_count($post_id)
    {
        return count($this->get_questions($post_id));
    }

    /** 是否有問題 */
    public function has_questions($post_id)
    {
        return $this->get_question_count($post_id) > 0;
    }

    /**
     * 取得待處理的預生成任務數量
     *
     * @param int $post_id 文章 ID
     * @return int
     */
    private function get_pending_count($post_id)
    {
        $questions = $this->get_questions($post_id);
        if (empty($questions)) {
            return 0;
        }

        $pending = 0;

        // 檢查排程任務
        $crons = _get_cron_array();
        if (!is_array($crons)) {
            return 0;
        }

        foreach ($crons as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                if ($hook === "moelog_aiqna_pregenerate") {
                    foreach ($events as $event) {
                        $args = $event["args"];
                        if (isset($args[0]) && $args[0] == $post_id) {
                            $pending++;
                        }
                    }
                }
            }
        }

        return $pending;
    }

    /**
     * 取得已快取的問題數量
     *
     * @param int $post_id 文章 ID
     * @return int
     */
    private function get_cached_count($post_id)
    {
        if (!class_exists("Moelog_AIQnA_Cache")) {
            return 0;
        }

        $questions = $this->get_questions($post_id);
        if (empty($questions)) {
            return 0;
        }

        $cached = 0;
        foreach ($questions as $question) {
            if (Moelog_AIQnA_Cache::exists($post_id, $question)) {
                $cached++;
            }
        }

        return $cached;
    }

    /**
     * AJAX 處理:重新生成所有答案
     */
    public function ajax_regenerate()
    {
        check_ajax_referer("moelog_aiqna_save", "nonce");

        $post_id = isset($_POST["post_id"]) ? intval($_POST["post_id"]) : 0;

        if (!$post_id || !current_user_can("edit_post", $post_id)) {
            wp_send_json_error("無權限");
        }

        // 清除快取
        if (class_exists("Moelog_AIQnA_Cache")) {
            Moelog_AIQnA_Cache::delete($post_id);
        }

        // 觸發預生成
        global $moelog_aiqna_instance;
        if (
            $moelog_aiqna_instance &&
            isset($moelog_aiqna_instance->pregenerate)
        ) {
            $result = $moelog_aiqna_instance->pregenerate->batch_pregenerate(
                $post_id
            );
            wp_send_json_success($result);
        }

        wp_send_json_error("預生成類別未初始化");
    }
}
