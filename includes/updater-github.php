<?php
/**
 * GitHub 自動更新
 *
 * 使用 Plugin Update Checker v5.6 由 GitHub Release 提供自動更新。
 * 只允許精確命名的正式 Release ZIP asset；缺少時不得退回
 * GitHub 自動產生、可能包含測試與開發檔案的 source archive。
 *
 * library 不存在時直接跳過更新檢查,不產生 fatal error。
 *
 * @package Moelog_AIQnA
 * @since   2.0.5
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Return the only accepted GitHub Release asset naming contract.
 *
 * Example: moelog-ai-qna-links-2.1.0.zip
 *
 * @return string
 */
function moelog_aiqna_release_asset_pattern()
{
    return '/^moelog-ai-qna-links-[0-9]+\.[0-9]+\.[0-9]+\.zip$/D';
}

/**
 * Initialize the GitHub updater without making the plugin depend on the
 * bundled updater library for normal operation.
 *
 * @param string|null $library_file Optional library path for contract tests.
 * @return object|null
 */
function moelog_aiqna_init_github_updater($library_file = null)
{
    $library_file = $library_file ?: MOELOG_AIQNA_DIR .
        "vendor/plugin-update-checker/plugin-update-checker.php";
    if (!is_file($library_file)) {
        return null;
    }

    require_once $library_file;

    $factory_class = "YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory";
    $api_class = "YahnisElsts\\PluginUpdateChecker\\v5p6\\Vcs\\Api";
    if (!class_exists($factory_class) || !class_exists($api_class)) {
        return null;
    }

    $updater = $factory_class::buildUpdateChecker(
        "https://github.com/Horlicks-p/moelog-ai-qna-links/",
        MOELOG_AIQNA_FILE,
        "moelog-ai-qna-links"
    );
    if (!is_object($updater) || !method_exists($updater, "getVcsApi")) {
        return null;
    }

    $api = $updater->getVcsApi();
    if (!is_object($api) || !method_exists($api, "enableReleaseAssets")) {
        return null;
    }

    $api->enableReleaseAssets(
        moelog_aiqna_release_asset_pattern(),
        constant($api_class . "::REQUIRE_RELEASE_ASSETS")
    );

    return $updater;
}

if (!defined("MOELOG_AIQNA_DISABLE_UPDATER_AUTO_INIT")) {
    $GLOBALS["moelog_aiqna_updater"] = moelog_aiqna_init_github_updater();
}
