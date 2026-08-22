<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function workOrderUser(Organization $organization): User
{
    return User::factory()->for($organization)->create();
}

function workOrderPayload(Client $client, array $overrides = []): array
{
    return array_merge([
        'client_id' => $client->id,
        'assignee_id' => null,
        'title' => 'Repair HVAC unit',
        'description' => 'Investigate intermittent shutdowns.',
        'internal_notes' => 'Call before arrival.',
        'status' => 'queued',
        'priority' => 'high',
        'due_date' => now()->addDays(3)->toDateString(),
    ], $overrides);
}

test('a user can create and view a work order in their organization', function () {
    $organization = Organization::factory()->create();
    $user = workOrderUser($organization);
    $client = Client::factory()->for($organization)->create();

    $response = $this->actingAs($user)->postJson('/api/work-orders', workOrderPayload($client));

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Repair HVAC unit')
        ->assertJsonPath('data.client.id', $client->id)
        ->assertJsonPath('data.status', 'queued');

    $workOrderId = $response->json('data.id');

    $this->getJson("/api/work-orders/{$workOrderId}")
        ->assertOk()
        ->assertJsonPath('data.id', $workOrderId)
        ->assertJsonPath('data.activities.0.type', 'created');

    $this->assertDatabaseHas('work_orders', [
        'id' => $workOrderId,
        'organization_id' => $organization->id,
        'client_id' => $client->id,
    ]);
});

test('work order client and assignee must belong to the same organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = workOrderUser($organization);
    $client = Client::factory()->for($organization)->create();
    $otherClient = Client::factory()->for($otherOrganization)->create();
    $otherUser = workOrderUser($otherOrganization);

    $this->actingAs($user)
        ->postJson('/api/work-orders', workOrderPayload($otherClient))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id']);

    $this->postJson('/api/work-orders', workOrderPayload($client, ['assignee_id' => $otherUser->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assignee_id']);
});

test('cross tenant work order access is hidden', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = workOrderUser($organization);
    $otherClient = Client::factory()->for($otherOrganization)->create();
    $otherWorkOrder = WorkOrder::factory()->for($otherOrganization)->create([
        'client_id' => $otherClient->id,
    ]);

    $this->actingAs($user)
        ->getJson("/api/work-orders/{$otherWorkOrder->id}")
        ->assertNotFound();

    $this->deleteJson("/api/work-orders/{$otherWorkOrder->id}")
        ->assertNotFound();
});

test('status and assignment changes create activity history', function () {
    $organization = Organization::factory()->create();
    $user = workOrderUser($organization);
    $assignee = workOrderUser($organization);
    $client = Client::factory()->for($organization)->create();

    $workOrder = WorkOrder::factory()->for($organization)->create([
        'client_id' => $client->id,
        'status' => 'queued',
        'assignee_id' => null,
    ]);

    $this->actingAs($user)
        ->putJson("/api/work-orders/{$workOrder->id}", workOrderPayload($client, [
            'status' => 'in_progress',
            'assignee_id' => $assignee->id,
        ]))
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.assignee.id', $assignee->id);

    $this->assertDatabaseHas('work_order_activities', [
        'work_order_id' => $workOrder->id,
        'type' => 'status_changed',
        'user_id' => $user->id,
    ]);
});

test('completing a work order sets completed at and reopening clears it', function () {
    $organization = Organization::factory()->create();
    $user = workOrderUser($organization);
    $client = Client::factory()->for($organization)->create();
    $workOrder = WorkOrder::factory()->for($organization)->create([
        'client_id' => $client->id,
        'status' => 'in_progress',
    ]);

    $this->actingAs($user)
        ->putJson("/api/work-orders/{$workOrder->id}", workOrderPayload($client, ['status' => 'completed']))
        ->assertOk();

    expect($workOrder->refresh()->completed_at)->not->toBeNull();

    $this->putJson("/api/work-orders/{$workOrder->id}", workOrderPayload($client, ['status' => 'queued']))
        ->assertOk();

    expect($workOrder->refresh()->completed_at)->toBeNull();
});

test('work order index supports filters and pagination', function () {
    $organization = Organization::factory()->create();
    $user = workOrderUser($organization);
    $client = Client::factory()->for($organization)->create(['name' => 'Maple Services']);

    WorkOrder::factory()->for($organization)->create([
        'client_id' => $client->id,
        'title' => 'Urgent furnace repair',
        'status' => 'queued',
        'priority' => 'urgent',
    ]);
    WorkOrder::factory()->for($organization)->create([
        'client_id' => $client->id,
        'title' => 'Routine inspection',
        'status' => 'completed',
        'priority' => 'normal',
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson('/api/work-orders?search=furnace&status=queued&priority=urgent&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Urgent furnace repair')
        ->assertJsonPath('meta.per_page', 1);
});
