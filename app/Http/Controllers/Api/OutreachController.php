<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendOutreachJob;
use App\Models\Approval;
use App\Models\OutreachMessage;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutreachController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = OutreachMessage::query()->latest();
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        return response()->json([
            'data' => $q->limit($limit)->get()->map(fn ($m) => $this->present($m)),
        ]);
    }

    public function show(string $publicId): JsonResponse
    {
        $msg = OutreachMessage::query()->where('public_id', $publicId)->firstOrFail();
        return response()->json(['data' => $this->present($msg, full: true)]);
    }

    public function approve(string $publicId): JsonResponse
    {
        $msg = OutreachMessage::query()->where('public_id', $publicId)->firstOrFail();

        if ($msg->status !== 'pending_approval' && $msg->status !== 'drafted') {
            return response()->json(['error' => ['type' => 'InvalidState', 'message' => "Outreach is in status {$msg->status}; cannot approve."]], 409);
        }

        Approval::create([
            'tenant_id'             => TenantContext::require()->id,
            'approvable_type'       => OutreachMessage::class,
            'approvable_id'         => $msg->id,
            'action'                => 'send_outreach',
            'status'                => Approval::STATUS_APPROVED,
            'decided_at'            => now(),
            'requested_by_user_id'  => optional(request()->user())->id,
            'decided_by_user_id'    => optional(request()->user())->id,
        ]);

        $msg->update([
            'status'              => 'approved',
            'approved_at'         => now(),
            'approved_by_user_id' => optional(request()->user())->id,
        ]);

        SendOutreachJob::dispatch($msg->tenant_id, $msg->id);

        return response()->json(['data' => $this->present($msg, full: true)]);
    }

    public function reject(Request $request, string $publicId): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);
        $msg = OutreachMessage::query()->where('public_id', $publicId)->firstOrFail();
        $msg->update(['status' => 'suppressed']);
        return response()->json(['data' => $this->present($msg)]);
    }

    private function present(OutreachMessage $m, bool $full = false): array
    {
        $base = [
            'id'             => $m->public_id,
            'candidate_id'   => $m->candidate?->public_id,
            'job_id'         => $m->jobPosting?->public_id,
            'channel'        => $m->channel,
            'subject'        => $m->subject,
            'status'         => $m->status,
            'sent_at'        => optional($m->sent_at)->toIso8601String(),
            'approved_at'    => optional($m->approved_at)->toIso8601String(),
            'created_at'     => $m->created_at->toIso8601String(),
        ];
        if ($full) {
            $base['body']         = $m->body;
            $base['from_address'] = $m->from_address;
            $base['to_address']   = $m->to_address;
            $base['model_used']   = $m->model_used;
            $base['provider_message_id'] = $m->provider_message_id;
        }
        return $base;
    }
}
