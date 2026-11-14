<?php

use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\MonitorController;
use App\Http\Controllers\Api\V1\OfficeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {


    Route::get('/cities',           [CityController::class, 'index']);
    Route::get('/cities/list',      [CityController::class, 'list']);

    Route::get('/offices',          [OfficeController::class, 'index']);
    Route::get('/offices/list',     [OfficeController::class, 'list']);

    Route::get('/departments',      [DepartmentController::class, 'index']);
    Route::get('/departments/list', [DepartmentController::class, 'list']);

    Route::get('/monitors',         [MonitorController::class, 'index']);
    Route::get('/monitors/list',    [MonitorController::class, 'list']);
});
