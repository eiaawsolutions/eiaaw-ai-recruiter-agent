<?php

namespace App\Jobs;

use App\Models\Candidate;
use App\Services\Agents\DraftingAgent;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDraftingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 20;
    public int $timeout = 90;

    public function __construct(public int $tenantId, public int $candidateId, public ?string $instructions = null) {}

    public function handle(DraftingAgent $agent, WebhookDispatcher $webhooks): void
    {
        TenantContext::bindById($this->tenantId);
        try {
            $candidate = Candidate::query()->findOrFail($this->candidateId);
            $message = $agent->draftOutreach($candidate, $this->instructions);

            $event = $message->status === 'drafted' ? 'outreach.drafted' : 'outreach.pending_approval';
            $webhooks->dispatch($event, [
                'outreach_id'  => $message->public_id,
                'candidate_id' => $candidate->public_id,
                'subject'      => $message->subject,
            ]);
        } finally {
            TenantContext::clear();
        }
    }
}
