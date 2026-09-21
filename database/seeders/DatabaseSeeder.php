<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            LandlordSeeder::class,
            AmenitySeeder::class,
            PropertySeeder::class,
            TenantSeeder::class,
            ReviewSeeder::class,
            ReportSeeder::class,
        ]);
    }
}