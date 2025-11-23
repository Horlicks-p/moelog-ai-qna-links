<?php
/**
 * Moelog AI Q&A Post Cache Helper
 *
 * 提供 Post 物件的快取機制，減少重複的資料庫查詢
 *
 * @package Moelog_AIQnA
 * @since   1.8.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class Moelog_AIQnA_Post_Cache
{
    /**
     * Post 物件快取（請求級別）
     *
     * @var array
     */
    private static $cache = [];

    /**
     * 快取統計
     *
     * @var array
     */
    private static $stats = [
        'requests' => 0,
        'hits' => 0,
        'misses' => 0,
        'object_cache_hits' => 0,
        'db_queries' => 0,
    ];

    /**
     * 取得 Post 物件（帶快取）
     *
     * @param int|WP_Post $post_id Post ID 或 Post 物件
     * @param string      $output  輸出格式
     * @return WP_Post|null
     */
    public static function get($post_id, $output = OBJECT)
    {
        // 如果已經是 Post 物件，直接返回
        if ($post_id instanceof WP_Post) {
            return $post_id;
        }

        $post_id = (int) $post_id;
        
        if ($post_id <= 0) {
            return null;
        }

        // 統計：增加請求計數
        self::$stats['requests']++;

        // 檢查請求級快取
        if (isset(self::$cache[$post_id])) {
            self::$stats['hits']++;
            return self::$cache[$post_id];
        }

        // 檢查物件快取（如果可用）
        $cache_key = "post_{$post_id}";
        $cached = wp_cache_get($cache_key, 'moelog_aiqna');
        
        if ($cached !== false && $cached instanceof WP_Post) {
            self::$cache[$post_id] = $cached;
            self::$stats['hits']++;
            self::$stats['object_cache_hits']++;
            return $cached;
        }

        // 從資料庫取得
        $post = get_post($post_id, $output);
        self::$stats['misses']++;
        self::$stats['db_queries']++;
        
        if (!$post) {
            return null;
        }

        // 存入快取
        self::$cache[$post_id] = $post;
        wp_cache_set($cache_key, $post, 'moelog_aiqna', 3600); // 快取 1 小時

        return $post;
    }

    /**
     * 批量取得 Post 物件
     *
     * @param array $post_ids Post ID 陣列
     * @return array Post 物件陣列（以 post_id 為鍵）
     */
    public static function get_multiple($post_ids)
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

            // 檢查請求級快取
            if (isset(self::$cache[$post_id])) {
                $result[$post_id] = self::$cache[$post_id];
                continue;
            }

            // 檢查物件快取
            $cache_key = "post_{$post_id}";
            $cached = wp_cache_get($cache_key, 'moelog_aiqna');
            
            if ($cached !== false && $cached instanceof WP_Post) {
                self::$cache[$post_id] = $cached;
                $result[$post_id] = $cached;
                continue;
            }

            $missing_ids[] = $post_id;
        }

        // 批量取得缺失的 Post
        if (!empty($missing_ids)) {
            $posts = get_posts([
                'post__in' => $missing_ids,
                'post_type' => 'any',
                'posts_per_page' => -1,
                'suppress_filters' => true,
            ]);

            foreach ($posts as $post) {
                $result[$post->ID] = $post;
                self::$cache[$post->ID] = $post;
                wp_cache_set("post_{$post->ID}", $post, 'moelog_aiqna', 3600);
            }
        }

        return $result;
    }

    /**
     * 清除 Post 快取
     *
     * @param int $post_id Post ID
     * @return void
     */
    public static function clear($post_id)
    {
        $post_id = (int) $post_id;
        
        // 清除請求級快取
        unset(self::$cache[$post_id]);
        
        // 清除物件快取
        wp_cache_delete("post_{$post_id}", 'moelog_aiqna');
    }

    /**
     * 清除所有 Post 快取
     *
     * @return void
     */
    public static function clear_all()
    {
        self::$cache = [];
    }

    /**
     * 預載入 Post 物件到快取
     *
     * @param array $post_ids Post ID 陣列
     * @return void
     */
    public static function preload($post_ids)
    {
        if (empty($post_ids) || !is_array($post_ids)) {
            return;
        }

        // 過濾出尚未快取的 ID
        $to_load = [];
        foreach ($post_ids as $post_id) {
            $post_id = (int) $post_id;
            if ($post_id > 0 && !isset(self::$cache[$post_id])) {
                $to_load[] = $post_id;
            }
        }

        if (empty($to_load)) {
            return;
        }

        // 批量載入
        self::get_multiple($to_load);
    }

    /**
     * 取得快取統計資訊
     *
     * @return array
     */
    public static function get_stats()
    {
        $stats = self::$stats;
        
        // 計算命中率
        if ($stats['requests'] > 0) {
            $stats['hit_rate'] = round(($stats['hits'] / $stats['requests']) * 100, 2);
            $stats['miss_rate'] = round(($stats['misses'] / $stats['requests']) * 100, 2);
            $stats['object_cache_hit_rate'] = $stats['hits'] > 0 
                ? round(($stats['object_cache_hits'] / $stats['hits']) * 100, 2)
                : 0;
        } else {
            $stats['hit_rate'] = 0;
            $stats['miss_rate'] = 0;
            $stats['object_cache_hit_rate'] = 0;
        }
        
        return $stats;
    }

    /**
     * 重置統計資訊
     *
     * @return void
     */
    public static function reset_stats()
    {
        self::$stats = [
            'requests' => 0,
            'hits' => 0,
            'misses' => 0,
            'object_cache_hits' => 0,
            'db_queries' => 0,
        ];
    }
}

