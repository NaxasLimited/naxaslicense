@extends('admin.layout')
@section('title', 'Activation Request')
@section('content')
<div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <a href="{{ route('admin.requests') }}" class="mb-2 inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-500">← Activation requests</a>
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Request {{ $activationRequest->request_token_prefix }}…</h1>
        <p class="mt-1 text-sm text-slate-500">Review the installation details before assigning a license.</p>
    </div>
    <span class="w-fit rounded-full bg-slate-200 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-700">{{ $activationRequest->status }}</span>
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Installation details</h2></div>
        <dl class="grid sm:grid-cols-2">
            @foreach([
                ['Domain', $activationRequest->normalized_domain],
                ['Environment', ucfirst($activationRequest->environment)],
                ['Product', $activationRequest->product->name],
                ['Edition', $activationRequest->edition->name],
                ['Application version', $activationRequest->application_version],
                ['Requested', $activationRequest->created_at->format('M j, Y · H:i')],
                ['Expires', $activationRequest->expires_at->format('M j, Y · H:i')],
                ['Request ID', $activationRequest->request_id],
            ] as [$label, $value])
                <div class="border-b border-slate-100 px-5 py-4 odd:sm:border-r"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt><dd class="mt-1 break-all text-sm font-medium text-slate-800">{{ $value }}</dd></div>
            @endforeach
        </dl>
        <div class="px-5 py-4"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Installation UUID</dt><dd class="mt-1 break-all font-mono text-sm text-slate-700">{{ $activationRequest->installation_uuid }}</dd></div>
    </section>

    <div class="space-y-5">
        @if($activationRequest->status === 'pending')
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Approve request</h2>
                <p class="mt-1 text-sm leading-5 text-slate-500">Assign a matching active license and issue the signed entitlement.</p>
                @if($licenses->isEmpty())
                    <div class="mt-5 rounded-xl border border-dashed border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                        <p class="font-semibold">No active licenses available</p>
                        <p class="mt-1 text-amber-800">Create a matching license before approving this request.</p>
                        <a href="{{ route('admin.licenses') }}" class="mt-3 inline-flex font-semibold text-amber-950 underline decoration-amber-400 underline-offset-4">Go to licenses</a>
                    </div>
                @else
                    <form class="mt-5 space-y-4" method="POST" action="{{ route('admin.requests.approve', $activationRequest) }}">
                        @csrf
                        <label class="grid gap-2 text-sm font-semibold">License
                            <select class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 font-normal outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100" name="license_id" required>
                                @foreach($licenses as $license)<option value="{{ $license->id }}">{{ $license->license_id }} · {{ $license->customer->name }}</option>@endforeach
                            </select>
                        </label>
                        <button class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">Approve and issue license</button>
                    </form>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Other actions</h2>
                <form class="mt-4 space-y-3" method="POST" action="{{ route('admin.requests.reject', $activationRequest) }}">@csrf
                    <label class="grid gap-2 text-sm font-semibold">Safe rejection reason<input class="rounded-xl border border-slate-300 px-3 py-3 font-normal outline-none placeholder:text-slate-400 focus:border-red-400 focus:ring-4 focus:ring-red-100" name="reason" required maxlength="255" placeholder="Message visible to the customer"></label>
                    <button class="w-full rounded-xl border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">Reject request</button>
                </form>
                <form class="mt-3" method="POST" action="{{ route('admin.requests.expire', $activationRequest) }}">@csrf<button class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Mark as expired</button></form>
            </section>
        @else
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-semibold">Request closed</h2><p class="mt-2 text-sm leading-6 text-slate-500">This request is <strong class="capitalize text-slate-700">{{ $activationRequest->status }}</strong> and no further action is available.</p>@if($activationRequest->safe_failure_message)<p class="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-800">{{ $activationRequest->safe_failure_message }}</p>@endif</section>
        @endif
    </div>
</div>
@endsection
