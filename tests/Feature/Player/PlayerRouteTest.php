<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the player page', function () {
    $r = $this->get('/monitor');

    $r->assertOk();
    $r->assertSee('id="stage"', false);
    $r->assertSee('id="daily-message"', false);
    $r->assertSee('id="persian-datetime"', false);
    $r->assertSee('player.js');
    $r->assertSee('player.css');
});

it('embeds the monitor IP safely as JSON', function () {
    $r = $this->get('/monitor?ip=10.0.0.1');

    $r->assertOk();
    $r->assertSee('window.MONITOR_IP = "10.0.0.1"', false);
});

it('escapes malicious ?ip= input', function () {
    // @json() must escape the < / > / " characters safely
    $payload = '</script><script>alert(1)</script>';
    $r = $this->get('/monitor?ip='.urlencode($payload));

    $r->assertOk();
    $r->assertDontSee($payload, false);      // raw payload must not appear
    $r->assertSee('window.MONITOR_IP', false); // but the safe encoding does
});

it('renders the initial Persian date server-side', function () {
    $r = $this->get('/monitor');

    $r->assertOk();
    // A Jalali year like 140x appears in the Full field
    $r->assertSee('140', false);
});
