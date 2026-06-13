<?php
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaSetupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — RuangASN v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // --- Public Auth ---
    Route::prefix('auth')->group(function () {
        Route::post('login', [LoginController::class, 'login']);
        Route::post('mfa/verify', [LoginController::class, 'mfaVerify']);
    });

    // --- Authenticated ---
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout', [LoginController::class, 'logout']);
            Route::get('me', [LoginController::class, 'me']);
            Route::post('mfa/setup', [MfaSetupController::class, 'setup']);
            Route::post('mfa/enable', [MfaSetupController::class, 'enable']);
            Route::post('mfa/disable', [MfaSetupController::class, 'disable']);
        });

        // Tasks
        Route::prefix('tasks')->group(function () {
            Route::get('/', [TaskController::class, 'index']);
            Route::post('/', [TaskController::class, 'store']);
            Route::get('{task}', [TaskController::class, 'show']);
            Route::put('{task}', [TaskController::class, 'update']);
            Route::delete('{task}', [TaskController::class, 'destroy']);
            Route::post('{task}/transition', [TaskController::class, 'transition']);
            Route::post('{task}/evidences', [TaskController::class, 'addEvidence']);
        });

        // Organizations
        Route::prefix('organizations')->group(function () {
            Route::get('/', [OrganizationController::class, 'index']);
            Route::post('/', [OrganizationController::class, 'store']);
            Route::get('{organization}', [OrganizationController::class, 'show']);
            Route::put('{organization}', [OrganizationController::class, 'update']);
            Route::get('{organization}/members', [OrganizationController::class, 'members']);
        });

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('{notification}/read', [NotificationController::class, 'markRead']);
            Route::post('read-all', [NotificationController::class, 'markAllRead']);
            Route::get('unread-count', [NotificationController::class, 'unreadCount']);
        });

    });

});
