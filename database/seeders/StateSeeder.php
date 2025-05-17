<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hk = Country::where('iso_code_2', 'HK')->first();
        $mo = Country::where('iso_code_2', 'MO')->first();

        $states = [];

        if ($hk) {
            $states[] = [
                'country_id' => $hk->id,
                'name' => ['en' => 'Hong Kong', 'zh-TW' => '香港', 'zh-CN' => '香港'],
                'code' => 'HK', // Or null if not applicable
                'is_active' => true,
            ];
        }

        if ($mo) {
            $states[] = [
                'country_id' => $mo->id,
                'name' => ['en' => 'Macau', 'zh-TW' => '澳門', 'zh-CN' => '澳门'],
                'code' => 'MO', // Or null if not applicable
                'is_active' => true,
            ];
        }

        foreach ($states as $stateData) {
            State::updateOrCreate(
                [
                    'country_id' => $stateData['country_id'],
                    'code' => $stateData['code'] // Use a composite key for uniqueness if codes are used
                ],
                $stateData
            );
        }
    }
}
