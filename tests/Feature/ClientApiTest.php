<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function userForOrganization(Organization $organization): User
{
    return User::factory()->for($organization)->create();
}

test('an authenticated user only sees clients in their organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = userForOrganization($organization);

    Client::factory()->for($organization)->create(['name' => 'Acme Services']);
    Client::factory()->for($otherOrganization)->create(['name' => 'Hidden Client']);

    $this->actingAs($user)
        ->getJson('/api/clients')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Acme Services');
});

test('a user can create and update a client in their organization', function () {
    $organization = Organization::factory()->create();
    $user = userForOrganization($organization);

    $createResponse = $this->actingAs($user)->postJson('/api/clients', [
        'name' => 'Northstar Dental',
        'email' => 'hello@northstar.test',
        'city' => 'Toronto',
        'province' => 'Ontario',
    ]);

    $createResponse->assertSuccessful()->assertJsonPath('data.name', 'Northstar Dental');

    $clientId = $createResponse->json('data.id');

    $this->putJson("/api/clients/{$clientId}", [
        'name' => 'Northstar Dental Group',
        'email' => 'hello@northstar.test',
        'city' => 'Toronto',
        'province' => 'Ontario',
    ])->assertOk()->assertJsonPath('data.name', 'Northstar Dental Group');

    $this->assertDatabaseHas('clients', [
        'id' => $clientId,
        'organization_id' => $organization->id,
        'name' => 'Northstar Dental Group',
    ]);
});

test('client validation rejects an invalid email', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/clients', [
            'name' => 'Example Client',
            'email' => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('cross tenant client access returns not found', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = userForOrganization($organization);
    $otherClient = Client::factory()->for($otherOrganization)->create();

    $this->actingAs($user)
        ->getJson("/api/clients/{$otherClient->id}")
        ->assertNotFound();

    $this->putJson("/api/clients/{$otherClient->id}", [
        'name' => 'Attempted Update',
    ])->assertNotFound();

    $this->deleteJson("/api/clients/{$otherClient->id}")
        ->assertNotFound();
});

test('client index supports search and pagination', function () {
    $organization = Organization::factory()->create();
    $user = userForOrganization($organization);

    Client::factory()->for($organization)->create(['name' => 'Maple Repair']);
    Client::factory()->for($organization)->create(['name' => 'Cedar Consulting']);

    $this->actingAs($user)
        ->getJson('/api/clients?search=maple&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Maple Repair')
        ->assertJsonPath('meta.per_page', 1);
});
