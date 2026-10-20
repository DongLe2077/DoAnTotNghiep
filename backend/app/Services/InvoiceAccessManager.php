<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\Occupancy;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceAccessManager
{
    public function grant(Invoice $invoice, array $data, int $actorId): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $actorId) {
            $locked = $this->lockInvoice($invoice);
            $assignment = RepresentativeAssignment::query()->where('occupancy_id', $locked->occupancy_id)
                ->lockForUpdate()->find($data['representative_assignment_id']);
            if ($assignment === null) {
                throw ValidationException::withMessages(['representative_assignment_id' => 'Người đại diện phải thuộc đúng đợt thuê của hóa đơn.']);
            }
            $stay = $assignment->tenantStay()->firstOrFail();
            Tenant::query()->lockForUpdate()->findOrFail($stay->tenant_id);
            $user = User::query()->where('tenant_id', $stay->tenant_id)->lockForUpdate()->first();
            if ($user === null || ! $user->is_active || ! $user->hasRole(User::ROLE_TENANT)) {
                throw ValidationException::withMessages(['representative_assignment_id' => 'Người đại diện cần tài khoản người thuê đang hoạt động.']);
            }
            $this->revokeCurrent($locked);
            InvoiceAccessGrant::create([
                'invoice_id' => $locked->id,
                'occupancy_id' => $locked->occupancy_id,
                'user_id' => $user->id,
                'representative_assignment_id' => $assignment->id,
                'granted_by' => $actorId,
                'grant_reason' => $data['grant_reason'],
            ]);

            return $locked;
        }, 3);
    }

    public function revoke(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $locked = $this->lockInvoice($invoice);
            $this->revokeCurrent($locked);

            return $locked;
        }, 3);
    }

    private function lockInvoice(Invoice $invoice): Invoice
    {
        $scope = Occupancy::query()->findOrFail($invoice->occupancy_id);
        Room::query()->lockForUpdate()->findOrFail($scope->room_id);
        RentalGroup::query()->lockForUpdate()->findOrFail($scope->rental_group_id);
        Occupancy::query()->lockForUpdate()->findOrFail($scope->id);

        return Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
    }

    private function revokeCurrent(Invoice $invoice): void
    {
        $grants = InvoiceAccessGrant::query()->where('invoice_id', $invoice->id)->whereNull('revoked_at')->lockForUpdate()->get();
        $now = now();
        foreach ($grants as $grant) {
            abort_if($grant->granted_at->greaterThan($now), 409, 'Thời điểm cấp quyền đang ở tương lai, chưa thể thu hồi.');
            $grant->revoked_at = $now;
            $grant->save();
        }
    }
}
