<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentJob;
use App\Models\Generation;
use App\Models\Project;
use App\Services\AgentOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerationController extends Controller
{
    public function __construct(private AgentOrchestrator $orchestrator) {}

    // POST /api/v1/projects/{project}/generations
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        // Check plan limits
        if (!$request->user()->canGenerate()) {
            return response()->json([
                'message' => 'Monthly generation limit reached. Please upgrade your plan.',
                'upgrade_url' => config('app.frontend_url').'/pricing',
            ], 402);
        }

        $data = $request->validate([
            'prompt'        => ['required', 'string', 'min:10', 'max:5000'],
            'agent_ids'     => ['nullable', 'array'],
            'agent_ids.*'   => ['string', 'in:architect,laravel,mobile,frontend,uiux,qa,reviewer,devops'],
        ]);

        // Create generation record
        $generation = Generation::create([
            'project_id'     => $project->id,
            'user_id'        => $request->user()->id,
            'prompt'         => $data['prompt'],
            'status'         => 'pending',
            'agent_pipeline' => $data['agent_ids'] ?? null,
        ]);

        // Dispatch the agent pipeline
        $this->orchestrator->dispatch($generation, $project);

        // Increment project generation count
        $project->increment('generation_count');

        return response()->json([
            'generation' => [
                'id'     => $generation->id,
                'status' => $generation->status,
                'stream_url' => "/api/v1/generations/{$generation->id}/stream",
            ],
        ], 201);
    }

    // GET /api/v1/generations/{generation}
    public function show(Generation $generation): JsonResponse
    {
        $this->authorize('view', $generation);

        return response()->json([
            'generation' => $generation->load('agentJobs'),
        ]);
    }

    // GET /api/v1/generations/{generation}/stream  — SSE
    public function stream(Request $request, Generation $generation): StreamedResponse
    {
        $this->authorize('view', $generation);

        $streamKey = "generation:{$generation->id}:stream";

        return response()->stream(function () use ($streamKey, $generation) {
            // Send headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no'); // Disable nginx buffering

            $lastIndex = 0;
            $timeout   = time() + 290; // 4 min 50 sec max
            $doneTypes = ['done', 'error'];

            while (time() < $timeout) {
                // Read events from Redis list
                $events = Cache::store('redis')->lrange($streamKey, $lastIndex, -1);

                foreach ($events as $i => $rawEvent) {
                    $event = json_decode($rawEvent, true);
                    if (!$event) continue;

                    // Send SSE event
                    echo "data: ".json_encode($event)."\n\n";
                    ob_flush();
                    flush();
                    $lastIndex++;

                    // If all agents are done, close stream
                    if ($event['type'] === 'generation_complete' || $event['type'] === 'generation_failed') {
                        echo "data: {\"type\":\"stream_close\"}\n\n";
                        ob_flush();
                        flush();
                        return;
                    }
                }

                // Send heartbeat every 15s to keep connection alive
                if ($lastIndex % 50 === 0) {
                    echo ": heartbeat\n\n";
                    ob_flush();
                    flush();
                }

                usleep(200_000); // poll every 200ms
            }

            // Timeout
            echo "data: {\"type\":\"stream_timeout\"}\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection'    => 'keep-alive',
        ]);
    }
}
