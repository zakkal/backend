<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\NotificationController;

// 1. ROUTE PUBLIK (Bisa diakses tanpa login)
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/google', [AuthController::class, 'googleLogin']);

Route::get('opportunities', [OpportunityController::class, 'index']);
Route::get('opportunities/{id}', [OpportunityController::class, 'show']);

// Endpoint untuk melihat komentar (publik bisa baca komentar)
Route::get('opportunities/{id}/comments', [OpportunityController::class, 'getComments']);


// 2. ROUTE TERPROTEKSI (Wajib Login / Pakai Token JWT)
Route::middleware('jwt')->group(function () {
    
    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    
    // Organization Profile
    Route::get('organization', [OrganizationController::class, 'show']);
    Route::post('organization/update', [OrganizationController::class, 'update']); 

    // CRUD Opportunities
    Route::post('opportunities', [OpportunityController::class, 'store']);
    Route::post('opportunities/{id}', [OpportunityController::class, 'update']); 
    Route::delete('opportunities/{id}', [OpportunityController::class, 'destroy']);

    // --- FITUR SOSIAL (LIKE & COMMENT) ---
    // Like / Unlike Opportunity
    Route::post('opportunities/{id}/like', [OpportunityController::class, 'toggleLike']);
    
    // Kirim Komentar (Bisa untuk komentar baru atau balas komentar/reply)
    Route::post('opportunities/{id}/comments', [OpportunityController::class, 'storeComment']);

    // Notifikasi
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Super Admin
    Route::get('superadmin/pending-admins', [AuthController::class, 'getPendingAdmins']);
    Route::post('superadmin/verify-admin/{id}', [AuthController::class, 'approveAdmin']);

    // Lain-lain
    Route::apiResource('products', ProductController::class);

    // Di bagian publik (tanpa login)
Route::get('categories', [OpportunityController::class, 'getCategories']);

});