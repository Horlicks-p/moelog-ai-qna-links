<?php
/**
 * Moelog AI Q&A Meta Cache Helper
 *
 * 提供 Post Meta 的批量獲取和快取機制
 *
 * @package Moelog_AIQnA
 * @since   1.8.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class Moelog_AIQnA_Meta_Cache
{
    /**
     * Meta 快取（請求級別）
     *
     * @var array
     */
    private static $cache = [];

    /**
     * 批量取得 Post Meta
     *
     * @param array  $post_ids Post ID 陣列
     * @param string $meta_key Meta 鍵值
     * @return array 以 post_id 為鍵的陣列
     */
    public static function get_batch($post_ids, $meta_key)
    {
        if (empty($post_ids) || !is_array($post_ids)) {
            return [];
        }

        $result = [];
        $missing_ids = [];

        // 先從快取中取得已有的
        foreach ($post_ids as $post_id) {
            $post_id = (int) $post_id;
            if ($post_id <= 0) {
                continue;
            }

            $cache_key = "{$post_id}_{$meta_key}";

            // 檢查請求級快取
            if (isset(self::$cache[$cache_key])) {
                $result[$post_id] = self::$cache[$cache_key];
                continue;
            }

            // 檢查物件快取
            $cached = wp_cache_get($cache_key, 'moelog_aiqna_meta');
            if ($cached !== false) {
                self::$cache[$cache_key] = $cached;
                $result[$post_id] = $cached;
                continue;
            }

            $missing_ids[] = $post_id;
        }

        // 批量取得缺失的 Meta
        if (!empty($missing_ids)) {
            global $wpdb;

            // 使用 IN 查詢批量獲取
            $placeholders = implode(',', array_fill(0, count($missing_ids), '%d'));
            $query = $wpdb->prepare(
                "SELECT post_id, meta_value 
                 FROM {$wpdb->postmeta} 
                 WHERE post_id IN ($placeholders) 
                   AND meta_key = %s",
                array_merge($missing_ids, [$meta_key])
            );

            $meta_results = $wpdb->get_results($query, ARRAY_A);

            // 初始化結果陣列（確保所有 ID 都有值）
            foreach ($missing_ids as $id) {
                $result[$id] = '';
            }

            // 填充結果
            foreach ($meta_results as $row) {
                $post_id = (int) $row['post_id'];
                $value = $row['meta_value'];
                
                $result[$post_id] = $value;
                
                // 存入快取
                $cache_key = "{$post_id}_{$meta_key}";
                self::$cache[$cache_key] = $value;
                wp_cache_set($cache_key, $value, 'moelog_aiqna_meta', 3600);
            }
        }

        return $result;
    }

    /**
     * 取得單一 Post Meta（帶快取）
     *
     * @param int    $post_id  Post ID
     * @param string $meta_key Meta 鍵值
     * @param bool   $single   是否返回單一值
     * @return mixed
     */
    public static function get($post_id, $meta_key, $single = true)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return $single ? '' : [];
        }

        $cache_key = "{$post_id}_{$meta_key}";

        // 檢查請求級快取
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        // 檢查物件快取
        $cached = wp_cache_get($cache_key, 'moelog_aiqna_meta');
        if ($cached !== false) {
            self::$cache[$cache_key] = $cached;
            return $cached;
        }

        // 從資料庫取得
        $value = get_post_meta($post_id, $meta_key, $single);

        // 存入快取
        self::$cache[$cache_key] = $value;
        wp_cache_set($cache_key, $value, 'moelog_aiqna_meta', 3600);

        return $value;
    }

    /**
     * 清除 Post Meta 快取
     *
     * @param int    $post_id  Post ID
     * @param string $meta_key Meta 鍵值（可選，不提供則清除該 Post 的所有 Meta 快取）
     * @return void
     */
    public static function clear($post_id, $meta_key = null)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        if ($meta_key !== null) {
            // 清除特定 Meta 快取
            $cache_key = "{$post_id}_{$meta_key}";
            unset(self::$cache[$cache_key]);
            wp_cache_delete($cache_key, 'moelog_aiqna_meta');
        } else {
            // 清除該 Post 的所有 Meta 快取（需要遍歷，但通常只清除特定鍵）
            // 這裡簡化處理，只清除請求級快取
            foreach (self::$cache as $key => $value) {
                if (strpos($key, "{$post_id}_") === 0) {
                    unset(self::$cache[$key]);
                    wp_cache_delete($key, 'moelog_aiqna_meta');
                }
            }
        }
    }

    /**
     * 清除所有 Meta 快取
     *
     * @return void
     */
    public static function clear_all()
    {
        self::$cache = [];
    }
}

