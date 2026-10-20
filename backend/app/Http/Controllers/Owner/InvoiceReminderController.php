<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\AppNotifications;
use Illuminate\Http\JsonResponse;

class InvoiceReminderController extends Controller
{
    public function __invoke(Invoice $invoice, AppNotifications $notifications): JsonResponse
    {
        $result = $notifications->remind($invoice);
        abort_if($result['notification'] === null, 409, $result['skip_reason']);

        return response()->json(['message' => $result['replayed'] ? 'Hóa đơn đã được nhắc cho người này hôm nay.' : 'Đã tạo thông báo nhắc thanh toán.',
            'replayed' => $result['replayed'], 'data' => ['notification_id' => $result['notification']->id]], $result['replayed'] ? 200 : 201);
    }
}
