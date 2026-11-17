<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => MediaType::Image,
            'status' => GenerationStatus::Queued,
            'prompt' => fake()->sentence(),
            'size' => ImageSize::Square->value,
            'quality' => ImageQuality::Medium->value,
            'estimated_provider_cost' => '0.057750000',
            'estimated_price' => '0.072187500',
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider_id' => 'gen_'.strtoupper(fake()->bothify('##??##??##??##??##??##')),
            'submitted_at' => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->submitted()->state(fn (array $attributes): array => [
            'status' => GenerationStatus::Processing,
        ]);
    }

    public function succeeded(): static
    {
        return $this->submitted()->state(fn (array $attributes): array => [
            'status' => GenerationStatus::Succeeded,
            'provider_cost' => '0.012785000',
            'price' => '0.015981250',
            'source_url' => 'https://pixelforge.test/images/example.png?signature=abc',
            'source_url_expires_at' => now()->addDay(),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->submitted()->state(fn (array $attributes): array => [
            'status' => GenerationStatus::Failed,
            'provider_cost' => '0.000000000',
            'price' => '0.000000000',
            'error_code' => 'generation_failed',
            'error_message' => 'The model could not generate an image.',
            'completed_at' => now(),
        ]);
    }
}
