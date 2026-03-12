<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// ─────────────────────────────────────────────
// ProjectController
// ─────────────────────────────────────────────
class ProjectController extends Controller
{
    public function indexByWorkspace(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $projects = $workspace->projects()
            ->withCount('files', 'generations')
            ->latest()
            ->get();

        return response()->json(['projects' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->canCreateProject()) {
            return response()->json([
                'message' => 'Project limit reached for your plan. Please upgrade.',
                'upgrade_url' => config('app.frontend_url').'/pricing',
            ], 402);
        }

        $data = $request->validate([
            'workspace_id'     => ['required', 'exists:workspaces,id'],
            'name'             => ['required', 'string', 'max:100', 'regex:/^[a-z0-9\-]+$/'],
            'type'             => ['required', 'in:laravel-web,laravel-api,react-native,flutter,react-spa,vue-spa,admin-panel'],
            'template'         => ['nullable', 'string'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'php_version'      => ['nullable', 'string'],
            'node_version'     => ['nullable', 'string'],
            'dart_version'     => ['nullable', 'string'],
            'mobile_framework' => ['nullable', 'in:react-native,flutter'],
            'db_driver'        => ['nullable', 'in:mysql,pgsql,sqlite,sqlsrv'],
            'port'             => ['nullable', 'integer', 'min:1024', 'max:65535'],
            'packages'         => ['nullable', 'array'],
            'directory'        => ['nullable', 'string'],
        ]);

        // Check mobile plan permission
        if (in_array($data['type'], ['react-native', 'flutter']) && !$request->user()->canUseMobile()) {
            return response()->json([
                'message' => 'Mobile projects require the Pro plan or higher.',
            ], 403);
        }

        $project = Project::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status'  => 'idle',
        ]);

        return response()->json(['project' => $project], 201);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'project' => $project->load('files', 'generations.agentJobs', 'plugins'),
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status'      => ['sometimes', 'in:idle,building,done,error'],
            'progress'    => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        $project->update($data);

        return response()->json(['project' => $project]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);
        $project->delete();
        return response()->json(['message' => 'Project deleted.']);
    }

    public function files(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $files = $project->files()
            ->select('id', 'path', 'language', 'status', 'last_generated_at')
            ->orderBy('path')
            ->get();

        return response()->json(['files' => $files]);
    }

    public function fileContent(Request $request, Project $project, string $path): JsonResponse
    {
        $this->authorize('view', $project);

        $file = $project->files()->where('path', $path)->firstOrFail();

        return response()->json(['file' => $file]);
    }

    public function installPlugin(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'name'    => ['required', 'string'],
            'type'    => ['required', 'in:composer,npm'],
            'version' => ['nullable', 'string'],
        ]);

        $plugin = $project->plugins()->create([
            ...$data,
            'user_id'      => $request->user()->id,
            'status'       => 'installed',
            'installed_at' => now(),
        ]);

        return response()->json(['plugin' => $plugin], 201);
    }

    public function plugins(Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        return response()->json(['plugins' => $project->plugins]);
    }

    public function uiUpload(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        if (!$request->user()->canUseCustomUI()) {
            return response()->json(['message' => 'Custom UI upload requires the Pro plan.'], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:zip,html,css'], // 50MB max
        ]);

        $path = $request->file('file')->store('ui-uploads/'.$project->id, 's3');

        $upload = $project->uiUploads()->create([
            'user_id'           => $request->user()->id,
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'storage_path'      => $path,
            'status'            => 'uploaded',
        ]);

        // Dispatch UIIntegratorAgent for analysis
        // (will be implemented in Phase 4)

        return response()->json(['upload' => $upload], 201);
    }
}

// ─────────────────────────────────────────────
// ApiKeyController
// ─────────────────────────────────────────────
