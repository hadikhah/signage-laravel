<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Enums\ContentType;
use App\Events\PlaylistRefreshEvent;
use App\Exceptions\Panel\ContentDeletedException;
use App\Exceptions\Panel\ContentOwnershipException;
use App\Exceptions\Panel\FileStorageException;
use App\Exceptions\Panel\UnsupportedContentTypeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ContentUploadRequest;
use App\Legacy\Codes;
use App\Legacy\Envelope;
use App\Models\Content;
use App\Models\Monitor;
use App\Services\Panel\ContentUploadService;
use App\Services\Playlist\IpNormalizer;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function __construct(
        private readonly ContentUploadService $service,
        private readonly IpNormalizer $ipNormalizer,
    ) {}

    public function store(ContentUploadRequest $request)
    {
        $monitor = $this->resolveMonitor($request);
        if ($monitor === null) {
            return response()->json(
                Envelope::error(Codes::ERROR_NOT_FOUND, 'No monitor registered at this IP'),
                404,
            );
        }

        $data = $request->validated();
        $type = $this->contentTypeFrom($data);

        try {
            $content = match ($type) {
                ContentType::Url => $this->service->createUrl(
                    $monitor,
                    $data['url'],
                    $data['url_user_name'] ?? null,
                    $data['url_password'] ?? null,
                    (int) $data['display_time'],
                ),
                ContentType::Image, ContentType::Video => $this->service->uploadFile(
                    $monitor,
                    $request->file('file'),
                    $type,
                    isset($data['display_time']) ? (int) $data['display_time'] : null,
                ),
                default => throw new UnsupportedContentTypeException(
                    "Unsupported content type: {$type->name}",
                ),
            };
        } catch (UnsupportedContentTypeException|ContentDeletedException $e) {
            return response()->json(
                Envelope::error(Codes::ERROR_VALIDATION, $e->getMessage()),
                422,
            );
        } catch (FileStorageException $e) {
            // Server-side failure — log it, return a generic 500. Do not
            // leak disk paths or permissions details to the client.
            report($e);

            return response()->json(
                Envelope::error(Codes::ERROR_SERVER, 'Failed to store uploaded file'),
                500,
            );
        }

        broadcast(new PlaylistRefreshEvent($monitor->ip_address));

        return response()->json(Envelope::list([
            'contentId' => $content->id,
            'contentName' => $content->content_name,
            'contentPath' => $content->content_type === ContentType::Url->value
                ? null
                : "/media/files/{$content->id}/".rawurlencode($content->content_name),
        ]));
    }

    public function destroy(Request $request, Content $content)
    {
        $monitor = $this->resolveMonitor($request);
        if ($monitor === null) {
            return response()->json(
                Envelope::error(Codes::ERROR_NOT_FOUND, 'No monitor registered at this IP'),
                404,
            );
        }

        try {
            $result = $this->service->delete($monitor, $content);
        } catch (ContentOwnershipException $e) {
            return response()->json(
                Envelope::error(Codes::ERROR_VALIDATION, $e->getMessage()),
                403,
            );
        }

        broadcast(new PlaylistRefreshEvent($monitor->ip_address));

        return response()->json(Envelope::list([
            'detached' => $result->detached,
            'deleted' => $result->deleted,
        ]));
    }

    // -------------------------------------------------------------------------

    private function contentTypeFrom(array $validated): ContentType
    {
        return ContentType::from((int) ($validated['content_type'] ?? 0));
    }

    private function resolveMonitor(Request $request): ?Monitor
    {
        $ip = $this->callerIp($request);

        $ips = config('signage.expand_loopback_variants')
            ? $this->ipNormalizer->variants($ip)
            : [$this->ipNormalizer->normalize($ip)];

        return Monitor::query()->whereIn('ip_address', $ips)->first();
    }

    private function callerIp(Request $request): string
    {
        if (app()->environment(['local', 'development', 'testing'])) {
            $o = $request->query('ip');
            if (is_string($o) && $o !== '') {
                return $o;
            }

            return (string) $request->ip();
        }

        $r = $request->header('X-Real-IP');
        if (is_string($r) && $r !== '') {
            return $r;
        }

        return (string) $request->ip();
    }
}
