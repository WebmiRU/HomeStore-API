<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Thumbnail;
use App\Services\ThumbnailService;
use Illuminate\Http\Response;
use RuntimeException;

class ThumbnailController extends Controller
{
    public function show(string $thumb, string $hash, ThumbnailService $thumbnails): Response
    {
        $thumbnail = Thumbnail::query()->where('key', $thumb)->first();

        abort_if($thumbnail === null, 404);

        $sourcePath = Image::query()->where('sha256', $hash)->value('path');

        abort_if($sourcePath === null, 404);

        try {
            $bytes = $thumbnails->make($sourcePath, $hash, $thumbnail);
        } catch (RuntimeException) {
            abort(404);
        }

        return response($bytes)
            ->header('Content-Type', $thumbnail->format->mime())
            ->header('Cache-Control', 'public, max-age=31536000, immutable');
    }
}
