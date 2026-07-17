<?php
/**
 * Plugin deactivation and uninstall cleanup.
 *
 * @package Moelog_AIQnA
 * @since   2.1.0
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Lifecycle
{
    /**
     * Remove every scheduled event owned by the plugin, including events with
     * dynamic post/question arguments.
     *
     * @return int
     */
    public static function unschedule_all_events()
    {
        $crons = _get_cron_array();
        if (!is_array($crons)) {
            return 0;
        }

        $removed = 0;
        foreach ($crons as $timestamp => $hooks) {
            foreach ($hooks as $hook => $events) {
                if (strpos((string) $hook, "moelog_aiqna_") !== 0) {
                    continue;
                }
                foreach ($events as $event) {
                    wp_unschedule_event(
                        $timestamp,
                        $hook,
                        isset($event["args"]) && is_array($event["args"])
                            ? $event["args"]
                            : []
                    );
                    $removed++;
                }
            }
        }

        return $removed;
    }

    /**
     * Remove all persistent plugin data.
     */
    public static function uninstall()
    {
        self::unschedule_all_events();

        $directories = get_option("moelog_aiqna_static_dir_history", []);
        $directories = is_array($directories) ? $directories : [];
        $directories[] = function_exists("moelog_aiqna_get_static_dir")
            ? moelog_aiqna_get_static_dir()
            : "ai-answers";

        global $wpdb;
        $meta_keys = [
            MOELOG_AIQNA_META_KEY,
            MOELOG_AIQNA_META_LANG_KEY,
            "_moelog_aiqna_content_hash",
            "_moelog_aiqna_previous_status",
            Moelog_AIQnA_Feedback_Controller::META_KEY,
        ];
        $placeholders = implode(",", array_fill(0, count($meta_keys), "%s"));
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta}
                 WHERE meta_key IN ({$placeholders}) OR meta_key LIKE %s",
                array_merge(
                    $meta_keys,
                    [$wpdb->esc_like(Moelog_AIQnA_Feedback_Controller::META_KEY_PREFIX) . "%"]
                )
            )
        );

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_moe_aiqna_%'
                OR option_name LIKE '_transient_timeout_moe_aiqna_%'
                OR option_name LIKE '_transient_moelog_aiqna_%'
                OR option_name LIKE '_transient_timeout_moelog_aiqna_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE 'moelog_aiqna_lock_%'
                OR option_name LIKE 'moelog_aiqna_usage_%'"
        );

        foreach (
            [
                MOELOG_AIQNA_OPT_KEY,
                MOELOG_AIQNA_SECRET_KEY,
                "moelog_aiqna_geo_mode",
                "moe_aiqna_needs_flush",
                "moelog_aiqna_cache_protection_version",
                "moelog_aiqna_db_version",
                "moelog_aiqna_static_dir_history",
                "moelog_aiqna_cache_stats",
            ] as $option
        ) {
            delete_option($option);
        }

        self::delete_cache_directories($directories);
    }

    /**
     * Delete only known cache artifacts from sanitized WP_CONTENT_DIR children.
     *
     * @param array $directories
     * @return int Number of files removed.
     */
    public static function delete_cache_directories($directories)
    {
        $removed = 0;
        foreach (array_unique((array) $directories) as $directory) {
            $directory = strtolower((string) $directory);
            if (preg_match('/^[a-z0-9-]+$/D', $directory) !== 1) {
                continue;
            }

            $path = WP_CONTENT_DIR . "/" . $directory;
            if (!is_dir($path)) {
                continue;
            }

            $files = glob($path . "/*");
            if (is_array($files)) {
                foreach ($files as $file) {
                    $basename = basename($file);
                    if (
                        is_file($file) &&
                        (
                            preg_match('/^[0-9]+-[a-f0-9]{16}(?:-[a-f0-9]{12})?\.html$/', $basename) === 1 ||
                            preg_match('/^[0-9]+-[a-f0-9]{16}(?:-[a-f0-9]{12})?\.html\.tmp-[a-z0-9]+$/i', $basename) === 1 ||
                            $basename === "index.html"
                        )
                    ) {
                        if (@unlink($file)) {
                            $removed++;
                        }
                    }
                }
            }

            foreach ([".htaccess", "web.config"] as $control_file) {
                if (is_file($path . "/" . $control_file) && @unlink($path . "/" . $control_file)) {
                    $removed++;
                }
            }
            $control_temps = glob($path . "/.*.tmp-*");
            if (is_array($control_temps)) {
                foreach ($control_temps as $temp_file) {
                    $basename = basename($temp_file);
                    if (
                        is_file($temp_file) &&
                        preg_match('/^\.(?:htaccess|index\.html)\.tmp-[a-z0-9]+$/i', $basename) === 1 &&
                        @unlink($temp_file)
                    ) {
                        $removed++;
                    }
                }
            }
            @rmdir($path);
        }

        return $removed;
    }
}
