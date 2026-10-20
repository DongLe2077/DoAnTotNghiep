<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenant\UploadMaintenancePhotosRequest;
use App\Http\Resources\MaintenancePhotoResource;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\MaintenancePhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaintenancePhotoController extends Controller
{
    public function store(UploadMaintenancePhotosRequest $request, string $maintenanceRequest, MaintenancePhotos $photos): JsonResponse
    {
        $result = $photos->upload($maintenanceRequest, $request->file('photos'), $request->user('web'));

        return response()->json(['message' => 'Đã lưu ảnh báo hỏng; ảnh trùng được giữ một bản.',
            'data' => MaintenancePhotoResource::collection($result)]);
    }

    public function show(Request $request, string $maintenanceRequest, string $photo): StreamedResponse
    {
        $query = MaintenanceRequest::query();
        if (! $request->user('web')->hasRole(User::ROLE_OWNER)) {
            $query->where('submitted_by', $request->user('web')->id);
        }
        $entry = $query->findOrFail($maintenanceRequest)->photos()->findOrFail($photo);
        $disk = Storage::disk('maintenance');
        abort_unless($disk->exists($entry->path), 404, 'Ảnh không còn trong bộ lưu trữ.');

        return $disk->response($entry->path, null, ['Content-Type' => $entry->mime_type,
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
    }
}
