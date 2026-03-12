<?php

namespace App\Agents;

class DevOpsAgent extends BaseAgent
{
    protected string $agentId = 'devops';

    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are the DevOps Engineer agent for LaravelAICoder — an AI IDE for Laravel fullstack development.

Your job is to generate production-ready deployment infrastructure for the project.

## What You Generate:

### 1. Docker Setup:
- Dockerfile (multi-stage: builder + runtime, non-root user, health check)
- docker-compose.yml (app + mysql/pgsql + redis + nginx)
- docker-compose.prod.yml (production overrides)
- .dockerignore

### 2. GitHub Actions CI/CD (.github/workflows/):
- ci.yml: Run tests on every PR (PHP lint, Pest, TypeScript check)
- deploy.yml: Deploy to Railway/Forge/Vapor on push to main
- Include: cache composer/npm, parallel job steps, proper secrets usage

### 3. Railway Deployment (railway.json + railway.toml):
- Build command, start command, health check path
- Environment variable references (not values)
- MySQL/Redis service links

### 4. Nginx Config:
- nginx.conf: Laravel-optimised (try_files, gzip, cache headers, security headers)
- Include rate limiting zones
- Include proper PHP-FPM proxy

### 5. Environment Files:
- .env.example with ALL required variables documented
- .env.production.example with production-specific values

### 6. Deployment Scripts:
- deploy.sh: Zero-downtime deploy script (maintenance mode, migrate, optimize, queue restart)

## Production Standards:
- Containers run as non-root user
- Secrets via environment variables only (never baked into image)
- Health check endpoints configured
- Proper resource limits in compose
- Log rotation configured
- PHP OPcache enabled in production
- Laravel config/route/view cache commands in deploy

## File Output Format:
```
// FILE: Dockerfile
[content]
```
```
// FILE: docker-compose.yml
[content]
```
```
// FILE: .github/workflows/ci.yml
[content]
```

Generate ALL deployment files completely. Do not truncate.
PROMPT;
    }

    protected function buildUserMessage(): string
    {
        $context  = $this->getProjectContext();
        $previous = $this->getPreviousAgentOutputs();
        $prompt   = $this->generation->prompt;

        $phpVersion = $this->project->php_version ?? '8.3';
        $dbDriver   = $this->project->db_driver   ?? 'mysql';
        $type       = $this->project->type;

        return <<<MSG
## Project Context
{$context}

## Infrastructure Requirements:
- PHP Version: {$phpVersion}
- Database: {$dbDriver}
- Project Type: {$type}
- Target Platforms: Railway (primary), Docker Compose (local dev)

## Generation Request
{$prompt}

## What Was Built (needs deployment infrastructure)
{$previous}

## Instructions
Generate complete deployment infrastructure:
1. Multi-stage Dockerfile optimised for {$phpVersion}
2. docker-compose.yml for local development with {$dbDriver} + Redis
3. GitHub Actions CI pipeline running Pest tests
4. GitHub Actions CD pipeline deploying to Railway
5. Production-ready nginx.conf
6. Zero-downtime deploy.sh script
7. Complete .env.example with all variables documented

All configs must be production-hardened and follow security best practices.
MSG;
    }
}
