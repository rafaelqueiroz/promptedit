<?php

namespace Tests\Feature\Pixelforge;

use App\Services\Pixelforge\WebhookSignature;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WebhookSignatureTest extends TestCase
{
    private const string SECRET = 'whsec_test_secret';

    private const string PAYLOAD = '{"id":"evt_1","type":"generation.succeeded"}';

    private function signature(): WebhookSignature
    {
        return new WebhookSignature(self::SECRET);
    }

    #[Test]
    public function it_accepts_a_correctly_signed_payload(): void
    {
        $now = 1_763_000_000;
        $header = $this->signature()->sign(self::PAYLOAD, $now);

        $this->assertTrue($this->signature()->isValid(self::PAYLOAD, $header, $now));
    }

    #[Test]
    public function it_rejects_a_tampered_payload(): void
    {
        $now = 1_763_000_000;
        $header = $this->signature()->sign(self::PAYLOAD, $now);

        $this->assertFalse($this->signature()->isValid('{"id":"evt_2"}', $header, $now));
    }

    #[Test]
    public function it_rejects_a_signature_made_with_another_secret(): void
    {
        $now = 1_763_000_000;
        $header = (new WebhookSignature('whsec_wrong'))->sign(self::PAYLOAD, $now);

        $this->assertFalse($this->signature()->isValid(self::PAYLOAD, $header, $now));
    }

    /**
     * A captured delivery must not be replayable days later.
     */
    #[Test]
    public function it_rejects_a_stale_timestamp(): void
    {
        $signedAt = 1_763_000_000;
        $header = $this->signature()->sign(self::PAYLOAD, $signedAt);

        $this->assertFalse($this->signature()->isValid(self::PAYLOAD, $header, $signedAt + 301));
        $this->assertTrue($this->signature()->isValid(self::PAYLOAD, $header, $signedAt + 299));
    }

    #[Test]
    public function it_rejects_a_future_timestamp_beyond_tolerance(): void
    {
        $signedAt = 1_763_000_000;
        $header = $this->signature()->sign(self::PAYLOAD, $signedAt);

        $this->assertFalse($this->signature()->isValid(self::PAYLOAD, $header, $signedAt - 301));
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function malformedHeaders(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'no signature part' => ['t=1763000000'],
            'no timestamp' => ['v1=abc'],
            'nonsense' => ['garbage'],
            'non numeric timestamp' => ['t=abc,v1=def'],
        ];
    }

    #[DataProvider('malformedHeaders')]
    #[Test]
    public function it_rejects_malformed_headers(?string $header): void
    {
        $this->assertFalse($this->signature()->isValid(self::PAYLOAD, $header, 1_763_000_000));
    }

    #[Test]
    public function it_refuses_to_verify_when_no_secret_is_configured(): void
    {
        $unconfigured = new WebhookSignature('');
        $header = $this->signature()->sign(self::PAYLOAD, 1_763_000_000);

        $this->assertFalse($unconfigured->isValid(self::PAYLOAD, $header, 1_763_000_000));
    }
}
