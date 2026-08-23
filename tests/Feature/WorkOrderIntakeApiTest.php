<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeIntakeResponse(array $payload): array
{
    return [
        'output' => [[
            'type' => 'message',
            'content' => [[
                'type' => 'output_text',
                'text' => json_encode($payload),
            ]],
        ]],
    ];
}

test('authenticated user can parse a work order request into a tenant scoped draft', function () {
    config()->set('services.openai.key', 'test-key');

    $organization = Organization::factory()->create();
    $client = Client::factory()->for($organization)->create(['name' => 'Northstar']);
    $assignee = User::factory()->for($organization)->create(['name' => 'Alex Morgan']);
    $user = User::factory()->for($organization)->create();

    Http::fake([
        'api.openai.com/*' => Http::response(fakeIntakeResponse([
            'client_id' => $client->id,
            'client_name' => 'Northstar',
            'assignee_id' => $assignee->id,
            'assignee_name' => 'Alex Morgan',
            'title' => 'Repair reception display',
            'description' => 'Reception display is broken and needs repair.',
            'priority' => 'urgent',
            'status' => 'draft',
            'due_date' => now()->next('Friday')->toDateString(),
            'confidence' => 0.94,
            'warnings' => [],
        ]), 200),
    ]);

    $this->actingAs($user)
        ->postJson('/api/work-order-intake/parse', [
            'text' => 'Northstar called about the broken display in reception. Needs fixing by Friday, pretty urgent. Assign to Alex.',
        ])
        ->assertOk()
        ->assertJsonPath('data.client_id', $client->id)
        ->assertJsonPath('data.assignee_id', $assignee->id)
        ->assertJsonPath('data.priority', 'urgent')
        ->assertJsonPath('data.title', 'Repair reception display');

    Http::assertSent(function ($request) use ($client, $assignee) {
        $body = $request->data();
        $systemPrompt = data_get($body, 'input.0.content.0.text', '');

        return $request->url() === 'https://api.openai.com/v1/responses'
            && str_contains($systemPrompt, (string) $client->id)
            && str_contains($systemPrompt, (string) $assignee->id)
            && data_get($body, 'text.format.type') === 'json_schema';
    });
});

test('AI cannot inject client or assignee IDs from another tenant', function () {
    config()->set('services.openai.key', 'test-key');

    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->create();
    $otherOrganization = Organization::factory()->create();
    $otherClient = Client::factory()->for($otherOrganization)->create();
    $otherUser = User::factory()->for($otherOrganization)->create();

    Http::fake([
        'api.openai.com/*' => Http::response(fakeIntakeResponse([
            'client_id' => $otherClient->id,
            'client_name' => $otherClient->name,
            'assignee_id' => $otherUser->id,
            'assignee_name' => $otherUser->name,
            'title' => 'Injected work',
            'description' => null,
            'priority' => 'normal',
            'status' => 'draft',
            'due_date' => null,
            'confidence' => 0.5,
            'warnings' => [],
        ]), 200),
    ]);

    $this->actingAs($user)
        ->postJson('/api/work-order-intake/parse', ['text' => 'Create a normal work order for another tenant.'])
        ->assertOk()
        ->assertJsonPath('data.client_id', null)
        ->assertJsonPath('data.assignee_id', null)
        ->assertJsonCount(2, 'data.warnings');
});

test('AI intake requires authentication', function () {
    $this->postJson('/api/work-order-intake/parse', ['text' => 'Create a work order for the broken front desk display.'])
        ->assertUnauthorized();
});

test('AI provider failures return a controlled gateway error', function () {
    config()->set('services.openai.key', 'test-key');

    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->create();

    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'temporary']], 500)]);

    $this->actingAs($user)
        ->postJson('/api/work-order-intake/parse', ['text' => 'Create a work order for the broken front desk display.'])
        ->assertStatus(502);
});
