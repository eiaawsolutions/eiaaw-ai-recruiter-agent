<?php

namespace App\Jobs;

use App\Models\Candidate;
use App\Services\Agents\ScreeningAgent;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunScreeningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 20;
    public int $timeout = 180;

    public function __construct(public int $tenantId, public int $candidateId) {}

    public function handle(ScreeningAgent $agent, WebhookDispatcher $webhooks): void
    {
        TenantContext::bindById($this->tenantId);
        try {
            $candidate = Candidate::query()->findOrFail($this->candidateId);
            $result = $agent->screen($candidate);

            $webhooks->dispatch('candidate.screened', [
                'candidate_id'  => $candidate->public_id,
                'job_id'        => $candidate->jobPosting->public_id,
                'overall_score' => $result->overall_score,
                'summary'       => $result->summary,
            ]);
        } finally {
            TenantContext::clear();
        }
    }
}
