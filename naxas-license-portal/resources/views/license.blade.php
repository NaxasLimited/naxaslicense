<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Buildora CMS License</title>@vite(['resources/css/app.css'])</head>
<body class="min-h-screen bg-zinc-50 p-4 text-zinc-900 sm:p-8"><main class="mx-auto max-w-2xl overflow-hidden rounded-xl bg-white p-5 shadow sm:p-8">
<h1 class="text-2xl font-semibold">Buildora CMS License</h1>
@if(session('status'))<p class="mt-4 rounded bg-emerald-50 p-3 text-emerald-900">{{ session('status') }}</p>@endif
@if($errors->any())<p class="mt-4 rounded bg-red-50 p-3 text-red-900">{{ $errors->first() }}</p>@endif
<dl class="mt-6 grid gap-3 break-words"><div><dt class="font-medium">Status</dt><dd>{{ $licenseState?->request_status === 'active' ? 'Active' : ucfirst($licenseState?->request_status ?? 'Inactive') }}</dd></div>
@if($licenseState?->request_status === 'pending')<div><dt class="font-medium">Activation request token</dt><dd class="select-all font-mono">{{ $licenseState->request_token }}</dd></div><div><dt class="font-medium">Expires</dt><dd>{{ $licenseState->request_expires_at }}</dd></div>@endif
@if($licenseState?->request_status === 'active')<div><dt class="font-medium">Updates</dt><dd>{{ data_get($licenseState->entitlement, 'update_entitlement') ? 'Included' : 'Not included' }}</dd></div><div><dt class="font-medium">Support</dt><dd>{{ data_get($licenseState->entitlement, 'support_entitlement') ? 'Included' : 'Not included' }}</dd></div>@endif</dl>
<div class="mt-6 flex flex-col gap-3 sm:flex-row">@if(!$licenseState || !in_array($licenseState->request_status, ['pending', 'active'], true))<form method="post" action="{{ route('license.create') }}">@csrf<button class="rounded bg-zinc-900 px-4 py-2 text-white disabled:opacity-50" onclick="this.disabled=true;this.form.submit()">Generate Activation Request</button></form>@endif
@if($licenseState?->request_status === 'pending')<form method="post" action="{{ route('license.poll') }}">@csrf<button class="rounded bg-blue-700 px-4 py-2 text-white disabled:opacity-50" onclick="this.disabled=true;this.form.submit()">Check Activation Status</button></form>@endif</div>
</main></body></html>
