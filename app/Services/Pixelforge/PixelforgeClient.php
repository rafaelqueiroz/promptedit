<?php

namespace App\Services\Pixelforge;

use App\Services\Pixelforge\Data\ProviderGeneration;
use App\Services\Pixelforge\Data\UsageReport;
use App\Services\Pixelforge\Exceptions\AuthenticationFailed;
use App\Services\Pixelforge\Exceptions\InvalidRequest;
use App\Services\Pixelforge\Exceptions\Overloaded;
use App\Services\Pixelforge\Exceptions\PixelforgeException;
use App\Services\Pixelforge\Exceptions\RateLimited;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Thin, typed client for the Pixelforge API.
 *
 * It does no retrying of its own: it translates HTTP failures into typed
 * exceptions and lets the calling job decide, so retry policy lives in exactly
 * one place.
 */
class PixelforgeClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly int $timeout = 15,
    ) {}

    /**
     * Submit a generation. Returns the accepted (queued) generation.
     *
     * The idempotency key makes a retried submission safe: Pixelforge replays
     * the original response instead of creating — and billing — a second
     * generation.
     *
     * @param  list<string>  $referenceImages
     * @param  array<string, string>  $metadata
     *
     * @throws PixelforgeException
     */
    public function createGeneration(
        string $prompt,
        string $size,
        string $quality,
        array $referenceImages = [],
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): ProviderGeneration {
        $payload = [
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
        ];

        // Pixelforge rejects unknown parameters, so only send what is set.
        if ($referenceImages !== []) {
            $payload['reference_images'] = $referenceImages;
        }

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        $request = $this->request();

        if ($idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }

        $response = $this->send(fn (): Response => $request->post('/v1/generations', $payload));

        return ProviderGeneration::fromArray($this->decode($response));
    }

    /**
     * Fetch a generation's current state. Used to reconcile when a webhook
     * never arrived.
     *
     * @throws PixelforgeException
     */
    public function getGeneration(string $id): ProviderGeneration
    {
        $response = $this->send(fn (): Response => $this->request()->get("/v1/generations/{$id}"));

        return ProviderGeneration::fromArray($this->decode($response));
    }

    /**
     * Pixelforge's own record of what it charged us, for the operator report.
     *
     * @throws PixelforgeException
     */
    public function usage(?string $startDate = null, ?string $endDate = null): UsageReport
    {
        $query = array_filter([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $response = $this->send(fn (): Response => $this->request()->get('/v1/usage', $query));

        return UsageReport::fromArray($this->decode($response));
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->baseUrl)
            ->withToken((string) $this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }

    /**
     * @param  callable(): Response  $send
     *
     * @throws PixelforgeException
     */
    private function send(callable $send): Response
    {
        try {
            $response = $send();
        } catch (ConnectionException $e) {
            // The request never completed, so no generation was created.
            throw new Overloaded(
                'Could not reach Pixelforge: '.$e->getMessage(),
                'connection_failed',
                null,
                null,
                $e,
            );
        }

        if ($response->successful()) {
            return $response;
        }

        throw $this->exceptionFor($response);
    }

    private function exceptionFor(Response $response): PixelforgeException
    {
        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        /** @var array<string, mixed> $error */
        $error = is_array($body['error'] ?? null) ? $body['error'] : [];

        $code = (string) ($error['code'] ?? 'unknown');
        $message = (string) ($error['message'] ?? 'Pixelforge returned HTTP '.$response->status().'.');
        $status = $response->status();
        $retryAfter = $this->retryAfter($response);

        return match (true) {
            $status === 401 => new AuthenticationFailed($message),
            $status === 429 => new RateLimited($message, $code, $retryAfter),
            $status >= 500 => new Overloaded($message, $code === 'unknown' ? 'overloaded' : $code, $status, $retryAfter),
            default => new InvalidRequest($message, $code, $status),
        };
    }

    private function retryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return is_numeric($header) ? (int) $header : null;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PixelforgeException
     */
    private function decode(Response $response): array
    {
        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new Overloaded('Pixelforge returned a malformed response body.', 'malformed_response', $response->status());
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
