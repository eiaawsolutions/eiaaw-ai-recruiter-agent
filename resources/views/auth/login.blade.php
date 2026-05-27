<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — EIAAW Recruiter</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
</head>
<body class="min-h-screen bg-slate-50">
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <div class="mb-6 flex items-center gap-2">
                <div class="h-8 w-8 rounded bg-slate-900 text-center font-semibold leading-8 text-white">R</div>
                <span class="font-semibold">EIAAW Recruiter</span>
            </div>
            <h1 class="text-xl font-semibold">Sign in</h1>
            @if ($errors->any())
                <div class="mt-3 rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium">Email</label>
                    <input type="email" name="email" required value="{{ old('email') }}" class="mt-1 w-full rounded-md border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium">Password</label>
                    <input type="password" name="password" required class="mt-1 w-full rounded-md border-slate-300">
                </div>
                <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-white">Sign in</button>
            </form>
        </div>
    </div>
</body>
</html>
