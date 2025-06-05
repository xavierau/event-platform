<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Enums\TransactionStatusEnum;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing users
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run user seeders first.');
            return;
        }

        // Create various types of transactions
        foreach ($users as $user) {
            // Each user gets 1-3 transactions
            $transactionCount = rand(1, 3);

            for ($i = 0; $i < $transactionCount; $i++) {
                Transaction::factory()
                    ->for($user)
                    ->create([
                        'status' => $this->getRandomStatus(),
                        'total_amount' => rand(1000, 50000), // $10 to $500 in cents
                        'currency' => 'usd',
                        'payment_gateway' => 'stripe',
                        'created_at' => now()->subDays(rand(1, 90)),
                    ]);
            }
        }

        // Create some specific test scenarios
        $testUser = $users->first();

        // Large transaction
        Transaction::factory()
            ->for($testUser)
            ->create([
                'total_amount' => 100000, // $1000
                'status' => TransactionStatusEnum::CONFIRMED,
                'currency' => 'usd',
                'payment_gateway' => 'stripe',
                'notes' => 'Large group booking',
            ]);

        // Failed transaction
        Transaction::factory()
            ->for($testUser)
            ->create([
                'total_amount' => 5000, // $50
                'status' => TransactionStatusEnum::FAILED_PAYMENT,
                'currency' => 'usd',
                'payment_gateway' => 'stripe',
                'notes' => 'Payment declined',
            ]);

        // Refunded transaction
        Transaction::factory()
            ->for($testUser)
            ->create([
                'total_amount' => 15000, // $150
                'status' => TransactionStatusEnum::REFUNDED,
                'currency' => 'usd',
                'payment_gateway' => 'stripe',
                'notes' => 'Event cancelled - full refund',
            ]);

        $this->command->info('Transactions seeded successfully!');
    }

    /**
     * Get a random transaction status with realistic distribution.
     */
    private function getRandomStatus(): TransactionStatusEnum
    {
        $random = rand(1, 100);

        if ($random <= 70) {
            return TransactionStatusEnum::CONFIRMED;
        } elseif ($random <= 85) { // 70 + 15
            return TransactionStatusEnum::PENDING_PAYMENT;
        } elseif ($random <= 95) { // 85 + 10
            return TransactionStatusEnum::FAILED_PAYMENT;
        } else { // remaining 5%
            return TransactionStatusEnum::REFUNDED;
        }
    }
}
