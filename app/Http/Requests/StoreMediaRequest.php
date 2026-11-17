<?php

namespace App\Http\Requests;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:3', 'max:'.config('pixelforge.max_prompt_characters')],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_image_urls' => ['sometimes', 'array', 'max:'.config('pixelforge.max_reference_images')],
            'reference_image_urls.*' => ['required', 'url:http,https', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference_image_urls.max' => 'You can use at most :max reference images.',
            'reference_image_urls.*.url' => 'Each reference image must be a valid URL.',
        ];
    }

    /**
     * Blank rows from the UI are not validation errors; they are just empty.
     */
    protected function prepareForValidation(): void
    {
        $urls = $this->input('reference_image_urls');

        if (is_array($urls)) {
            $this->merge([
                'reference_image_urls' => array_values(array_filter(
                    array_map(fn (mixed $url): string => is_string($url) ? trim($url) : '', $urls),
                    fn (string $url): bool => $url !== '',
                )),
            ]);
        }
    }

    public function size(): ImageSize
    {
        return ImageSize::from((string) $this->validated('size'));
    }

    public function quality(): ImageQuality
    {
        return ImageQuality::from((string) $this->validated('quality'));
    }

    /**
     * @return list<string>
     */
    public function referenceImageUrls(): array
    {
        /** @var list<string> $urls */
        $urls = $this->validated('reference_image_urls') ?? [];

        return $urls;
    }
}
