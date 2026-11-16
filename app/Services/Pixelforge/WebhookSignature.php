<?php

namespace App\Services\Pixelforge;

/**
 * Verifies the Pixelforge-Signature header.
 *
 * Format: `t=<unix timestamp>,v1=<hex hmac>` where the HMAC is SHA-256 over
 * "<timestamp>.<raw body>" keyed with the endpoint's signing secret.
 */
final readonly class WebhookSignature
{
    public function __construct(
        private string $secret,
        private int $toleranceSeconds = 300,
    ) {}

    /**
     * @param  string  $payload  the raw request body, byte for byte
     */
    public function isValid(string $payload, ?string $header, ?int $now = null): bool
    {
        if ($this->secret === '' || $header === null || $header === '') {
            return false;
        }

        $parts = $this->parse($header);
        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;

        if ($timestamp === null || $signature === null || ! ctype_digit($timestamp)) {
            return false;
        }

        // Reject replays of an old, captured delivery.
        if (abs(($now ?? time()) - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $this->secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Builds a signature, for tests and for the Postman collection's pre-request script.
     */
    public function sign(string $payload, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $this->secret);
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $segment) {
            $pair = explode('=', trim($segment), 2);

            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        return $parts;
    }
}
