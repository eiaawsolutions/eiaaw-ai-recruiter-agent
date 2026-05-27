<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunSourcingJob;
use App\Models\JobPosting;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPostingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jobs = JobPosting::query()
            ->latest()
            ->limit(min(100, max(1, (int) $request->query('limit', 25))))
            ->get();

        return response()->json([
            'data' => $jobs->map(fn ($j) => $this->present($j)),
        ]);
    }

    public function show(string $publicId): JsonResponse
    {
        $job = JobPosting::query()->where('public_id', $publicId)->firstOrFail();
        return response()->json(['data' => $this->present($job)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'         => 'required|string|max:200',
            'department'    => 'nullable|string|max:120',
            'seniority'     => 'nullable|in:intern,junior,mid,senior,staff,principal',
            'work_mode'     => 'nullable|in:remote,hybrid,onsite',
            'location'      => 'nullable|string|max:200',
            'country'       => 'nullable|string|size:2',
            'comp_currency' => 'nullable|string|max:8',
            'comp_min'      => 'nullable|integer|min:0',
            'comp_max'      => 'nullable|integer|min:0|gte:comp_min',
            'comp_period'   => 'nullable|in:year,month,day,hour',
            'scope'         => 'nullable|string|max:8000',
            'must_haves'    => 'nullable|array',
            'must_haves.*'  => 'string|max:300',
            'nice_to_haves' => 'nullable|array',
            'nice_to_haves.*'  => 'string|max:300',
            'disqualifiers' => 'nullable|array',
            'disqualifiers.*'  => 'string|max:300',
            'ideal_candidate_archetypes' => 'nullable|array',
            'auto_source'   => 'nullable|boolean',
            'source_target' => 'nullable|integer|min:1|max:50',
        ]);

        $job = JobPosting::create(array_merge($data, [
            'status'    => 'sourcing',
            'opened_at' => now(),
        ]));

        if ($data['auto_source'] ?? false) {
            RunSourcingJob::dispatch(TenantContext::require()->id, $job->id, (int) ($data['source_target'] ?? 10));
        }

        return response()->json(['data' => $this->present($job)], 201);
    }

    public function destroy(string $publicId): JsonResponse
    {
        $job = JobPosting::query()->where('public_id', $publicId)->firstOrFail();
        $job->update(['status' => 'closed', 'closed_at' => now()]);
        return response()->json(['data' => $this->present($job)]);
    }

    public function startSourcing(Request $request, string $publicId): JsonResponse
    {
        $target = max(1, min(50, (int) $request->input('target', 10)));
        $job    = JobPosting::query()->where('public_id', $publicId)->firstOrFail();
        RunSourcingJob::dispatch(TenantContext::require()->id, $job->id, $target);
        return response()->json([
            'data' => [
                'job_public_id' => $job->public_id,
                'queued'        => true,
                'target'        => $target,
            ],
        ], 202);
    }

    private function present(JobPosting $j): array
    {
        return [
            'id'         => $j->public_id,
            'title'      => $j->title,
            'department' => $j->department,
            'seniority'  => $j->seniority,
            'work_mode'  => $j->work_mode,
            'location'   => $j->location,
            'country'    => $j->country,
            'comp'       => [
                'currency' => $j->comp_currency,
                'min'      => $j->comp_min,
                'max'      => $j->comp_max,
                'period'   => $j->comp_period,
            ],
            'scope'         => $j->scope,
            'must_haves'    => $j->must_haves    ?? [],
            'nice_to_haves' => $j->nice_to_haves ?? [],
            'disqualifiers' => $j->disqualifiers ?? [],
            'status'        => $j->status,
            'opened_at'     => optional($j->opened_at)->toIso8601String(),
            'closed_at'     => optional($j->closed_at)->toIso8601String(),
        ];
    }
}
