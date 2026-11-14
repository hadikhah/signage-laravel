<?php

namespace App\Services\Playlist;

use App\Enums\ContentType;
use App\Models\ApplicationSetting;
use App\Models\Content;
use App\Models\Monitor;
use App\Models\View;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the playlist for a monitor by IP and optional view.
 *
 * Rules (TASK-01, Step 6):
 *   1. Normalize IP; loopback compatibility.
 *   2. Bypass: bypass_all_content && monitor in share_monitors
 *      → share_contents, is_monitor_content=false, daily_message="".
 *   3. View: ?view= set and != "undefined"
 *      → view_contents ∩ active monitor_contents; display_time from monitor_contents.
 *   4. Default: monitor's active monitor_contents ordered by
 *      (content_order, contents.order, content_id).
 *   5. Append base: active_base_content && monitor in base_monitors.
 *   6. Filter: contents.deleted = true always excluded; date window applied
 *      with CORRECT precedence — see ADR-0002.
 *
 *  IP lookup strategy (ADR-0003): `IpNormalizer::variants()` is used only in
 * 	local/development/testing. Production and staging use the exact normalized
 * 	form, per spec's "in local/dev" scope for loopback compatibility.
 * ---------------------------------------------------------------------------
 * TODO(contract): assumptions that will change with 05-DATA-MODEL.md
 * ---------------------------------------------------------------------------
 * - share_contents/base_contents have no `active` column → all rows treated as active.
 * - share/base pairing: any share_monitors row → all share_contents (no per-row pairing).
 * - display_time for share/base items is 0 (no source column on those tables).
 * - is_monitor_content for view path set to false (source is view, not monitor).
 *   If reviewer says true, flip one line in buildViewPlaylist().
 * - Date-window filtering ON by default per ADR-0002.
 * ---------------------------------------------------------------------------
 */
final class PlaylistResolver
{
    public function __construct(private readonly IpNormalizer $ipNormalizer) {}

    public function forMonitor(string $ip, ?string $view): Playlist
    {
        $monitor = $this->findMonitor($ip);
        if ($monitor === null) {
            return Playlist::empty();
        }

        $settings = ApplicationSetting::current();

        if ($settings->bypass_all_content && $this->hasShareLink($monitor)) {
            return $this->buildBypassPlaylist($monitor);
        }

        if ($this->viewIsValid($view)) {
            return $this->buildViewPlaylist($monitor, $view);
        }

        return $this->buildDefaultPlaylist($monitor, $settings);
    }

    // -------------------------------------------------------------------------
    // Rule 2 — bypass / share
    // -------------------------------------------------------------------------

    private function buildBypassPlaylist(Monitor $monitor): Playlist
    {
        $ids = DB::table('share_contents')->pluck('content_id')->all();

        $items = $this->loadContentsByIds($ids)
            ->map(fn(Content $c) => $this->toItem($c, displayTime: 0))
            ->values()
            ->all();

        return new Playlist(
            items: $items,
            monitor: $monitor,
            bypassAllContent: true,
            isMonitorContent: false,
            dailyMessage: '',
        );
    }

    // -------------------------------------------------------------------------
    // Rule 3 — view
    // -------------------------------------------------------------------------

    private function buildViewPlaylist(Monitor $monitor, string $view): Playlist
    {
        // Precise lookup: view with this name must belong to this monitor.
        $viewModel = View::query()
            ->where('name', $view)
            ->whereHas('monitors', fn($q) => $q->where('monitors.id', $monitor->id))
            ->first();

        if ($viewModel === null) {
            return Playlist::empty();
        }

        $rows = DB::table('contents')
            ->join('view_contents', 'view_contents.content_id', '=', 'contents.id')
            ->join('monitor_contents', function ($j) use ($monitor) {
                $j->on('monitor_contents.content_id', '=', 'contents.id')
                    ->where('monitor_contents.monitor_id', '=', $monitor->id)
                    ->where('monitor_contents.active', '=', true);
            })
            ->where('view_contents.view_id', '=', $viewModel->id)
            ->where('contents.deleted', '=', false)
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->whereNull('contents.from_date')->orWhere('contents.from_date', '<=', $today);
            })
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->whereNull('contents.to_date')->orWhere('contents.to_date', '>=', $today);
            })
            ->select([
                'contents.*',
                'monitor_contents.display_time as mc_display_time',
            ])
            ->orderBy('monitor_contents.content_order')
            ->orderBy('contents.order')
            ->orderBy('contents.id')
            ->get();

        $items = $rows->map(function ($row) {
            $content = Content::with(['contentUrlUserPassword', 'user'])->find($row->id);

            return $this->toItem($content, displayTime: (int) $row->mc_display_time);
        })->values()->all();

        return new Playlist(
            items: $items,
            monitor: $monitor,
            bypassAllContent: false,
            isMonitorContent: false, // source is view, not monitor's own playlist (TODO)
            dailyMessage: $this->dailyMessageFor($monitor),
        );
    }

    // -------------------------------------------------------------------------
    // Rules 4 + 5 — default + base append
    // -------------------------------------------------------------------------

    private function buildDefaultPlaylist(Monitor $monitor, ApplicationSetting $settings): Playlist
    {
        $rows = DB::table('contents')
            ->join('monitor_contents', 'monitor_contents.content_id', '=', 'contents.id')
            ->where('monitor_contents.monitor_id', '=', $monitor->id)
            ->where('monitor_contents.active', '=', true)
            ->where('contents.deleted', '=', false)
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->whereNull('contents.from_date')->orWhere('contents.from_date', '<=', $today);
            })
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->whereNull('contents.to_date')->orWhere('contents.to_date', '>=', $today);
            })
            ->select([
                'contents.*',
                'monitor_contents.display_time as mc_display_time',
            ])
            ->orderBy('monitor_contents.content_order')
            ->orderBy('contents.order')
            ->orderBy('contents.id')
            ->get();

        $items = $rows->map(function ($row) {
            $content = Content::with(['contentUrlUserPassword', 'user'])->find($row->id);

            return $this->toItem($content, displayTime: (int) $row->mc_display_time);
        })->values()->all();

        // Rule 5: append base when active_base_content is on and monitor is in base_monitors
        if ($settings->active_base_content && $this->hasBaseLink($monitor)) {
            $baseIds = DB::table('base_contents')->pluck('content_id')->all();
            $baseItems = $this->loadContentsByIds($baseIds)
                ->map(fn(Content $c) => $this->toItem($c, displayTime: 0))
                ->values()
                ->all();
            $items = array_merge($items, $baseItems);
        }

        return new Playlist(
            items: $items,
            monitor: $monitor,
            bypassAllContent: false,
            isMonitorContent: true,
            dailyMessage: $this->dailyMessageFor($monitor),
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findMonitor(string $ip): ?Monitor
    {

        $ips = config('signage.expand_loopback_variants')
            ? $this->ipNormalizer->variants($ip)
            : [$this->ipNormalizer->normalize($ip)];

        return Monitor::query()
            ->whereIn('ip_address', $ips)
            ->first();
    }

    private function viewIsValid(?string $view): bool
    {
        return $view !== null && $view !== '' && $view !== 'undefined';
    }

    private function hasShareLink(Monitor $monitor): bool
    {
        return DB::table('share_monitors')->where('monitor_id', $monitor->id)->exists();
    }

    private function hasBaseLink(Monitor $monitor): bool
    {
        return DB::table('base_monitors')->where('monitor_id', $monitor->id)->exists();
    }

    private function dailyMessageFor(Monitor $monitor): string
    {
        $monitor->loadMissing('department');

        return (string) ($monitor->department?->daily_message ?? '');
    }

    private function loadContentsByIds(array $ids)
    {
        return Content::query()
            ->with(['contentUrlUserPassword', 'user'])
            ->whereIn('id', $ids)
            ->where('deleted', false)
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->whereNull('from_date')->orWhere('from_date', '<=', $today);
            })
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->whereNull('to_date')->orWhere('to_date', '>=', $today);
            })
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    private function toItem(Content $content, int $displayTime): PlaylistItem
    {
        $isUrl = $content->content_type === ContentType::Url->value;
        $cred = $content->contentUrlUserPassword;

        return new PlaylistItem(
            contentId: $content->id,
            contentName: (string) $content->content_name,
            userId: $content->user_id,
            userName: $content->user?->name,
            contentType: (int) $content->content_type,
            contentPath: $isUrl ? null : "/media/files/{$content->id}/{$content->content_name}",
            clientFileName: $content->client_file_name,
            fromDate: $content->from_date,
            toDate: $content->to_date,
            uploadedAt: $content->uploaded_at ?? $content->created_at,   // ← add this
            displayTime: $displayTime,
            active: true,
            url: $isUrl ? $cred?->url : null,
            urlUserName: $isUrl ? $cred?->user_name : null,
            urlPassword: $isUrl ? $cred?->password : null,
            isAutoLogin: $isUrl ? (bool) $content->is_auto_login : null,
        );
    }
}
