<?php

use App\Events\PlaylistRefreshEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('dispatches PlaylistRefreshEvent with the given ip', function () {
    Event::fake([PlaylistRefreshEvent::class]);

    $r = $this->postJson('/api/v1/playback/refresh', ['ip' => '10.0.0.1']);

    $r->assertOk();
    expect($r->json('ResultCode'))->toBe(5000);
    expect($r->json('Data.dispatched'))->toBeTrue();

    Event::assertDispatched(PlaylistRefreshEvent::class, fn ($e) => $e->ip === '10.0.0.1');
});

it('dispatches PlaylistRefreshEvent with null ip (refresh all)', function () {
    Event::fake([PlaylistRefreshEvent::class]);

    $this->postJson('/api/v1/playback/refresh', [])->assertOk();

    Event::assertDispatched(PlaylistRefreshEvent::class, fn ($e) => $e->ip === null);
});

it('broadcasts on the playlist.refresh public channel', function () {
    $event = new PlaylistRefreshEvent('10.0.0.1');

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe('playlist.refresh');
});

it('uses the short event name PlaylistRefreshEvent', function () {
    expect((new PlaylistRefreshEvent)->broadcastAs())->toBe('PlaylistRefresh');
});

it('local: allows refreshing any ip (dev override)', function () {
    $r = $this->postJson('/api/v1/playback/refresh', ['ip' => '10.0.0.99']);

    $r->assertOk();
    expect($r->json('Data.ip'))->toBe('10.0.0.99');
});

it('local: ?ip= query override works with empty body', function () {
    $r = $this->postJson('/api/v1/playback/refresh?ip=10.0.0.1', []);

    $r->assertOk();
    expect($r->json('Data.ip'))->toBe('10.0.0.1');
});

it('production: rejects cross-IP refresh', function () {
    $this->app['env'] = 'production';

    $r = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->withHeader('X-Real-IP', '10.0.0.1')
        ->postJson('/api/v1/playback/refresh', ['ip' => '10.0.0.2']);

    $r->assertStatus(403);
});
