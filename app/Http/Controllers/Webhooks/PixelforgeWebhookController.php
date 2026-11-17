<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Media\PixelforgeWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Pixelforge's webhooks.
 *
 * Nothing is decided here: the processor returns an outcome and this maps it
 * to a status code.
 */
class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, PixelforgeWebhookProcessor $processor): JsonResponse
    {
        $outcome = $processor->process(
            $request->getContent(),
            $request->header('Pixelforge-Signature'),
        );

        return response()->json(['message' => $outcome->message()], $outcome->httpStatus());
    }
}
