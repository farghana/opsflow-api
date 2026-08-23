<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard summary returns tenant scoped operational metrics and recent orders', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = User::factory()->for($organization)->create();

    WorkOrder::factory()->for($organization)->create([
        'status' => 'in_progress',
        'priority' => 'urgent',
        'due_date' => now()->subDay()->toDateString(),
        'created_at' => now()->subMinute(),
    ]);

    WorkOrder::factory()->for($organization)->create([
        'status' => 'queued',
        'priority' => 'normal',
        'due_date' => now()->addDays(2)->toDateString(),
        'created_at' => now()->subMinutes(2),
    ]);

    WorkOrder::factory()->for($organization)->create([
        'status' => 'completed',
        'priority' => 'high',
        'completed_at' => now()->subDays(3),
        'created_at' => now()->subMinutes(3),
    ]);

    WorkOrder::factory()->for($organization)->create([
        'status' => 'completed',
        'completed_at' => now()->subDays(10),
        'created_at' => now()->subMinutes(4),
    ]);

    WorkOrder::factory()->for($otherOrganization)->create([
        'status' => 'in_progress',
        'priority' => 'urgent',
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('metrics.open', 2)
        ->assertJsonPath('metrics.overdue', 1)
        ->assertJsonPath('metrics.high_priority', 1)
        ->assertJsonPath('metrics.completed_last_7_days', 1)
        ->assertJsonCount(4, 'recent_work_orders');

    expect(collect($response->json('recent_work_orders'))->pluck('id'))
        ->not->toContain(WorkOrder::where('organization_id', $otherOrganization->id)->value('id'));
});

test('dashboard summary requires authentication', function () {
    $this->getJson('/api/dashboard/summary')->assertUnauthorized();
});
