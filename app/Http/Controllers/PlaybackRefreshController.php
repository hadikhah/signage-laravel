<?php

namespace App\Http\Controllers;

use App\Events\PlaylistRefreshEvent;
use App\Http\Requests\RefreshRequest;
use App\Legacy\Envelope;

class PlaybackRefreshController extends Controller
{
    public function __invoke(RefreshRequest $request)
    {
        $ip = $request->validated('ip');

        broadcast(new PlaylistRefreshEvent($ip));

        return response()->json(
            Envelope::list([
                'dispatched' => true,
                'ip' => $ip,
            ]),
        );
    }
}
