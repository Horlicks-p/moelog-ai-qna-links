<?php
/**
 * Moelog AI Q&A Helper Utilities
 *
 * 提供通用的輔助函數:
 * - 語言偵測
 * - 文字處理
 * - 時間格式化
 * - URL 處理
 * - 資料驗證
 *
 * @package Moelog_AIQnA
 * @since   1.8.3
 */

if (!defined("ABSPATH")) {
    exit();
}

// =========================================
// 語言偵測
// =========================================

/**
 * 偵測文字語言
 *
 * @param string $text 要偵測的文字
 * @return string 語言代碼 (ja|zh|en)
 */
function moelog_aiqna_detect_language($text)
{
    $text = trim((string) $text);

    if (empty($text)) {
        return "en";
    }

    // 策略 1: 偵測日文字符
    // 平假名、片假名、半形片假名
    if (
        preg_match(
            "/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{31F0}-\x{31FF}\x{FF66}-\x{FF9F}]/u",
            $text
        )
    ) {
        return "ja";
    }

    // 策略 2: 偵測中文字符
    // 漢字、全形標點
    if (preg_match("/[\p{Han}\x{3000}-\x{303F}\x{FF01}-\x{FF5E}]/u", $text)) {
        // 計算漢字與拉丁字母比例
        $han_count = preg_match_all("/\p{Han}/u", $text, $matches);
        $latin_count = preg_match_all("/[A-Za-z]/u", $text, $matches);

        // 漢字數量 > 0 且 拉丁字母 < 漢字數量的一半
        if ($han_count > 0 && $latin_count < max(1, $han_count / 2)) {
            return "zh";
        }
    }

    // 策略 3: 純英文判定
    // 只包含英文字母、數字、常見標點
    if (preg_match('/^[A-Za-z0-9\s.,!?\-\'\"()]+$/u', $text)) {
        return "en";
    }

    // 策略 4: 使用 PHP 的編碼偵測(如果可用)
    if (function_exists("mb_detect_encoding")) {
        $encoding = mb_detect_encoding(
            $text,
            ["UTF-8", "EUC-JP", "SJIS", "ISO-8859-1"],
            true
        );

        if ($encoding === "EUC-JP" || $encoding === "SJIS") {
            return "ja";
        }

        if ($encoding === "UTF-8" && preg_match("/\p{Han}/u", $text)) {
            return "zh";
        }
    }

    // 預設返回英文
    return "en";
}

// =========================================
// 文字處理
// =========================================

/**
 * 安全截斷文字(支援多位元組字符)
 *
 * @param string $text      原始文字
 * @param int    $max_chars 最大字符數
 * @param string $suffix    截斷後的後綴
 * @return string
 */
function moelog_aiqna_truncate($text, $max_chars = 100, $suffix = "...")
{
    $text = trim((string) $text);

    if (empty($text)) {
        return "";
    }

    if (function_exists("mb_strlen")) {
        if (mb_strlen($text, "UTF-8") <= $max_chars) {
            return $text;
        }
        return mb_substr($text, 0, $max_chars, "UTF-8") . $suffix;
    } else {
        if (strlen($text) <= $max_chars) {
            return $text;
        }
        return substr($text, 0, $max_chars) . $suffix;
    }
}

/**
 * 清理文字(移除多餘空白、換行)
 *
 * @param string $text 原始文字
 * @return string
 */
function moelog_aiqna_clean_text($text)
{
    $text = trim((string) $text);

    // 將多個空白替換為單一空白
    $text = preg_replace("/\s+/u", " ", $text);

    // 移除不可見字符
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', "", $text);

    return $text;
}

/**
 * 移除 HTML 標籤並清理文字
 *
 * @param string $html HTML 內容
 * @return string
 */
function moelog_aiqna_strip_html($html)
{
    // 移除 script 和 style 標籤及內容
    $html = preg_replace("/<script\b[^>]*>(.*?)<\/script>/is", "", $html);
    $html = preg_replace("/<style\b[^>]*>(.*?)<\/style>/is", "", $html);

    // 移除所有 HTML 標籤
    $text = wp_strip_all_tags($html);

    // 清理文字
    return moelog_aiqna_clean_text($text);
}

/**
 * 將換行符號統一化
 *
 * @param string $text 原始文字
 * @return string
 */
function moelog_aiqna_normalize_newlines($text)
{
    // 將 \r\n 和 \r 統一為 \n
    return str_replace(["\r\n", "\r"], "\n", $text);
}

/**
 * 高亮關鍵字
 *
 * @param string $text    原始文字
 * @param string $keyword 要高亮的關鍵字
 * @param string $tag     包裹標籤
 * @return string
 */
function moelog_aiqna_highlight_keyword($text, $keyword, $tag = "mark")
{
    if (empty($keyword)) {
        return $text;
    }

    $escaped_keyword = preg_quote($keyword, "/");
    $pattern = "/(" . $escaped_keyword . ")/ui";

    return preg_replace($pattern, "<{$tag}>$1</{$tag}>", $text);
}

// =========================================
// 陣列與資料處理
// =========================================

/**
 * 解析問題列表(支援字串或陣列)
 *
 * @param mixed $raw 原始資料
 * @return array
 */
function moelog_aiqna_parse_questions($raw)
{
    // 已經是陣列
    if (is_array($raw)) {
        $questions = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $q = trim($item);
                if ($q !== "") {
                    $questions[] = $q;
                }
            } elseif (is_array($item) && isset($item["q"])) {
                $q = trim((string) $item["q"]);
                if ($q !== "") {
                    $questions[] = $q;
                }
            }
        }
        return array_slice($questions, 0, 8);
    }

    // 字串格式(每行一題)
    if (is_string($raw)) {
        $lines = preg_split('/\r\n|\n|\r/', $raw);
        $questions = array_filter(array_map("trim", $lines));
        return array_slice(array_values($questions), 0, 8);
    }

    return [];
}

/**
 * 安全取得陣列值
 *
 * @param array  $array   陣列
 * @param string $key     鍵值
 * @param mixed  $default 預設值
 * @return mixed
 */
function moelog_aiqna_array_get($array, $key, $default = null)
{
    if (!is_array($array)) {
        return $default;
    }

    return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * 檢查陣列是否為關聯陣列
 *
 * @param array $array 陣列
 * @return bool
 */
function moelog_aiqna_is_assoc_array($array)
{
    if (!is_array($array)) {
        return false;
    }

    if (empty($array)) {
        return false;
    }

    return array_keys($array) !== range(0, count($array) - 1);
}

// =========================================
// 時間與日期
// =========================================

/**
 * 格式化相對時間(例如: 2 小時前)
 *
 * @param int $timestamp Unix 時間戳
 * @return string
 */
function moelog_aiqna_time_ago($timestamp)
{
    if (!is_numeric($timestamp)) {
        $timestamp = strtotime($timestamp);
    }

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return __("剛剛", "moelog-ai-qna");
    }

    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return sprintf(
            _n("%d 分鐘前", "%d 分鐘前", $minutes, "moelog-ai-qna"),
            $minutes
        );
    }

    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return sprintf(
            _n("%d 小時前", "%d 小時前", $hours, "moelog-ai-qna"),
            $hours
        );
    }

    if ($diff < 2592000) {
        $days = floor($diff / 86400);
        return sprintf(_n("%d 天前", "%d 天前", $days, "moelog-ai-qna"), $days);
    }

    return date_i18n(get_option("date_format"), $timestamp);
}

/**
 * 取得格式化的時間長度(例如: 2h 30m)
 *
 * @param int $seconds 秒數
 * @return string
 */
function moelog_aiqna_format_duration($seconds)
{
    $seconds = abs((int) $seconds);

    if ($seconds < 60) {
        return $seconds . "s";
    }

    if ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return $secs > 0 ? "{$minutes}m {$secs}s" : "{$minutes}m";
    }

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
}

// =========================================
// URL 與網域處理
// =========================================

/**
 * 取得乾淨的網域名稱(移除 www)
 *
 * @param string $url URL
 * @return string
 */
function moelog_aiqna_get_clean_domain($url)
{
    $host = parse_url($url, PHP_URL_HOST);

    if (!$host) {
        return "";
    }

    return preg_replace("/^www\./", "", $host);
}

/**
 * 檢查 URL 是否有效
 *
 * @param string $url URL
 * @return bool
 */
function moelog_aiqna_is_valid_url($url)
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * 檢查是否為安全的 URL(HTTP/HTTPS)
 *
 * @param string $url URL
 * @return bool
 */
function moelog_aiqna_is_safe_url($url)
{
    if (!moelog_aiqna_is_valid_url($url)) {
        return false;
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array(strtolower($scheme), ["http", "https"], true);
}

/**
 * 將 URL 轉換為可顯示的短格式
 *
 * @param string $url        URL
 * @param int    $max_length 最大長度
 * @return string
 */
function moelog_aiqna_shorten_url($url, $max_length = 50)
{
    if (strlen($url) <= $max_length) {
        return $url;
    }

    // 保留協議和網域
    $parts = parse_url($url);
    $short = ($parts["scheme"] ?? "https") . "://" . ($parts["host"] ?? "");

    // 如果還有空間,加上路徑開頭
    if (isset($parts["path"]) && strlen($short) < $max_length - 10) {
        $path = substr($parts["path"], 0, $max_length - strlen($short) - 5);
        $short .= $path . "...";
    }

    return $short;
}

// =========================================
// 檔案大小格式化
// =========================================

/**
 * 格式化檔案大小(人類可讀)
 *
 * @param int $bytes    位元組數
 * @param int $decimals 小數位數
 * @return string
 */
function moelog_aiqna_format_bytes($bytes, $decimals = 2)
{
    $bytes = abs((int) $bytes);

    if ($bytes === 0) {
        return "0 B";
    }

    $units = ["B", "KB", "MB", "GB", "TB"];
    $factor = floor(log($bytes) / log(1024));
    $factor = min($factor, count($units) - 1);

    $size = $bytes / pow(1024, $factor);

    return number_format($size, $decimals) . " " . $units[$factor];
}

// =========================================
// 數字格式化
// =========================================

/**
 * 格式化數字(加入千分位)
 *
 * @param mixed $number 數字
 * @param int   $decimals 小數位數
 * @return string
 */
function moelog_aiqna_format_number($number, $decimals = 0)
{
    if (!is_numeric($number)) {
        return "0";
    }

    return number_format((float) $number, $decimals);
}

/**
 * 格式化百分比
 *
 * @param float $value    數值
 * @param float $total    總數
 * @param int   $decimals 小數位數
 * @return string
 */
function moelog_aiqna_format_percentage($value, $total, $decimals = 1)
{
    if ($total == 0) {
        return "0%";
    }

    $percentage = ($value / $total) * 100;
    return number_format($percentage, $decimals) . "%";
}

// =========================================
// 雜湊與加密
// =========================================

/**
 * 生成短雜湊值
 *
 * @param string $data   原始資料
 * @param int    $length 長度
 * @return string
 */
function moelog_aiqna_short_hash($data, $length = 8)
{
    return substr(hash("sha256", $data), 0, $length);
}

/**
 * 生成隨機字串
 *
 * @param int  $length 長度
 * @param bool $secure 是否使用安全隨機
 * @return string
 */
function moelog_aiqna_random_string($length = 16, $secure = true)
{
    if ($secure && function_exists("random_bytes")) {
        try {
            return bin2hex(random_bytes($length / 2));
        } catch (Exception $e) {
            // 降級為非安全模式
        }
    }

    $characters =
        "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $string = "";
    $max = strlen($characters) - 1;

    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[rand(0, $max)];
    }

    return $string;
}

// =========================================
// 驗證函數
// =========================================

/**
 * 驗證 Email 地址
 *
 * @param string $email Email 地址
 * @return bool
 */
function moelog_aiqna_is_valid_email($email)
{
    return is_email($email) !== false;
}

/**
 * 驗證 IP 地址
 *
 * @param string $ip IP 地址
 * @return bool
 */
function moelog_aiqna_is_valid_ip($ip)
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * 驗證正整數
 *
 * @param mixed $value 值
 * @return bool
 */
function moelog_aiqna_is_positive_int($value)
{
    return is_numeric($value) && (int) $value > 0 && (int) $value == $value;
}

/**
 * 驗證語言代碼
 *
 * @param string $lang 語言代碼
 * @return bool
 */
function moelog_aiqna_is_valid_language($lang)
{
    $valid_langs = ["auto", "zh", "ja", "en"];
    return in_array($lang, $valid_langs, true);
}

// =========================================
// HTML 輔助
// =========================================

/**
 * 生成 HTML 屬性字串
 *
 * @param array $attributes 屬性陣列
 * @return string
 */
function moelog_aiqna_build_attributes($attributes)
{
    if (empty($attributes)) {
        return "";
    }

    $html = [];
    foreach ($attributes as $key => $value) {
        if ($value === null || $value === false) {
            continue;
        }

        if ($value === true) {
            $html[] = esc_attr($key);
        } else {
            $html[] = esc_attr($key) . '="' . esc_attr($value) . '"';
        }
    }

    return implode(" ", $html);
}

/**
 * 生成 HTML 標籤
 *
 * @param string      $tag        標籤名稱
 * @param array       $attributes 屬性
 * @param string|null $content    內容(null = 自閉合標籤)
 * @return string
 */
function moelog_aiqna_html_tag($tag, $attributes = [], $content = null)
{
    $tag = esc_attr($tag);
    $attr_string = moelog_aiqna_build_attributes($attributes);

    if ($content === null) {
        // 自閉合標籤
        return "<{$tag}" . ($attr_string ? " {$attr_string}" : "") . ">";
    }

    return "<{$tag}" .
        ($attr_string ? " {$attr_string}" : "") .
        ">{$content}</{$tag}>";
}

// =========================================
// 除錯輔助
// =========================================

/**
 * 格式化變數輸出(除錯用)
 *
 * @param mixed  $var  變數
 * @param bool   $die  是否中止執行
 * @param string $label 標籤
 * @return void
 */
function moelog_aiqna_dump($var, $die = false, $label = "")
{
    if (!defined("WP_DEBUG") || !WP_DEBUG) {
        return;
    }

    echo '<pre style="background:#f5f5f5;padding:10px;border:1px solid #ddd;margin:10px 0;overflow:auto;">';

    if ($label) {
        echo "<strong>" . esc_html($label) . ":</strong>" . "\n";
    }

    if (is_array($var) || is_object($var)) {
        print_r($var);
    } else {
        var_dump($var);
    }

    echo "</pre>";

    if ($die) {
        die();
    }
}

/**
 * 記錄除錯訊息
 *
 * @param string $message 訊息
 * @param string $level   級別 (info|warning|error)
 * @return void
 */
function moelog_aiqna_log($message, $level = "info")
{
    if (!defined("WP_DEBUG") || !WP_DEBUG) {
        return;
    }

    $prefix = "[Moelog AIQnA]";

    switch ($level) {
        case "error":
            $prefix .= " ERROR:";
            break;
        case "warning":
            $prefix .= " WARNING:";
            break;
        default:
            $prefix .= " INFO:";
    }

    error_log($prefix . " " . $message);
}

// =========================================
// 條件判斷
// =========================================

/**
 * 檢查是否為 AJAX 請求
 *
 * @return bool
 */
function moelog_aiqna_is_ajax()
{
    return defined("DOING_AJAX") && DOING_AJAX;
}

/**
 * 檢查是否為 Cron 任務
 *
 * @return bool
 */
function moelog_aiqna_is_cron()
{
    return defined("DOING_CRON") && DOING_CRON;
}

/**
 * 檢查是否為 REST API 請求
 *
 * @return bool
 */
function moelog_aiqna_is_rest()
{
    return defined("REST_REQUEST") && REST_REQUEST;
}

/**
 * 檢查當前使用者是否有管理權限
 *
 * @return bool
 */
function moelog_aiqna_is_admin_user()
{
    return current_user_can("manage_options");
}

// =========================================
// WordPress 整合輔助
// =========================================

/**
 * 取得文章類型的可讀名稱
 *
 * @param string $post_type 文章類型
 * @return string
 */
function moelog_aiqna_get_post_type_label($post_type)
{
    $post_type_obj = get_post_type_object($post_type);

    if (!$post_type_obj) {
        return $post_type;
    }

    return $post_type_obj->labels->singular_name ?? $post_type;
}

/**
 * 安全取得 Post Meta
 *
 * @param int    $post_id 文章 ID
 * @param string $key     Meta 鍵值
 * @param mixed  $default 預設值
 * @return mixed
 */
function moelog_aiqna_get_post_meta($post_id, $key, $default = "")
{
    $value = get_post_meta($post_id, $key, true);

    return $value !== "" ? $value : $default;
}

/**
 * 檢查文章是否存在且已發布
 *
 * @param int $post_id 文章 ID
 * @return bool
 */
function moelog_aiqna_is_published_post($post_id)
{
    $post = get_post($post_id);

    return $post && $post->post_status === "publish";
}

// =========================================
// 快取輔助
// =========================================

/**
 * 取得記憶體快取(使用 WordPress Object Cache)
 *
 * @param string $key   鍵值
 * @param string $group 分組
 * @return mixed|false
 */
function moelog_aiqna_cache_get($key, $group = "moelog_aiqna")
{
    return wp_cache_get($key, $group);
}

/**
 * 設定記憶體快取
 *
 * @param string $key        鍵值
 * @param mixed  $value      值
 * @param string $group      分組
 * @param int    $expiration 過期時間(秒)
 * @return bool
 */
function moelog_aiqna_cache_set(
    $key,
    $value,
    $group = "moelog_aiqna",
    $expiration = 0
) {
    return wp_cache_set($key, $value, $group, $expiration);
}

/**
 * 刪除記憶體快取
 *
 * @param string $key   鍵值
 * @param string $group 分組
 * @return bool
 */
function moelog_aiqna_cache_delete($key, $group = "moelog_aiqna")
{
    return wp_cache_delete($key, $group);
}

// =========================================
// 其他實用函數
// =========================================

/**
 * 取得插件版本號
 *
 * @return string
 */
function moelog_aiqna_get_version()
{
    return MOELOG_AIQNA_VERSION;
}

/**
 * 取得插件目錄路徑
 *
 * @param string $path 子路徑
 * @return string
 */
function moelog_aiqna_get_path($path = "")
{
    return MOELOG_AIQNA_DIR . ltrim($path, "/");
}

/**
 * 取得插件 URL
 *
 * @param string $path 子路徑
 * @return string
 */
function moelog_aiqna_get_url($path = "")
{
    return MOELOG_AIQNA_URL . ltrim($path, "/");
}

/**
 * 檢查功能是否啟用
 *
 * @param string $feature 功能名稱
 * @return bool
 */
function moelog_aiqna_is_feature_enabled($feature)
{
    $features = [
        "geo_mode" => get_option("moelog_aiqna_geo_mode", false),
    ];

    return !empty($features[$feature]);
}
/**
 * 在檔案最後新增以下輔助函數
 */

/**
 * 取得快取有效期限(秒)
 *
 * @return int TTL 秒數
 */
function moelog_aiqna_get_cache_ttl()
{
    $options = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $days = isset($options["cache_ttl_days"])
        ? absint($options["cache_ttl_days"])
        : 30;

    // 確保範圍在 1-365 天之間
    if ($days < 1) {
        $days = 1;
    } elseif ($days > 365) {
        $days = 365;
    }

    return $days * 86400; // 轉換為秒
}

/**
 * 取得快取有效期限(天數)
 *
 * @return int TTL 天數
 */
function moelog_aiqna_get_cache_ttl_days()
{
    $options = get_option(MOELOG_AIQNA_OPT_KEY, []);
    $days = isset($options["cache_ttl_days"])
        ? absint($options["cache_ttl_days"])
        : 30;

    if ($days < 1) {
        return 1;
    } elseif ($days > 365) {
        return 365;
    }

    return $days;
}
