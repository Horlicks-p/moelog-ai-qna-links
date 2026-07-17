<?php
/** Bootstrap the plugin in the WordPress PHPUnit environment. */

$_tests_dir = getenv("WP_TESTS_DIR");
if (!$_tests_dir) {
    fwrite(STDERR, "WP_TESTS_DIR is not set. Run this suite through wp-env.\n");
    exit(1);
}

// WP 核心測試套件要求 PHPUnit Polyfills；由 composer 安裝到 tests/vendor。
$_polyfills = dirname(__DIR__) .
    "/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php";
if (!is_file($_polyfills)) {
    fwrite(
        STDERR,
        "PHPUnit Polyfills missing. Run `composer install` in the plugin root first.\n"
    );
    exit(1);
}
require_once $_polyfills;

require_once rtrim($_tests_dir, "/\\") . "/includes/functions.php";

tests_add_filter("muplugins_loaded", function () {
    require dirname(__DIR__, 2) . "/moelog-ai-qna.php";
});

require rtrim($_tests_dir, "/\\") . "/includes/bootstrap.php";
