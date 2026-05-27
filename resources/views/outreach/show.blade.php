@extends('layouts.app')
@section('title', $message->subject)
@section('content')
<div class="mb-4 text-xs uppercase tracking-wide text-slate-500">Outreach to {{ $message->candidate?->name }}</div>
<h1 class="mb-1 text-2xl font-semibold">{{ $message->subject }}</h1>
<div class="mb-6 text-sm text-slate-600">
    To: {{ $message->to_address ?: '(no verified email)' }} ·
    From: {{ $message->from_address }} ·
    Status: <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $message->status }}</span>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <pre class="whitespace-pre-wrap text-sm leading-relaxed">{{ $message->body }}</pre>
        </div>
    </div>

    <aside class="space-y-3">
        @if (in_array($message->status, ['drafted','pending_approval'], true))
            <form method="POST" action="{{ route('outreach.approve', $message->public_id) }}">
                @csrf
                <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm text-white">Approve & send</button>
            </form>
            <form method="POST" action="{{ route('outreach.reject', $message->public_id) }}">
                @csrf
                <button class="w-full rounded-md border border-rose-300 bg-white px-4 py-2 text-sm text-rose-700">Reject</button>
            </form>
        @else
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
                @if ($message->sent_at)
                    Sent {{ $message->sent_at->diffForHumans() }}.
                @endif
                @if ($message->provider_message_id)
                    <div class="mt-2 text-xs text-slate-400">{{ $message->provider_message_id }}</div>
                @endif
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-4 text-xs text-slate-500">
            <div class="font-semibold text-slate-700">Approval guarantee</div>
            No outbound message leaves this tenant without an Approval row. This page is the only path.
        </div>
    </aside>
</div>
@endsection
