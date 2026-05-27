<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\Integrations\WorkforceHandoff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hands a hired candidate row to EIAAW Workforce's OnboardingInvite flow.
 * Other systems plug in by registering a webhook subscriber on
 * `candidate.hired` and calling their own handoff endpoint.
 */
class HandoffController extends Controller
{
    public function workforce(Request $request, WorkforceHandoff $handoff, string $candidatePublicId): JsonResponse
    {
        $candidate = Candidate::query()->where('public_id', $candidatePublicId)->firstOrFail();

        if ($candidate->stage !== 'hired' && $candidate->stage !== 'shortlisted') {
            return response()->json([
                'error' => ['type' => 'InvalidState', 'message' => "Candidate stage {$candidate->stage} not eligible for handoff."],
            ], 409);
        }

        $result = $handoff->send($candidate, [
            'role_title' => $request->input('role_title', $candidate->jobPosting->title),
            'start_date' => $request->input('start_date'),
            'comp'       => $request->input('comp'),
        ]);

        $candidate->update(['stage' => 'hired']);

        return response()->json(['data' => $result]);
    }
}
