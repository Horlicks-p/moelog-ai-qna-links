<?php
/**
 * Moelog AI Q&A Access Policy
 *
 * 集中判斷文章是否可由公開答案路由、快取及預生成流程使用。
 *
 * @package Moelog_AIQnA
 * @since   2.0.4
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Access_Policy
{
    /**
     * 判斷文章是否可公開產生及顯示 AI 答案。
     *
     * 密碼保護文章雖可能被 WordPress 視為 publicly viewable，正文仍受
     * 密碼閘門保護，因此不能送往 AI provider 或公開答案快取。
     *
     * @param mixed $post WP_Post、文章 ID 或可傳給 get_post() 的值。
     * @return bool
     */
    public static function is_publicly_accessible($post)
    {
        if (!($post instanceof WP_Post)) {
            $post_id = absint($post);
            if (!$post_id) {
                return false;
            }
            $post = get_post($post_id);
        }

        if (!($post instanceof WP_Post)) {
            return false;
        }

        if (!empty($post->post_password)) {
            return false;
        }

        if (function_exists("is_post_publicly_viewable")) {
            return is_post_publicly_viewable($post);
        }

        // WordPress 5.0～5.6 相容後備：文章類型及狀態都必須公開。
        $post_type = get_post_type_object($post->post_type);
        if (
            !$post_type ||
            !(
                !empty($post_type->publicly_queryable) ||
                (!empty($post_type->_builtin) && !empty($post_type->public))
            )
        ) {
            return false;
        }

        $post_status = get_post_status_object($post->post_status);
        return $post_status && !empty($post_status->public);
    }
}
