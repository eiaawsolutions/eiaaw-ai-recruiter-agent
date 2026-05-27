<?php

namespace App\Jobs;

use App\Models\JobPosting;
use App\Services\Agents\SourcingAgent;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunSourcingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 240;

    public function __construct(
        public int $tenantId,
        public int $jobPostingId,
        public int $target = 10,
    ) {}

    public function handle(SourcingAgent $agent, WebhookDispatcher $webhooks): void
    {
        TenantContext::bindById($this->tenantId);
        try {
            $job = JobPosting::query()->findOrFail($this->jobPostingId);
            $result = $agent->source($job, $this->target);

            foreach ($result['accepted'] as $candidate) {
                $webhooks->dispatch('candidate.sourced', [
                    'candidate_id'         => $candidate->public_id,
                    'job_id'               => $job->public_id,
                    'name'                 => $candidate->name,
                    'confidence_score'     => $candidate->confidence_score,
                    'lead_temperature'     => $candidate->lead_temperature,
                    'verification_sources' => $candidate->sources()->pluck('url')->all(),
                ]);
            }
        } finally {
            TenantContext::clear();
        }
    }
}
