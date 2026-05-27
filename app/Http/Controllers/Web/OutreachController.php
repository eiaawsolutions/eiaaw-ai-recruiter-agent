<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SendOutreachJob;
use App\Models\Approval;
use App\Models\OutreachMessage;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OutreachController extends Controller
{
    public function index(Request $request): View
    {
        $q = OutreachMessage::query()->with('candidate')->latest();
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        $messages = $q->paginate(50)->withQueryString();
        return view('outreach.index', compact('messages'));
    }

    public function show(string $publicId): View
    {
        $message = OutreachMessage::query()->where('public_id', $publicId)
            ->with(['candidate', 'jobPosting'])
            ->firstOrFail();
        return view('outreach.show', compact('message'));
    }

    public function approve(Request $request, string $publicId): RedirectResponse
    {
        $m = OutreachMessage::query()->where('public_id', $publicId)->firstOrFail();
        if (! in_array($m->status, ['drafted', 'pending_approval'], true)) {
            return back()->withErrors(['status' => "Cannot approve in status {$m->status}."]);
        }

        Approval::create([
            'tenant_id'             => TenantContext::require()->id,
            'approvable_type'       => OutreachMessage::class,
            'approvable_id'         => $m->id,
            'action'                => 'send_outreach',
            'status'                => Approval::STATUS_APPROVED,
            'decided_at'            => now(),
            'requested_by_user_id'  => $request->user()->id,
            'decided_by_user_id'    => $request->user()->id,
        ]);

        $m->update([
            'status'              => 'approved',
            'approved_at'         => now(),
            'approved_by_user_id' => $request->user()->id,
        ]);

        SendOutreachJob::dispatch($m->tenant_id, $m->id);
        return back()->with('status', 'Approved and queued for send.');
    }

    public function reject(Request $request, string $publicId): RedirectResponse
    {
        $m = OutreachMessage::query()->where('public_id', $publicId)->firstOrFail();
        $m->update(['status' => 'suppressed']);
        return back()->with('status', 'Outreach suppressed.');
    }
}
