<?php

use App\Models\Announcement;
use App\Models\AppNotification;
use App\Models\CashCreationRequest;
use App\Models\CashTransaction;
use App\Models\CashTransactionHistory;
use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\InvoiceChange;
use App\Models\InvoiceItem;
use App\Models\MaintenanceExpenseHistory;
use App\Models\MaintenanceExpenseLink;
use App\Models\MaintenancePhoto;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestHistory;
use App\Models\MeterReading;
use App\Models\MeterReadingRequestRecord;
use App\Models\Occupancy;
use App\Models\OccupancyService;
use App\Models\Payment;
use App\Models\PaymentCorrection;
use App\Models\RentalGroup;
use App\Models\RentalProperty;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;

return [
    'models' => [
        'announcements' => Announcement::class,
        'app_notifications' => AppNotification::class,
        'cash_creation_requests' => CashCreationRequest::class,
        'cash_transaction_histories' => CashTransactionHistory::class,
        'cash_transactions' => CashTransaction::class,
        'invoice_access_grants' => InvoiceAccessGrant::class,
        'invoice_changes' => InvoiceChange::class,
        'invoice_items' => InvoiceItem::class,
        'invoices' => Invoice::class,
        'maintenance_expense_histories' => MaintenanceExpenseHistory::class,
        'maintenance_expense_links' => MaintenanceExpenseLink::class,
        'maintenance_photos' => MaintenancePhoto::class,
        'maintenance_request_histories' => MaintenanceRequestHistory::class,
        'maintenance_requests' => MaintenanceRequest::class,
        'meter_reading_requests' => MeterReadingRequestRecord::class,
        'meter_readings' => MeterReading::class,
        'occupancies' => Occupancy::class,
        'occupancy_services' => OccupancyService::class,
        'payment_corrections' => PaymentCorrection::class,
        'payments' => Payment::class,
        'rental_groups' => RentalGroup::class,
        'rental_properties' => RentalProperty::class,
        'representative_assignments' => RepresentativeAssignment::class,
        'rooms' => Room::class,
        'services' => Service::class,
        'tenant_stays' => TenantStay::class,
        'tenants' => Tenant::class,
        'users' => User::class,
    ],
];
