<?php

namespace App\Services;

use App\Models\AgentJob;
use App\Models\Generation;
use App\Models\Project;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AgentOrchestrator
{
    public function dispatch(Generation $generation, Project $project): void
    {
        $generation->update(['status' => 'running', 'started_at' => now()]);

        // Determine which agents to run
        $pipelineGroups = $this->resolvePipeline($generation, $project);
        $agentConfigs   = config('agents.agents');

        // Create AgentJob records for all agents
        $sequence = 0;
        $allJobs  = [];
        foreach ($pipelineGroups as $group) {
            foreach ($group as $agentId) {
                $job = AgentJob::create([
                    'generation_id' => $generation->id,
                    'agent_id'      => $agentId,
                    'sequence'      => $sequence++,
                    'status'        => 'pending',
                ]);
                $allJobs[$agentId] = $job;
            }
        }

        // Store total job count for completion tracking
        Cache::store('redis')->set(
            "generation:{$generation->id}:total_jobs",
            count($allJobs),
            3600
        );

        // Update generation with resolved pipeline
        $generation->update([
            'agent_pipeline' => array_merge(...$pipelineGroups),
        ]);

        // Dispatch groups sequentially, agents within a group in parallel
        $this->dispatchGroupsSequentially($generation, $pipelineGroups, $allJobs, $agentConfigs);
    }

    private function dispatchGroupsSequentially(
        Generation $generation,
        array $pipelineGroups,
        array $allJobs,
        array $agentConfigs,
    ): void {
        // Build a chain of Bus::batch() groups
        // Each group waits for the previous to complete

        $batchGroups = [];

        foreach ($pipelineGroups as $group) {
            $groupJobs = [];
            foreach ($group as $agentId) {
                $job     = $allJobs[$agentId];
                $class   = $agentConfigs[$agentId]['class'] ?? null;
                if (!$class) {
                    Log::warning("No class defined for agent: {$agentId}");
                    continue;
                }
                $groupJobs[] = new $class($generation->id, $job->id);
            }

            if (!empty($groupJobs)) {
                $batchGroups[] = $groupJobs;
            }
        }

        if (empty($batchGroups)) {
            $generation->update(['status' => 'failed']);
            return;
        }

        // Chain the batches: batch1 → batch2 → batch3 ...
        $this->chainBatches($generation, $batchGroups);
    }

    private function chainBatches(Generation $generation, array $batchGroups): void
    {
        if (empty($batchGroups)) return;

        $genId = $generation->id;

        // Build chain from last to first
        $chain = null;

        foreach (array_reverse($batchGroups) as $i => $groupJobs) {
            $isLast = $i === 0; // reversed, so first in reversed = last in original

            $batch = Bus::batch($groupJobs)
                ->then(function () use ($genId, $isLast) {
                    if ($isLast) {
                        // All agents done — mark generation complete
                        $gen = Generation::find($genId);
                        $gen?->update(['status' => 'done', 'finished_at' => now()]);

                        Cache::store('redis')->lpush(
                            "generation:{$genId}:stream",
                            json_encode(['type' => 'generation_complete', 'data' => ['generation_id' => $genId]])
                        );

                        // Update project status
                        $gen?->project?->update(['status' => 'done', 'progress' => 100]);
                    }
                })
                ->catch(function (\Throwable $e) use ($genId) {
                    Generation::find($genId)?->update(['status' => 'failed', 'finished_at' => now()]);
                    Cache::store('redis')->lpush(
                        "generation:{$genId}:stream",
                        json_encode(['type' => 'generation_failed', 'data' => ['error' => $e->getMessage()]])
                    );
                })
                ->onQueue('agents')
                ->allowFailures(false);

            // Nest: if there's a next batch, the current batch's then() dispatches next
        }

        // For simplicity in v1, dispatch groups sequentially via callbacks
        // In production, use laravel/bus chain or a more sophisticated orchestrator
        Bus::batch($batchGroups[0])
            ->then(function () use ($generation, $batchGroups) {
                $remaining = array_slice($batchGroups, 1);
                if (!empty($remaining)) {
                    $this->chainBatches($generation, $remaining);
                } else {
                    $generation->update(['status' => 'done', 'finished_at' => now()]);
                    Cache::store('redis')->lpush(
                        "generation:{$generation->id}:stream",
                        json_encode(['type' => 'generation_complete', 'data' => ['generation_id' => $generation->id]])
                    );
                    $generation->project?->update(['status' => 'done', 'progress' => 100]);
                }
            })
            ->catch(function (\Throwable $e) use ($generation) {
                $generation->update(['status' => 'failed', 'finished_at' => now()]);
            })
            ->onQueue('agents')
            ->dispatch();
    }

    private function resolvePipeline(Generation $generation, Project $project): array
    {
        // If user specified specific agents, use those as a flat single-group pipeline
        if (!empty($generation->agent_pipeline)) {
            return [$generation->agent_pipeline];
        }

        // Otherwise use the default pipeline for this project type
        return config('agents.pipelines.'.$project->type, [['architect', 'laravel', 'qa']]);
    }
}
