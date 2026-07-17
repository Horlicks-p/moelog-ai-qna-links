<?php
/**
 * Structured provider result value object.
 *
 * @package Moelog_AIQnA
 */

if (!defined("ABSPATH")) {
    exit();
}

class Moelog_AIQnA_Provider_Result
{
    /**
     * @param string $text     Provider response text.
     * @param array  $metadata Optional usage and request metadata.
     * @return array
     */
    public static function success($text, $metadata = [])
    {
        return self::build(
            true,
            $text,
            "",
            200,
            false,
            $metadata
        );
    }

    /**
     * @param string $error_code Stable machine-readable error code.
     * @param string $text       Localized user-facing error text.
     * @param int    $http_status Provider or local HTTP status.
     * @param bool   $retryable  Whether a later attempt may succeed.
     * @param array  $metadata   Optional usage and request metadata.
     * @return array
     */
    public static function error(
        $error_code,
        $text,
        $http_status = 0,
        $retryable = false,
        $metadata = []
    ) {
        return self::build(
            false,
            $text,
            $error_code,
            $http_status,
            $retryable,
            $metadata
        );
    }

    /**
     * @param mixed $result Candidate result.
     * @return bool
     */
    public static function is_valid($result)
    {
        return is_array($result) &&
            array_key_exists("ok", $result) &&
            array_key_exists("text", $result) &&
            is_bool($result["ok"]);
    }

    private static function build(
        $ok,
        $text,
        $error_code,
        $http_status,
        $retryable,
        $metadata
    ) {
        $metadata = is_array($metadata) ? $metadata : [];

        return [
            "ok" => (bool) $ok,
            "text" => trim((string) $text),
            "error_code" => (string) $error_code,
            "http_status" => max(0, (int) $http_status),
            "retryable" => (bool) $retryable,
            "retry_after" => max(0, (int) ($metadata["retry_after"] ?? 0)),
            "usage" => is_array($metadata["usage"] ?? null)
                ? $metadata["usage"]
                : [],
            "provider_request_id" => (string) (
                $metadata["provider_request_id"] ?? ""
            ),
        ];
    }
}
