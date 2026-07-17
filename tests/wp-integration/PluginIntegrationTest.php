<?php
/** Core WordPress integration contracts for the plugin. */

class Moelog_AIQnA_Plugin_Integration_Test extends WP_UnitTestCase
{
    private $saved_settings;

    public function set_up()
    {
        parent::set_up();
        $this->saved_settings = get_option(MOELOG_AIQNA_OPT_KEY, []);
    }

    public function tear_down()
    {
        update_option(MOELOG_AIQNA_OPT_KEY, $this->saved_settings);
        Moelog_AIQnA_Settings::clear_cache();
        parent::tear_down();
    }

    public function test_plugin_bootstrap_and_structured_result_are_available()
    {
        $plugin_data = get_file_data(
            dirname(__DIR__, 2) . "/moelog-ai-qna.php",
            ["version" => "Version"]
        );

        $this->assertSame($plugin_data["version"], MOELOG_AIQNA_VERSION);
        $this->assertTrue(class_exists("Moelog_AIQnA_Provider_Result"));
        $result = Moelog_AIQnA_Provider_Result::success("ok");
        $this->assertTrue($result["ok"]);
    }

    public function test_access_policy_tracks_real_post_status()
    {
        $published_id = self::factory()->post->create(["post_status" => "publish"]);
        $draft_id = self::factory()->post->create(["post_status" => "draft"]);

        $this->assertTrue(
            Moelog_AIQnA_Access_Policy::is_publicly_accessible(get_post($published_id))
        );
        $this->assertFalse(
            Moelog_AIQnA_Access_Policy::is_publicly_accessible(get_post($draft_id))
        );
    }

    public function test_saved_model_is_not_replaced_by_new_default()
    {
        update_option(MOELOG_AIQNA_OPT_KEY, [
            "provider" => "anthropic",
            "model" => "claude-site-pinned-model",
        ]);
        Moelog_AIQnA_Settings::clear_cache();

        $this->assertSame(
            "claude-site-pinned-model",
            Moelog_AIQnA_Settings::get_model()
        );
    }
}
