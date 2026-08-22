<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Organization;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'client_id' => function (array $attributes) {
                return Client::factory()->create(['organization_id' => $attributes['organization_id']])->id;
            },
            'assignee_id' => null,
            'order_number' => 'WO-'.now()->format('ym').'-'.Str::upper(Str::random(6)),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'internal_notes' => fake()->optional()->sentence(),
            'status' => 'draft',
            'priority' => 'normal',
            'due_date' => fake()->optional()->dateTimeBetween('now', '+30 days')?->format('Y-m-d'),
            'completed_at' => null,
        ];
    }
}
