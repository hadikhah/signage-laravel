<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaybackRequest;
use App\Legacy\Envelope;
use App\Services\Playback\PlaybackTransformer;
use App\Services\Playlist\PlaylistResolver;

class PlaybackController extends Controller
{
    public function __construct(
        private readonly PlaylistResolver $resolver,
        private readonly PlaybackTransformer $transformer,
    ) {}

    public function monitorContents(PlaybackRequest $request)
    {
        $ip   = $this->resolveIp($request);
        $view = $request->input('view');
        $view = ($view === '' || $view === 'undefined') ? null : $view;

        $playlist = $this->resolver->forMonitor($ip, $view);

        return response()->json(
            Envelope::playback($this->transformer->transform($playlist)),
        );
    }

    /**
     * Resolve the monitor IP from the request.
     *
     * Production: use the source IP only.
     * Local/testing: allow `?ip=` override so curl from host can simulate
     * any monitor without network gymnastics.
     */
    private function resolveIp(PlaybackRequest $request): string
    {
        $ip = (string) $request->ip();

        if (config("signage.allow_ip_override")) {
            $override = $request->input('ip');
            if (is_string($override) && $override !== '') {
                $ip = $override;
            }
        }

        return $ip;
    }
}
