<?php
/**
 * Trusted proxy aware client IP resolver.
 *
 * @package Moelog_AIQnA
 * @since   2.0.4
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Client_IP
{
    /**
     * Resolve the request client IP.
     *
     * Forwarded headers are considered only when REMOTE_ADDR belongs to an
     * explicitly trusted proxy range. Configure ranges with the
     * MOELOG_AIQNA_TRUSTED_PROXIES constant (array or comma/newline separated
     * string) or the moelog_aiqna_trusted_proxies filter.
     *
     * @param array|null $server Server variables; defaults to $_SERVER.
     * @return string
     */
    public static function resolve($server = null)
    {
        $server = is_array($server) ? $server : $_SERVER;
        $remote = self::normalize_ip($server["REMOTE_ADDR"] ?? "");
        if ($remote === null) {
            return "0.0.0.0";
        }

        $trusted = self::get_trusted_proxies();
        if (!self::is_trusted($remote, $trusted)) {
            return $remote;
        }

        $cf_ip = self::normalize_ip($server["HTTP_CF_CONNECTING_IP"] ?? "");
        if ($cf_ip !== null) {
            return $cf_ip;
        }

        $forwarded = self::parse_forwarded_for(
            $server["HTTP_X_FORWARDED_FOR"] ?? ""
        );
        if (!$forwarded) {
            return $remote;
        }

        // Walk from the nearest hop towards the original client. Trusted proxy
        // hops are skipped; the first untrusted valid address is the client.
        for ($index = count($forwarded) - 1; $index >= 0; $index--) {
            if (!self::is_trusted($forwarded[$index], $trusted)) {
                return $forwarded[$index];
            }
        }

        return $forwarded[0];
    }

    /**
     * Produce a site-scoped anonymous identifier suitable for rate-limit keys.
     *
     * @param string|null $ip
     * @return string
     */
    public static function anonymized_id($ip = null)
    {
        $ip = self::normalize_ip($ip === null ? self::resolve() : $ip);
        if ($ip === null) {
            $ip = "0.0.0.0";
        }

        return hash_hmac("sha256", $ip, wp_salt("nonce"));
    }

    /**
     * @return array
     */
    public static function get_trusted_proxies()
    {
        $configured = defined("MOELOG_AIQNA_TRUSTED_PROXIES")
            ? constant("MOELOG_AIQNA_TRUSTED_PROXIES")
            : [];

        if (is_string($configured)) {
            $configured = preg_split('/[\s,]+/', $configured, -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($configured)) {
            $configured = [];
        }

        $configured = apply_filters("moelog_aiqna_trusted_proxies", $configured);
        if (!is_array($configured)) {
            return [];
        }

        $result = [];
        foreach ($configured as $range) {
            $range = trim((string) $range);
            if ($range !== "" && self::is_valid_range($range)) {
                $result[] = $range;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param string $ip
     * @param array  $ranges
     * @return bool
     */
    public static function is_trusted($ip, $ranges)
    {
        foreach ($ranges as $range) {
            if (self::ip_in_range($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $ip
     * @param string $range IP or CIDR.
     * @return bool
     */
    public static function ip_in_range($ip, $range)
    {
        $ip = self::normalize_ip($ip);
        if ($ip === null) {
            return false;
        }

        $parts = explode("/", (string) $range, 2);
        $network = self::normalize_ip($parts[0]);
        if ($network === null) {
            return false;
        }

        $ip_binary = @inet_pton($ip);
        $network_binary = @inet_pton($network);
        if ($ip_binary === false || strlen($ip_binary) !== strlen($network_binary)) {
            return false;
        }

        $max_bits = strlen($ip_binary) * 8;
        $prefix = isset($parts[1]) ? filter_var($parts[1], FILTER_VALIDATE_INT) : $max_bits;
        if ($prefix === false || $prefix < 0 || $prefix > $max_bits) {
            return false;
        }

        $full_bytes = intdiv($prefix, 8);
        $remaining_bits = $prefix % 8;
        if ($full_bytes > 0 && substr($ip_binary, 0, $full_bytes) !== substr($network_binary, 0, $full_bytes)) {
            return false;
        }

        if ($remaining_bits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remaining_bits)) & 0xFF;
        return (ord($ip_binary[$full_bytes]) & $mask) ===
            (ord($network_binary[$full_bytes]) & $mask);
    }

    private static function normalize_ip($ip)
    {
        $ip = trim((string) $ip);
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    private static function parse_forwarded_for($header)
    {
        $result = [];
        foreach (explode(",", (string) $header) as $candidate) {
            $ip = self::normalize_ip($candidate);
            if ($ip !== null) {
                $result[] = $ip;
            }
        }
        return $result;
    }

    private static function is_valid_range($range)
    {
        $parts = explode("/", $range, 2);
        $ip = self::normalize_ip($parts[0]);
        if ($ip === null) {
            return false;
        }
        if (!isset($parts[1])) {
            return true;
        }
        $max = strpos($ip, ":") !== false ? 128 : 32;
        return preg_match('/^[0-9]+$/D', $parts[1]) === 1 &&
            (int) $parts[1] <= $max;
    }
}
