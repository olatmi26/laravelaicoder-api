<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Models
    |--------------------------------------------------------------------------
    */
    'default_provider' => env('DEFAULT_AI_PROVIDER', 'anthropic'),
    'models' => [
        'anthropic' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
        'openai'    => env('OPENAI_MODEL',    'gpt-4o'),
        'gemini'    => env('GEMINI_MODEL',    'gemini-1.5-pro'),
        'groq'      => env('GROQ_MODEL',      'llama-3.1-70b-versatile'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Definitions
    |--------------------------------------------------------------------------
    | Each agent has: id, name, class, description, max_tokens
    */
    'agents' => [
        'architect' => [
            'id'         => 'architect',
            'name'       => 'Project Architect',
            'class'      => \App\Agents\ArchitectAgent::class,
            'description'=> 'Designs system architecture, ERD, API contracts, and module plan',
            'max_tokens' => 4096,
            'icon'       => '🏗',
            'color'      => '#4080FF',
        ],
        'laravel' => [
            'id'         => 'laravel',
            'name'       => 'Laravel Engineer',
            'class'      => \App\Agents\LaravelEngineerAgent::class,
            'description'=> 'Generates PSR-12 PHP: controllers, models, migrations, routes, policies',
            'max_tokens' => 8192,
            'icon'       => '🔴',
            'color'      => '#FF6B35',
        ],
        'mobile' => [
            'id'         => 'mobile',
            'name'       => 'Mobile Engineer',
            'class'      => \App\Agents\MobileEngineerAgent::class,
            'description'=> 'Generates React Native and Flutter apps, navigation, typed API clients',
            'max_tokens' => 8192,
            'icon'       => '📱',
            'color'      => '#9B6DFF',
        ],
        'frontend' => [
            'id'         => 'frontend',
            'name'       => 'Frontend Engineer',
            'class'      => \App\Agents\FrontendEngineerAgent::class,
            'description'=> 'Generates React, Vue, Livewire, Inertia components with Tailwind',
            'max_tokens' => 8192,
            'icon'       => '🎨',
            'color'      => '#00C4CC',
        ],
        'uiux' => [
            'id'         => 'uiux',
            'name'       => 'UI/UX Integrator',
            'class'      => \App\Agents\UIIntegratorAgent::class,
            'description'=> 'Analyzes HTML themes, extracts design tokens, converts to Blade/React',
            'max_tokens' => 4096,
            'icon'       => '✦',
            'color'      => '#F5A623',
        ],
        'qa' => [
            'id'         => 'qa',
            'name'       => 'QA Engineer',
            'class'      => \App\Agents\QAEngineerAgent::class,
            'description'=> 'Writes Pest PHP tests: feature, unit, factories, assertions',
            'max_tokens' => 6144,
            'icon'       => '✓',
            'color'      => '#10D98C',
        ],
        'reviewer' => [
            'id'         => 'reviewer',
            'name'       => 'Code Reviewer',
            'class'      => \App\Agents\CodeReviewerAgent::class,
            'description'=> 'Reviews for PSR-12, SOLID, security vulnerabilities, best practices',
            'max_tokens' => 4096,
            'icon'       => '🔍',
            'color'      => '#FF9F43',
        ],
        'devops' => [
            'id'         => 'devops',
            'name'       => 'DevOps Engineer',
            'class'      => \App\Agents\DevOpsEngineerAgent::class,
            'description'=> 'Generates Dockerfile, GitHub Actions CI, Forge deploy scripts, .env',
            'max_tokens' => 4096,
            'icon'       => '🚀',
            'color'      => '#00BFA5',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Pipelines per Project Type
    |--------------------------------------------------------------------------
    | Define which agents run, and in what order, for each project type.
    | Groups run in parallel. Sequential = each group waits for previous.
    |
    | Format: array of groups. Each group runs its agents in parallel.
    */
    'pipelines' => [
        'laravel-web' => [
            ['architect'],                         // Step 1
            ['laravel'],                           // Step 2
            ['frontend', 'qa'],                    // Step 3 — parallel
            ['reviewer'],                          // Step 4
            ['devops'],                            // Step 5
        ],
        'laravel-api' => [
            ['architect'],
            ['laravel'],
            ['qa', 'reviewer'],                    // parallel
            ['devops'],
        ],
        'react-native' => [
            ['architect'],
            ['mobile'],
            ['qa'],
        ],
        'flutter' => [
            ['architect'],
            ['mobile'],
            ['qa'],
        ],
        'react-spa' => [
            ['architect'],
            ['frontend'],
            ['qa'],
        ],
        'vue-spa' => [
            ['architect'],
            ['frontend'],
            ['qa'],
        ],
        'admin-panel' => [
            ['architect'],
            ['laravel'],
            ['frontend', 'uiux'],                  // parallel
            ['qa'],
            ['devops'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | System Prompts Path
    |--------------------------------------------------------------------------
    */
    'prompts_path' => resource_path('agent-prompts'),

];
