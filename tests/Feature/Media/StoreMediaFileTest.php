<?php

namespace Tests\Feature\Media;

use App\Jobs\StoreMediaFile;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreMediaFileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_downloads_the_image_into_our_own_storage(): void
    {
        Storage::fake('public');
        config()->set('media.disk', 'public');

        Http::fake(['*' => Http::response('fake-png-bytes')]);

        $media = Media::factory()->succeeded()->create();

        (new StoreMediaFile($media->id))->handle();

        $media->refresh();

        $this->assertSame("media/{$media->id}.png", $media->path);
        $this->assertTrue($media->isStored());
    }

    #[Test]
    public function it_does_not_download_twice(): void
    {
        Storage::fake('public');
        Http::fake();

        $media = Media::factory()->succeeded()->create(['disk' => 'public', 'path' => 'media/x.png']);

        (new StoreMediaFile($media->id))->handle();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_gives_up_when_the_url_has_already_expired(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('gone', 403)]);

        $media = Media::factory()->succeeded()->create(['source_url_expires_at' => now()->subHour()]);

        (new StoreMediaFile($media->id))->handle();

        $this->assertFalse($media->refresh()->isStored());
    }
}
