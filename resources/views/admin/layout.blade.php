<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') · Naxas License Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 font-sans text-slate-900 antialiased">
<div class="min-h-screen w-full lg:grid lg:grid-cols-[260px_minmax(0,1fr)]">
    <aside class="relative z-20 border-b border-slate-800 bg-slate-950 text-slate-300 lg:sticky lg:top-0 lg:h-screen lg:border-b-0 lg:border-r">
        <div class="flex h-16 items-center border-b border-white/10 px-5">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 font-semibold text-white">
                <span class="grid size-9 place-items-center rounded-xl bg-indigo-500 text-sm font-bold shadow-lg shadow-indigo-950/40">NX</span>
                <span><span class="block leading-tight">Naxas</span><span class="block text-xs font-normal text-slate-400">License Portal</span></span>
            </a>
        </div>
        <nav class="flex gap-1 overflow-x-auto p-3 lg:block lg:space-y-1 lg:overflow-visible" aria-label="Admin navigation">
            @php $nav = [['admin.dashboard', 'Dashboard'], ['admin.requests', 'Activation requests'], ['admin.licenses', 'Licenses'], ['admin.customers', 'Customers']]; @endphp
            @foreach($nav as [$route, $label])
                <a href="{{ route($route) }}" @class(['whitespace-nowrap rounded-lg px-3 py-2.5 text-sm font-medium transition lg:block', 'bg-indigo-500 text-white shadow-sm' => request()->routeIs($route) || ($route === 'admin.requests' && request()->routeIs('admin.requests.*')), 'hover:bg-white/10 hover:text-white' => ! request()->routeIs($route)])>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="hidden border-t border-white/10 p-3 lg:absolute lg:inset-x-0 lg:bottom-0 lg:block">
            <div class="mb-3 px-3 text-xs text-slate-400">Signed in as<br><span class="text-sm font-medium text-slate-200">{{ auth()->user()->email }}</span></div>
            <form method="POST" action="/logout">@csrf<button class="w-full rounded-lg border border-white/10 px-3 py-2 text-left text-sm font-medium hover:bg-white/10 hover:text-white">Sign out</button></form>
        </div>
    </aside>
    <div class="min-w-0">
        <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-5 sm:px-8">
            <div><p class="text-xs font-semibold uppercase tracking-wider text-indigo-600">Administration</p><p class="text-sm text-slate-500">Manage licenses and activations</p></div>
            <form class="lg:hidden" method="POST" action="/logout">@csrf<button class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium">Sign out</button></form>
        </header>
        <main class="mx-auto max-w-7xl p-5 sm:p-8">
            @if(session('status'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
