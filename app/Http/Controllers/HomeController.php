<?php

namespace App\Http\Controllers;

use App\Http\Resources\MediaResource;
use App\Services\Media\ComposerOptions;
use App\Services\Media\MediaHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const array SAMPLE_PROMPTS = [
        'A lighthouse at dusk, long exposure, fog rolling over the rocks',
        'Isometric cutaway of a tiny ramen shop, warm light, paper texture',
        'Studio photo of a translucent glass chess knight on black slate',
        'Watercolor map of an imaginary archipelago with hand-lettered names',
    ];

    public function __invoke(Request $request, ComposerOptions $composer, MediaHistory $history): Response
    {
        $user = $request->user();

        return Inertia::render('Welcome', [
            ...$composer->toArray(),
            'samplePrompts' => self::SAMPLE_PROMPTS,
            // Signed-in visitors generate and see results without leaving the
            // page, so the landing page is the product rather than an advert
            // for it.
            // A plain list, not a resource collection: that would nest the
            // items under a "data" key the landing page has no use for.
            'recentMedia' => $user === null
                ? []
                : MediaResource::collection($history->recentForUser($user))->resolve(),
        ]);
    }
}
