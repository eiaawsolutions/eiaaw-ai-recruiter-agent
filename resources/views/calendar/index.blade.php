@extends('layouts.app')
@section('title', 'Calendar connections')
@section('content')
<h1 class="mb-6 text-2xl font-semibold">Calendar connections</h1>

<div class="mb-6 flex gap-3">
    <a href="{{ route('calendar.start', 'google') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm">Connect Google</a>
    <a href="{{ route('calendar.start', 'microsoft') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm">Connect Microsoft</a>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-5 py-3">Provider</th>
                <th class="px-5 py-3">Account</th>
                <th class="px-5 py-3">Active</th>
                <th class="px-5 py-3">Expires</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($connections as $c)
                <tr>
                    <td class="px-5 py-3 font-medium">{{ $c->provider }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $c->account_email }}</td>
                    <td class="px-5 py-3">{{ $c->is_active ? 'yes' : 'no' }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ optional($c->access_token_expires_at)->diffForHumans() }}</td>
                    <td class="px-5 py-3">
                        @if ($c->is_active)
                            <form method="POST" action="{{ route('calendar.disconnect', $c->id) }}">
                                @csrf
                                <button class="text-xs text-rose-600 hover:underline">Disconnect</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No calendars connected. Confirmed interview slots will fall back to manually-entered meeting URLs.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
