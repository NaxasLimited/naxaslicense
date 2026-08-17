@extends('admin.layout')
@section('title', 'Dashboard')
@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Dashboard</h1><p class="mt-1 text-sm text-slate-500">A live overview of your licensing operation.</p></div>
        <a href="{{ route('admin.requests') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Review requests</a>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([['Pending requests', $pendingCount, 'Needs review'], ['Active licenses', $activeLicenseCount, 'Usable licenses'], ['Active installations', $activeInstallationCount, 'Currently activated'], ['Customers', $customerCount, 'Active accounts']] as [$label, $value, $hint])
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="mb-4 flex items-center justify-between"><p class="text-sm font-medium text-slate-500">{{ $label }}</p><span class="size-2.5 rounded-full bg-indigo-500"></span></div><p class="text-3xl font-bold tracking-tight text-slate-950">{{ number_format($value) }}</p><p class="mt-1 text-xs text-slate-400">{{ $hint }}</p></section>
        @endforeach
    </div>
    <section class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="font-semibold text-slate-950">Recent activation requests</h2><p class="text-sm text-slate-500">Latest requests from connected installations</p></div><a class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" href="{{ route('admin.requests') }}">View all</a></div>
        @if($requests->isEmpty())<div class="px-5 py-12 text-center text-sm text-slate-500">No activation requests yet.</div>@else
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3 font-semibold">Request</th><th class="px-5 py-3 font-semibold">Domain</th><th class="px-5 py-3 font-semibold">Product</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100">
            @foreach($requests as $request)<tr class="hover:bg-slate-50/80"><td class="px-5 py-4 font-mono text-xs text-slate-600">{{ $request->request_token_prefix }}…</td><td class="px-5 py-4 font-medium text-slate-900">{{ $request->normalized_domain }}</td><td class="px-5 py-4 text-slate-500">{{ $request->product->name }} · {{ $request->edition->name }}</td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-700">{{ $request->status }}</span></td><td class="px-5 py-4 text-right"><a class="font-semibold text-indigo-600 hover:text-indigo-500" href="{{ route('admin.requests.show', $request) }}">Open</a></td></tr>@endforeach
            </tbody></table></div>
        @endif
    </section>
@endsection
