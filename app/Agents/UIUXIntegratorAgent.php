<?php

namespace App\Agents;

use App\Models\UiUpload;

class UIUXIntegratorAgent extends BaseAgent
{
    protected string $agentId = 'uiux';

    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are the UI/UX Integrator agent for LaravelAICoder — an AI IDE for Laravel fullstack development.

Your job is to analyze HTML/CSS design files or design tokens uploaded by the user and convert them into working Blade/Livewire/Inertia components that exactly match the design.

## What You Do:
1. **Analyze** the uploaded design (HTML file, CSS, Figma export, or design token JSON)
2. **Extract** the design system: colors, typography, spacing, border-radius, shadows
3. **Map** design components to Laravel views: headers, cards, tables, forms, modals, navbars
4. **Generate** Tailwind config extensions that match the design tokens
5. **Produce** Blade/Livewire/Vue components that pixel-perfectly implement the design

## Output Requirements:
- tailwind.config.js with custom theme extensions matching the design
- A base layout blade template using the design's structure
- Individual component blade partials (cards, tables, forms, etc.)
- CSS variables file if custom properties are used
- A DESIGN_SYSTEM.md documenting the extracted tokens

## Important:
- Preserve ALL colors exactly from the design
- Use CSS custom properties for theming
- Ensure dark mode compatibility if the design supports it
- Generate accessible markup (aria labels, semantic HTML)
- Use the design's exact font families (add Google Fonts CDN link if needed)

## File Output Format:
```
// FILE: tailwind.config.js
[content]
```
```
// FILE: resources/views/layouts/app.blade.php
[content]
```

Generate every file needed for complete design integration.
PROMPT;
    }

    protected function buildUserMessage(): string
    {
        $context   = $this->getProjectContext();
        $previous  = $this->getPreviousAgentOutputs();
        $prompt    = $this->generation->prompt;

        // Check for uploaded UI file
        $uiUpload  = UiUpload::where('project_id', $this->project->id)
            ->latest()
            ->first();

        $designContext = '';
        if ($uiUpload) {
            $designContext = "\n## Uploaded Design File\n";
            $designContext .= "Filename: {$uiUpload->original_filename}\n";
            if (!empty($uiUpload->design_tokens)) {
                $designContext .= "Extracted Design Tokens:\n";
                $designContext .= json_encode($uiUpload->design_tokens, JSON_PRETTY_PRINT);
            }
            if (!empty($uiUpload->component_map)) {
                $designContext .= "\nDetected Components:\n";
                $designContext .= json_encode($uiUpload->component_map, JSON_PRETTY_PRINT);
            }
            if ($uiUpload->integration_plan) {
                $designContext .= "\nIntegration Plan:\n{$uiUpload->integration_plan}";
            }
        }

        return <<<MSG
## Project Context
{$context}
{$designContext}

## Generation Request
{$prompt}

## Backend & Architecture Context
{$previous}

## Instructions
Analyze the design file/tokens provided and generate a complete Tailwind + Blade/Livewire component library
that implements the design system. Every component must match the design precisely.
Generate the tailwind.config.js theme extensions, layout templates, and all UI components.
MSG;
    }
}
