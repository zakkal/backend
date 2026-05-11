<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\OrganizationController;
use App\Http\Controllers\API\OpportunityController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ChatController; // Tambahan Import

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- 1. ROUTE PUBLIK ---
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('google', [AuthController::class, 'googleLogin']);
});

// Lowongan (Akses Publik)
Route::get('opportunities', [OpportunityController::class, 'index']);
Route::get('opportunities/{id}', [OpportunityController::class, 'show']);
Route::get('opportunities/{id}/comments', [OpportunityController::class, 'getComments']);
Route::get('categories', [OpportunityController::class, 'getCategories']);


// --- 2. ROUTE TERPROTEKSI (JWT) ---
Route::middleware('jwt')->group(function () {
    
    // Profil & Logout
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
    
    // Profil Organisasi
    Route::prefix('organization')->group(function () {
        Route::get('/', [OrganizationController::class, 'show']);
        Route::post('update', [OrganizationController::class, 'update']); 
    });

    // Fitur Chat (Conversations & Messages)
    Route::prefix('chats')->group(function () {
        Route::get('/', [ChatController::class, 'getConversations']);
        Route::post('/', [ChatController::class, 'startConversation']);
        Route::get('{id}/messages', [ChatController::class, 'getMessages']);
        Route::post('{id}/messages', [ChatController::class, 'sendMessage']);
    });

    // Managemen Opportunities
    Route::post('opportunities', [OpportunityController::class, 'store']);
    Route::post('opportunities/{id}', [OpportunityController::class, 'update']);
    Route::delete('opportunities/{id}', [OpportunityController::class, 'destroy']);

    // Fitur Sosial
    Route::get('opportunities/{id}/likes', [OpportunityController::class, 'getLikeStatus']);
    Route::post('opportunities/{id}/like', [OpportunityController::class, 'toggleLike']);
    Route::post('opportunities/{id}/comments', [OpportunityController::class, 'storeComment']);

    // Notifikasi
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
    });

    // Fitur Super Admin
    Route::prefix('superadmin')->group(function () {
        Route::get('pending-admins', [AuthController::class, 'getPendingAdmins']);
        Route::post('verify-admin/{id}', [AuthController::class, 'approveAdmin']);
    });

    // Resource Lainnya
    Route::apiResource('products', ProductController::class);
});