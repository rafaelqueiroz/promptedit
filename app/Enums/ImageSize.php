<?php

namespace App\Enums;

// [confirm] the sizes Pixelforge actually accepts.
enum ImageSize: string
{
    case Square = '1024x1024';
    case Portrait = '1024x1536';
    case Landscape = '1536x1024';

    public function label(): string
    {
        return match ($this) {
            self::Square => 'Square',
            self::Portrait => 'Portrait',
            self::Landscape => 'Landscape',
        };
    }

    public function description(): string
    {
        return str_replace('x', ' × ', $this->value);
    }

    /**
     * @return array{value: string, label: string, description: string}
     */
    public function toOption(): array
    {
        return ['value' => $this->value, 'label' => $this->label(), 'description' => $this->description()];
    }
}
