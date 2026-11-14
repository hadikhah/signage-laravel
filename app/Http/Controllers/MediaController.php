<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a media file for a content row.
 *
 * Route: GET /media/files/{contentId}/{filename}
 *
 * Guards:
 *   - {filename} must not contain path components (basename check).
 *   - {filename} must match the stored content_name exactly.
 *   - {contentId} must exist and not be soft-flagged as deleted.
 *   - The file must exist on disk; missing → 404 + warning log.
 */
class MediaController extends Controller
{
    public function show(Request $request, int $contentId, string $filename): BinaryFileResponse
    {
        // 1. Path traversal guard — filename must be a bare name.
        if (basename($filename) !== $filename) {
            abort(404, 'Invalid filename');
        }

        // 2. Content must exist and not be deleted.
        $content = Content::find($contentId);
        if ($content === null || $content->deleted) {
            Log::info('Media 404: unknown or deleted content', [
                'content_id' => $contentId,
                'filename'   => $filename,
                'ip'         => $request->ip(),
            ]);
            abort(404);
        }

        // 3. Filename must match the content's stored name (case-sensitive).
        if ($content->content_name !== $filename) {
            Log::info('Media 404: filename mismatch', [
                'content_id'    => $contentId,
                'requested'     => $filename,
                'stored_name'   => $content->content_name,
            ]);
            abort(404);
        }

        // 4. File must exist on disk.
        $path = "files/{$contentId}/{$filename}";
        $disk = Storage::disk('media');

        if (! $disk->exists($path)) {
            Log::warning('Media file missing on disk', [
                'content_id' => $contentId,
                'filename'   => $filename,
                'path'       => $disk->path($path),
            ]);
            abort(404);
        }

        return response()->file($disk->path($path));
    }
}
