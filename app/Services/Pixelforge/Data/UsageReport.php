<?php

namespace App\Services\Pixelforge\Data;

/**
 * GET /v1/usage: Pixelforge's own daily totals, which the operator report is
 * reconciled against.
 */
final readonly class UsageReport
{
    /**
     * @param  list<UsageDay>  $days
     */
    public function __construct(
        public string $startDate,
        public string $endDate,
        public array $days,
        public ?UsageDay $total,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = array_values((array) ($payload['data'] ?? []));

        /** @var array<string, mixed>|null $total */
        $total = $payload['total'] ?? null;

        return new self(
            startDate: (string) ($payload['start_date'] ?? ''),
            endDate: (string) ($payload['end_date'] ?? ''),
            days: array_map(UsageDay::fromArray(...), $rows),
            total: is_array($total) ? UsageDay::fromArray($total) : null,
        );
    }
}
