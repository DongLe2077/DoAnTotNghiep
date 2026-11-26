<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ScheduledDeletion;
use App\Services\ScheduledDeletions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScheduledDeletionController extends Controller
{
    public function __construct(private readonly ScheduledDeletions $deletions) {}

    public function index(): JsonResponse
    {
        $jobs = ScheduledDeletion::query()->orderBy('purge_at')->orderBy('id')->paginate(30);

        return response()->json([
            'data' => $jobs->getCollection()->map(fn (ScheduledDeletion $job) => $this->data($job)),
            'meta' => ['current_page' => $jobs->currentPage(), 'last_page' => $jobs->lastPage(), 'total' => $jobs->total()],
        ]);
    }

    public function show(string $type, int $id): JsonResponse
    {
        $job = ScheduledDeletion::query()->where('record_type', $type)->where('record_id', $id)->first();

        return response()->json(['data' => $job ? $this->data($job) : null]);
    }

    public function preview(string $type, int $id): JsonResponse
    {
        return response()->json(['data' => $this->deletions->preview($type, $id)]);
    }

    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $input = $request->validate(['confirmation' => ['required', 'string', 'size:64']]);
        $job = $this->deletions->schedule($type, $id, $input['confirmation']);

        return response()->json(['data' => $this->data($job)], 202);
    }

    public function previewPurge(int $job): JsonResponse
    {
        return response()->json(['data' => $this->deletions->previewPurge($job)]);
    }

    public function purge(Request $request, int $job): Response
    {
        $input = $request->validate(['confirmation' => ['required', 'string', 'size:64']]);
        $this->deletions->purgeNow($job, $input['confirmation']);

        return response()->noContent();
    }

    public function destroy(int $job): Response
    {
        $this->deletions->cancel($job);

        return response()->noContent();
    }

    private function data(ScheduledDeletion $job): array
    {
        return [
            'id' => $job->id, 'record_type' => $job->record_type, 'record_id' => $job->record_id,
            'label' => $job->label, 'purge_at' => $job->purge_at->toIso8601String(),
            'problem' => $job->problem, 'cleaning_photos' => $job->photo_paths !== null,
            'impact' => $this->deletions->impact($job->records),
        ];
    }
}
