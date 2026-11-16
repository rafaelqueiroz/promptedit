<?php

namespace App\Enums;

// [confirm] the quality levels Pixelforge actually accepts.
enum ImageQuality: string
{
    case Standard = 'standard';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::High => 'High',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Standard => 'Fast and inexpensive',
            self::High => 'More detail, costs more',
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
