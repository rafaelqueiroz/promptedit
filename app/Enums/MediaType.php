<?php

namespace App\Enums;

/**
 * PromptEdit generates more than images; only images are wired up so far.
 */
enum MediaType: string
{
    case Image = 'image';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
