<?php

use App\Models\Content;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('media');
});

it('serves an existing media file', function () {
    $c = Content::factory()->create([
        'content_name' => 'welcome.jpg',
        'deleted'      => false,
    ]);
    Storage::disk('media')->put("files/{$c->id}/welcome.jpg", 'fake-jpeg-bytes');

    $r = $this->get("/media/files/{$c->id}/welcome.jpg");

    $r->assertOk();
});

it('returns 404 for a missing content row', function () {
    $this->get('/media/files/9999/file.jpg')->assertNotFound();
});

it('returns 404 for a deleted content row', function () {
    $c = Content::factory()->create([
        'content_name' => 'x.jpg',
        'deleted'      => true,
    ]);
    Storage::disk('media')->put("files/{$c->id}/x.jpg", 'x');

    $this->get("/media/files/{$c->id}/x.jpg")->assertNotFound();
});

it('returns 404 when the filename does not match the stored name', function () {
    $c = Content::factory()->create(['content_name' => 'welcome.jpg']);
    Storage::disk('media')->put("files/{$c->id}/welcome.jpg", 'x');

    $this->get("/media/files/{$c->id}/other.jpg")->assertNotFound();
});

it('returns 404 when the file is missing on disk (logs a warning)', function () {
    $c = Content::factory()->create(['content_name' => 'welcome.jpg']);

    $this->get("/media/files/{$c->id}/welcome.jpg")->assertNotFound();
});

it('rejects path traversal attempts', function () {
    $c = Content::factory()->create(['content_name' => 'x.jpg']);

    // Encoded ../ in the filename
    $this->get("/media/files/{$c->id}/..%2F..%2Fetc%2Fpasswd")->assertNotFound();
});

it('rejects non-numeric contentId (route constraint)', function () {
    $this->get('/media/files/abc/x.jpg')->assertNotFound();
});