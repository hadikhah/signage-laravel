<?php

use App\Services\Playlist\IpNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->normalizer = new IpNormalizer;
});

it('normalizes plain IPv4', function () {
    expect($this->normalizer->normalize('10.0.0.1'))->toBe('10.0.0.1');
});

it('normalizes IPv4-mapped IPv6 to IPv4', function () {
    expect($this->normalizer->normalize('::ffff:127.0.0.1'))->toBe('127.0.0.1');
});

it('normalizes uppercase IPv6 to lowercase', function () {
    expect($this->normalizer->normalize('2001:DB8::1'))->toBe('2001:db8::1');
});

it('trims whitespace', function () {
    expect($this->normalizer->normalize('  10.0.0.1  '))->toBe('10.0.0.1');
});

it('returns empty string for empty input', function () {
    expect($this->normalizer->normalize(''))->toBe('');
});

it('returns non-IP input unchanged', function () {
    expect($this->normalizer->normalize('not-an-ip'))->toBe('not-an-ip');
});

it('provides loopback variants for 127.0.0.1', function () {
    expect($this->normalizer->variants('127.0.0.1'))
        ->toContain('127.0.0.1', '::1', '::ffff:127.0.0.1');
});

it('provides loopback variants for ::1', function () {
    expect($this->normalizer->variants('::1'))
        ->toContain('127.0.0.1', '::1', '::ffff:127.0.0.1');
});

it('provides loopback variants for ::ffff:127.0.0.1', function () {
    expect($this->normalizer->variants('::ffff:127.0.0.1'))
        ->toContain('127.0.0.1', '::1', '::ffff:127.0.0.1');
});

it('provides a single variant for non-loopback IPv4', function () {
    expect($this->normalizer->variants('10.0.0.1'))->toBe(['10.0.0.1']);
});

it('provides a single variant for non-loopback IPv6', function () {
    expect($this->normalizer->variants('2001:DB8::1'))->toBe(['2001:db8::1']);
});

it('recognizes loopback addresses', function () {
    expect($this->normalizer->isLocal('127.0.0.1'))->toBeTrue();
    expect($this->normalizer->isLocal('::1'))->toBeTrue();
    expect($this->normalizer->isLocal('::ffff:127.0.0.1'))->toBeTrue();
    expect($this->normalizer->isLocal('10.0.0.1'))->toBeFalse();
});
