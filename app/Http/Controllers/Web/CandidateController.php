<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\RunDraftingJob;
use App\Jobs\RunScreeningJob;
use App\Models\Candidate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function index(Request $request): View
    {
        $q = Candidate::query()->with('sources', 'jobPosting')->latest();
        if ($stage = $request->query('stage')) {
            $q->where('stage', $stage);
        }
        $candidates = $q->paginate(50)->withQueryString();
        return view('candidates.index', compact('candidates'));
    }

    public function show(string $publicId): View
    {
        $candidate = Candidate::query()->where('public_id', $publicId)
            ->with(['sources', 'screening', 'outreachMessages', 'interviews', 'jobPosting'])
            ->firstOrFail();
        return view('candidates.show', compact('candidate'));
    }

    public function screen(string $publicId): RedirectResponse
    {
        $c = Candidate::query()->where('public_id', $publicId)->firstOrFail();
        RunScreeningJob::dispatch($c->tenant_id, $c->id);
        return back()->with('status', 'Screening queued.');
    }

    public function draft(Request $request, string $publicId): RedirectResponse
    {
        $c = Candidate::query()->where('public_id', $publicId)->firstOrFail();
        RunDraftingJob::dispatch($c->tenant_id, $c->id, $request->input('instructions'));
        return back()->with('status', 'Drafting queued.');
    }

    public function shortlist(string $publicId): RedirectResponse
    {
        $c = Candidate::query()->where('public_id', $publicId)->firstOrFail();
        $c->update(['stage' => 'shortlisted']);
        return back()->with('status', 'Candidate shortlisted.');
    }

    public function discard(Request $request, string $publicId): RedirectResponse
    {
        $c = Candidate::query()->where('public_id', $publicId)->firstOrFail();
        $c->update([
            'stage' => 'discarded',
            'discard_reason' => $request->input('reason', 'manual_discard'),
        ]);
        return back()->with('status', 'Candidate discarded.');
    }
}
