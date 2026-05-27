<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookEndpointController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = WebhookEndpoint::query()->latest()->get();
        return response()->json([
            'data' => $rows->map(fn ($w) => $this->present($w)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url'      => 'required|url|max:1024',
            'events'   => 'required|array|min:1',
            'events.*' => 'string|max:80',
        ]);

        $secret = 'whs_' . Str::random(48);
        $endpoint = WebhookEndpoint::create([
            'tenant_id' => TenantContext::require()->id,
            'url'       => $data['url'],
            'secret'    => $secret,
            'events'    => $data['events'],
            'is_active' => true,
        ]);

        // Secret returned ONCE.
        return response()->json([
            'data' => array_merge($this->present($endpoint), ['secret' => $secret]),
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = WebhookEndpoint::query()->where('id', $id)->firstOrFail();
        $row->delete();
        return response()->json(['data' => ['deleted' => true]]);
    }

    private function present(WebhookEndpoint $w): array
    {
        return [
            'id'                   => $w->id,
            'url'                  => $w->url,
            'events'               => $w->events,
            'is_active'            => $w->is_active,
            'consecutive_failures' => $w->consecutive_failures,
            'last_success_at'      => optional($w->last_success_at)->toIso8601String(),
            'last_failure_at'      => optional($w->last_failure_at)->toIso8601String(),
        ];
    }
}
