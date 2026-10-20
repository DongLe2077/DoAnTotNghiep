<?php

namespace App\Services;

use App\Models\MaintenancePhoto;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MaintenancePhotos
{
    public function upload(int|string $reportId, array $files, User $actor): Collection
    {
        MaintenanceRequest::query()->where('submitted_by', $actor->id)->findOrFail($reportId);
        $paths = [];
        try {
            // Files are compensated on failure; do not retry filesystem work on deadlocks.
            return DB::transaction(function () use ($reportId, $files, $actor, &$paths) {
                $report = MaintenanceRequest::query()->where('submitted_by', $actor->id)->lockForUpdate()->findOrFail($reportId);
                abort_if($report->status === 'resolved', 409, 'Yêu cầu đã hoàn tất, không thể bổ sung ảnh.');
                $existing = $report->photos()->get()->keyBy('sha256');
                $unique = [];
                foreach ($files as $file) {
                    $hash = hash_file('sha256', $file->getRealPath());
                    $unique[$hash] = $file;
                }
                $new = array_diff_key($unique, $existing->all());
                abort_if($existing->count() + count($new) > 5, 422, 'Mỗi yêu cầu tối đa 5 ảnh khác nhau.');
                foreach ($new as $hash => $file) {
                    $path = $report->id.'/'.Str::uuid().'.'.$file->extension();
                    $paths[] = $path;
                    $file->storeAs((string) $report->id, basename($path), 'maintenance');
                    MaintenancePhoto::create(['maintenance_request_id' => $report->id, 'uploaded_by' => $actor->id,
                        'path' => $path, 'sha256' => $hash, 'mime_type' => $file->getMimeType(), 'byte_size' => $file->getSize()]);
                }
                if ($new !== []) {
                    $report->revision++;
                    $report->save();
                }

                return $report->photos()->get();
            });
        } catch (Throwable $error) {
            foreach ($paths as $path) {
                try {
                    Storage::disk('maintenance')->delete($path);
                } catch (Throwable $cleanupError) {
                    report($cleanupError);
                }
            }
            throw $error;
        }
    }
}
