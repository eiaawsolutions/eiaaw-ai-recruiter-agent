<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CalendarConnection;
use App\Models\InterviewSlot;
use App\Services\Scheduling\CalendarProviderFactory;
use App\Services\Scheduling\SlotBookingService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __construct(
        private readonly CalendarProviderFactory $factory,
    ) {}

    public function index(): View
    {
        $connections = CalendarConnection::query()->latest()->get();
        return view('calendar.index', compact('connections'));
    }

    public function start(Request $request, string $provider): RedirectResponse
    {
        $p = $this->factory->make($provider);
        $state = Str::random(48);
        session([
            'calendar_oauth_state' => $state,
            'calendar_oauth_provider' => $p->name(),
        ]);
        $redirect = URL::route('calendar.callback', $provider);
        return redirect()->away($p->authorizationUrl($state, $redirect));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $expected = session('calendar_oauth_state');
        $given    = (string) $request->query('state', '');
        if (! $expected || ! hash_equals($expected, $given)) {
            return redirect()->route('calendar.index')->withErrors(['oauth' => 'Invalid OAuth state.']);
        }
        session()->forget(['calendar_oauth_state', 'calendar_oauth_provider']);

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('calendar.index')->withErrors(['oauth' => 'Missing OAuth code.']);
        }

        $redirect = URL::route('calendar.callback', $provider);
        $p = $this->factory->make($provider);

        try {
            $p->exchangeCode($code, $redirect, TenantContext::require()->id, optional($request->user())->id);
        } catch (\Throwable $e) {
            return redirect()->route('calendar.index')->withErrors(['oauth' => 'Token exchange failed: ' . $e->getMessage()]);
        }

        return redirect()->route('calendar.index')->with('status', ucfirst($provider) . ' calendar connected.');
    }

    public function disconnect(string $publicId): RedirectResponse
    {
        $c = CalendarConnection::query()->where('public_id', $publicId)->firstOrFail();
        $c->update(['is_active' => false]);
        return back()->with('status', 'Calendar disconnected.');
    }

    public function confirmSlot(SlotBookingService $booking, string $publicId): RedirectResponse
    {
        $slot = InterviewSlot::query()->where('public_id', $publicId)->firstOrFail();
        if ($slot->status !== 'proposed') {
            return back()->withErrors(['slot' => "Slot is in status {$slot->status}; cannot confirm."]);
        }
        $booking->confirm($slot);
        return back()->with('status', 'Interview slot confirmed and event created.');
    }
}
