<?php
/**
 * Moelog AI Q&A Debug Helper Class
 *
 * 統一的 Debug 輔助方法，減少重複代碼
 *
 * @package Moelog_AIQnA
 * @since   1.8.4
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Debug
{
    /**
     * 檢查是否啟用 Debug 模式
     *
     * @return bool
     */
    public static function is_enabled()
    {
        return defined("WP_DEBUG") && WP_DEBUG;
    }

    /**
     * 記錄日誌訊息（僅在 Debug 模式下）
     *
     * @param string $message 日誌訊息
     * @param array  $context 額外上下文（可選）
     * @return void
     */
    public static function log($message, $context = [])
    {
        if (!self::is_enabled()) {
            return;
        }

        $prefix = "[Moelog AIQnA]";
        
        if (!empty($context)) {
            $message .= " | Context: " . wp_json_encode($context);
        }

        error_log($prefix . " " . $message);
    }

    /**
     * 記錄格式化日誌訊息
     *
     * @param string $format 格式化字串（使用 sprintf 格式）
     * @param mixed  ...$args 參數
     * @return void
     */
    public static function logf($format, ...$args)
    {
        if (!self::is_enabled()) {
            return;
        }

        $message = vsprintf($format, $args);
        self::log($message);
    }

    /**
     * 記錄錯誤訊息
     *
     * @param string $message 錯誤訊息
     * @param mixed  $error   錯誤物件或額外資訊
     * @return void
     */
    public static function log_error($message, $error = null)
    {
        if (!self::is_enabled()) {
            return;
        }

        $context = [];
        
        if ($error instanceof WP_Error) {
            $context = [
                "error_code" => $error->get_error_code(),
                "error_message" => $error->get_error_message(),
                "error_data" => $error->get_error_data(),
            ];
        } elseif ($error !== null) {
            $context = ["error" => $error];
        }

        self::log("ERROR: " . $message, $context);
    }

    /**
     * 記錄警告訊息
     *
     * @param string $message 警告訊息
     * @param array  $context 額外上下文
     * @return void
     */
    public static function log_warning($message, $context = [])
    {
        if (!self::is_enabled()) {
            return;
        }

        self::log("WARNING: " . $message, $context);
    }

    /**
     * 記錄資訊訊息
     *
     * @param string $message 資訊訊息
     * @param array  $context 額外上下文
     * @return void
     */
    public static function log_info($message, $context = [])
    {
        if (!self::is_enabled()) {
            return;
        }

        self::log("INFO: " . $message, $context);
    }

    /**
     * 條件執行（僅在 Debug 模式下）
     *
     * @param callable $callback 回調函數
     * @return mixed|null
     */
    public static function if_enabled($callback)
    {
        if (!self::is_enabled()) {
            return null;
        }

        if (is_callable($callback)) {
            return call_user_func($callback);
        }

        return null;
    }
}

