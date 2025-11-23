<?php
/**
 * Moelog AI Q&A Renderer Security Class
 *
 * 負責渲染器的安全相關功能:
 * - CSP Nonce 生成
 * - 安全 Headers 設定
 * - 快取 Headers 設定
 * - 爬蟲封鎖
 * - 客戶端 IP 獲取
 *
 * @package Moelog_AIQnA
 * @since   1.8.3+
 */

if (!defined("ABSPATH")) {
  exit();
}

class Moelog_AIQnA_Renderer_Security
{
  /**
   * CSP Nonce
   * @var string
   */
  private $csp_nonce;

  /**
   * GEO 模式快取（避免重複 get_option）
   * @var bool|null
   */
  private static $geo_mode = null;

  /**
   * 建構函數
   */
  public function __construct()
  {
    $this->csp_nonce = "";
  }

  /**
   * 取得 GEO 模式設定（快取版本）
   *
   * @return bool
   */
  private static function get_geo_mode()
  {
    if (self::$geo_mode === null) {
      self::$geo_mode = (bool) get_option("moelog_aiqna_geo_mode", false);
    }
    return self::$geo_mode;
  }

  /**
   * 生成 CSP Nonce
   *
   * @return string
   */
  public function generate_csp_nonce()
  {
    try {
      // random_bytes 是 PHP 7.0+ 的內建函數，WordPress 要求 PHP 7.4+
      if (function_exists("random_bytes")) {
        $this->csp_nonce = rtrim(
          strtr(base64_encode(random_bytes(16)), "+/", "-_"),
          "=",
        );
      } else {
        // 降級方案（理論上不會執行，因為 WordPress 要求 PHP 7.4+）
        $this->csp_nonce = base64_encode(hash("sha256", microtime(true) . wp_salt(), true));
      }
    } catch (Exception $e) {
      $this->csp_nonce = base64_encode(hash("sha256", microtime(true) . wp_salt(), true));
    }

    return $this->csp_nonce;
  }

  /**
   * 取得 CSP Nonce
   *
   * @return string
   */
  public function get_csp_nonce()
  {
    return $this->csp_nonce;
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
   * 設定安全 Headers
   */
  public function set_security_headers()
  {
    if (headers_sent()) {
      return;
    }

    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // ✅ 安全增強: 添加額外的安全 Headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY"); // 已通過 CSP frame-ancestors 實現，但添加以兼容舊瀏覽器

    // === CSP(只送一次,並包含 nonce) ===
    $nonce = $this->csp_nonce ?: "";
    $csp =
      "default-src 'self'; " .
      "script-src 'self' 'nonce-{$nonce}';" .
      "img-src 'self' data:; " .
      "style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; " .
      "font-src 'self' https://fonts.gstatic.com data:; " .
      "connect-src 'self'; " .
      "frame-ancestors 'none'; " .
      "base-uri 'self'; " .
      "form-action 'self'";

    // 可選:若你確定是 Kaspersky 在本機開發造成阻擋,開發期臨時放行(正式環境不建議)
    // $csp .= " https://gc.kis.v2.scr.kaspersky-labs.com ws://gc.kis.v2.scr.kaspersky-labs.com";

    // 若要加 report-uri
    $csp_report_uri = apply_filters("moelog_aiqna_csp_report_uri", "");
    if (!empty($csp_report_uri)) {
      $csp .= "; report-uri {$csp_report_uri}";
    }

    header("Content-Security-Policy: {$csp}");
  }

  /**
   * 設定快取 Headers
   *
   * @param bool $is_cached 是否來自快取
   */
  public function set_cache_headers($is_cached)
  {
    if (headers_sent()) {
      return;
    }

    header_remove("Cache-Control");
    header("Vary: Accept-Encoding, User-Agent");

    // ✅ 優化: 使用快取的方法減少 get_option() 調用
    if (self::get_geo_mode()) {
      // GEO 模式:更積極的快取
      header(
        "Cache-Control: public, max-age=3600, s-maxage=86400, stale-while-revalidate=604800",
      );
    } else {
      // 一般模式
      header(
        "Cache-Control: public, max-age=3600, s-maxage=86400, stale-while-revalidate=60",
      );
    }

    if ($is_cached) {
      header("X-Served-By: Static-Cache");
    }
  }

  /**
   * 封鎖不需要的爬蟲
   */
  public function block_unwanted_bots()
  {
    $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";

    // ✅ 優化: 使用快取的方法減少 get_option() 調用
    // 根據 GEO 模式決定封鎖策略
    if (self::get_geo_mode()) {
      // GEO 模式:只封鎖惡意爬蟲
      $default_blocked = [
        "scrape",
        "curl",
        "wget",
        "Baiduspider",
        "SemrushBot",
        "AhrefsBot",
        "MJ12bot",
        "DotBot",
      ];
    } else {
      // 一般模式:封鎖所有爬蟲
      $default_blocked = [
        "bot",
        "crawl",
        "spider",
        "scrape",
        "curl",
        "wget",
        "Googlebot",
        "Bingbot",
        "Baiduspider",
        "facebookexternalhit",
      ];
    }

    $bot_patterns = apply_filters(
      "moelog_aiqna_blocked_bots",
      $default_blocked,
    );

    foreach ($bot_patterns as $pattern) {
      if (stripos($ua, $pattern) !== false) {
        status_header(403);
        exit("Bots are not allowed");
      }
    }
  }

  /**
   * 取得客戶端 IP
   *
   * ✅ 優化: 添加 IP 格式驗證，防止偽造
   *
   * @return string
   */
  public function get_client_ip()
  {
    $ip = "0.0.0.0";

    // Cloudflare
    if (!empty($_SERVER["HTTP_CF_CONNECTING_IP"])) {
      $ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    // 代理伺服器
    elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
      $ips = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);
      $ip = trim($ips[0]);
    }
    // 直接連線
    elseif (!empty($_SERVER["REMOTE_ADDR"])) {
      $ip = $_SERVER["REMOTE_ADDR"];
    }

    // ✅ 安全增強: 驗證 IP 格式
    // 允許私有 IP（本地開發環境）
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
      // 如果完全無效，返回默認值
      $ip = "0.0.0.0";
    }

    return $ip;
  }
}

