<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunScreeningJob;
use App\Jobs\RunDraftingJob;
use App\Models\Candidate;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Candidate::query()->latest();

        if ($jobPublicId = $request->query('job_id')) {
            $job = JobPosting::query()->where('public_id', $jobPublicId)->firstOrFail();
            $q->where('job_posting_id', $job->id);
        }
        if ($stage = $request->query('stage')) {
            $q->where('stage', $stage);
        }
        if ($conf = $request->query('confidence')) {
            $q->where('confidence_score', $conf);
        }

        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        return response()->json([
            'data' => $q->limit($limit)->with('sources')->get()->map(fn ($c) => $this->present($c)),
        ]);
    }

    public function show(string $publicId): JsonResponse
    {
        $candidate = Candidate::query()->where('public_id', $publicId)
            ->with(['sources', 'screening', 'outreachMessages', 'interviews'])
            ->firstOrFail();

        return response()->json(['data' => $this->presentFull($candidate)]);
    }

    public function screen(string $publicId): JsonResponse
    {
        $candidate = Candidate::query()->where('public_id', $publicId)->firstOrFail();
        RunScreeningJob::dispatch($candidate->tenant_id, $candidate->id);
        return response()->json(['data' => ['queued' => true, 'candidate_id' => $candidate->public_id]], 202);
    }

    public function draftOutreach(Request $request, string $publicId): JsonResponse
    {
        $data = $request->validate([
            'instructions' => 'nullable|string|max:2000',
        ]);
        $candidate = Candidate::query()->where('public_id', $publicId)->firstOrFail();
        $instructions = (string) ($data['instructions'] ?? '');
        RunDraftingJob::dispatch($candidate->tenant_id, $candidate->id, $instructions !== '' ? $instructions : null);
        return response()->json(['data' => ['queued' => true, 'candidate_id' => $candidate->public_id]], 202);
    }

    public function shortlist(string $publicId): JsonResponse
    {
        $candidate = Candidate::query()->where('public_id', $publicId)->firstOrFail();
        $candidate->update(['stage' => 'shortlisted']);
        return response()->json(['data' => $this->present($candidate)]);
    }

    public function discard(Request $request, string $publicId): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);
        $reason    = trim((string) ($data['reason'] ?? 'manual_discard'));
        $candidate = Candidate::query()->where('public_id', $publicId)->firstOrFail();
        $candidate->update(['stage' => 'discarded', 'discard_reason' => $reason]);
        return response()->json(['data' => $this->present($candidate)]);
    }

    private function present(Candidate $c): array
    {
        return [
            'id'               => $c->public_id,
            'name'             => $c->name,
            'title'            => $c->title,
            'company'          => $c->company,
            'location'         => $c->location,
            'country'          => $c->country,
            'email'            => $c->email,
            'phone'            => $c->phone,
            'linkedin_url'     => $c->linkedin_url,
            'company_website'  => $c->company_website,
            'candidate_type'   => $c->candidate_type,
            'lead_temperature' => $c->lead_temperature,
            'confidence_score' => $c->confidence_score,
            'reason_for_fit'   => $c->reason_for_fit,
            'buying_signal'    => $c->buying_signal,
            'stage'            => $c->stage,
            'created_at'       => $c->created_at->toIso8601String(),
            'verification_sources_count' => $c->sources->count(),
        ];
    }

    private function presentFull(Candidate $c): array
    {
        $base = $this->present($c);
        $base['verification_sources'] = $c->sources->map(fn ($s) => [
            'kind' => $s->kind, 'url' => $s->url, 'excerpt' => $s->excerpt,
        ]);
        $base['screening'] = $c->screening ? [
            'overall_score'        => $c->screening->overall_score,
            'summary'              => $c->screening->summary,
            'must_have_matches'    => $c->screening->must_have_matches,
            'nice_to_have_matches' => $c->screening->nice_to_have_matches,
            'disqualifier_hits'    => $c->screening->disqualifier_hits,
            'risk_flags'           => $c->screening->risk_flags,
        ] : null;
        $base['outreach'] = $c->outreachMessages->map(fn ($m) => [
            'id' => $m->public_id, 'subject' => $m->subject, 'status' => $m->status,
            'sent_at' => optional($m->sent_at)->toIso8601String(),
        ]);
        $base['interviews'] = $c->interviews->map(fn ($i) => [
            'id'        => $i->public_id,
            'stage'     => $i->stage,
            'starts_at' => $i->starts_at->toIso8601String(),
            'ends_at'   => $i->ends_at->toIso8601String(),
            'status'    => $i->status,
        ]);
        return $base;
    }
}
