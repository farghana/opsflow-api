<?php

namespace App\Services\Ai;

use App\Models\Organization;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WorkOrderIntakeParser
{
    public function parse(string $text, Organization $organization): array
    {
        $apiKey = config('services.anthropic.key');
        abort_if(blank($apiKey), 503, 'AI intake is not configured.');

        $clients = $organization->clients()
            ->select(['id', 'name', 'company_name'])
            ->orderBy('name')
            ->get();

        $teamMembers = $organization->users()
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model', 'claude-sonnet-5'),
                'max_tokens' => 1200,
                'system' => $this->systemPrompt($clients->toArray(), $teamMembers->toArray()),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $text,
                    ],
                ],
                'output_config' => [
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => $this->schema(),
                    ],
                ],
            ]);

        if ($response->failed()) {
            report(new RuntimeException('Anthropic intake request failed: '.$response->body()));
            abort(502, 'AI intake is temporarily unavailable.');
        }

        $content = collect($response->json('content', []))->firstWhere('type', 'text');
        $outputText = is_array($content) ? ($content['text'] ?? null) : null;
        $parsed = is_string($outputText) ? json_decode($outputText, true) : null;

        if (! is_array($parsed)) {
            throw new RuntimeException('AI intake returned an invalid structured response.');
        }

        return $this->resolveTenantEntities($parsed, $clients->toArray(), $teamMembers->toArray());
    }

    private function systemPrompt(array $clients, array $teamMembers): string
    {
        return implode("\n", [
            'You convert messy operational requests into work-order drafts.',
            'Today is '.today()->toDateString().'.',
            'Never invent a client or team member. Use only exact IDs from the provided tenant lists.',
            'If a name is ambiguous or absent, return null for its ID and add a warning.',
            'Infer relative dates such as today, tomorrow, Friday, next week using the current date.',
            'Keep status as draft unless the request explicitly indicates work is already queued, in progress, blocked, completed, or cancelled.',
            'Priority must be low, normal, high, or urgent. Use urgent only when the text clearly signals immediacy/severity.',
            'Clients: '.json_encode($clients, JSON_UNESCAPED_SLASHES),
            'Team members: '.json_encode($teamMembers, JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'client_id' => ['type' => ['integer', 'null']],
                'client_name' => ['type' => ['string', 'null']],
                'assignee_id' => ['type' => ['integer', 'null']],
                'assignee_name' => ['type' => ['string', 'null']],
                'title' => ['type' => 'string'],
                'description' => ['type' => ['string', 'null']],
                'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                'status' => ['type' => 'string', 'enum' => ['draft', 'queued', 'in_progress', 'blocked', 'completed', 'cancelled']],
                'due_date' => ['type' => ['string', 'null']],
                'confidence' => ['type' => 'number'],
                'warnings' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => [
                'client_id', 'client_name', 'assignee_id', 'assignee_name', 'title',
                'description', 'priority', 'status', 'due_date', 'confidence', 'warnings',
            ],
        ];
    }

    private function resolveTenantEntities(array $parsed, array $clients, array $teamMembers): array
    {
        $clientIds = collect($clients)->pluck('id')->map(fn ($id) => (int) $id);
        $memberIds = collect($teamMembers)->pluck('id')->map(fn ($id) => (int) $id);
        $warnings = collect($parsed['warnings'] ?? []);

        if ($parsed['client_id'] !== null && ! $clientIds->contains((int) $parsed['client_id'])) {
            $parsed['client_id'] = null;
            $warnings->push('The suggested client could not be matched to this organization.');
        }

        if ($parsed['assignee_id'] !== null && ! $memberIds->contains((int) $parsed['assignee_id'])) {
            $parsed['assignee_id'] = null;
            $warnings->push('The suggested assignee could not be matched to this organization.');
        }

        $parsed['confidence'] = max(0, min(1, (float) ($parsed['confidence'] ?? 0)));
        $parsed['warnings'] = $warnings->unique()->values()->all();

        return $parsed;
    }
}
