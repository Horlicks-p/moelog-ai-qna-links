<?php
/**
 * Site-wide AI generation locks and usage budgets.
 *
 * @package Moelog_AIQnA
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_AI_Guard
{
    const LOCK_TTL = 120;

    /**
     * Acquire a single-flight lock for one answer fingerprint.
     *
     * @param string $cache_key Answer cache key.
     * @return bool
     */
    public static function acquire_generation_lock($cache_key)
    {
        $option = self::lock_option($cache_key);
        $expires = time() + self::LOCK_TTL;

        if (add_option($option, $expires, "", false)) {
            return true;
        }

        if ((int) get_option($option, 0) < time()) {
            delete_option($option);
            return add_option($option, $expires, "", false);
        }

        return false;
    }

    /**
     * Release a single-flight lock.
     *
     * @param string $cache_key Answer cache key.
     * @return bool
     */
    public static function release_generation_lock($cache_key)
    {
        return delete_option(self::lock_option($cache_key));
    }

    /**
     * Atomically consume one site-local daily and monthly generation unit.
     *
     * A zero limit disables that period's budget.
     *
     * @return bool
     */
    public static function consume_generation_budget()
    {
        $daily = (int) apply_filters(
            "moelog_aiqna_daily_generation_limit",
            Moelog_AIQnA_Settings::get("ai_daily_limit", 100)
        );
        $monthly = (int) apply_filters(
            "moelog_aiqna_monthly_generation_limit",
            Moelog_AIQnA_Settings::get("ai_monthly_limit", 2000)
        );

        $daily_period = "day-" . current_time("Y-m-d");
        $monthly_period = "month-" . current_time("Y-m");
        $daily_consumed = false;

        if ($daily > 0 && !self::increment_counter($daily_period, $daily)) {
            return false;
        }
        $daily_consumed = $daily > 0;

        if (
            $monthly > 0 &&
            !self::increment_counter($monthly_period, $monthly)
        ) {
            if ($daily_consumed) {
                self::decrement_counter($daily_period);
            }
            return false;
        }

        return true;
    }

    /**
     * Increment a non-autoloaded option only while it remains below its limit.
     *
     * @param string $period Counter period identifier.
     * @param int    $limit  Maximum successful increments.
     * @return bool
     */
    private static function increment_counter($period, $limit)
    {
        global $wpdb;

        $option = "moelog_aiqna_usage_" . sanitize_key($period);
        if (add_option($option, 1, "", false)) {
            return true;
        }

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options}
                 SET option_value = CAST(option_value AS UNSIGNED) + 1
                 WHERE option_name = %s
                   AND CAST(option_value AS UNSIGNED) < %d",
                $option,
                $limit
            )
        );
        wp_cache_delete($option, "options");

        return $updated === 1;
    }

    /**
     * Roll back a previously reserved counter when a later budget rejects it.
     *
     * @param string $period Counter period identifier.
     * @return void
     */
    private static function decrement_counter($period)
    {
        global $wpdb;

        $option = "moelog_aiqna_usage_" . sanitize_key($period);
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options}
                 SET option_value = GREATEST(CAST(option_value AS UNSIGNED) - 1, 0)
                 WHERE option_name = %s",
                $option
            )
        );
        wp_cache_delete($option, "options");
    }

    /**
     * @param string $cache_key Answer cache key.
     * @return string
     */
    private static function lock_option($cache_key)
    {
        return "moelog_aiqna_lock_" . substr(
            hash("sha256", (string) $cache_key),
            0,
            32
        );
    }
}
