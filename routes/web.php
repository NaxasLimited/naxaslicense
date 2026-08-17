<?php

use App\Http\Controllers\Admin\PortalController;
use App\Models\ActivationRequest;
use App\Services\DomainNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::redirect('/dashboard', '/admin')->middleware(['auth', 'admin'])->name('dashboard');
Route::get('/health', fn () => response()->json(['status' => 'ok', 'application' => 'Naxas License Portal', 'version' => config('app.version', '1.0.0')]));
Route::view('/activate', 'activate')->name('activate');
Route::post('/activate', function (Request $request, DomainNormalizer $domains) {
    $validated = $request->validate(['request_token' => 'required|string|max:64', 'domain' => 'required|string|max:253']);
    $activation = ActivationRequest::where('request_token_hash', hash('sha256', $validated['request_token']))->first();

    try {
        $submittedDomain = $domains->canonical($domains->normalize($validated['domain']));
        $domainMatches = $activation && hash_equals($domains->canonical($activation->normalized_domain), $submittedDomain);
    } catch (InvalidArgumentException) {
        $domainMatches = false;
    }

    if (! $domainMatches) {
        return response()->view('activate', ['lookupError' => 'The activation request could not be found.'], 422);
    }
    if ($activation->status === 'pending' && $activation->expires_at->isPast()) {
        $activation->update(['status' => 'expired']);
    }

    return view('activate', ['activation' => $activation->fresh()]);
})->middleware('throttle:portal.submit');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/activation-requests', [PortalController::class, 'requests'])->name('requests');
    Route::get('/activation-requests/{activationRequest}', [PortalController::class, 'show'])->name('requests.show');
    Route::post('/activation-requests/{activationRequest}/approve', [PortalController::class, 'approve'])->middleware('throttle:admin.activation.approve')->name('requests.approve');
    Route::post('/activation-requests/{activationRequest}/reject', [PortalController::class, 'reject'])->name('requests.reject');
    Route::post('/activation-requests/{activationRequest}/expire', [PortalController::class, 'expire'])->name('requests.expire');
    Route::get('/customers', [PortalController::class, 'customers'])->name('customers');
    Route::post('/customers', [PortalController::class, 'storeCustomer'])->name('customers.store');
    Route::get('/licenses', [PortalController::class, 'licenses'])->name('licenses');
    Route::post('/licenses', [PortalController::class, 'storeLicense'])->name('licenses.store');
    Route::post('/licenses/{license}/activate', [PortalController::class, 'activate'])->name('licenses.activate');
    Route::post('/licenses/{license}/suspend', [PortalController::class, 'suspend'])->name('licenses.suspend');
    Route::post('/licenses/{license}/revoke', [PortalController::class, 'revoke'])->name('licenses.revoke');
    Route::post('/license-activations/{activation}/deactivate', [PortalController::class, 'deactivate'])->name('activations.deactivate');
});
require __DIR__.'/settings.php';
