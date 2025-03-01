<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsersController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::group(["prefix" => "auth"], function () {
    Route::controller(UsersController::class)->group(function () {
        Route::post("login", 'login');
        Route::post("register", 'register');
    });
});

Route::middleware(['jwt.auth'])->group(function () {
    Route::controller(UsersController::class)->group(function () {
        Route::group(["prefix" => "auth"], function () {
            Route::post("logout", 'logout');
        });
    });
});
