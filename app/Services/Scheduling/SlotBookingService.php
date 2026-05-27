<?php

namespace App\Services\Scheduling;

use App\Models\CalendarConnection;
use App\Models\InterviewSlot;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class SlotBookingService
{
    public function __construct(private readonly CalendarProviderFactory $factory) {}

    /**
     * Confirm a proposed slot: writes the calendar event via the active
     * provider, stores the meeting URL, and flips status to `confirmed`.
     * If no active connection exists, falls back to noop (preserves the
     * `meeting_url` the operator entered manually) so the workflow never
     * blocks on missing integrations.
     */
    public function confirm(InterviewSlot $slot, array $attendeeEmails = []): InterviewSlot
    {
        $tenantId = TenantContext::id() ?? $slot->tenant_id;

        $connection = CalendarConnection::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotIn('provider', ['noop'])
            ->orderByDesc('updated_at')
            ->first();

        $provider = $this->factory->make($connection?->provider ?? 'noop');

        if (! $connection && $provider->name() === 'noop') {
            // Synthesize a transient noop connection so we have something to
            // hand to the provider interface.
            $connection = new CalendarConnection([
                'tenant_id' => $tenantId,
                'provider'  => 'noop',
                'account_email' => 'noop@local',
            ]);
        }

        $emails = array_values(array_filter(array_merge(
            $attendeeEmails,
            [$slot->candidate->email ?: null, $slot->tenant->contact_email ?: null],
        )));

        $result = $provider->createEvent($connection, $slot, $emails);

        DB::transaction(function () use ($slot, $result) {
            $slot->update([
                'status'      => 'confirmed',
                'meeting_url' => $result['meeting_url'] ?: $slot->meeting_url,
                'notes'       => trim(($slot->notes ? $slot->notes . "\n" : '') . 'event_id=' . $result['event_id']),
            ]);
            $slot->candidate->update(['stage' => 'interview_scheduled']);
        });

        return $slot->fresh();
    }
}
