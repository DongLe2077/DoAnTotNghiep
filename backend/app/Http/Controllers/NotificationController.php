<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationIndexRequest;
use App\Http\Resources\AppNotificationResource;
use App\Services\AppNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function __construct(private readonly AppNotifications $notifications) {}

    public function index(NotificationIndexRequest $request): AnonymousResourceCollection
    {
        $query = $this->notifications->visible($request->user('web'));
        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }
        if ($request->has('type')) {
            $query->where('type', $request->validated('type'));
        }

        return AppNotificationResource::collection($query->orderByDesc('id')->paginate($request->validated('per_page', 15))->withQueryString());
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread_count' => $this->notifications->visible($request->user('web'))->whereNull('read_at')->count()]]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $entry = $this->notifications->visible($request->user('web'))->findOrFail($notification);
        $this->notifications->visible($request->user('web'))->whereKey($entry->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => new AppNotificationResource($this->notifications->visible($request->user('web'))->findOrFail($entry->id))]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $count = $this->notifications->visible($request->user('web'))->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'Đã đánh dấu thông báo đã đọc.', 'data' => ['updated_count' => $count]]);
    }
}
