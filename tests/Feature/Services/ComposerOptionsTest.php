<?php

namespace Tests\Feature\Services;

use App\Services\Media\ComposerOptions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComposerOptionsTest extends TestCase
{
    /**
     * Both the landing page and the generate page render the same composer,
     * so they must be given the same prices.
     */
    #[Test]
    public function it_exposes_every_size_and_quality_with_server_computed_prices(): void
    {
        $props = $this->app->make(ComposerOptions::class)->toArray();

        $this->assertCount(3, $props['options']['sizes']);
        $this->assertCount(3, $props['options']['qualities']);
        $this->assertSame('medium', $props['defaults']['quality']);
        $this->assertSame(4, $props['maxReferenceImages']);

        // One entry per possible reference image count, 0 through the max.
        $this->assertCount(5, $props['estimates']['1024x1024']['medium']);
        $this->assertSame('0.072187500', $props['estimates']['1024x1024']['medium'][0]);
    }
}
