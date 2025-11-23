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
 * 加密 API Key
 * 
 * @param string $plaintext 明文 API Key
 * @return string 加密後的字串 (格式: base64(iv):base64(encrypted))
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
    
    // 格式: base64(iv):base64(encrypted)
    return base64_encode($iv) . ':' . base64_encode($encrypted);
}

/**
 * 解密 API Key
 * 
 * @param string $encrypted 加密字串
 * @return string 明文 API Key
 */
function moelog_aiqna_decrypt_api_key($encrypted) {
    if (empty($encrypted)) {
        return '';
    }
    
    // 檢查格式
    if (strpos($encrypted, ':') === false) {
        // 舊格式或簡單混淆,嘗試解混淆
        return moelog_aiqna_simple_deobfuscate($encrypted);
    }
    
    if (!function_exists('openssl_decrypt')) {
        return moelog_aiqna_simple_deobfuscate($encrypted);
    }
    
    // 分割 IV 和加密內容
    list($iv_base64, $encrypted_base64) = explode(':', $encrypted, 2);
    
    $iv = base64_decode($iv_base64);
    $encrypted_data = base64_decode($encrypted_base64);
    
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
 * @param string $plaintext 明文
 * @return string 混淆後字串
 */
function moelog_aiqna_simple_obfuscate($plaintext) {
    $key = wp_salt('auth');
    $result = '';
    $key_length = strlen($key);
    
    for ($i = 0; $i < strlen($plaintext); $i++) {
        $result .= chr(ord($plaintext[$i]) ^ ord($key[$i % $key_length]));
    }
    
    return base64_encode($result);
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
 * 檢查是否為加密格式
 * 
 * @param string $value 檢查值
 * @return bool
 */
function moelog_aiqna_is_encrypted($value) {
    // 檢查是否包含冒號分隔符 (OpenSSL 格式)
    if (strpos($value, ':') !== false) {
        $parts = explode(':', $value);
        if (count($parts) === 2) {
            // 驗證是否為有效 base64
            return base64_decode($parts[0]) !== false && 
                   base64_decode($parts[1]) !== false;
        }
    }
    
    // 檢查是否為簡單混淆格式 (純 base64)
    if (preg_match('/^[A-Za-z0-9+\/]+=*$/', $value)) {
        return base64_decode($value) !== false;
    }
    
    return false;
}
