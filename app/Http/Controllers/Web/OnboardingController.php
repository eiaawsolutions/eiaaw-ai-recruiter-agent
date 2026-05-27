<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ExtractBrandDnaJob;
use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\TenantContext;
use App\Support\UrlGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Three-step onboarding wizard:
 *  1. /start  — anonymous: create tenant + owner user + optional brand URL
 *  2. /brand  — authenticated: queue brand-DNA extraction (Follow-up 5)
 *  3. /keys   — authenticated: mint first API key + optional webhook endpoint
 */
class OnboardingController extends Controller
{
    public function start(): View
    {
        return view('onboarding.start');
    }

    public function storeStart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_name'    => 'required|string|max:120',
            'contact_email'  => 'required|email|max:200',
            'admin_name'     => 'required|string|max:120',
            'admin_password' => 'required|string|min:8|max:200',
            'timezone'       => 'nullable|string|max:64',
            'brand_url'      => 'nullable|url|max:300',
            'brand_voice'    => 'nullable|string|max:200',
        ]);

        if (! empty($data['brand_url']) && ! UrlGuard::isSafe($data['brand_url'])) {
            return back()->withErrors(['brand_url' => 'Brand URL must be a public https endpoint.'])->withInput();
        }

        if (Tenant::query()->where('slug', Str::slug($data['tenant_name']))->exists()) {
            return back()->withErrors(['tenant_name' => 'A tenant with that name already exists.'])->withInput();
        }

        $tenant = null;
        $user   = null;
        DB::transaction(function () use ($data, &$tenant, &$user) {
            $tenant = Tenant::create([
                'name'             => $data['tenant_name'],
                'contact_email'    => $data['contact_email'],
                'timezone'         => $data['timezone'] ?? 'UTC',
                'brand_voice'      => $data['brand_voice'] ?? null,
                'require_approval' => true,
                'is_active'        => true,
                'brand_profile'    => ! empty($data['brand_url']) ? ['source_url' => $data['brand_url']] : null,
            ]);
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name'      => $data['admin_name'],
                'email'     => $data['contact_email'],
                'password'  => Hash::make($data['admin_password']),
                'role'      => 'owner',
            ]);
        });

        TenantContext::bind($tenant);
        Auth::login($user);

        if (! empty($data['brand_url'])) {
            ExtractBrandDnaJob::dispatch($tenant->id, $data['brand_url']);
        }

        return redirect()->route('onboarding.brand')->with('status', 'Tenant created — pick up where the brand extractor leaves off.');
    }

    public function brand(): View
    {
        $tenant = TenantContext::require();
        return view('onboarding.brand', compact('tenant'));
    }

    public function rerunBrand(Request $request): RedirectResponse
    {
        $tenant = TenantContext::require();
        $request->validate(['brand_url' => 'required|url|max:300']);
        $url = (string) $request->input('brand_url');
        if (! UrlGuard::isSafe($url)) {
            return back()->withErrors(['brand_url' => 'Brand URL must be a public https endpoint.']);
        }
        ExtractBrandDnaJob::dispatch($tenant->id, $url);
        return back()->with('status', 'Brand extraction queued.');
    }

    public function keys(): View
    {
        $apiKeys  = ApiKey::query()->latest()->get();
        $hooks    = WebhookEndpoint::query()->latest()->get();
        $plaintext = session('onboarding.new_key_plaintext');
        $secret    = session('onboarding.new_webhook_secret');
        session()->forget(['onboarding.new_key_plaintext', 'onboarding.new_webhook_secret']);

        return view('onboarding.keys', compact('apiKeys', 'hooks', 'plaintext', 'secret'));
    }

    public function mintKey(Request $request): RedirectResponse
    {
        $data = $request->validate(['label' => 'required|string|max:120']);
        [, $plaintext] = ApiKey::mint(TenantContext::require(), $data['label'], ['*'], null, $request->ip());
        return back()->with([
            'status' => 'API key minted.',
            'onboarding.new_key_plaintext' => $plaintext,
        ]);
    }

    public function registerWebhook(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url'      => 'required|url|max:1024',
            'events'   => 'required|array|min:1',
            'events.*' => 'string|max:80',
        ]);
        if (! UrlGuard::isSafe($data['url'])) {
            return back()->withErrors(['url' => 'Webhook URL must be a public https endpoint.']);
        }
        $secret = 'whs_' . Str::random(48);
        WebhookEndpoint::create([
            'tenant_id' => TenantContext::require()->id,
            'url'       => $data['url'],
            'secret'    => $secret,
            'events'    => $data['events'],
            'is_active' => true,
        ]);
        return back()->with([
            'status' => 'Webhook registered.',
            'onboarding.new_webhook_secret' => $secret,
        ]);
    }

    public function finish(): RedirectResponse
    {
        return redirect()->route('dashboard')->with('status', 'Onboarding complete. Create your first job to get going.');
    }
}
