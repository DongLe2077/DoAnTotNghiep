<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\InvoiceItem;
use App\Models\MeterReading;
use App\Models\Occupancy;
use App\Models\OccupancyService;
use App\Models\RentalGroup;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class InvoiceBilling
{
    public function preview(Occupancy $occupancy, string $month): array
    {
        return DB::transaction(fn () => $this->calculate($this->lockContext($occupancy), $month), 3);
    }

    public function issue(Occupancy $occupancy, array $data, int $actorId): Invoice
    {
        try {
            return DB::transaction(function () use ($occupancy, $data, $actorId) {
                $locked = $this->lockContext($occupancy);
                $preview = $this->calculate($locked, $data['billing_month']);
                abort_unless($preview['can_issue'], 409, implode(' ', $preview['blockers']));
                abort_unless(hash_equals($preview['preview_token'], $data['preview_token']), 409, 'Dữ liệu tính tiền đã thay đổi. Hãy xem trước hóa đơn lại trước khi phát hành.');
                $invoice = Invoice::create([
                    'invoice_number' => 'HD-'.str_replace('-', '', $data['billing_month']).'-'.$locked->id,
                    'occupancy_id' => $locked->id, 'billing_month' => $data['billing_month'].'-01',
                    'representative_assignment_id' => $preview['representative_assignment_id'], 'issued_by' => $actorId,
                ]);
                foreach ($preview['items'] as $line) {
                    InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $locked->id] + array_intersect_key($line, array_flip([
                        'item_type', 'description', 'unit', 'quantity', 'unit_price', 'occupancy_service_id',
                        'meter_reading_id', 'charge_period_start', 'charge_period_end',
                    ])));
                }
                if ($preview['recipient_user_id'] !== null) {
                    InvoiceAccessGrant::create([
                        'invoice_id' => $invoice->id, 'occupancy_id' => $locked->id,
                        'user_id' => $preview['recipient_user_id'],
                        'representative_assignment_id' => $preview['representative_assignment_id'],
                        'granted_by' => $actorId, 'grant_reason' => 'Cấp cho người đại diện khi phát hành hóa đơn.',
                    ]);
                }

                return $invoice;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Hóa đơn hoặc nguồn tính tiền đã được sử dụng. Hãy tải lại danh sách hóa đơn.');
        }
    }

    private function lockContext(Occupancy $occupancy): Occupancy
    {
        Room::query()->lockForUpdate()->findOrFail($occupancy->room_id);
        RentalGroup::query()->lockForUpdate()->findOrFail($occupancy->rental_group_id);

        return Occupancy::query()->lockForUpdate()->findOrFail($occupancy->id);
    }

    private function calculate(Occupancy $occupancy, string $month): array
    {
        $monthStart = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01', config('app.timezone'));
        $monthEnd = $monthStart->addMonth();
        $now = CarbonImmutable::now()->startOfSecond();
        $start = $monthStart->max($occupancy->started_at);
        $end = $monthEnd->min($occupancy->ended_at ?? $monthEnd);
        abort_unless($start->lessThan($end) && $start->lessThanOrEqualTo($now), 422, 'Tháng tính tiền không giao với thời gian thuê đã diễn ra.');
        $blockers = [];
        $warnings = [];
        if ($end->greaterThan($now)) {
            $blockers[] = 'Chỉ phát hành sau khi hết tháng hoặc đã ghi nhận trả/chuyển phòng.';
        }
        $cutoff = $end->min($now);
        $existing = $occupancy->invoices()->whereDate('billing_month', $month.'-01')->lockForUpdate()->first();
        if ($existing !== null) {
            $blockers[] = 'Đợt thuê đã có hóa đơn cho tháng này.';
        }

        // A group lock serializes all invoices, including transfers between rooms.
        // Locking reads avoid stale repeatable-read snapshots on MySQL.
        $groupInvoices = Invoice::query()->whereIn('occupancy_id', Occupancy::query()->select('id')->where('rental_group_id', $occupancy->rental_group_id))
            ->whereDate('billing_month', $month.'-01')->orderBy('id')->lockForUpdate()->get();
        $rentInvoice = null;
        foreach ($groupInvoices as $priorInvoice) {
            $rentLines = $priorInvoice->items()->where('item_type', 'rent')->lockForUpdate()->get();
            if ($rentLines->count() !== 1) {
                $blockers[] = 'Hóa đơn đã có của nhóm thiếu hoặc trùng dòng tiền phòng, cần kiểm tra dữ liệu.';
            }
            $rentInvoice ??= $priorInvoice;
        }
        $rentSource = Occupancy::query()->where('rental_group_id', $occupancy->rental_group_id)
            ->where('started_at', '<', $monthEnd)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $monthStart))
            ->orderBy('started_at')->orderBy('id')->lockForUpdate()->firstOrFail();
        $rent = $rentInvoice === null ? $rentSource->monthly_rent : '0.00';
        $items = [$this->line([
            'item_type' => 'rent', 'description' => $rentInvoice === null ? 'Tiền phòng tháng '.$month : 'Tiền phòng tháng '.$month.' đã tính trên hóa đơn '.$rentInvoice->invoice_number,
            'unit' => 'tháng', 'quantity' => '1.000', 'unit_price' => $rent,
            'occupancy_service_id' => null, 'meter_reading_id' => null,
            'charge_period_start' => null, 'charge_period_end' => null,
        ], $blockers)];

        $periods = $occupancy->occupancyServices()->where('effective_from', '<', $cutoff)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $start))
            ->orderBy('service_id')->orderBy('effective_from')->orderBy('id')->lockForUpdate()->get();
        $previousEnds = [];
        foreach ($periods as $period) {
            $from = $start->max($period->effective_from);
            $until = $cutoff->min($period->effective_until ?? $cutoff);
            if (! $from->lessThan($until)) {
                continue;
            }
            if (isset($previousEnds[$period->service_id]) && $from->lessThan($previousEnds[$period->service_id])) {
                $blockers[] = "Dịch vụ #{$period->service_id} có khoảng áp dụng trùng nhau.";
            }
            $previousEnds[$period->service_id] = $until;
            $charged = InvoiceItem::query()->where('occupancy_service_id', $period->id)
                ->where('charge_period_start', '<', $until)->where('charge_period_end', '>', $from)->lockForUpdate()->first();
            if ($charged !== null) {
                $blockers[] = "Khoảng dịch vụ #{$period->id} đã được tính trên hóa đơn đã phát hành.";
            }
            $name = $period->service()->value('name') ?? 'Dịch vụ #'.$period->service_id;
            if ($period->billing_method === 'metered') {
                $this->meteredLines($period, $from, $until, $name, $items, $blockers);
            } elseif ($period->billing_method === 'per_room') {
                // Keep quantity one: a three-decimal month fraction would lose money precision.
                // The linked immutable period retains the original monthly rate.
                $seconds = $until->getTimestamp() - $from->getTimestamp();
                $monthSeconds = $monthEnd->getTimestamp() - $monthStart->getTimestamp();
                $price = BigDecimal::of($period->unit_price)->multipliedBy($seconds)->dividedBy($monthSeconds, 4, RoundingMode::HALF_UP);
                $items[] = $this->line([
                    'item_type' => 'service', 'description' => mb_substr($name.' — phí phân bổ theo thời gian sử dụng trong tháng', 0, 255),
                    'unit' => $period->unit, 'quantity' => '1.000', 'unit_price' => (string) $price,
                    'occupancy_service_id' => $period->id, 'meter_reading_id' => null,
                    'charge_period_start' => $from->format('Y-m-d H:i:s'), 'charge_period_end' => $until->format('Y-m-d H:i:s'),
                    'source_unit_price' => $period->unit_price, 'billing_method' => 'per_room',
                    'duration_seconds' => $seconds, 'month_seconds' => $monthSeconds,
                ], $blockers);
            } else {
                $blockers[] = "Dịch vụ #{$period->id} có cách tính phí không hợp lệ.";
            }
        }

        $representatives = $occupancy->representativeAssignments()->where('started_at', '<=', $now)
            ->orderByDesc('started_at')->orderByDesc('id')->lockForUpdate()->get();
        $representative = $representatives->first(fn ($assignment) => $occupancy->ended_at === null
            ? $assignment->ended_at === null
            : $assignment->started_at->lessThan($occupancy->ended_at) && ($assignment->ended_at === null || $assignment->ended_at->greaterThanOrEqualTo($occupancy->ended_at)));
        $recipient = null;
        if ($representative !== null) {
            $stay = $representative->tenantStay()->lockForUpdate()->first();
            if ($stay !== null && $stay->occupancy_id === $occupancy->id) {
                Tenant::query()->lockForUpdate()->findOrFail($stay->tenant_id);
                $recipient = User::query()->where('tenant_id', $stay->tenant_id)->lockForUpdate()->first();
                if ($occupancy->ended_at === null && $stay->moved_out_at !== null) {
                    $recipient = null;
                }
            }
        }
        if ($representative === null) {
            $blockers[] = $occupancy->ended_at === null
                ? 'Đợt thuê chưa có người đại diện. Cần chọn người đại diện đang cư trú để lập hóa đơn.'
                : 'Đợt thuê đã đóng nhưng chưa có đại diện. Chủ trọ cần bổ sung đại diện lập hóa đơn từ thành viên còn ở tại lúc kết thúc thuê.';
        }
        if ($recipient === null || ! $recipient->is_active || ! $recipient->hasRole(User::ROLE_TENANT)) {
            if ($occupancy->ended_at === null) {
                $blockers[] = 'Cần người đại diện của đợt thuê có tài khoản người thuê đang hoạt động.';
            } else {
                $warnings[] = 'Đại diện lịch sử chưa có tài khoản đang hoạt động; hóa đơn được lưu cho chủ trọ và chưa cấp quyền người thuê.';
            }
            $recipient = null;
        }
        $total = BigDecimal::zero()->toScale(2);
        foreach ($items as $item) {
            $total = $total->plus($item['line_total']);
        }
        $result = [
            'occupancy_id' => $occupancy->id, 'billing_month' => $month,
            'period_start' => $start->format('Y-m-d H:i:s'), 'period_end' => $cutoff->format('Y-m-d H:i:s'),
            'is_final_period' => $end->lessThanOrEqualTo($now),
            'existing_invoice_id' => $existing?->id, 'rent_already_billed_invoice_id' => $rentInvoice?->id,
            'rent_source_occupancy_id' => $rentSource->id,
            'representative_assignment_id' => $representative?->id, 'recipient_user_id' => $recipient?->id,
            'items' => $items, 'total_amount' => (string) $total,
            'can_issue' => $blockers === [], 'blockers' => array_values(array_unique($blockers)), 'warnings' => $warnings,
        ];
        $result['preview_token'] = hash_hmac('sha256', json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), config('app.key'));

        return $result;
    }

    private function meteredLines(OccupancyService $period, CarbonImmutable $from, CarbonImmutable $until, string $name, array &$items, array &$blockers): void
    {
        $readings = MeterReading::query()->where('occupancy_service_id', $period->id)
            ->where('period_start', '<', $until)->where('period_end', '>', $from)
            ->orderBy('period_start')->orderBy('id')->lockForUpdate()->get();
        $cursor = $from;
        $predecessor = MeterReading::query()->whereIn('occupancy_service_id', OccupancyService::query()->select('id')
            ->where('occupancy_id', $period->occupancy_id)->where('service_id', $period->service_id))
            ->where('period_end', '<=', $from)->orderByDesc('period_end')->orderByDesc('id')->lockForUpdate()->first();
        $lastValue = $predecessor?->period_end->equalTo($from) ? $predecessor->current_reading : null;
        foreach ($readings as $reading) {
            if (! $cursor->equalTo($reading->period_start) || $reading->period_end->greaterThan($until)
                || ! $reading->period_end->greaterThan($reading->period_start)
                || BigDecimal::of($reading->current_reading)->isLessThan($reading->previous_reading)
                || BigDecimal::of($reading->previous_reading)->isNegative()
                || ($lastValue !== null && ! BigDecimal::of($reading->previous_reading)->isEqualTo($lastValue))) {
                $blockers[] = "Kỳ chỉ số {$name} (#{$period->id}) bị hở, trùng, sai chỉ số hoặc vượt khoảng tính tiền.";

                continue;
            }
            $used = InvoiceItem::query()->where('meter_reading_id', $reading->id)->lockForUpdate()->first();
            if ($used !== null) {
                $blockers[] = "Chỉ số #{$reading->id} đã được tính trên hóa đơn khác.";
            }
            $items[] = $this->line([
                'item_type' => 'service', 'description' => mb_substr($name.' — tính theo chỉ số', 0, 255),
                'unit' => $period->unit, 'quantity' => (string) BigDecimal::of($reading->current_reading)->minus($reading->previous_reading)->toScale(3),
                'unit_price' => $period->unit_price, 'source_unit_price' => $period->unit_price, 'billing_method' => 'metered',
                'occupancy_service_id' => $period->id, 'meter_reading_id' => $reading->id,
                'charge_period_start' => $reading->period_start->format('Y-m-d H:i:s'),
                'charge_period_end' => $reading->period_end->format('Y-m-d H:i:s'),
                'previous_reading' => $reading->previous_reading, 'current_reading' => $reading->current_reading,
            ], $blockers);
            $cursor = CarbonImmutable::instance($reading->period_end);
            $lastValue = $reading->current_reading;
        }
        if (! $cursor->equalTo($until)) {
            $blockers[] = "Chưa đủ chỉ số {$name} (#{$period->id}) đến {$until->format('Y-m-d H:i:s')}.";
        }
    }

    private function line(array $data, array &$blockers): array
    {
        $data['quantity'] = (string) BigDecimal::of($data['quantity'])->toScale(3);
        $data['unit_price'] = (string) BigDecimal::of($data['unit_price'])->toScale(4);
        $data['line_total'] = (string) BigDecimal::of($data['quantity'])->multipliedBy($data['unit_price'])->toScale(2, RoundingMode::HALF_UP);
        if (BigDecimal::of($data['unit_price'])->isGreaterThan('99999999999999.9999')
            || BigDecimal::of($data['unit_price'])->isNegative()
            || BigDecimal::of($data['quantity'])->isGreaterThan('999999999999.999')
            || BigDecimal::of($data['quantity'])->isNegative()) {
            $blockers[] = 'Đơn giá hoặc số lượng vượt giới hạn của dòng hóa đơn.';
        }
        if (BigDecimal::of($data['line_total'])->isGreaterThan('9999999999999999.99')) {
            $blockers[] = 'Một dòng hóa đơn vượt giới hạn số tiền của CSDL.';
        }

        return $data;
    }
}
