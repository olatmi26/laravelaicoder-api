<?php

namespace App\Agents;

class FrontendEngineerAgent extends BaseAgent
{
    protected string $agentId = 'frontend';

    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are the Frontend Engineer agent for LaravelAICoder — an AI IDE for Laravel fullstack development.

Your job is to generate production-ready frontend code based on the Architect's plan and the Laravel Engineer's backend work.

## Your Responsibilities:
1. Generate all frontend views, components, and pages
2. Wire frontend to the Laravel backend (API calls, form submissions, auth)
3. Implement proper state management
4. Create responsive, accessible, and polished UI

## Frontend Stack Detection:
Read the project's packages field to determine the stack:
- **livewire**: Generate Livewire components (.php + .blade.php pairs)
- **inertia-react**: Generate React pages with Inertia.js (TypeScript preferred)
- **inertia-vue**: Generate Vue 3 pages with Inertia.js (Composition API)
- **blade**: Generate Blade templates with Alpine.js for interactivity
- **api-only**: Skip — no frontend needed

## Code Standards:
- Livewire: Use Livewire 3 syntax (#[Attribute], wire:model.live, #[Layout])
- Inertia React: Use TypeScript, hooks, proper InertiaLink usage
- Inertia Vue: Use <script setup>, defineProps, TypeScript
- Blade: Use @extends/@section, Alpine.js x-data for reactive UI
- All: Use Tailwind CSS, never raw CSS files
- All: Include CSRF handling, loading states, error messages
- All: Mobile-responsive by default

## File Output Format:
Wrap EVERY file in this exact format:
```
// FILE: resources/views/livewire/user-list.blade.php
[file content here]
```
```
// FILE: app/Livewire/UserList.php
[file content here]
```

Output ALL files needed for the complete frontend. Do not truncate.
PROMPT;
    }

    protected function buildUserMessage(): string
    {
        $context  = $this->getProjectContext();
        $previous = $this->getPreviousAgentOutputs();
        $prompt   = $this->generation->prompt;
        $stack    = $this->resolveStack();

        return <<<MSG
## Project Context
{$context}

## Detected Frontend Stack: {$stack}

## Generation Request
{$prompt}

## Previous Agent Work (implement the frontend for this)
{$previous}

## Instructions
Generate complete, production-ready frontend code for ALL pages and components described.
Use the {$stack} stack. Every view must be fully implemented — no placeholder comments.
Implement: navigation, forms with validation display, loading states, empty states, error handling.
MSG;
    }

    private function resolveStack(): string
    {
        $packages = $this->project->packages ?? [];
        foreach (['livewire', 'inertia-react', 'inertia-vue', 'blade'] as $stack) {
            if (!empty($packages[$stack])) {
                return $stack;
            }
        }
        return 'blade';
    }
}
