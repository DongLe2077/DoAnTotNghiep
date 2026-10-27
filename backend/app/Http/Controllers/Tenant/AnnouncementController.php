<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\AppNotifications;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __invoke(Request $request, string $announcement, AppNotifications $notifications)
    {
        $receipt = $notifications->visible($request->user('web'))->where('type', 'owner_announcement')->where('announcement_id', $announcement)
            ->with('announcement')->firstOrFail();
        $entry = $receipt->announcement;

        return response()->json(['data' => ['id' => $entry->id, 'title' => $entry->title, 'body' => $entry->body,
            'created_at' => $entry->created_at->format('Y-m-d H:i:s'), 'notification_id' => $receipt->id,
            'is_read' => $receipt->read_at !== null, 'read_at' => $receipt->read_at?->format('Y-m-d H:i:s')]]);
    }
}
