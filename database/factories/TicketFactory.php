<?php

namespace Database\Factories;

use App\Models\TicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_number'    => 'TKT-' . date('Y') . '-' . str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'requester_name'   => fake()->name(),
            'requester_email'  => fake()->safeEmail(),
            'requester_area'   => fake()->word(),
            'description'      => fake()->paragraph(),
            'verification_code'=> (string) fake()->numberBetween(100000, 999999),
            'priority'         => fake()->randomElement(['alta', 'media', 'baja']),
            'status_id'        => TicketStatus::factory(),
            'channel'          => 'portal',
        ];
    }
}
