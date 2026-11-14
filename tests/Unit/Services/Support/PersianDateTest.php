<?php

use App\Services\Support\PersianDate;
use Carbon\Carbon;

it('converts Gregorian to Jalali date', function () {
    // Verified against the Step 7 sample: 2025-11-07 → 1404/08/16
    expect(PersianDate::dateOnly(Carbon::parse('2025-11-07')))->toBe('1404/08/16');
});

it('formats Gregorian ISO for UploadeDateTime', function () {
    expect(PersianDate::dateTime(Carbon::parse('2026-01-01 00:00:00')))
        ->toBe('2026-01-01T00:00:00');
});

it('formats time only', function () {
    expect(PersianDate::timeOnly(Carbon::parse('2026-01-01 12:34:56')))->toBe('12:34');
});

it('returns all fields for MonitorDateTime object', function () {
    $dt = PersianDate::dateTimeObject(Carbon::parse('2025-11-07 12:00:00'));

    expect($dt['TodayDate'])->toBe('1404/08/16');
    expect($dt['PersianYear'])->toBe(1404);
    expect($dt['PersianMonth'])->toBe(8);
    expect($dt['PersianDay'])->toBe(16);
    expect($dt['Year'])->toBe(2025);
    expect($dt['Month'])->toBe(11);
    expect($dt['Day'])->toBe(7);
    expect($dt['WeekDayText'])->toBeString();
    expect($dt['PersianWeekDayText'])->toBeString();
    expect($dt['Full'])->toContain('1404/08/16-');
});
