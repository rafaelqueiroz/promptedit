<?php

namespace App\Enums;

enum ImageQuality: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function description(): string
    {
        return match ($this) {
            self::Low => 'Fastest and cheapest',
            self::Medium => 'Balanced detail and cost',
            self::High => 'Most detail, costs more',
        };
    }

    /**
     * @return array{value: string, label: string, description: string}
     */
    public function toOption(): array
    {
        return ['value' => $this->value, 'label' => $this->label(), 'description' => $this->description()];
    }
}
