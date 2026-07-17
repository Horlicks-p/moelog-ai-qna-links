<?php
/**
 * Standalone GitHub updater safety contract tests.
 *
 * Run: php tests/unit/updater-contract-test.php
 */

namespace YahnisElsts\PluginUpdateChecker\v5 {
    class PucFactory
    {
        public static function buildUpdateChecker($repository, $plugin_file, $slug)
        {
            $GLOBALS["moelog_updater_factory_args"] = [
                $repository,
                $plugin_file,
                $slug,
            ];
            return $GLOBALS["moelog_fake_updater"];
        }
    }
}

namespace YahnisElsts\PluginUpdateChecker\v5p6\Vcs {
    class Api
    {
        const REQUIRE_RELEASE_ASSETS = 2;
    }
}

namespace {
    define("ABSPATH", __DIR__);
    define("MOELOG_AIQNA_DIR", dirname(__DIR__, 2) . "/");
    define("MOELOG_AIQNA_FILE", MOELOG_AIQNA_DIR . "moelog-ai-qna.php");
    define("MOELOG_AIQNA_DISABLE_UPDATER_AUTO_INIT", true);

    class Moelog_Fake_Updater_Api
    {
        public $pattern;
        public $preference;

        public function enableReleaseAssets($pattern, $preference)
        {
            $this->pattern = $pattern;
            $this->preference = $preference;
        }
    }

    class Moelog_Fake_Updater
    {
        public $api;

        public function __construct($api)
        {
            $this->api = $api;
        }

        public function getVcsApi()
        {
            return $this->api;
        }
    }

    require_once MOELOG_AIQNA_DIR . "includes/updater-github.php";

    $failures = [];
    $assertions = 0;
    $expect = function ($condition, $label) use (&$failures, &$assertions) {
        $assertions++;
        if (!$condition) {
            $failures[] = $label;
        }
    };

    $pattern = moelog_aiqna_release_asset_pattern();
    foreach (["moelog-ai-qna-links-2.1.0.zip"] as $accepted) {
        $expect(preg_match($pattern, $accepted) === 1, "accept " . $accepted);
    }
    foreach (
        [
            "moelog-ai-qna-links.zip",
            "moelog-ai-qna-links-latest.zip",
            "moelog-ai-qna-links-2.1.zip",
            "moelog-ai-qna-links-2.1.0-rc.1.zip",
            "moelog-ai-qna-links-2.1.0-source.zip",
            "moelog-ai-qna-links-2.1.0-source.zip.exe",
            "prefix-moelog-ai-qna-links-2.1.0.zip",
        ] as $rejected
    ) {
        $expect(preg_match($pattern, $rejected) === 0, "reject " . $rejected);
    }

    $missing = sys_get_temp_dir() . "/moelog-missing-updater-" . uniqid() . ".php";
    $expect(
        moelog_aiqna_init_github_updater($missing) === null,
        "missing updater library must not fatal"
    );

    $stub_library = tempnam(sys_get_temp_dir(), "moelog-puc-");
    file_put_contents($stub_library, "<?php // Contract-test stub.\n");
    $fake_api = new Moelog_Fake_Updater_Api();
    $GLOBALS["moelog_fake_updater"] = new Moelog_Fake_Updater($fake_api);

    try {
        $updater = moelog_aiqna_init_github_updater($stub_library);
        $expect($updater === $GLOBALS["moelog_fake_updater"], "updater returned");
        $expect(
            $fake_api->pattern === $pattern,
            "exact release asset pattern configured"
        );
        $expect(
            $fake_api->preference ===
                \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\Api::REQUIRE_RELEASE_ASSETS,
            "release assets are mandatory"
        );
        $expect(
            $GLOBALS["moelog_updater_factory_args"] === [
                "https://github.com/Horlicks-p/moelog-ai-qna-links/",
                MOELOG_AIQNA_FILE,
                "moelog-ai-qna-links",
            ],
            "updater factory contract"
        );
    } finally {
        @unlink($stub_library);
    }

    if ($failures) {
        foreach ($failures as $failure) {
            fwrite(STDERR, "FAIL: " . $failure . "\n");
        }
        exit(1);
    }

    fwrite(
        STDOUT,
        sprintf("Updater contract tests passed (%d assertions).\n", $assertions)
    );
}
