<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_TENANT = 'tenant';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'tenant_id',
        'username',
        'password_hash',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'tenant_id' => 'integer',
            'password_hash' => 'hashed',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'recorded_by', 'id');
    }

    public function invoiceAccessGrants(): HasMany
    {
        return $this->hasMany(InvoiceAccessGrant::class, 'user_id', 'id');
    }

    public function grantedInvoiceAccessGrants(): HasMany
    {
        return $this->hasMany(InvoiceAccessGrant::class, 'granted_by', 'id');
    }

    public function issuedInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'issued_by', 'id');
    }

    public function submittedMaintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'submitted_by', 'id');
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class, 'recorded_by', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'recorded_by', 'id');
    }
}
