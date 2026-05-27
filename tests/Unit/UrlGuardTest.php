<?php

use App\Support\UrlGuard;

it('accepts a public https URL on the default port', function () {
    expect(UrlGuard::isSafe('https://example.com/hook'))->toBeTrue();
});

it('rejects http when allow_http is not set', function () {
    expect(UrlGuard::isSafe('http://example.com/hook'))->toBeFalse();
});

it('rejects URLs with userinfo', function () {
    expect(UrlGuard::isSafe('https://user:pass@example.com/hook'))->toBeFalse();
});

it('rejects non-default ports', function () {
    expect(UrlGuard::isSafe('https://example.com:8443/hook'))->toBeFalse();
});

it('rejects malformed URLs', function () {
    expect(UrlGuard::isSafe('not-a-url'))->toBeFalse();
});

it('rejects loopback IPs', function () {
    expect(UrlGuard::isSafe('https://127.0.0.1/'))->toBeFalse();
});

it('rejects RFC1918 private ranges', function () {
    expect(UrlGuard::isSafe('https://10.0.0.5/'))->toBeFalse();
    expect(UrlGuard::isSafe('https://172.16.0.1/'))->toBeFalse();
    expect(UrlGuard::isSafe('https://192.168.1.1/'))->toBeFalse();
});

it('rejects cloud metadata link-local', function () {
    // The classic 169.254.169.254 cloud-metadata pivot.
    expect(UrlGuard::isSafe('https://169.254.169.254/latest/meta-data/'))->toBeFalse();
});

it('rejects CGNAT range', function () {
    expect(UrlGuard::isSafe('https://100.64.0.1/'))->toBeFalse();
});

it('rejects IPv6 literals', function () {
    expect(UrlGuard::isSafe('https://[::1]/'))->toBeFalse();
});

it('rejects 0.0.0.0', function () {
    expect(UrlGuard::isSafe('https://0.0.0.0/'))->toBeFalse();
});

it('rejects multicast', function () {
    expect(UrlGuard::isSafe('https://224.0.0.1/'))->toBeFalse();
});
