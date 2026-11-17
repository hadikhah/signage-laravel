<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Monitor;
use App\Models\MonitorContent;
use App\Services\Playlist\IpNormalizer;
use Illuminate\Http\Request;

class PanelPageController extends Controller
{
    public function __construct(private readonly IpNormalizer $ipNormalizer) {}

    public function index(Request $request)
    {
        $ip = $this->callerIp($request);

        $ips = config('signage.expand_loopback_variants')
            ? $this->ipNormalizer->variants($ip)
            : [$this->ipNormalizer->normalize($ip)];

        $monitor = Monitor::query()->whereIn('ip_address', $ips)->first();

        $monitorContents = $monitor
            ? MonitorContent::query()
                ->where('monitor_id', $monitor->id)
                ->with('content')
                ->whereHas('content', fn ($q) => $q->where('deleted', false))
                ->orderBy('content_order')
                ->get()
                // ->values()
            : collect();

        return view('panel.index', [
            'monitor' => $monitor,
            'ip' => $ip,
            'monitorContents' => $monitorContents,
            'devOverride' => app()->environment(['local', 'development', 'testing']),
        ]);
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
