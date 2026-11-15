<?php

namespace App\Http\Controllers;

use App\Services\Support\PersianDate;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function index(Request $request)
    {

        $ip = (string) $request->ip();

        if (app()->environment(['local', 'development', 'testing'])) {
            $override = $request->query('ip');
            if (is_string($override) && $override !== '') {
                $ip = $override;
            }
        }

        $scheme = config('reverb.apps.apps.0.options.scheme') === 'https' ? 'wss' : 'ws';
        $host = config('reverb.apps.apps.0.options.host');
        $port = config('reverb.apps.apps.0.options.port');
        $key = config('reverb.apps.apps.0.key');

        return view('player.index', [
            'monitorIp' => $ip,
            'initialDateTime' => PersianDate::dateTimeObject(now()),
            'reverbWsUrl' => "{$scheme}://{$host}:{$port}/app/{$key}",
        ]);
    }
}
