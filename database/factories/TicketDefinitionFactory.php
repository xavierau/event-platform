<?php

namespace Database\Factories;

use App\Models\TicketDefinition;
use App\Enums\TicketDefinitionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class TicketDefinitionFactory extends Factory
{
    protected $model = TicketDefinition::class;

    public function definition(): array
    {
        $nameEn = $this->faker->words(3, true) . ' Ticket';
        $availabilityStarts = $this->faker->optional(0.7)->dateTimeBetween('-1 month', '+1 month');
        $availabilityEnds = $availabilityStarts ? $this->faker->dateTimeBetween($availabilityStarts, $availabilityStarts->format('Y-m-d H:i:s') . ' +2 months') : null;

        return [
            'name' => ['en' => $nameEn, 'zh-TW' => $nameEn . ' (繁)', 'zh-CN' => $nameEn . ' (简)'],
            'description' => $this->faker->optional(0.5)->paragraph ? ['en' => $this->faker->paragraph, 'zh-TW' => $this->faker->paragraph . ' (繁)', 'zh-CN' => $this->faker->paragraph . ' (简)'] : null,
            'price' => $this->faker->numberBetween(1000, 10000), // e.g., 10.00 to 100.00
            'currency' => 'USD', // Default currency, matches new migration
            'total_quantity' => $this->faker->optional(0.8)->numberBetween(50, 500),
            // Using availability_window_..._utc column names as per migration
            'availability_window_start_utc' => $availabilityStarts,
            'availability_window_end_utc' => $availabilityEnds,
            // The string versions availability_window_start/end are for raw user input if needed, not directly set by factory here.
            'min_per_order' => 1,
            'max_per_order' => $this->faker->optional(0.7)->numberBetween(2, 10),
            'status' => Arr::random([
                TicketDefinitionStatus::DRAFT->value,
                TicketDefinitionStatus::ACTIVE->value,
                TicketDefinitionStatus::INACTIVE->value,
                TicketDefinitionStatus::ARCHIVED->value
            ]),
            'timezone' => null, // Added timezone, defaulting to null
            'metadata' => $this->faker->optional(0.3)->randomElement([
                ['info' => 'Some extra JSON data'],
                ['restrictions' => 'Adults only'],
            ]),
        ];
    }
}
