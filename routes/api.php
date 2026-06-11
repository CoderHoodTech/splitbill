<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BalanceController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FriendController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SettlementController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Public auth endpoints — throttled to discourage credential stuffing.
    Route::middleware('throttle:6,1')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Friends
        Route::get('/friends', [FriendController::class, 'index']);
        Route::get('/friends/requests', [FriendController::class, 'requests']);
        // POST is throttled so friend codes can't be enumerated.
        Route::post('/friends/requests', [FriendController::class, 'store'])
            ->middleware('throttle:friend-code-lookup');
        Route::post('/friends/requests/{friendRequest}/accept', [FriendController::class, 'accept']);
        Route::post('/friends/requests/{friendRequest}/decline', [FriendController::class, 'decline']);

        // Groups
        Route::get('/groups', [GroupController::class, 'index']);
        Route::post('/groups', [GroupController::class, 'store']);
        Route::get('/groups/{group}', [GroupController::class, 'show']);
        Route::post('/groups/{group}/members', [GroupController::class, 'addMember']);
        Route::delete('/groups/{group}/members/{user}', [GroupController::class, 'removeMember']);

        // Expenses
        Route::get('/groups/{group}/expenses', [ExpenseController::class, 'index']);
        Route::post('/groups/{group}/expenses', [ExpenseController::class, 'store']);

        // Balances + settlements
        Route::get('/groups/{group}/balances', [BalanceController::class, 'show']);
        Route::post('/groups/{group}/settlements', [SettlementController::class, 'store']);

        // Activity feed
        Route::get('/groups/{group}/activities', [ActivityController::class, 'groupIndex']);

        // Notifications inbox
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    });
});
