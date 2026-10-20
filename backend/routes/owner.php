<?php

use App\Http\Controllers\MaintenancePhotoController;
use App\Http\Controllers\Owner\BillingRepresentativeController;
use App\Http\Controllers\Owner\CashFlowController;
use App\Http\Controllers\Owner\CashTransactionController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\InvoiceController;
use App\Http\Controllers\Owner\InvoiceIssueController;
use App\Http\Controllers\Owner\InvoiceReminderController;
use App\Http\Controllers\Owner\MaintenanceRequestController;
use App\Http\Controllers\Owner\MeterReadingController;
use App\Http\Controllers\Owner\OccupancyController;
use App\Http\Controllers\Owner\PaymentController;
use App\Http\Controllers\Owner\ReceivableController;
use App\Http\Controllers\Owner\RentalGroupController;
use App\Http\Controllers\Owner\RoomController;
use App\Http\Controllers\Owner\ServiceController;
use App\Http\Controllers\Owner\TenantController;
use Illuminate\Support\Facades\Route;

// Included inside the authenticated, active owner route group.
Route::get('dashboard', DashboardController::class)->name('dashboard');
Route::get('receivables/summary', [ReceivableController::class, 'summary'])->name('receivables.summary');
Route::get('receivables/groups', [ReceivableController::class, 'groups'])->name('receivables.groups');
Route::get('receivables', [ReceivableController::class, 'index'])->name('receivables.index');
Route::get('rooms/rental-rate', [RoomController::class, 'rentalRate'])->name('rooms.rental-rate.show');
Route::put('rooms/rental-rate', [RoomController::class, 'updateRentalRate'])->name('rooms.rental-rate.update');
Route::apiResource('rooms', RoomController::class);
Route::apiResource('services', ServiceController::class);

Route::put('tenants/{tenant}/account', [TenantController::class, 'account'])->name('tenants.account');
Route::get('tenants/{tenant}/stays', [TenantController::class, 'stays'])->name('tenants.stays');
Route::apiResource('tenants', TenantController::class);
Route::apiResource('rental-groups', RentalGroupController::class)->parameters(['rental-groups' => 'rentalGroup']);
Route::apiResource('occupancies', OccupancyController::class)->only(['index', 'store', 'show']);
Route::post('occupancies/{occupancy}/members', [OccupancyController::class, 'addMembers'])->name('occupancies.members.add');
Route::post('occupancies/{occupancy}/members/{stay}/leave', [OccupancyController::class, 'leaveMember'])->name('occupancies.members.leave');
Route::post('occupancies/{occupancy}/representative', [OccupancyController::class, 'changeRepresentative'])->name('occupancies.representative');
Route::post('occupancies/{occupancy}/services', [OccupancyController::class, 'applyService'])->name('occupancies.services.apply');
Route::post('occupancies/{occupancy}/services/{period}/end', [OccupancyController::class, 'endService'])->name('occupancies.services.end');
Route::post('occupancies/{occupancy}/transfer', [OccupancyController::class, 'transfer'])->name('occupancies.transfer');
Route::post('occupancies/{occupancy}/checkout', [OccupancyController::class, 'checkOut'])->name('occupancies.checkout');
Route::post('occupancies/{occupancy}/billing-representative', BillingRepresentativeController::class)->name('occupancies.billing-representative');

Route::get('occupancies/{occupancy}/meter-readings', [MeterReadingController::class, 'index'])->name('meter-readings.index');
Route::post('occupancies/{occupancy}/meter-readings', [MeterReadingController::class, 'store'])->name('meter-readings.store');
Route::put('occupancies/{occupancy}/meter-readings/{reading}', [MeterReadingController::class, 'update'])->name('meter-readings.update');
Route::delete('occupancies/{occupancy}/meter-readings/{reading}', [MeterReadingController::class, 'destroy'])->name('meter-readings.destroy');
Route::post('occupancies/{occupancy}/invoices/preview', [InvoiceIssueController::class, 'preview'])->name('invoices.preview');
Route::post('occupancies/{occupancy}/invoices', [InvoiceIssueController::class, 'store'])->name('invoices.store');
Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
Route::post('invoices/{invoice}/reminder', InvoiceReminderController::class)->middleware('throttle:10,1')->name('invoices.reminder');
Route::put('invoices/{invoice}/access', [InvoiceController::class, 'access'])->name('invoices.access');
Route::delete('invoices/{invoice}/access', [InvoiceController::class, 'revokeAccess'])->name('invoices.access.revoke');
Route::get('invoices/{invoice}/payments', [PaymentController::class, 'forInvoice'])->name('invoices.payments.index');
Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
Route::get('cash-transactions/{cashTransaction}/history', [CashTransactionController::class, 'history'])->name('cash-transactions.history');
Route::post('cash-transactions/{cashTransaction}/revert', [CashTransactionController::class, 'revert'])->name('cash-transactions.revert');
Route::apiResource('cash-transactions', CashTransactionController::class)->parameters(['cash-transactions' => 'cashTransaction']);
Route::get('cash-flow/summary', [CashFlowController::class, 'summary'])->name('cash-flow.summary');
Route::get('cash-flow', [CashFlowController::class, 'index'])->name('cash-flow.index');
Route::apiResource('maintenance-requests', MaintenanceRequestController::class)->parameters(['maintenance-requests' => 'maintenanceRequest'])->only(['index', 'show', 'update']);
Route::get('maintenance-requests/{maintenanceRequest}/photos/{photo}', [MaintenancePhotoController::class, 'show'])->name('maintenance-requests.photos.show');
