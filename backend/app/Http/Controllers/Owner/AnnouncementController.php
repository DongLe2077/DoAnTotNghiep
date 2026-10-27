<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\AnnouncementIndexRequest;
use App\Http\Requests\Owner\PreviewAnnouncementRequest;
use App\Http\Requests\Owner\PublishAnnouncementRequest;
use App\Http\Resources\AnnouncementRecipientResource;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Services\Announcements;

class AnnouncementController extends Controller
{
    public function __construct(private readonly Announcements $announcements) {}

    public function index(AnnouncementIndexRequest $request)
    {
        return AnnouncementResource::collection($this->announcements->query($request->validated())->orderByDesc('id')
            ->paginate($request->validated('per_page', 15))->withQueryString());
    }

    public function preview(PreviewAnnouncementRequest $request)
    {
        return response()->json(['data' => $this->announcements->preview($request->validated())]);
    }

    public function store(PublishAnnouncementRequest $request)
    {
        $result = $this->announcements->publish($request->validated(), $request->user('web')->id);

        return response()->json(['replayed' => $result['replayed'], 'data' => $this->resource($result['announcement']->id)], $result['replayed'] ? 200 : 201);
    }

    public function show(Announcement $announcement)
    {
        return $this->resource($announcement->id);
    }

    public function recipients(AnnouncementIndexRequest $request, Announcement $announcement)
    {
        $query = $announcement->notifications()->with('user.tenant');
        if ($request->validated('read_status') === 'read') {
            $query->whereNotNull('read_at');
        } elseif ($request->validated('read_status') === 'unread') {
            $query->whereNull('read_at');
        }

        return AnnouncementRecipientResource::collection($query->orderBy('id')->paginate($request->validated('per_page', 15))->withQueryString());
    }

    private function resource(int $id): AnnouncementResource
    {
        return new AnnouncementResource($this->announcements->query()->findOrFail($id));
    }
}
