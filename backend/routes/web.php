<?php

use App\Http\Controllers\AdminSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/login', [AdminSessionController::class, 'showLogin'])->name('admin.login.form');
Route::post('/admin/login', [AdminSessionController::class, 'login'])->name('admin.login.submit');

Route::middleware('auth')->group(function () {
    Route::get('/admin', [AdminSessionController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('/admin/logout', [AdminSessionController::class, 'logout'])->name('admin.logout');
});
