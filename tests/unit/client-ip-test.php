<?php
/**
 * Standalone tests for trusted proxy client IP resolution.
 *
 * Run: php tests/unit/client-ip-test.php
 */

define("ABSPATH", __DIR__);

$trusted_proxy_ranges = [];
$trusted_cloudflare_ranges = [];

function apply_filters($name, $value)
{
    global $trusted_proxy_ranges, $trusted_cloudflare_ranges;
    if ($name === "moelog_aiqna_trusted_proxies") {
        return $trusted_proxy_ranges;
    }
    if ($name === "moelog_aiqna_trusted_cloudflare_proxies") {
        return $trusted_cloudflare_ranges;
    }
    return $value;
}

function wp_salt($scheme = "auth")
{
    return "test-site-salt-" . $scheme;
}

require_once dirname(__DIR__, 2) . "/includes/class-client-ip.php";

function assert_client_ip($expected, $server, $label)
{
    $actual = Moelog_AIQnA_Client_IP::resolve($server);
    if ($actual !== $expected) {
        fwrite(STDERR, sprintf("FAIL: %s (expected %s, got %s)\n", $label, $expected, $actual));
        exit(1);
    }
}

// Default policy: forwarded headers are ignored.
assert_client_ip("203.0.113.10", [
    "REMOTE_ADDR" => "203.0.113.10",
    "HTTP_CF_CONNECTING_IP" => "198.51.100.7",
    "HTTP_X_FORWARDED_FOR" => "198.51.100.8",
], "direct spoofed headers");

$trusted_proxy_ranges = ["10.0.0.0/8", "2001:db8:ffff::/48"];
assert_client_ip("198.51.100.20", [
    "REMOTE_ADDR" => "10.1.2.3",
    "HTTP_CF_CONNECTING_IP" => "198.51.100.7",
    "HTTP_X_FORWARDED_FOR" => "198.51.100.20, 10.1.2.3",
], "ordinary trusted proxy ignores spoofed Cloudflare header");

$trusted_cloudflare_ranges = ["192.0.2.0/24", "2001:db8:cf::/48"];
assert_client_ip("198.51.100.7", [
    "REMOTE_ADDR" => "192.0.2.10",
    "HTTP_CF_CONNECTING_IP" => "198.51.100.7",
], "explicitly trusted Cloudflare IPv4 proxy");

assert_client_ip("2001:db8:1::7", [
    "REMOTE_ADDR" => "2001:db8:cf::10",
    "HTTP_CF_CONNECTING_IP" => "2001:db8:1::7",
], "explicitly trusted Cloudflare IPv6 proxy");

assert_client_ip("198.51.100.20", [
    "REMOTE_ADDR" => "10.1.2.3",
    "HTTP_X_FORWARDED_FOR" => "198.51.100.20, 10.9.8.7",
], "trusted multi-hop proxy chain");

assert_client_ip("2001:db8:1::25", [
    "REMOTE_ADDR" => "2001:db8:ffff::10",
    "HTTP_X_FORWARDED_FOR" => "2001:db8:1::25, 2001:db8:ffff::20",
], "trusted IPv6 proxy chain");

assert_client_ip("10.2.2.2", [
    "REMOTE_ADDR" => "10.1.1.1",
    "HTTP_X_FORWARDED_FOR" => "invalid, 10.2.2.2",
], "all forwarded hops trusted");

assert_client_ip("0.0.0.0", [
    "REMOTE_ADDR" => "not-an-ip",
    "HTTP_X_FORWARDED_FOR" => "198.51.100.20",
], "invalid direct peer");

if (!Moelog_AIQnA_Client_IP::ip_in_range("192.0.2.42", "192.0.2.0/24")) {
    fwrite(STDERR, "FAIL: IPv4 CIDR match\n");
    exit(1);
}
if (Moelog_AIQnA_Client_IP::ip_in_range("192.0.3.42", "192.0.2.0/24")) {
    fwrite(STDERR, "FAIL: IPv4 CIDR mismatch\n");
    exit(1);
}
if (!Moelog_AIQnA_Client_IP::ip_in_range("2001:db8::1", "2001:db8::/32")) {
    fwrite(STDERR, "FAIL: IPv6 CIDR match\n");
    exit(1);
}

$id = Moelog_AIQnA_Client_IP::anonymized_id("192.0.2.42");
if (strlen($id) !== 64 || strpos($id, "192.0.2.42") !== false) {
    fwrite(STDERR, "FAIL: anonymous identifier\n");
    exit(1);
}

fwrite(STDOUT, "Client IP tests passed (12 assertions).\n");
