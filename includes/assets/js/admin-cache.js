/**
 * Moelog AI Q&A - Admin Cache Management
 *
 * 快取管理頁面 JavaScript
 * 從 class-admin-cache.php 分離出來，提升可維護性
 *
 * @package Moelog_AIQnA
 * @since   1.10.3
 */

(function($) {
    'use strict';

    // 確保 jQuery 已載入
    if (typeof jQuery === 'undefined') {
        console.error('Moelog AI Q&A: jQuery is not loaded');
        return;
    }

    // 取得本地化設定
    var config = window.moelogAiqnaCacheConfig || {};
    var i18n = config.i18n || {};
    var ajaxUrl = config.ajaxUrl || '';
    var nonce = config.nonce || '';

    /**
     * 快取管理模組
     */
    var CacheManager = {
        /**
         * DOM 元素快取
         */
        $elements: {},

        /**
         * 初始化
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
        },

        /**
         * 快取 DOM 元素
         */
        cacheElements: function() {
            this.$elements = {
                postId: $('#clear_post_id'),
                loadBtn: $('#load-questions-btn'),
                questionSelect: $('#clear_question_select'),
                questionInput: $('#clear_question'),
                loading: $('#questions-loading'),
                error: $('#questions-error'),
                postTitle: $('#post-title-display'),
                form: $('#moelog-clear-single-form')
            };
        },

        /**
         * 綁定事件
         */
        bindEvents: function() {
            var self = this;

            // 載入問題列表按鈕
            this.$elements.loadBtn.on('click', function() {
                self.loadQuestions();
            });

            // 問題選擇下拉選單變更
            this.$elements.questionSelect.on('change', function() {
                self.onQuestionSelect($(this).val());
            });

            // 手動輸入問題時清空下拉選單選擇
            this.$elements.questionInput.on('input', function() {
                if ($(this).val() !== self.$elements.questionSelect.val()) {
                    self.$elements.questionSelect.val('');
                }
            });

            // 表單提交驗證
            this.$elements.form.on('submit', function(e) {
                return self.validateForm(e);
            });
        },

        /**
         * 載入問題列表
         */
        loadQuestions: function() {
            var self = this;
            var postId = parseInt(this.$elements.postId.val());

            // 驗證 Post ID
            if (!postId || postId < 1) {
                alert(i18n.invalidPostId || '請輸入有效的文章 ID');
                return;
            }

            // 顯示載入狀態
            this.showLoading(true);
            this.hideError();
            this.$elements.questionSelect.hide().empty();
            this.$elements.postTitle.text('');

            // 發送 AJAX 請求
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'moelog_aiqna_get_questions',
                    post_id: postId,
                    nonce: nonce
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                },
                success: function(response) {
                    self.showLoading(false);

                    if (response.success && response.data.questions) {
                        self.populateQuestions(
                            response.data.questions,
                            response.data.post_title || ''
                        );
                    } else {
                        self.showError(response.data.message || i18n.loadFailed || '載入失敗');
                    }
                },
                error: function(xhr, status, error) {
                    self.showLoading(false);
                    self.handleAjaxError(xhr, status, error);
                }
            });
        },

        /**
         * 填充問題下拉選單
         *
         * @param {Array}  questions 問題列表
         * @param {string} postTitle 文章標題
         */
        populateQuestions: function(questions, postTitle) {
            var self = this;
            var $select = this.$elements.questionSelect;

            // 顯示文章標題
            if (postTitle) {
                this.$elements.postTitle.text('《' + postTitle + '》');
            }

            // 清空並填充下拉選單
            $select.empty();
            $select.append(
                $('<option></option>')
                    .attr('value', '')
                    .text(i18n.selectQuestion || '請選擇問題')
            );

            questions.forEach(function(question, index) {
                var displayText = question.length > 60
                    ? question.substring(0, 60) + '...'
                    : question;

                $select.append(
                    $('<option></option>')
                        .attr('value', question)
                        .text((index + 1) + '. ' + displayText)
                        .attr('title', question)
                );
            });

            $select.show();
            this.$elements.questionInput.prop('required', false);
        },

        /**
         * 問題選擇變更處理
         *
         * @param {string} selectedValue 選擇的問題值
         */
        onQuestionSelect: function(selectedValue) {
            if (selectedValue) {
                this.$elements.questionInput.val(selectedValue);
            } else {
                this.$elements.questionInput.val('');
            }
        },

        /**
         * 表單驗證
         *
         * @param {Event} e 事件物件
         * @return {boolean}
         */
        validateForm: function(e) {
            var selectedQuestion = this.$elements.questionSelect.val();
            var inputQuestion = this.$elements.questionInput.val().trim();
            var finalQuestion = selectedQuestion || inputQuestion;

            if (!finalQuestion) {
                e.preventDefault();
                alert(i18n.selectOrInputQuestion || '請選擇或輸入問題');
                return false;
            }

            // 確保提交時使用正確的問題文字
            this.$elements.questionInput.val(finalQuestion);

            // 檢查 nonce 欄位
            if (!this.$elements.form.find('input[name="moelog_aiqna_clear_single_nonce"]').length) {
                console.error('Nonce field is missing!');
                e.preventDefault();
                alert(i18n.nonceMissing || '安全驗證欄位缺失，請重新整理頁面');
                return false;
            }

            return true;
        },

        /**
         * 顯示/隱藏載入狀態
         *
         * @param {boolean} show 是否顯示
         */
        showLoading: function(show) {
            if (show) {
                this.$elements.loading.show();
            } else {
                this.$elements.loading.hide();
            }
        },

        /**
         * 顯示錯誤訊息
         *
         * @param {string} message 錯誤訊息
         */
        showError: function(message) {
            this.$elements.error.text(message).show();
        },

        /**
         * 隱藏錯誤訊息
         */
        hideError: function() {
            this.$elements.error.hide();
        },

        /**
         * 處理 AJAX 錯誤
         *
         * @param {Object} xhr    XMLHttpRequest 物件
         * @param {string} status 狀態
         * @param {string} error  錯誤訊息
         */
        handleAjaxError: function(xhr, status, error) {
            var errorMsg = i18n.ajaxFailed || 'AJAX 請求失敗，請稍後再試';

            // WordPress 返回 -1 或 0 表示 nonce 驗證失敗
            if ((xhr.status === 400 || xhr.status === 403) &&
                (xhr.responseText === '-1' || xhr.responseText === '0')) {
                errorMsg = i18n.securityFailed || '安全驗證失敗，請重新整理頁面後再試';
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
                    } catch (e) {
                        // 忽略解析錯誤
                    }
                }
            }

            this.showError(errorMsg);

            // 開發模式下輸出詳細錯誤
            if (typeof console !== 'undefined' && console.error) {
                console.error('AJAX Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
            }
        }
    };

    // DOM 就緒時初始化
    $(document).ready(function() {
        // 只在快取管理頁面初始化
        if ($('#moelog-clear-single-form').length) {
            CacheManager.init();
        }
    });

})(jQuery);
