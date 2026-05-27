<!doctype html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EIAAW Recruiter')</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%230f172a'/%3E%3Ctext x='16' y='22' text-anchor='middle' fill='%23f8fafc' font-family='ui-sans-serif' font-weight='700' font-size='14'%3ER%3C/text%3E%3C/svg%3E">
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    </style>
</head>
<body class="min-h-full text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
            <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded bg-slate-900 text-center font-semibold leading-8 text-white">R</div>
                <div>
                    <a href="{{ route('dashboard') }}" class="text-base font-semibold">EIAAW Recruiter</a>
                    <div class="text-xs text-slate-500">{{ auth()->user()?->tenant?->name }}</div>
                </div>
            </div>
            <nav class="flex items-center gap-1 text-sm">
                <a class="rounded px-3 py-2 hover:bg-slate-100 {{ request()->routeIs('dashboard') ? 'bg-slate-100 font-medium' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="rounded px-3 py-2 hover:bg-slate-100 {{ request()->routeIs('jobs.*') ? 'bg-slate-100 font-medium' : '' }}" href="{{ route('jobs.index') }}">Jobs</a>
                <a class="rounded px-3 py-2 hover:bg-slate-100 {{ request()->routeIs('candidates.*') ? 'bg-slate-100 font-medium' : '' }}" href="{{ route('candidates.index') }}">Candidates</a>
                <a class="rounded px-3 py-2 hover:bg-slate-100 {{ request()->routeIs('outreach.*') ? 'bg-slate-100 font-medium' : '' }}" href="{{ route('outreach.index') }}">Outreach</a>
                <a class="rounded px-3 py-2 hover:bg-slate-100 {{ request()->routeIs('calendar.*') ? 'bg-slate-100 font-medium' : '' }}" href="{{ route('calendar.index') }}">Calendars</a>
                <form action="{{ route('logout') }}" method="POST" class="ml-2">
                    @csrf
                    <button class="rounded px-3 py-2 text-slate-500 hover:bg-slate-100">Sign out</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-6 py-8">
        @if (session('status'))
            <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800">
                @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mt-12 border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-7xl px-6 py-4 text-xs text-slate-500">
            EIAAW Recruiter v{{ recruiter_version() }} · Every candidate is verified before storage · No fabricated leads.
        </div>
    </footer>
</body>
</html>
