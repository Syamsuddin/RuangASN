<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --- Guest ---
Route::middleware('guest')->group(function () {
    Route::get('/', fn() => redirect('/login'));
    Route::get('/login', fn() => Inertia::render('Auth/Login'))->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
    Route::post('/login/mfa', [LoginController::class, 'mfaVerify'])->name('login.mfa');
});

// --- Authenticated ---
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/tasks', fn() => Inertia::render('Tasks/Index', [
        'tasks'   => \App\Http\Resources\TaskResource::collection(
            \App\Models\Task::paginate(20)
        ),
        'filters' => request()->only(['status', 'priority']),
    ]))->name('tasks.index');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
