<?php
/**
 * GitHub 自動更新
 *
 * 使用 Plugin Update Checker v5.6 由 GitHub Release 提供自動更新。
 * 若 Release 附有外掛 ZIP asset（moelog-ai-qna-links-*.zip)則優先下載,
 * 否則退回 tag 的原始碼壓縮包。
 *
 * library 不存在時直接跳過更新檢查,不產生 fatal error。
 *
 * @package Moelog_AIQnA
 * @since   2.0.5
 */

if (!defined("ABSPATH")) {
    exit();
}

$moelog_aiqna_puc = MOELOG_AIQNA_DIR . "vendor/plugin-update-checker/plugin-update-checker.php";
if (!file_exists($moelog_aiqna_puc)) {
    return;
}

require_once $moelog_aiqna_puc;

$moelog_aiqna_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    "https://github.com/Horlicks-p/moelog-ai-qna-links/",
    MOELOG_AIQNA_FILE,
    "moelog-ai-qna-links"
);

// 優先使用 Release 中的發布包 asset(只含執行檔案、不含 tests/docs)
$moelog_aiqna_updater->getVcsApi()->enableReleaseAssets(
    '/^moelog-ai-qna-links.*\.zip$/i'
);
