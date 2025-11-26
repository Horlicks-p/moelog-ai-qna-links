<?php
/**
 * Moelog AI Q&A Cache Class
 *
 * 負責快取管理,包含:
 * - Transient 快取(API 回應)
 * - 靜態 HTML 檔案快取(完整答案頁)
 * - 快取清除與統計
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
  exit();
}

class Moelog_AIQnA_Cache
{
  /**
   * 靜態檔案目錄名稱
   */
  const STATIC_DIR = MOELOG_AIQNA_STATIC_DIR; // 'ai-answers'

  /**
   * 快取有效期(秒)
   */
  //const TTL = MOELOG_AIQNA_CACHE_TTL; // 86400 (24小時)
  // 改為:
  // 此常數已廢棄,改用 self::get_ttl() 方法

  /**
   * 靜態檔案完整路徑
   * @var string
   */
  private static $static_dir_path;

  /**
   * 快取統計
   * @var array
   */
  private static $stats = [
    'transient_requests' => 0,
    'transient_hits' => 0,
    'transient_misses' => 0,
    'static_checks' => 0,
    'static_hits' => 0,
    'static_misses' => 0,
  ];

  /**
   * 初始化靜態變數
   */
  private static function init()
  {
    if (!isset(self::$static_dir_path)) {
      self::$static_dir_path = WP_CONTENT_DIR . "/" . self::STATIC_DIR;
    }
  }

  // =========================================
  // 靜態 HTML 快取
  // =========================================
  /**
   * 在 class Moelog_AIQnA_Cache 中新增方法
   */
  /**
   * 取得動態快取有效期限
   *
   * @return int TTL 秒數
   */
  private static function get_ttl()
  {
    static $ttl = null;

    if ($ttl === null) {
      $ttl = moelog_aiqna_get_cache_ttl();
    }

    return $ttl;
  }
  /**
   * 檢查靜態 HTML 是否存在且有效（優化：使用快取減少檔案系統操作）
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   * @return bool
   */
  public static function exists($post_id, $question)
  {
    // 統計：增加檢查計數
    self::$stats['static_checks']++;

    // 使用快取鍵值來檢查記憶體快取
    $cache_key = "static_exists_" . md5($post_id . "|" . $question);
    $cached = wp_cache_get($cache_key, "moelog_aiqna");
    
    if ($cached !== false) {
      if ((bool) $cached) {
        self::$stats['static_hits']++;
      } else {
        self::$stats['static_misses']++;
      }
      return (bool) $cached;
    }
    
    $path = self::get_static_path($post_id, $question);

    // ✅ 性能優化: 使用 @ 抑制錯誤，但記錄到日誌（如果啟用 Debug）
    if (!@file_exists($path)) {
      // ✅ 優化: 延長快取時間到 5 分鐘，減少文件系統檢查
      wp_cache_set($cache_key, 0, "moelog_aiqna", 300);
      self::$stats['static_misses']++;
      return false;
    }

    // ✅ 性能優化: 使用 stat() 緩存（一次性獲取文件信息）
    $stat = @stat($path);
    if ($stat === false) {
      wp_cache_set($cache_key, 0, "moelog_aiqna", 300);
      self::$stats['static_misses']++;
      return false;
    }

    // 檢查是否過期
    $age = time() - $stat['mtime'];
    if ($age >= self::get_ttl()) {
      // 自動刪除過期檔案
      @unlink($path);
      wp_cache_set($cache_key, 0, "moelog_aiqna", 300);
      self::$stats['static_misses']++;
      return false;
    }

    // ✅ 性能優化: 延長快取時間到 15 分鐘，減少文件系統操作
    wp_cache_set($cache_key, 1, "moelog_aiqna", 900);
    self::$stats['static_hits']++;
    return true;
  }

  /**
   * 儲存靜態 HTML 快取
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   * @param string $html     HTML 內容
   * @return bool 成功返回 true
   */
  public static function save($post_id, $question, $html)
  {
    // 確保目錄存在
    if (!self::ensure_directory()) {
      return false;
    }

    $path = self::get_static_path($post_id, $question);

    // 寫入檔案
    $result = file_put_contents($path, $html, LOCK_EX);

    if ($result === false) {
      Moelog_AIQnA_Debug::log_error(
        sprintf("Failed to save static file: %s", $path)
      );
      return false;
    }

    // 設定檔案權限
    @chmod($path, 0644);

    // 清除相關的快取標記
    $cache_key = "static_exists_" . md5($post_id . "|" . $question);
    wp_cache_delete($cache_key, "moelog_aiqna");

    Moelog_AIQnA_Debug::logf(
      "Saved static file: %s (%s bytes)",
      basename($path),
      number_format($result)
    );

    return true;
  }

  /**
   * 載入靜態 HTML 快取
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   * @return string|false 成功返回 HTML,失敗返回 false
   */
  public static function load($post_id, $question)
  {
    if (!self::exists($post_id, $question)) {
      return false;
    }

    $path = self::get_static_path($post_id, $question);
    $html = file_get_contents($path);

    if ($html === false) {
      Moelog_AIQnA_Debug::log_error(
        sprintf("Failed to load static file: %s", $path)
      );
      return false;
    }

    // 更新訪問時間(可選)
    @touch($path);

    return $html;
  }

  /**
   * 刪除靜態 HTML 快取（同時清除相關快取標記）
   *
   * @param int         $post_id  文章 ID
   * @param string|null $question 問題文字(null = 刪除該文章所有問題)
   * @return bool
   */
  public static function delete($post_id, $question = null)
  {
    self::init();

    if (!file_exists(self::$static_dir_path)) {
      return false;
    }

    if ($question !== null) {
      // 刪除單一檔案
      $path = self::get_static_path($post_id, $question);
      $result = self::delete_file($path);
      $hash = self::generate_hash($post_id, $question);
      self::delete_feedback_stats($post_id, $hash);
      
      // 清除相關快取標記
      $cache_key = "static_exists_" . md5($post_id . "|" . $question);
      wp_cache_delete($cache_key, "moelog_aiqna");
      
      return $result;
    } else {
      // 刪除該文章的所有檔案
      $pattern = self::$static_dir_path . "/" . $post_id . "-*.html";
      $files = glob($pattern);

      if ($files === false) {
        return false;
      }

      $count = 0;
      foreach ($files as $file) {
        $deleted = self::delete_file($file);
        self::delete_feedback_stats_from_filename($file);
        if ($deleted) {
          $count++;
        }
      }

      Moelog_AIQnA_Debug::logf(
        "Deleted %d static files for post %d",
        $count,
        $post_id
      );

      return $count > 0;
    }
  }

  /**
   * 清除所有靜態 HTML 快取
   *
   * ✅ 性能優化: 批量處理，避免過載
   *
   * @return int 刪除的檔案數量
   */
  public static function clear_all()
  {
    self::init();

    if (!file_exists(self::$static_dir_path)) {
      return 0;
    }

    // ✅ 優化: 使用 glob() 批量獲取文件列表
    $files = glob(self::$static_dir_path . "/*.html");

    if ($files === false || empty($files)) {
      return 0;
    }

    $count = 0;
    $batch_size = 100; // 每批處理 100 個文件

    // ✅ 優化: 批量刪除，避免過載
    foreach (array_chunk($files, $batch_size) as $batch) {
      foreach ($batch as $file) {
        $deleted = self::delete_file($file);
        self::delete_feedback_stats_from_filename($file);
        if ($deleted) {
          $count++;
        }
      }
      
      // ✅ 優化: 每批之間稍作停頓，避免過載（僅在大量文件時）
      if (count($files) > $batch_size) {
        usleep(10000); // 10ms
      }
    }

    // 同步清理無對應檔案的互動統計
    if ($count > 0 && class_exists("Moelog_AIQnA_Feedback_Controller")) {
      Moelog_AIQnA_Feedback_Controller::cleanup_orphaned_stats();
    }

    // ✅ 優化: 清除所有相關快取標記
    wp_cache_flush_group("moelog_aiqna");

    Moelog_AIQnA_Debug::logf(
      "Cleared all static files: %d deleted",
      $count
    );

    return $count;
  }

  /**
   * 清除過期的靜態檔案
   *
   * @return int 刪除的檔案數量
   */
  public static function clear_expired()
  {
    self::init();

    if (!file_exists(self::$static_dir_path)) {
      return 0;
    }

    $files = glob(self::$static_dir_path . "/*.html");

    if ($files === false) {
      return 0;
    }

    $count = 0;
    $now = time();

    foreach ($files as $file) {
      $age = $now - filemtime($file);
      if ($age >= self::get_ttl()) {
        self::delete_feedback_stats_from_filename($file);
        if (self::delete_file($file)) {
          $count++;
        }
      }
    }

    if ($count > 0 && class_exists("Moelog_AIQnA_Feedback_Controller")) {
      Moelog_AIQnA_Feedback_Controller::cleanup_orphaned_stats();
    }

    if ($count > 0) {
      Moelog_AIQnA_Debug::logf("Cleared %d expired static files", $count);
    }

    return $count;
  }

  // =========================================
  // Transient 快取(API 回應)
  // =========================================

  /**
   * 取得 Transient 快取（支援物件快取）
   *
   * @param string $key 快取鍵值
   * @return mixed|false
   */
  public static function get_transient($key)
  {
    // 統計：增加請求計數
    self::$stats['transient_requests']++;

    // 先嘗試從物件快取取得（如果可用）
    $cache_key = "transient_" . $key;
    $cached = wp_cache_get($cache_key, "moelog_aiqna");
    
    if ($cached !== false) {
      self::$stats['transient_hits']++;
      return $cached;
    }
    
    // 回退到標準 Transient
    $value = get_transient($key);
    
    if ($value !== false) {
      self::$stats['transient_hits']++;
      // 如果取得成功，存入物件快取
      wp_cache_set($cache_key, $value, "moelog_aiqna", 3600);
    } else {
      self::$stats['transient_misses']++;
    }
    
    return $value;
  }

  /**
   * 設定 Transient 快取（支援物件快取）
   *
   * @param string $key        快取鍵值
   * @param mixed  $value      快取值
   * @param int    $expiration 過期時間(秒),預設 24 小時
   * @return bool
   */
  public static function set_transient($key, $value, $expiration = null)
  {
    if ($expiration === null) {
      $expiration = self::get_ttl();
    }

    // 同時存入物件快取（使用較短的過期時間以避免不一致）
    $cache_key = "transient_" . $key;
    $cache_expiration = min($expiration, 3600); // 最多快取 1 小時
    wp_cache_set($cache_key, $value, "moelog_aiqna", $cache_expiration);

    return set_transient($key, $value, $expiration);
  }

  /**
   * 刪除 Transient 快取（同時清除物件快取）
   *
   * @param string $key 快取鍵值
   * @return bool
   */
  public static function delete_transient($key)
  {
    // 同時清除物件快取
    $cache_key = "transient_" . $key;
    wp_cache_delete($cache_key, "moelog_aiqna");
    
    return delete_transient($key);
  }

  /**
   * 清除所有 AI Q&A 相關的 Transient
   *
   * @return int 刪除的數量
   */
  public static function clear_all_transients()
  {
    global $wpdb;

    $count = $wpdb->query(
      "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_moe_aiqna_%' 
                OR option_name LIKE '_transient_timeout_moe_aiqna_%'",
    );

    Moelog_AIQnA_Debug::logf("Cleared %d transients", $count);

    return (int) $count;
  }

  /**
   * 清除特定文章的所有 Transient
   *
   * @param int $post_id 文章 ID
   * @return int
   */
  public static function clear_post_transients($post_id)
  {
    global $wpdb;

    $pattern = "%moe_aiqna_%" . $post_id . "%";

    $count = $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                    OR option_name LIKE %s",
        "_transient_" . $pattern,
        "_transient_timeout_" . $pattern,
      ),
    );

    // 清除物件快取中的相關快取（使用通配符清除）
    // 注意：wp_cache 不支援通配符，所以我們只能清除已知的快取鍵
    // 這裡可以通過快取鍵前綴來優化，但需要額外的追蹤機制
    
    return (int) $count;
  }

  // =========================================
  // 輔助方法
  // =========================================

  /**
   * 取得靜態檔案路徑
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   * @return string
   */
  public static function get_static_path($post_id, $question)
  {
    self::init();

    // 生成唯一檔名
    $hash = self::generate_hash($post_id, $question);

    return self::$static_dir_path . "/" . $post_id . "-" . $hash . ".html";
  }

  /**
   * 生成問題的雜湊值
   *
   * @param int    $post_id  文章 ID
   * @param string $question 問題文字
   * @return string 16 字元的 hash
   */
  public static function generate_hash($post_id, $question)
  {
    return substr(hash("sha256", $post_id . "|" . $question), 0, 16);
  }
  // 讓外部（啟用、儲存設定）可顯式準備靜態根目錄
  public static function prepare_static_root()
  {
    return self::ensure_directory(); // 呼叫你既有的 private 方法
  }

  /**
   * 確保靜態檔案目錄存在
   *
   * @return bool
   */
  private static function ensure_directory()
  {
    self::init();

    if (file_exists(self::$static_dir_path)) {
      return true;
    }

    // 建立目錄
    if (!wp_mkdir_p(self::$static_dir_path)) {
      Moelog_AIQnA_Debug::log_error(
        sprintf("Failed to create directory: %s", self::$static_dir_path)
      );
      return false;
    }

    // 設定目錄權限
    @chmod(self::$static_dir_path, 0755);

    // 建立 .htaccess 保護
    self::create_htaccess();

    // 建立 index.html 防止目錄瀏覽
    self::create_index_html();

    Moelog_AIQnA_Debug::logf(
      "Created directory: %s",
      self::$static_dir_path
    );

    return true;
  }

  /**
   * 建立 .htaccess 保護檔案
   */
  private static function create_htaccess()
  {
    $htaccess_file = self::$static_dir_path . "/.htaccess";

    if (file_exists($htaccess_file)) {
      return;
    }

    $content = <<<EOT
    # Moelog AI Q&A Static Files Protection
    # Prevent directory listing
    Options -Indexes

    # Allow HTML files
    <FilesMatch "\.html$">
        Require all granted
    </FilesMatch>

    # Block everything else
    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>
    EOT;

    file_put_contents($htaccess_file, $content);
    @chmod($htaccess_file, 0644);
  }

  /**
   * 建立 index.html 防止目錄瀏覽
   */
  private static function create_index_html()
  {
    $index_file = self::$static_dir_path . "/index.html";

    if (file_exists($index_file)) {
      return;
    }

    $content = "<!-- Silence is golden. -->";
    file_put_contents($index_file, $content);
    @chmod($index_file, 0644);
  }

  /**
   * 安全刪除檔案
   *
   * @param string $path 檔案路徑
   * @return bool
   */
  private static function delete_file($path)
  {
    if (!file_exists($path)) {
      return false;
    }

    if (!is_file($path)) {
      return false;
    }

    // 安全檢查:確保檔案在正確的目錄下
    $real_path = realpath($path);
    $real_dir = realpath(self::$static_dir_path);

    if ($real_path === false || $real_dir === false) {
      return false;
    }

    if (strpos($real_path, $real_dir) !== 0) {
      Moelog_AIQnA_Debug::log_warning(
        sprintf("Security: Attempted to delete file outside cache directory: %s", $path)
      );
      return false;
    }

    return @unlink($path);
  }

  /**
   * 依據檔名刪除對應的互動統計
   *
   * @param string $file 靜態檔案路徑
   */
  private static function delete_feedback_stats_from_filename($file)
  {
    $basename = basename($file);
    if (
      preg_match('/^(\d+)-([a-f0-9]{16})\.html$/', $basename, $matches) === 1
    ) {
      $post_id = absint($matches[1]);
      $hash = $matches[2];
      self::delete_feedback_stats($post_id, $hash);
    }
  }

  /**
   * 刪除特定問題的互動統計
   *
   * @param int    $post_id 文章 ID
   * @param string $hash    問題雜湊
   */
  private static function delete_feedback_stats($post_id, $hash)
  {
    $post_id = absint($post_id);
    // PHP 8.1+: 確保 preg_replace 不返回 null
    $hash = preg_replace('/[^a-f0-9]/', "", strtolower((string) $hash)) ?? "";

    if (!$post_id || empty($hash)) {
      return;
    }

    delete_post_meta($post_id, "_moelog_aiqna_feedback_stats_" . $hash);
  }

  // =========================================
  // 快取統計
  // =========================================

  /**
   * 取得快取統計資訊
   *
   * ✅ 優化: 添加統計結果快取，避免頻繁的檔案系統操作
   *
   * @param bool $force_refresh 是否強制刷新快取
   * @return array {
   *     @type int    $static_count   靜態檔案數量
   *     @type int    $static_size    靜態檔案總大小(bytes)
   *     @type int    $transient_count Transient 數量
   *     @type string $directory      快取目錄路徑
   *     @type bool   $directory_writable 目錄是否可寫
   *     @type int    $transient_requests Transient 請求次數
   *     @type int    $transient_hits Transient 命中次數
   *     @type int    $transient_misses Transient 未命中次數
   *     @type float  $transient_hit_rate Transient 命中率(%)
   *     @type int    $static_checks 靜態檔案檢查次數
   *     @type int    $static_hits 靜態檔案命中次數
   *     @type int    $static_misses 靜態檔案未命中次數
   *     @type float  $static_hit_rate 靜態檔案命中率(%)
   *     @type int    $cached_at 統計快取時間
   * }
   */
  public static function get_stats($force_refresh = false)
  {
    self::init();

    // ✅ 優化: 使用快取避免頻繁的檔案系統操作
    $cache_key = 'moelog_aiqna_cache_stats';
    
    if (!$force_refresh) {
      $cached_stats = get_transient($cache_key);
      if ($cached_stats !== false && is_array($cached_stats)) {
        // 添加即時的命中率統計（這些不需要快取）
        $cached_stats["transient_requests"] = self::$stats['transient_requests'] ?? 0;
        $cached_stats["transient_hits"] = self::$stats['transient_hits'] ?? 0;
        $cached_stats["transient_misses"] = self::$stats['transient_misses'] ?? 0;
        $cached_stats["static_checks"] = self::$stats['static_checks'] ?? 0;
        $cached_stats["static_hits"] = self::$stats['static_hits'] ?? 0;
        $cached_stats["static_misses"] = self::$stats['static_misses'] ?? 0;
        
        // 重新計算命中率
        if ($cached_stats["transient_requests"] > 0) {
          $cached_stats["transient_hit_rate"] = round(
            ($cached_stats["transient_hits"] / $cached_stats["transient_requests"]) * 100,
            2
          );
        }
        if ($cached_stats["static_checks"] > 0) {
          $cached_stats["static_hit_rate"] = round(
            ($cached_stats["static_hits"] / $cached_stats["static_checks"]) * 100,
            2
          );
        }
        
        return $cached_stats;
      }
    }

    $stats = [
      "static_count" => 0,
      "static_size" => 0,
      "transient_count" => 0,
      "directory" => self::$static_dir_path,
      "directory_writable" => false,
      // 統計信息
      "transient_requests" => self::$stats['transient_requests'] ?? 0,
      "transient_hits" => self::$stats['transient_hits'] ?? 0,
      "transient_misses" => self::$stats['transient_misses'] ?? 0,
      "static_checks" => self::$stats['static_checks'] ?? 0,
      "static_hits" => self::$stats['static_hits'] ?? 0,
      "static_misses" => self::$stats['static_misses'] ?? 0,
      "transient_hit_rate" => 0.0,
      "static_hit_rate" => 0.0,
      "cached_at" => time(),
    ];

    // 計算命中率
    if ($stats["transient_requests"] > 0) {
      $stats["transient_hit_rate"] = round(
        ($stats["transient_hits"] / $stats["transient_requests"]) * 100,
        2
      );
    }

    if ($stats["static_checks"] > 0) {
      $stats["static_hit_rate"] = round(
        ($stats["static_hits"] / $stats["static_checks"]) * 100,
        2
      );
    }

    // 靜態檔案統計
    if (file_exists(self::$static_dir_path)) {
      $stats["directory_writable"] = is_writable(self::$static_dir_path);

      $files = glob(self::$static_dir_path . "/*.html");
      if ($files !== false) {
        $stats["static_count"] = count($files);

        // ✅ 優化: 批量處理檔案大小計算
        $total_size = 0;
        foreach ($files as $file) {
          $size = @filesize($file);
          if ($size !== false) {
            $total_size += $size;
          }
        }
        $stats["static_size"] = $total_size;
      }
    }

    // Transient 統計
    global $wpdb;
    $stats["transient_count"] = (int) $wpdb->get_var(
      "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_moe_aiqna_%'",
    );

    // ✅ 優化: 快取統計結果 5 分鐘
    set_transient($cache_key, $stats, 300);

    return $stats;
  }

  /**
   * 清除統計快取
   *
   * @return bool
   */
  public static function clear_stats_cache()
  {
    return delete_transient('moelog_aiqna_cache_stats');
  }

  /**
   * 取得快取使用的磁碟空間(人類可讀格式)
   *
   * @return string 例如: "1.25 MB"
   */
  public static function get_size_formatted()
  {
    $stats = self::get_stats();
    $size = $stats["static_size"];

    $units = ["B", "KB", "MB", "GB"];
    $unit_index = 0;

    while ($size >= 1024 && $unit_index < count($units) - 1) {
      $size /= 1024;
      $unit_index++;
    }

    return number_format($size, 2) . " " . $units[$unit_index];
  }

  /**
   * 取得特定文章的快取檔案列表
   *
   * @param int $post_id 文章 ID
   * @return array 檔案資訊陣列
   */
  public static function get_post_cache_files($post_id)
  {
    self::init();

    if (!file_exists(self::$static_dir_path)) {
      return [];
    }

    $pattern = self::$static_dir_path . "/" . $post_id . "-*.html";
    $files = glob($pattern);

    if ($files === false) {
      return [];
    }

    $result = [];
    foreach ($files as $file) {
      $result[] = [
        "path" => $file,
        "basename" => basename($file),
        "size" => filesize($file),
        "mtime" => filemtime($file),
        "age" => time() - filemtime($file),
        "expired" => time() - filemtime($file) >= self::get_ttl(),
      ];
    }

    return $result;
  }

  // =========================================
  // 快取預熱(可選功能)
  // =========================================

  /**
   * 預熱特定文章的所有問題快取
   *
   * @param int $post_id 文章 ID
   * @return array 預熱結果
   */
  public static function warm_up($post_id)
  {
    $questions = get_post_meta($post_id, MOELOG_AIQNA_META_KEY, true);

    if (!$questions) {
      return [
        "success" => false,
        "message" => "文章沒有設定問題",
      ];
    }

    $questions_list = array_filter(
      array_map("trim", preg_split('/\r\n|\n|\r/', $questions)),
    );

    if (empty($questions_list)) {
      return [
        "success" => false,
        "message" => "問題清單為空",
      ];
    }

    // 排程預生成任務
    $scheduled = 0;
    foreach ($questions_list as $question) {
      if (!self::exists($post_id, $question)) {
        wp_schedule_single_event(
          time() + $scheduled * 90, // 每 90 秒一個
          "moelog_aiqna_pregenerate",
          [$post_id, $question],
        );
        $scheduled++;
      }
    }

    return [
      "success" => true,
      "total" => count($questions_list),
      "scheduled" => $scheduled,
      "message" => sprintf("已排程 %d 個預生成任務", $scheduled),
    ];
  }
}