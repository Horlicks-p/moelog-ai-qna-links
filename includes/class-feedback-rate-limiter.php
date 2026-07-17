<?php
/**
 * Atomic fixed-window limiter for public feedback endpoints.
 *
 * @package Moelog_AIQnA
 * @since   2.0.8
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Feedback_Rate_Limiter
{
    const OPTION_PREFIX = "moelog_aiqna_feedback_rate_";
    const CLEANUP_PREFIX = "moelog_aiqna_feedback_rate_cleanup_";

    /**
     * Atomically consume one request from a fixed time window.
     *
     * @param string   $action
     * @param string   $identity Anonymous identity; never stored verbatim.
     * @param int      $limit
     * @param int      $window
     * @param int|null $now Testable Unix timestamp override.
     * @return array
     */
    public static function consume($action, $identity, $limit, $window, $now = null)
    {
        $limit = max(1, (int) $limit);
        $window = max(1, (int) $window);
        $now = $now === null ? time() : max(0, (int) $now);
        $parts = self::counter_parts($action, $identity, $window, $now);
        $allowed = false;

        if (add_option($parts["option"], 1, "", false)) {
            $allowed = true;
            $count = 1;
        } else {
            global $wpdb;
            $updated = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->options}
                     SET option_value = CAST(option_value AS UNSIGNED) + 1
                     WHERE option_name = %s
                       AND CAST(option_value AS UNSIGNED) < %d",
                    $parts["option"],
                    $limit
                )
            );
            wp_cache_delete($parts["option"], "options");
            $allowed = $updated === 1;
            $count = max(0, (int) get_option($parts["option"], 0));
        }

        self::cleanup_expired($now);
        $status = self::format_status($allowed, $count, $limit, $parts["reset_at"], $now);

        if (function_exists("do_action")) {
            do_action(
                "moelog_aiqna_feedback_rate_limit_status",
                $action,
                $status
            );
        }

        return $status;
    }

    /**
     * Read the current window without consuming quota.
     *
     * @param string   $action
     * @param string   $identity
     * @param int      $limit
     * @param int      $window
     * @param int|null $now
     * @return array
     */
    public static function get_status($action, $identity, $limit, $window, $now = null)
    {
        $limit = max(1, (int) $limit);
        $window = max(1, (int) $window);
        $now = $now === null ? time() : max(0, (int) $now);
        $parts = self::counter_parts($action, $identity, $window, $now);
        $count = max(0, (int) get_option($parts["option"], 0));

        return self::format_status(
            $count < $limit,
            $count,
            $limit,
            $parts["reset_at"],
            $now
        );
    }

    /**
     * @param string $action
     * @param string $identity
     * @param int    $window
     * @param int    $now
     * @return array
     */
    private static function counter_parts($action, $identity, $window, $now)
    {
        $reset_at = (intdiv($now, $window) + 1) * $window;
        $identity_hash = substr(hash("sha256", (string) $identity), 0, 32);
        $option = self::OPTION_PREFIX . sanitize_key($action) . "_" .
            $identity_hash . "_" . $reset_at;

        return ["option" => $option, "reset_at" => $reset_at];
    }

    /**
     * @return array
     */
    private static function format_status($allowed, $count, $limit, $reset_at, $now)
    {
        return [
            "allowed" => (bool) $allowed,
            "count" => max(0, (int) $count),
            "limit" => $limit,
            "remaining" => max(0, $limit - (int) $count),
            "reset_at" => $reset_at,
            "retry_after" => max(1, $reset_at - $now),
        ];
    }

    /**
     * Remove expired counters at most once per UTC hour.
     */
    private static function cleanup_expired($now)
    {
        global $wpdb;

        $cleanup_window = intdiv($now, 3600);
        $marker = self::CLEANUP_PREFIX . $cleanup_window;
        if (!add_option($marker, 1, "", false)) {
            return;
        }

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                   AND option_name NOT LIKE %s
                   AND CAST(SUBSTRING_INDEX(option_name, '_', -1) AS UNSIGNED) <= %d",
                $wpdb->esc_like(self::OPTION_PREFIX) . "%",
                $wpdb->esc_like(self::CLEANUP_PREFIX) . "%",
                $now
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                   AND option_name <> %s",
                $wpdb->esc_like(self::CLEANUP_PREFIX) . "%",
                $marker
            )
        );

        if ($deleted === false) {
            delete_option($marker);
        }
    }
}
