/**
 * Moelog AI Q&A - Admin Settings
 *
 * 設定頁面 JavaScript
 * 從 class-admin.php 分離出來，提升可維護性
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
    var config = window.moelogAiqnaSettingsConfig || {};
    var i18n = config.i18n || {};
    var ajaxUrl = config.ajaxUrl || '';
    var nonce = config.nonce || '';

    /**
     * 設定管理模組
     */
    var SettingsManager = {
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
                apiKeyInput: $('#api_key'),
                toggleBtn: $('#toggle-api-key'),
                testBtn: $('#test-api-key'),
                testResult: $('#test-result'),
                providerSelect: $('#provider'),
                modelInput: $('#model')
            };
        },

        /**
         * 綁定事件
         */
        bindEvents: function() {
            var self = this;

            // API Key 顯示/隱藏切換
            this.$elements.toggleBtn.on('click', function() {
                self.toggleApiKeyVisibility();
            });

            // 測試 API 連線
            this.$elements.testBtn.on('click', function() {
                self.testApiConnection();
            });
        },

        /**
         * 切換 API Key 顯示/隱藏
         */
        toggleApiKeyVisibility: function() {
            var $input = this.$elements.apiKeyInput;
            var $btn = this.$elements.toggleBtn;

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $btn.text(i18n.hide || '隱藏');
            } else {
                $input.attr('type', 'password');
                $btn.text(i18n.show || '顯示');
            }
        },

        /**
         * 測試 API 連線
         */
        testApiConnection: function() {
            var self = this;
            var $btn = this.$elements.testBtn;
            var $result = this.$elements.testResult;
            var provider = this.$elements.providerSelect.val();
            var apiKey = this.$elements.apiKeyInput.val();
            var model = this.$elements.modelInput.val();

            // 驗證 API Key
            if (!apiKey || (apiKey !== 'from_constant' && (apiKey === '' || apiKey === '********************'))) {
                $result.html('<span style="color:red;">✗ ' + (i18n.enterApiKey || '請先輸入 API Key') + '</span>');
                return;
            }

            // 禁用按鈕並顯示測試中狀態
            $btn.prop('disabled', true).text(i18n.testing || '測試中...');
            $result.html('<span style="color:#999;">⏳ ' + (i18n.connecting || '連線中...') + '</span>');

            // 發送測試請求
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'moelog_aiqna_test_api',
                    nonce: nonce,
                    provider: provider,
                    api_key: apiKey,
                    model: model
                },
                timeout: 30000,
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                    } else {
                        $result.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<span style="color:red;">✗ ' + (i18n.requestFailed || '請求失敗') + ': ' + error + '</span>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(i18n.testConnection || '測試連線');
                }
            });
        }
    };

    // DOM 就緒時初始化
    $(document).ready(function() {
        // 只在設定頁面初始化（檢查是否存在相關元素）
        if ($('#api_key').length || $('#test-api-key').length) {
            SettingsManager.init();
        }
    });

})(jQuery);
