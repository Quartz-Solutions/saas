<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            // Global
            ['code' => 'USD', 'name' => 'US Dollar',          'symbol' => '$',   'decimal_places' => 2, 'rounding_increment' => 1],
            ['code' => 'EUR', 'name' => 'Euro',               'symbol' => '€',   'decimal_places' => 2, 'rounding_increment' => 1],
            ['code' => 'GBP', 'name' => 'British Pound',      'symbol' => '£',   'decimal_places' => 2, 'rounding_increment' => 1],
            // Egypt
            ['code' => 'EGP', 'name' => 'Egyptian Pound',     'symbol' => 'E£',  'decimal_places' => 2, 'rounding_increment' => 1],
            // Saudi Arabia — 5-fil rounding for SAR
            ['code' => 'SAR', 'name' => 'Saudi Riyal',        'symbol' => 'SR',  'decimal_places' => 2, 'rounding_increment' => 5],
            // UAE
            ['code' => 'AED', 'name' => 'UAE Dirham',         'symbol' => 'AED', 'decimal_places' => 2, 'rounding_increment' => 25],
            // Qatar
            ['code' => 'QAR', 'name' => 'Qatari Riyal',       'symbol' => 'QR',  'decimal_places' => 2, 'rounding_increment' => 5],
            // Kuwait — 3 decimal places
            ['code' => 'KWD', 'name' => 'Kuwaiti Dinar',      'symbol' => 'KD',  'decimal_places' => 3, 'rounding_increment' => 5],
            // Malaysia
            ['code' => 'MYR', 'name' => 'Malaysian Ringgit',  'symbol' => 'RM',  'decimal_places' => 2, 'rounding_increment' => 5],
        ];

        foreach ($currencies as $row) {
            Currency::updateOrCreate(
                ['code' => $row['code']],
                $row + ['is_active' => true]
            );
        }
    }
}
