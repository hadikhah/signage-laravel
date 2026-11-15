<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function index(Request $request)
    {

        $ip = (string) $request->ip();

        // Dev/test convenience — same rule as PlaybackController (ADR-0005).
        // If your production deployment doesn't propagate client IP via nginx
        // (TrustProxies), the ?ip= override will be the only way to test the
        // player against a specific monitor. See "Production note" below.
        if (app()->environment(['local', 'development', 'testing'])) {
            $override = $request->query('ip');
            if (is_string($override) && $override !== '') {
                $ip = $override;
            }
        }

        return view('player.index', [
            'monitorIp' => $ip,
            'initialDateTime' => \App\Services\Support\PersianDate::dateTimeObject(now()),
        ]);
    }
}
