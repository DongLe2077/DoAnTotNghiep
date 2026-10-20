<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MaintenancePhotoController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RoleAccessController;
use App\Http\Controllers\Tenant\InvoiceController;
use App\Http\Controllers\Tenant\MaintenanceRequestController;
use App\Http\Controllers\Tenant\ProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Loaded through the web group to keep session cookies and CSRF protection.
Route::prefix('api')->name('api.')->group(function () {
    Route::get('auth/csrf-cookie', [AuthController::class, 'csrfCookie'])->name('auth.csrf-cookie');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login-ip')->name('auth.login');

    Route::middleware(['auth:web', 'auth.session'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::middleware('active.user')->group(function () {
            Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
            Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
            Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
            Route::put('auth/password', [AuthController::class, 'changePassword'])->middleware('throttle:password-change')->name('auth.password');

            Route::prefix('owner')->name('owner.')->middleware('role:'.User::ROLE_OWNER)->group(function () {
                Route::get('access', RoleAccessController::class)->name('access');
                require __DIR__.'/owner.php';
            });

            Route::prefix('tenant')->name('tenant.')->middleware('role:'.User::ROLE_TENANT)->group(function () {
                Route::get('access', RoleAccessController::class)->name('access');
                Route::get('profile', [ProfileController::class, 'show'])->name('profile');
                Route::get('current-stay', [ProfileController::class, 'currentStay'])->name('current-stay');
                Route::get('stays', [ProfileController::class, 'stays'])->name('stays');
                Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
                Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
                Route::get('maintenance-requests/eligible-occupancies', [MaintenanceRequestController::class, 'eligibleOccupancies'])->name('maintenance-requests.eligible-occupancies');
                Route::get('maintenance-requests', [MaintenanceRequestController::class, 'index'])->name('maintenance-requests.index');
                Route::post('maintenance-requests/{maintenanceRequest}/photos', [MaintenancePhotoController::class, 'store'])->name('maintenance-requests.photos.store');
                Route::get('maintenance-requests/{maintenanceRequest}/photos/{photo}', [MaintenancePhotoController::class, 'show'])->name('maintenance-requests.photos.show');
                Route::get('maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'show'])->name('maintenance-requests.show');
                Route::post('occupancies/{occupancy}/maintenance-requests', [MaintenanceRequestController::class, 'store'])->name('maintenance-requests.store');
            });
        });
    });
});
