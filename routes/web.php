<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
  

Route::get('/', [HomeController::class, 'noRoute']);
Route::get('/file/{img}', [HomeController::class, 'getImage']);
Route::get('/file/{folder}/{img}', [HomeController::class, 'getImage']);
Route::get('/file/{folder1}/{folder2}/{img}', [HomeController::class, 'getImageNested']);
