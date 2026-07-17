<?php
/** Bootstrap the plugin in the WordPress PHPUnit environment. */

$_tests_dir = getenv("WP_TESTS_DIR");
if (!$_tests_dir) {
    fwrite(STDERR, "WP_TESTS_DIR is not set. Run this suite through wp-env.\n");
    exit(1);
}

require_once rtrim($_tests_dir, "/\\") . "/includes/functions.php";

tests_add_filter("muplugins_loaded", function () {
    require dirname(__DIR__, 2) . "/moelog-ai-qna.php";
});

require rtrim($_tests_dir, "/\\") . "/includes/bootstrap.php";
