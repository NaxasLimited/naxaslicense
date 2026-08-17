@extends('admin.layout')
@section('title', 'Licenses')
@section('content')
<div class="mb-7"><h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Licenses</h1><p class="mt-1 text-sm text-slate-500">Issue licenses and control active installations.</p></div>

<section class="mb-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="font-semibold">Issue a license</h2>
    @if($customers->isEmpty())
        <div class="mt-4 flex flex-col gap-4 rounded-xl border border-dashed border-amber-300 bg-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="font-semibold text-amber-950">Create a customer first</p><p class="mt-1 text-sm text-amber-800">A license must belong to an active customer account.</p></div>
            <a href="{{ route('admin.customers') }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-amber-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-800">Add customer</a>
        </div>
    @elseif($editions->isEmpty())
        <div class="mt-4 rounded-xl border border-dashed border-amber-300 bg-amber-50 p-5"><p class="font-semibold text-amber-950">No active product edition is available</p><p class="mt-1 text-sm text-amber-800">Activate a product edition before issuing a license.</p></div>
    @else
        <form method="POST" action="{{ route('admin.licenses.store') }}" class="mt-4 grid gap-4 md:grid-cols-3 xl:grid-cols-[1fr_1fr_1fr_auto]">
            @csrf
            <label class="grid gap-1.5 text-sm font-medium">Customer<select class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-normal" name="customer_id" required>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }} · {{ $customer->email }}</option>@endforeach</select></label>
            <label class="grid gap-1.5 text-sm font-medium">Product edition<select class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-normal" name="product_edition_id" required>@foreach($editions as $edition)<option value="{{ $edition->id }}" @selected(old('product_edition_id') == $edition->id)>{{ $edition->product->name }} · {{ $edition->name }}</option>@endforeach</select></label>
            <label class="grid gap-1.5 text-sm font-medium">Expiry date <span class="sr-only">optional</span><input class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-normal" type="date" name="expires_at" value="{{ old('expires_at') }}"></label>
            <button class="self-end rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">Create license</button>
        </form>
    @endif
</section>

<div class="space-y-4">
@forelse($licenses as $license)
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div><div class="flex flex-wrap items-center gap-2"><h2 class="font-mono font-semibold">{{ $license->license_id }}</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize">{{ $license->status }}</span></div><p class="mt-1 text-sm text-slate-500">{{ $license->customer->name }} · {{ $license->license_type }} · {{ $license->activations->where('status', 'active')->count() }} active installation(s)</p></div>
            <div class="flex flex-wrap gap-2">
                @if($license->status === 'active')<form method="POST" action="{{ route('admin.licenses.suspend', $license) }}">@csrf<button class="rounded-lg border border-amber-300 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50">Suspend</button></form>
                @elseif($license->status === 'suspended')<form method="POST" action="{{ route('admin.licenses.activate', $license) }}">@csrf<button class="rounded-lg border border-emerald-300 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Reactivate</button></form>@endif
                @if($license->status !== 'revoked')<form class="flex" method="POST" action="{{ route('admin.licenses.revoke', $license) }}">@csrf<input class="w-40 rounded-l-lg border border-slate-300 px-3 py-2 text-sm" name="reason" required placeholder="Revocation reason"><button class="rounded-r-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-500">Revoke</button></form>@endif
            </div>
        </div>
        @if($license->activations->isNotEmpty())<div class="mt-5 divide-y divide-slate-100 border-t border-slate-100">@foreach($license->activations as $activation)<div class="flex flex-col gap-3 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"><div><span class="font-medium">{{ $activation->normalized_domain }}</span><span class="ml-2 font-mono text-xs text-slate-400">{{ $activation->installation_uuid }}</span><span class="ml-2 capitalize text-slate-500">{{ $activation->status }}</span></div>@if(in_array($activation->status, ['active', 'suspended'], true))<form method="POST" action="{{ route('admin.activations.deactivate', $activation) }}">@csrf<button class="text-sm font-semibold text-red-600 hover:text-red-500">Deactivate installation</button></form>@endif</div>@endforeach</div>@endif
    </section>
@empty
    <section class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-sm text-slate-500">No licenses have been issued.</section>
@endforelse
</div>
@endsection
