<?php
/**
 * Moelog AI Q&A Encryption Helpers
 * 
 * 提供 API Key 加密/解密功能
 * 
 * @package Moelog_AIQnA
 * @since   1.8.3++
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 檢查是否使用降級加密方案
 * 
 * @return bool
 */
function moelog_aiqna_is_using_fallback_encryption() {
    return !function_exists('openssl_encrypt');
}

/**
 * 顯示加密降級警告（在後台設定頁面）
 */
function moelog_aiqna_show_encryption_warning() {
    // 只在插件設定頁面顯示
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'settings_page_moelog_aiqna') {
        return;
    }
    
    // 只有在使用降級方案時才顯示
    if (!moelog_aiqna_is_using_fallback_encryption()) {
        return;
    }
    
    // 檢查是否有 API Key（沒有的話不需要警告）
    $settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    if (empty($settings['api_key'])) {
        return;
    }
    
    ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php esc_html_e('Moelog AI Q&A - 安全提醒:', 'moelog-ai-qna'); ?></strong>
            <?php esc_html_e('您的伺服器未安裝 OpenSSL 擴充，API Key 目前使用較弱的混淆方式儲存。', 'moelog-ai-qna'); ?>
            <?php esc_html_e('建議聯繫您的主機商啟用 PHP OpenSSL 擴充以獲得更強的加密保護。', 'moelog-ai-qna'); ?>
        </p>
        <p>
            <small>
                <?php esc_html_e('或者，您可以在 wp-config.php 中使用常數定義 API Key:', 'moelog-ai-qna'); ?>
                <code>define('MOELOG_AIQNA_API_KEY', 'your-api-key');</code>
            </small>
        </p>
    </div>
    <?php
}
add_action('admin_notices', 'moelog_aiqna_show_encryption_warning');

/**
 * 加密 API Key
 * 
 * ✅ 優化: 使用前綴標識區分加密格式
 * 
 * @param string $plaintext 明文 API Key
 * @return string 加密後的字串 (格式: moe_enc_v1:base64(iv):base64(encrypted))
 */
function moelog_aiqna_encrypt_api_key($plaintext) {
    if (empty($plaintext)) {
        return '';
    }
    
    // 檢查是否支援 OpenSSL
    if (!function_exists('openssl_encrypt')) {
        // 降級方案:使用 WordPress Auth Key 做簡單混淆
        return moelog_aiqna_simple_obfuscate($plaintext);
    }
    
    // 使用 OpenSSL 加密
    $method = 'AES-256-CBC';
    $key = moelog_aiqna_get_encryption_key();
    
    // 生成隨機 IV
    $iv_length = openssl_cipher_iv_length($method);
    try {
        $iv = random_bytes($iv_length);
    } catch (Exception $e) {
        $iv = openssl_random_pseudo_bytes($iv_length);
    }
    
    // 加密
    $encrypted = openssl_encrypt($plaintext, $method, $key, OPENSSL_RAW_DATA, $iv);
    
    if ($encrypted === false) {
        return moelog_aiqna_simple_obfuscate($plaintext);
    }
    
    // ✅ 優化: 使用前綴標識
    // 格式: moe_enc_v1:base64(iv):base64(encrypted)
    return MOELOG_AIQNA_ENCRYPTED_PREFIX . base64_encode($iv) . ':' . base64_encode($encrypted);
}

/**
 * 解密 API Key
 * 
 * ✅ 優化: 支援新舊格式，使用前綴標識區分
 * 
 * @param string $encrypted 加密字串
 * @return string 明文 API Key
 */
function moelog_aiqna_decrypt_api_key($encrypted) {
    if (empty($encrypted)) {
        return '';
    }
    
    // ✅ 優化: 檢查新版前綴格式
    if (strpos($encrypted, MOELOG_AIQNA_OBFUSCATED_PREFIX) === 0) {
        // 新版混淆格式
        $data = substr($encrypted, strlen(MOELOG_AIQNA_OBFUSCATED_PREFIX));
        return moelog_aiqna_simple_deobfuscate($data);
    }
    
    if (strpos($encrypted, MOELOG_AIQNA_ENCRYPTED_PREFIX) === 0) {
        // 新版加密格式
        $data = substr($encrypted, strlen(MOELOG_AIQNA_ENCRYPTED_PREFIX));
        
        if (!function_exists('openssl_decrypt')) {
            return '';
        }
        
        // 分割 IV 和加密內容
        $parts = explode(':', $data, 2);
        if (count($parts) !== 2) {
            return '';
        }
        
        list($iv_base64, $encrypted_base64) = $parts;
        
        $iv = base64_decode($iv_base64, true);
        $encrypted_data = base64_decode($encrypted_base64, true);
        
        if ($iv === false || $encrypted_data === false) {
            return '';
        }
        
        // 解密
        $method = 'AES-256-CBC';
        $key = moelog_aiqna_get_encryption_key();
        
        $decrypted = openssl_decrypt($encrypted_data, $method, $key, OPENSSL_RAW_DATA, $iv);
        
        return $decrypted !== false ? $decrypted : '';
    }
    
    // 向下相容: 舊版格式
    if (strpos($encrypted, ':') === false) {
        // 舊版簡單混淆格式
        return moelog_aiqna_simple_deobfuscate($encrypted);
    }
    
    if (!function_exists('openssl_decrypt')) {
        return moelog_aiqna_simple_deobfuscate($encrypted);
    }
    
    // 舊版 OpenSSL 格式: base64(iv):base64(encrypted)
    $parts = explode(':', $encrypted, 2);
    if (count($parts) !== 2) {
        return '';
    }
    
    list($iv_base64, $encrypted_base64) = $parts;
    
    $iv = base64_decode($iv_base64, true);
    $encrypted_data = base64_decode($encrypted_base64, true);
    
    if ($iv === false || $encrypted_data === false) {
        return '';
    }
    
    // 解密
    $method = 'AES-256-CBC';
    $key = moelog_aiqna_get_encryption_key();
    
    $decrypted = openssl_decrypt($encrypted_data, $method, $key, OPENSSL_RAW_DATA, $iv);
    
    return $decrypted !== false ? $decrypted : '';
}

/**
 * 取得加密金鑰 (基於 WordPress 密鑰)
 * 
 * @return string 32 字元金鑰
 */
function moelog_aiqna_get_encryption_key() {
    // 使用多個 WordPress 密鑰組合
    $raw_key = AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY . NONCE_KEY;
    
    // 生成固定長度的金鑰
    return hash('sha256', $raw_key, true);
}

/**
 * 簡單混淆 (降級方案,不支援 OpenSSL 時使用)
 * 
 * ✅ 優化: 使用前綴標識區分格式
 * 
 * @param string $plaintext 明文
 * @return string 混淆後字串 (格式: moe_obf_v1:base64(xor_data))
 */
function moelog_aiqna_simple_obfuscate($plaintext) {
    $key = wp_salt('auth');
    $result = '';
    $key_length = strlen($key);
    
    for ($i = 0; $i < strlen($plaintext); $i++) {
        $result .= chr(ord($plaintext[$i]) ^ ord($key[$i % $key_length]));
    }
    
    // ✅ 優化: 使用前綴標識
    return MOELOG_AIQNA_OBFUSCATED_PREFIX . base64_encode($result);
}

/**
 * 簡單解混淆
 * 
 * @param string $obfuscated 混淆字串
 * @return string 明文
 */
function moelog_aiqna_simple_deobfuscate($obfuscated) {
    $decoded = base64_decode($obfuscated);
    if ($decoded === false) {
        return '';
    }
    
    $key = wp_salt('auth');
    $result = '';
    $key_length = strlen($key);
    
    for ($i = 0; $i < strlen($decoded); $i++) {
        $result .= chr(ord($decoded[$i]) ^ ord($key[$i % $key_length]));
    }
    
    return $result;
}

/**
 * 加密格式前綴標識
 * 用於區分加密值和普通 base64 字串
 */
define('MOELOG_AIQNA_ENCRYPTED_PREFIX', 'moe_enc_v1:');
define('MOELOG_AIQNA_OBFUSCATED_PREFIX', 'moe_obf_v1:');

/**
 * 檢查是否為加密格式
 * 
 * ✅ 優化: 使用前綴標識避免誤判普通 base64 字串（如某些 API Key）
 * 
 * @param string $value 檢查值
 * @return bool
 */
function moelog_aiqna_is_encrypted($value) {
    if (empty($value) || !is_string($value)) {
        return false;
    }
    
    // ✅ 優化: 檢查新版前綴標識
    if (strpos($value, MOELOG_AIQNA_ENCRYPTED_PREFIX) === 0) {
        return true;
    }
    
    if (strpos($value, MOELOG_AIQNA_OBFUSCATED_PREFIX) === 0) {
        return true;
    }
    
    // 向下相容: 檢查舊版 OpenSSL 格式 (iv:encrypted)
    // 舊格式特徵: 兩個 base64 字串用冒號分隔，且 IV 長度固定為 16 bytes (base64 後約 24 字元)
    if (strpos($value, ':') !== false) {
        $parts = explode(':', $value, 2);
        if (count($parts) === 2) {
            $iv = base64_decode($parts[0], true);
            $encrypted = base64_decode($parts[1], true);
            
            // IV 長度應該是 16 bytes (AES-256-CBC)
            if ($iv !== false && $encrypted !== false && strlen($iv) === 16) {
                return true;
            }
        }
    }
    
    // 向下相容: 檢查舊版簡單混淆格式
    // 舊格式特徵: 純 base64，但解碼後不是有效的 API Key 格式
    // 注意: 這個檢查比較寬鬆，所以放在最後
    if (preg_match('/^[A-Za-z0-9+\/]{20,}={0,2}$/', $value)) {
        $decoded = base64_decode($value, true);
        if ($decoded !== false) {
            // 如果解碼後包含大量不可列印字元，可能是混淆後的值
            $printable = preg_match('/^[\x20-\x7E]+$/', $decoded);
            if (!$printable) {
                return true;
            }
            
            // 如果解碼後看起來像 API Key（sk-xxx, AIza-xxx 等），則不是加密格式
            if (preg_match('/^(sk-|AIza|claude-|ant-)/i', $decoded)) {
                return false;
            }
        }
    }
    
    return false;
}

/**
 * 取得加密格式版本
 * 
 * @param string $value 加密值
 * @return string 版本標識 ('v1_openssl', 'v1_obfuscated', 'legacy_openssl', 'legacy_obfuscated', 'unknown')
 */
function moelog_aiqna_get_encryption_version($value) {
    if (empty($value) || !is_string($value)) {
        return 'unknown';
    }
    
    if (strpos($value, MOELOG_AIQNA_ENCRYPTED_PREFIX) === 0) {
        return 'v1_openssl';
    }
    
    if (strpos($value, MOELOG_AIQNA_OBFUSCATED_PREFIX) === 0) {
        return 'v1_obfuscated';
    }
    
    if (strpos($value, ':') !== false) {
        return 'legacy_openssl';
    }
    
    if (moelog_aiqna_is_encrypted($value)) {
        return 'legacy_obfuscated';
    }
    
    return 'unknown';
}
