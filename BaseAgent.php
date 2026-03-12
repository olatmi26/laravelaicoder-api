<?php

namespace App\Agents;

use App\Models\AgentJob;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\AIProviderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class BaseAgent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $timeout   = 300; // 5 minutes per agent
    public int    $tries     = 2;
    public string $queue     = 'agents';

    protected Generation    $generation;
    protected AgentJob      $agentJob;
    protected Project       $project;
    protected User          $user;
    protected string        $agentId;

    public function __construct(
        protected int $generationId,
        protected int $agentJobId,
    ) {}

    // ── Each agent must implement these ──

    abstract protected function getSystemPrompt(): string;
    abstract protected function buildUserMessage(): string;

    // ── Job handle ──

    public function handle(AIProviderService $ai): void
    {
        $this->agentJob  = AgentJob::findOrFail($this->agentJobId);
        $this->generation = Generation::findOrFail($this->generationId);
        $this->project    = $this->generation->project;
        $this->user       = $this->generation->user;

        $this->agentJob->update(['status' => 'running', 'started_at' => now()]);
        $this->pushStreamEvent('start', ['agent' => $this->agentJob->agent_id]);

        try {
            $systemPrompt = $this->getSystemPrompt();
            $userMessage  = $this->buildUserMessage();

            $fullResponse  = '';
            $tokensInput   = 0;
            $tokensOutput  = 0;

            // Stream tokens back to SSE channel in real-time
            $ai->streamCompletion(
                user:         $this->user,
                systemPrompt: $systemPrompt,
                userMessage:  $userMessage,
                maxTokens:    config('agents.agents.'.$this->agentJob->agent_id.'.max_tokens', 4096),
                onToken: function (string $token) use (&$fullResponse) {
                    $fullResponse .= $token;
                    $this->pushStreamEvent('token', ['token' => $token, 'agent' => $this->agentJob->agent_id]);
                },
                onComplete: function (array $usage) use (&$tokensInput, &$tokensOutput) {
                    $tokensInput  = $usage['input_tokens']  ?? 0;
                    $tokensOutput = $usage['output_tokens'] ?? 0;
                },
            );

            // Parse files out of the response
            $generatedFiles = $this->parseGeneratedFiles($fullResponse);

            // Save agent job result
            $this->agentJob->update([
                'status'          => 'done',
                'output_text'     => $fullResponse,
                'generated_files' => array_keys($generatedFiles),
                'tokens_input'    => $tokensInput,
                'tokens_output'   => $tokensOutput,
                'cost_usd'        => $this->calculateCost($tokensInput, $tokensOutput),
                'finished_at'     => now(),
            ]);

            // Save files to project_files table
            foreach ($generatedFiles as $path => $content) {
                $this->project->files()->updateOrCreate(
                    ['path' => $path],
                    [
                        'content'            => $content,
                        'language'           => $this->detectLanguage($path),
                        'generation_id'      => $this->generationId,
                        'last_generated_at'  => now(),
                    ]
                );
            }

            // Update generation token totals
            $this->generation->increment('tokens_used', $tokensInput + $tokensOutput);

            $this->pushStreamEvent('done', [
                'agent'  => $this->agentJob->agent_id,
                'files'  => array_keys($generatedFiles),
                'tokens' => $tokensInput + $tokensOutput,
            ]);

        } catch (\Throwable $e) {
            Log::error("Agent {$this->agentJob->agent_id} failed", [
                'generation' => $this->generationId,
                'error'      => $e->getMessage(),
            ]);

            $this->agentJob->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);

            $this->pushStreamEvent('error', [
                'agent'   => $this->agentJob->agent_id,
                'message' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }

    // ── Stream event to Redis (consumed by SSE endpoint) ──

    protected function pushStreamEvent(string $type, array $data = []): void
    {
        $key   = "generation:{$this->generationId}:stream";
        $event = json_encode(['type' => $type, 'data' => $data, 'ts' => microtime(true)]);

        Cache::store('redis')->lpush($key, $event);
        Cache::store('redis')->expire($key, 3600); // 1 hour TTL
    }

    // ── Parse ```php ... ``` code blocks from agent output into [path => content] ──

    protected function parseGeneratedFiles(string $response): array
    {
        $files   = [];
        $pattern = '/```(?:php|javascript|typescript|dart|json|yaml|blade)?\s*\/\/ File: ([^\n]+)\n(.*?)```/s';

        preg_match_all($pattern, $response, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $path    = trim($match[1]);
            $content = trim($match[2]);
            if ($path && $content) {
                $files[$path] = $content;
            }
        }

        // Fallback: if no file annotations found, store as single file
        if (empty($files)) {
            preg_match_all('/```(?:php|javascript|typescript|dart)\n(.*?)```/s', $response, $codeBlocks, PREG_SET_ORDER);
            foreach ($codeBlocks as $i => $block) {
                $files["generated/output_".($i+1).".php"] = $block[1];
            }
        }

        return $files;
    }

    protected function detectLanguage(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match($ext) {
            'php'            => 'php',
            'js', 'jsx'      => 'javascript',
            'ts', 'tsx'      => 'typescript',
            'dart'           => 'dart',
            'json'           => 'json',
            'yaml', 'yml'    => 'yaml',
            'blade'          => 'blade',
            default          => 'plaintext',
        };
    }

    protected function calculateCost(int $inputTokens, int $outputTokens): float
    {
        // Claude claude-sonnet-4-5 pricing (approximate): $3/$15 per million tokens
        $inputCost  = ($inputTokens  / 1_000_000) * 3.00;
        $outputCost = ($outputTokens / 1_000_000) * 15.00;
        return round($inputCost + $outputCost, 6);
    }

    // ── Context helpers for child agents ──

    protected function getProjectContext(): string
    {
        $project = $this->project;
        return implode("\n", [
            "Project Name: {$project->name}",
            "Project Type: {$project->type_label}",
            "Template: ".($project->template ?? 'Custom'),
            "PHP Version: ".($project->php_version ?? '8.3'),
            "Database: ".($project->db_driver ?? 'mysql'),
            "Description: ".($project->description ?? 'No description provided.'),
            "Packages: ".implode(', ', array_keys(array_filter($project->packages ?? []))),
        ]);
    }

    protected function getPreviousAgentOutputs(): string
    {
        $jobs = AgentJob::where('generation_id', $this->generationId)
            ->where('status', 'done')
            ->where('id', '<', $this->agentJobId)
            ->orderBy('sequence')
            ->get();

        if ($jobs->isEmpty()) return '';

        $output = "\n\n--- Previous Agent Outputs ---\n";
        foreach ($jobs as $job) {
            $name    = config("agents.agents.{$job->agent_id}.name", $job->agent_id);
            $output .= "\n### {$name} Output:\n{$job->output_text}\n";
        }

        return $output;
    }
}
