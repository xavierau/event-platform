<?php

namespace App\Actions\Registration;

use App\DataTransferObjects\Registration\RegistrationWithSubscriptionData;
use App\Models\User;
use App\Enums\RoleNameEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Cashier\Exceptions\CustomerAlreadyCreated;

class CreateUserWithSubscriptionAction
{
    public function execute(RegistrationWithSubscriptionData $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'mobile_number' => $data->mobile_number,
                'password' => Hash::make($data->password),
            ]);
            
            $user->assignRole(RoleNameEnum::USER->value);
            
            try {
                $user->createAsStripeCustomer([
                    'name' => $data->name,
                    'email' => $data->email,
                    'metadata' => array_merge($data->metadata ?? [], [
                        'registration_date' => now()->toIso8601String(),
                        'selected_plan' => $data->selected_price_id,
                    ]),
                ]);
            } catch (CustomerAlreadyCreated $e) {
                // Customer already exists, continue
            }
            
            return $user;
        });
    }
}