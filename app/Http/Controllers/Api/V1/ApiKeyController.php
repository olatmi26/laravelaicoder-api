<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    // GET /api/v1/user/api-keys
    public function index(Request $request): JsonResponse
    {
        $keys = $request->user()
            ->apiKeys()
            ->select(['id', 'provider', 'model', 'is_active', 'last_used_at', 'created_at'])
            ->latest()
            ->get()
            ->map(fn ($k) => array_merge($k->toArray(), ['masked_key' => $k->masked_key]));

        return response()->json(['api_keys' => $keys]);
    }

    // POST /api/v1/user/api-keys
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->canUseBYOK()) {
            return response()->json([
                'message' => 'BYOK is not available on your plan. Please upgrade to Pro.',
            ], 402);
        }

        $data = $request->validate([
            'provider' => ['required', 'in:anthropic,openai,gemini,groq'],
            'key'      => ['required', 'string', 'min:10'],
            'model'    => ['nullable', 'string', 'max:100'],
        ]);

        // One key per provider per user — upsert
        $apiKey = $request->user()->apiKeys()->updateOrCreate(
            ['provider' => $data['provider']],
            [
                'key'       => $data['key'],   // uses setKeyAttribute → encrypts automatically
                'model'     => $data['model'] ?? null,
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'API key saved.',
            'api_key' => [
                'id'         => $apiKey->id,
                'provider'   => $apiKey->provider,
                'model'      => $apiKey->model,
                'masked_key' => $apiKey->masked_key,
                'is_active'  => $apiKey->is_active,
            ],
        ], 201);
    }

    // PUT /api/v1/user/api-keys/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $apiKey = $request->user()->apiKeys()->findOrFail($id);

        $data = $request->validate([
            'key'       => ['sometimes', 'string', 'min:10'],
            'model'     => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['key'])) {
            $apiKey->key = $data['key'];   // triggers setKeyAttribute
            unset($data['key']);
        }

        $apiKey->fill($data)->save();

        return response()->json([
            'message' => 'API key updated.',
            'api_key' => [
                'id'         => $apiKey->id,
                'provider'   => $apiKey->provider,
                'masked_key' => $apiKey->masked_key,
                'is_active'  => $apiKey->is_active,
            ],
        ]);
    }

    // DELETE /api/v1/user/api-keys/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->apiKeys()->findOrFail($id)->delete();
        return response()->json(['message' => 'API key deleted.']);
    }
}
