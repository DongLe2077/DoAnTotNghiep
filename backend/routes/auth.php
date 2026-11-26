<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LiveUpdatesController;
use App\Http\Controllers\MaintenancePhotoController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayosWebhookController;
use App\Http\Controllers\PropertyMemberHomeController;
use App\Http\Controllers\PropertyOnboardingController;
use App\Http\Controllers\PropertyWorkspaceController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RoleAccessController;
use App\Http\Controllers\RoomMarketplaceController;
use App\Http\Controllers\Tenant\AnnouncementController;
use App\Http\Controllers\Tenant\CheckoutApplicationController;
use App\Http\Controllers\Tenant\InvoiceController;
use App\Http\Controllers\Tenant\InvoicePayosController;
use App\Http\Controllers\Tenant\MaintenanceRequestController;
use App\Http\Controllers\Tenant\PdfDocumentController;
use App\Http\Controllers\Tenant\ProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Loaded through the web group to keep session cookies and CSRF protection.
Route::prefix('api')->name('api.')->group(function () {
    Route::get('live/market', [LiveUpdatesController::class, 'market'])->middleware('throttle:120,1');
    Route::get('marketplace/listings', [RoomMarketplaceController::class, 'index']);
    Route::get('marketplace/listings/{id}', [RoomMarketplaceController::class, 'detail'])->whereNumber('id');
    Route::get('marketplace/listings/{id}/photo', [RoomMarketplaceController::class, 'photo'])->whereNumber('id');
    Route::post('webhooks/payos/{property}', PayosWebhookController::class)->whereNumber('property')->name('webhooks.payos.property');
    Route::post('webhooks/payos', PayosWebhookController::class)->name('webhooks.payos');
    Route::get('auth/csrf-cookie', [AuthController::class, 'csrfCookie'])->name('auth.csrf-cookie');
    Route::get('auth/registration', [RegistrationController::class, 'options'])->name('auth.registration.options');
    Route::post('auth/registration', [RegistrationController::class, 'store'])
        ->middleware('throttle:account-registration')->name('auth.registration.store');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login-ip')->name('auth.login');

    Route::middleware(['auth:web', 'auth.session'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::middleware('active.user')->group(function () {
            Route::get('live/user', [LiveUpdatesController::class, 'user'])->middleware('throttle:120,1');
            Route::get('live/property', [LiveUpdatesController::class, 'property'])->middleware(['property.context', 'throttle:120,1']);
            Route::post('marketplace/listings/{id}/requests', [RoomMarketplaceController::class, 'createRequest'])->whereNumber('id')->middleware('throttle:12,1');
            Route::get('marketplace/requests', [RoomMarketplaceController::class, 'ownRequests']);
            Route::put('marketplace/requests/{id}', [RoomMarketplaceController::class, 'userAction'])->whereNumber('id')->middleware('throttle:30,1');
            Route::get('properties', [PropertyWorkspaceController::class, 'index']);
            Route::post('properties', [PropertyWorkspaceController::class, 'store'])->middleware('throttle:10,1');
            Route::put('properties/selected', [PropertyWorkspaceController::class, 'select']);
            Route::post('properties/join-preview', [PropertyOnboardingController::class, 'preview'])->middleware('throttle:property-invitation');
            Route::post('properties/join-requests', [PropertyOnboardingController::class, 'join'])->middleware('throttle:property-invitation');
            Route::get('properties/join-requests', [PropertyOnboardingController::class, 'ownRequests']);
            Route::delete('properties/join-requests/{id}', [PropertyOnboardingController::class, 'withdraw'])->whereNumber('id')->middleware('throttle:30,1');
            Route::get('properties/photo', [PropertyWorkspaceController::class, 'photo'])->middleware('property.context');
            Route::get('properties/members', [PropertyWorkspaceController::class, 'members'])->middleware('property.context');
            Route::put('properties/tenant-link', [PropertyWorkspaceController::class, 'link'])->middleware('property.context');
            Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
            Route::middleware('property.context:optional')->group(function () {
                Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
                Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
                Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
                Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
            });
            Route::put('auth/password', [AuthController::class, 'changePassword'])->middleware('throttle:password-change')->name('auth.password');

            Route::prefix('owner')->name('owner.')->middleware(['property.context', 'role:'.User::ROLE_OWNER])->group(function () {
                Route::get('access', RoleAccessController::class)->name('access');
                Route::get('invitations', [PropertyOnboardingController::class, 'invitations']);
                Route::post('invitations', [PropertyOnboardingController::class, 'issue'])->middleware('throttle:30,1');
                Route::post('invitations/{id}/rotate', [PropertyOnboardingController::class, 'issue'])->whereNumber('id')->middleware('throttle:30,1');
                Route::delete('invitations/{id}', [PropertyOnboardingController::class, 'revokeInvite'])->whereNumber('id')->middleware('throttle:30,1');
                Route::get('join-requests', [PropertyOnboardingController::class, 'ownerRequests']);
                Route::put('join-requests/{id}', [PropertyOnboardingController::class, 'decide'])->whereNumber('id')->middleware('throttle:60,1');
                Route::delete('members/{id}', [PropertyOnboardingController::class, 'revokeMember'])->whereNumber('id')->middleware('throttle:30,1');
                Route::prefix('marketplace')->group(function () {
                    Route::get('listings', [RoomMarketplaceController::class, 'ownerListings']);
                    Route::post('listings', [RoomMarketplaceController::class, 'saveListing'])->middleware('throttle:30,1');
                    Route::post('listings/{id}', [RoomMarketplaceController::class, 'saveListing'])->whereNumber('id')->middleware('throttle:30,1');
                    Route::delete('listings/{id}', [RoomMarketplaceController::class, 'hideListing'])->whereNumber('id');
                    Route::get('requests', [RoomMarketplaceController::class, 'ownerRequests']);
                    Route::put('requests/{id}', [RoomMarketplaceController::class, 'ownerAction'])->whereNumber('id');
                    Route::post('requests/{id}/move-in', [RoomMarketplaceController::class, 'moveIn'])->whereNumber('id')->middleware('throttle:30,1');
                });
                require __DIR__.'/owner.php';
            });

            Route::prefix('tenant')->name('tenant.')->middleware(['property.context', 'role:'.User::ROLE_TENANT])->group(function () {
                Route::get('workspace', PropertyMemberHomeController::class)->name('workspace');
                Route::get('announcements/{announcement}', AnnouncementController::class)->name('announcements.show');
                Route::get('access', RoleAccessController::class)->name('access');
                Route::get('profile', [ProfileController::class, 'show'])->name('profile');
                Route::get('current-stay', [ProfileController::class, 'currentStay'])->name('current-stay');
                Route::get('checkout-requests', [CheckoutApplicationController::class, 'index'])->name('checkout-requests.index');
                Route::post('occupancies/{occupancy}/checkout-requests', [CheckoutApplicationController::class, 'store'])->whereNumber('occupancy')->name('checkout-requests.store');
                Route::delete('checkout-requests/{checkoutApplication}', [CheckoutApplicationController::class, 'destroy'])->whereNumber('checkoutApplication')->name('checkout-requests.destroy');
                Route::get('stays', [ProfileController::class, 'stays'])->name('stays');
                Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
                Route::get('invoices/{invoice}/pdf', [PdfDocumentController::class, 'invoice'])->whereNumber('invoice')->name('invoices.pdf');
                Route::get('invoices/{invoice}/payments/{payment}/pdf', [PdfDocumentController::class, 'receipt'])->whereNumber(['invoice', 'payment'])->name('payments.pdf');
                Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
                Route::get('invoices/{invoice}/payos', [InvoicePayosController::class, 'show'])->whereNumber('invoice')->middleware('throttle:60,1,payos-status')->name('invoices.payos.show');
                Route::post('invoices/{invoice}/payos', [InvoicePayosController::class, 'store'])->whereNumber('invoice')->middleware('throttle:10,1,payos-start')->name('invoices.payos.store');
                Route::get('maintenance-requests/eligible-occupancies', [MaintenanceRequestController::class, 'eligibleOccupancies'])->name('maintenance-requests.eligible-occupancies');
                Route::get('maintenance-requests', [MaintenanceRequestController::class, 'index'])->name('maintenance-requests.index');
                Route::get('maintenance-requests/{maintenanceRequest}/history', [MaintenanceRequestController::class, 'history'])->name('maintenance-requests.history');
                Route::post('maintenance-requests/{maintenanceRequest}/photos', [MaintenancePhotoController::class, 'store'])->name('maintenance-requests.photos.store');
                Route::get('maintenance-requests/{maintenanceRequest}/photos/{photo}', [MaintenancePhotoController::class, 'show'])->name('maintenance-requests.photos.show');
                Route::get('maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'show'])->name('maintenance-requests.show');
                Route::post('occupancies/{occupancy}/maintenance-requests', [MaintenanceRequestController::class, 'store'])->name('maintenance-requests.store');
            });
        });
    });
});
