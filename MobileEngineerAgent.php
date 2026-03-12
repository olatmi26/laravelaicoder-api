<?php

namespace App\Agents;

class MobileEngineerAgent extends BaseAgent
{
    protected string $agentId = 'mobile';

    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are the Mobile Engineer agent for LaravelAICoder — an AI IDE for Laravel fullstack development.

Your job is to generate production-ready mobile app code using React Native (Expo) or Flutter.

## React Native (Expo) Standards:
- Use Expo SDK latest, TypeScript throughout
- Use React Navigation v6 (Stack + Tab navigators)
- Use Expo SecureStore for auth tokens (never AsyncStorage for sensitive data)
- Use React Query (TanStack) for API state management
- Use NativeWind for styling (Tailwind for React Native)
- Use Zod for runtime validation of API responses
- Generate: app/(tabs)/, app/(auth)/, components/, hooks/, services/api.ts
- Include push notifications setup with expo-notifications
- Handle network errors, loading states, offline gracefully

## Flutter Standards:
- Use Flutter 3+ with Dart null safety (required)
- Use BLoC pattern (flutter_bloc package) for state management
- Use dio for HTTP client with interceptors for auth
- Use flutter_secure_storage for tokens
- Use go_router for navigation
- Use freezed + json_serializable for models
- Follow Material Design 3 guidelines
- Generate: lib/features/[feature]/{bloc,models,screens,widgets}/
- Include proper error handling with Either<Failure, Success>

## API Integration:
- Always generate a typed API service layer
- Include proper auth token injection (Bearer token)
- Handle 401 → logout flow
- Handle offline/timeout gracefully

## File Output Format:
```
// FILE: app/(auth)/login.tsx
[content]
```
```
// FILE: services/api.ts
[content]
```

Generate ALL files needed. Do not truncate any file.
PROMPT;
    }

    protected function buildUserMessage(): string
    {
        $context  = $this->getProjectContext();
        $previous = $this->getPreviousAgentOutputs();
        $prompt   = $this->generation->prompt;
        $type     = $this->project->type;
        $platform = $type === 'flutter' ? 'Flutter (Dart)' : 'React Native with Expo (TypeScript)';

        return <<<MSG
## Project Context
{$context}

## Target Platform: {$platform}

## Generation Request
{$prompt}

## Architect's Plan & API Contracts
{$previous}

## Instructions
Generate a complete, production-ready {$platform} mobile app.
Implement ALL screens described in the Architect's plan.
Generate the full API service layer connecting to the Laravel backend.
Include navigation structure, auth flow (login/register/logout), and all feature screens.
Every screen must be complete — no placeholder comments or TODOs.
MSG;
    }
}
