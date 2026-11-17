<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMediaRequest;
use App\Http\Resources\MediaResource;
use App\Services\Media\MediaCreator;
use App\Services\Media\MediaHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function index(Request $request, MediaHistory $history): Response
    {
        return Inertia::render('media/Index', [
            'media' => MediaResource::collection($history->forUser($request->user())),
        ]);
    }

    public function store(StoreMediaRequest $request, MediaCreator $creator): RedirectResponse
    {
        $creator->create(
            user: $request->user(),
            prompt: (string) $request->validated('prompt'),
            size: $request->size(),
            quality: $request->quality(),
            referenceImages: $request->referenceImageUrls(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Generation queued.')]);

        // Back where they were: the new generation appears in place, rather
        // than sending them off to another page to watch it.
        return back();
    }
}
