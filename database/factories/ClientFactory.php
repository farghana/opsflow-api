<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'company_name' => fake()->optional()->company(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'address_line_1' => fake()->optional()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->optional()->city(),
            'province' => fake()->optional()->state(),
            'postal_code' => fake()->optional()->postcode(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
