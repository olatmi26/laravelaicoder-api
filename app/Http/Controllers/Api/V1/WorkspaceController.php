<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workspace;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// ─────────────────────────────────────────────
// WorkspaceController
// ─────────────────────────────────────────────
class WorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspaces = $request->user()
            ->workspaces()
            ->withCount('projects')
            ->latest()
            ->get();

        return response()->json(['workspaces' => $workspaces]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $workspace = $request->user()->workspaces()->create($data);

        return response()->json(['workspace' => $workspace], 201);
    }

    public function show(Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);
        return response()->json(['workspace' => $workspace->load('projects')]);
    }

    public function update(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('update', $workspace);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $workspace->update($data);
        return response()->json(['workspace' => $workspace]);
    }

    public function destroy(Workspace $workspace): JsonResponse
    {
        $this->authorize('delete', $workspace);
        $workspace->delete();
        return response()->json(['message' => 'Workspace deleted.']);
    }
}

