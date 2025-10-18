<?php
/**
 * Helpers – Template (Optional)
 *
 * 這檔案是可選的。如果你需要把 answer-page.php 的一些重複片段抽成函式，
 * 再引入到 class-renderer.php 裡使用，可以用這個檔案集中管理。
 *
 * 用法（範例）：
 *   echo moe_tpl_disclaimer(get_option('moelog_aiqna_settings', [])['disclaimer'] ?? '');
 */

if (!defined('ABSPATH')) exit;

/**
 * 輸出免責聲明 HTML（已做基本轉義）
 */
function moe_tpl_disclaimer($text)
{
    $text = trim((string) $text);
    if ($text === '') return '';
    return '<div class="moe-disclaimer">' . wp_kses_post($text) . '</div>';
}

/**
 * 產生「回到原文」區塊
 */
function moe_tpl_back_to_origin($url, $title = '')
{
    $url   = esc_url($url);
    $title = esc_html($title);
    $label = $title ? $title : $url;

    return sprintf(
        '<div class="moe-original-section"><a class="moe-original-link" href="%s">%s</a></div>',
        $url,
        $label
    );
}
