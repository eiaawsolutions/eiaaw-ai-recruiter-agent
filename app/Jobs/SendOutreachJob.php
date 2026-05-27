<?php

namespace App\Jobs;

use App\Models\OutreachMessage;
use App\Services\Outreach\MailgunOutreachSender;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOutreachJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 30;
    public int $timeout = 60;

    public function __construct(public int $tenantId, public int $outreachId) {}

    public function handle(MailgunOutreachSender $sender, WebhookDispatcher $webhooks): void
    {
        TenantContext::bindById($this->tenantId);
        try {
            $msg = OutreachMessage::query()->findOrFail($this->outreachId);

            if ($msg->status !== 'approved') {
                // Defensive: never send unapproved messages.
                return;
            }

            if (! $msg->to_address) {
                $msg->update(['status' => 'failed']);
                return;
            }

            $sender->send($msg);

            if ($msg->candidate->stage !== 'outreach_sent') {
                $msg->candidate->update(['stage' => 'outreach_sent']);
            }

            $webhooks->dispatch('outreach.sent', [
                'outreach_id'  => $msg->public_id,
                'candidate_id' => $msg->candidate->public_id,
                'subject'      => $msg->subject,
                'sent_at'      => optional($msg->sent_at)->toIso8601String(),
            ]);
        } finally {
            TenantContext::clear();
        }
    }
}
