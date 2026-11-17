<?php

use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\MonitorController;
use App\Http\Controllers\Api\V1\OfficeController;
use App\Http\Controllers\Api\V1\Panel\ContentController;
use App\Http\Controllers\Api\V1\PlaybackController;
use App\Http\Controllers\PlaybackRefreshController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/cities', [CityController::class, 'index']);
    Route::get('/cities/list', [CityController::class, 'list']);

    Route::get('/offices', [OfficeController::class, 'index']);
    Route::get('/offices/list', [OfficeController::class, 'list']);

    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/departments/list', [DepartmentController::class, 'list']);

    Route::get('/monitors', [MonitorController::class, 'index']);
    Route::get('/monitors/list', [MonitorController::class, 'list']);

    Route::get('/playback/monitor-contents', [PlaybackController::class, 'monitorContents'])
        ->name('playback.monitor-contents');

    Route::post('/playback/refresh', PlaybackRefreshController::class)
        ->middleware('throttle:10,1')
        ->name('playback.refresh');

    Route::post('/panel/contents', [ContentController::class, 'store'])
        ->middleware('throttle:20,60')
        ->name('panel.contents.store');

    Route::delete('/panel/contents/{content}', [ContentController::class, 'destroy'])
        ->middleware('throttle:20,60')
        ->name('panel.contents.destroy');
});

Route::get('/Content/MonitorContents', [PlaybackController::class, 'monitorContents']);
