<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\RunSourcingJob;
use App\Models\JobPosting;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobController extends Controller
{
    public function index(): View
    {
        $jobs = JobPosting::query()->latest()->paginate(25);
        return view('jobs.index', compact('jobs'));
    }

    public function create(): View { return view('jobs.create'); }

    public function store(Request $request): RedirectResponse
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
            'comp_max'      => 'nullable|integer|min:0',
            'comp_period'   => 'nullable|in:year,month,day,hour',
            'scope'         => 'nullable|string|max:8000',
            'must_haves'    => 'nullable|string',
            'nice_to_haves' => 'nullable|string',
            'disqualifiers' => 'nullable|string',
        ]);

        foreach (['must_haves', 'nice_to_haves', 'disqualifiers'] as $k) {
            if (! empty($data[$k])) {
                $data[$k] = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $data[$k]))));
            }
        }

        $job = JobPosting::create(array_merge($data, [
            'status'        => 'sourcing',
            'opened_at'     => now(),
            'owner_user_id' => optional($request->user())->id,
        ]));

        if ($request->boolean('auto_source')) {
            RunSourcingJob::dispatch(TenantContext::require()->id, $job->id, (int) ($request->input('source_target', 10)));
        }

        return redirect()->route('jobs.show', $job->public_id)->with('status', 'Job created.');
    }

    public function show(string $publicId): View
    {
        $job = JobPosting::query()->where('public_id', $publicId)
            ->with('candidates')
            ->firstOrFail();
        $candidates = $job->candidates()->with('sources')->latest()->paginate(50);
        return view('jobs.show', compact('job', 'candidates'));
    }

    public function source(Request $request, string $publicId): RedirectResponse
    {
        $target = max(1, min(50, (int) $request->input('target', 10)));
        $job    = JobPosting::query()->where('public_id', $publicId)->firstOrFail();
        RunSourcingJob::dispatch(TenantContext::require()->id, $job->id, $target);
        return back()->with('status', "Sourcing queued for {$target} candidates.");
    }
}
